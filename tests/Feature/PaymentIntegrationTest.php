<?php

namespace Ejoi8\PaymentGateway\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Ejoi8\PaymentGateway\Services\PaymentService;
use Ejoi8\PaymentGateway\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up the database
        $this->setUpDatabase();
        
        // Register routes for testing
        $this->setupRoutes();
    }

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setupRoutes(): void
    {
        Route::get('/test-success', function () {
            return 'Payment Success';
        })->name('payment-gateway.success');

        Route::get('/test-failed', function () {
            return 'Payment Failed';
        })->name('payment-gateway.failed');

        Route::post('/test-callback/{gateway}', function ($gateway) {
            return 'Callback for ' . $gateway;
        })->name('payment-gateway.callback');
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup payment gateway config
        $app['config']->set('payment-gateway', [
            'default_gateway' => 'manual',
            'currency' => 'MYR',
            'table_name' => 'payments',
            'gateways' => [
                'manual' => [
                    'enabled' => true,
                    'upload_path' => 'payment-proofs',
                    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                    'max_file_size' => 5120,
                ],
                'toyyibpay' => [
                    'enabled' => false,
                    'secret_key' => 'test_secret',
                    'category_code' => 'test_category',
                    'sandbox' => true,
                ],
                'chipin' => [
                    'enabled' => false,
                    'brand_id' => 'test_brand',
                    'secret_key' => 'test_secret',
                    'sandbox' => true,
                ]
            ]
        ]);
    }

    /** @test */
    public function it_creates_payment_record_in_database()
    {
        // Arrange
        $paymentService = $this->app->make(PaymentService::class);
        $paymentData = [
            'amount' => 100.50,
            'currency' => 'MYR',
            'gateway' => 'manual',
            'description' => 'Test Payment',
            'customer_email' => 'test@example.com',
            'customer_name' => 'John Doe',
            'customer_phone' => '+60123456789'
        ];

        // Act
        $result = $paymentService->createPayment($paymentData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('payments', [
            'amount' => 100.50,
            'currency' => 'MYR',
            'gateway' => 'manual',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com',
            'customer_name' => 'John Doe'
        ]);

        $payment = Payment::first();
        $this->assertNotNull($payment->reference_id);
        $this->assertStringStartsWith('PAY-', $payment->reference_id);
    }

    /** @test */
    public function it_creates_payment_with_external_reference()
    {
        // Arrange
        $paymentService = $this->app->make(PaymentService::class);
        $paymentData = [
            'amount' => 250.00,
            'gateway' => 'manual',
            'description' => 'Order Payment',
            'customer_email' => 'customer@example.com'
        ];

        // Act
        $result = $paymentService->createPaymentWithExternalReference(
            $paymentData,
            'ORDER-12345',
            'order'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('payments', [
            'external_reference_id' => 'ORDER-12345',
            'reference_type' => 'order',
            'amount' => 250.00
        ]);
    }    /** @test */
    public function it_finds_payments_by_external_reference()
    {
        // Arrange
        $orderId = 'ORDER-FIND-TEST';
        
        // Create multiple payments for the same order
        Payment::create([
            'reference_id' => 'PAY-TEST-1',
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_FAILED,
            'external_reference_id' => $orderId,
            'reference_type' => 'order',
            'customer_email' => 'test@example.com'
        ]);        // Small delay to ensure different timestamps
        usleep(100000); // 100ms delay

        Payment::create([
            'reference_id' => 'PAY-TEST-2',
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PAID,
            'external_reference_id' => $orderId,
            'reference_type' => 'order',
            'customer_email' => 'test@example.com'
        ]);

        $paymentService = $this->app->make(PaymentService::class);

        // Act
        $payments = $paymentService->getPaymentsByExternalReference($orderId, 'order');
        $hasSuccessful = $paymentService->hasSuccessfulPayment($orderId, 'order');
        $latestPayment = $paymentService->getLatestPaymentByExternalReference($orderId, 'order');

        // Assert
        $this->assertCount(2, $payments);
        $this->assertTrue($hasSuccessful);
        $this->assertNotNull($latestPayment);
        $this->assertEquals('PAY-TEST-2', $latestPayment->reference_id);
    }

    /** @test */
    public function it_handles_payment_status_transitions()
    {
        // Arrange
        $payment = Payment::create([
            'reference_id' => 'PAY-STATUS-TEST',
            'gateway' => 'manual',
            'amount' => 150.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        // Act & Assert - Mark as paid
        $result = $payment->markAsPaid('TXN12345', ['gateway_response' => 'success']);
        $this->assertTrue($result);
        
        $payment->refresh();
        $this->assertEquals(Payment::STATUS_PAID, $payment->status);
        $this->assertEquals('TXN12345', $payment->gateway_transaction_id);
        $this->assertNotNull($payment->paid_at);

        // Create another payment to test failure
        $failedPayment = Payment::create([
            'reference_id' => 'PAY-FAILED-TEST',
            'gateway' => 'manual',
            'amount' => 75.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        // Act & Assert - Mark as failed
        $result = $failedPayment->markAsFailed('Insufficient funds', ['error' => 'Payment declined']);
        $this->assertTrue($result);
        
        $failedPayment->refresh();
        $this->assertEquals(Payment::STATUS_FAILED, $failedPayment->status);
        $this->assertNotNull($failedPayment->failed_at);
        $this->assertEquals('Insufficient funds', $failedPayment->metadata['failure_reason']);
    }

    /** @test */
    public function it_uses_query_scopes_correctly()
    {
        // Arrange - Create payments with different statuses
        Payment::create([
            'reference_id' => 'PAY-PENDING-1',
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        Payment::create([
            'reference_id' => 'PAY-PAID-1',
            'gateway' => 'manual',
            'amount' => 200.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PAID,
            'customer_email' => 'test@example.com'
        ]);

        Payment::create([
            'reference_id' => 'PAY-FAILED-1',
            'gateway' => 'manual',
            'amount' => 50.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_FAILED,
            'customer_email' => 'test@example.com'
        ]);

        // Act
        $pendingPayments = Payment::pending()->get();
        $paidPayments = Payment::paid()->get();
        $failedPayments = Payment::failed()->get();

        // Assert
        $this->assertCount(1, $pendingPayments);
        $this->assertCount(1, $paidPayments);
        $this->assertCount(1, $failedPayments);

        $this->assertEquals('PAY-PENDING-1', $pendingPayments->first()->reference_id);
        $this->assertEquals('PAY-PAID-1', $paidPayments->first()->reference_id);
        $this->assertEquals('PAY-FAILED-1', $failedPayments->first()->reference_id);
    }

    /** @test */
    public function it_calculates_formatted_amount_correctly()
    {
        // Arrange
        $payment = Payment::create([
            'reference_id' => 'PAY-FORMAT-TEST',
            'gateway' => 'manual',
            'amount' => 1234.56,
            'currency' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        // Act
        $formattedAmount = $payment->formatted_amount;

        // Assert
        $this->assertEquals('1,234.56 USD', $formattedAmount);
    }

    /** @test */
    public function it_identifies_manual_payments()
    {
        // Arrange
        $manualPayment = Payment::create([
            'reference_id' => 'PAY-MANUAL-TEST',
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        $chipinPayment = Payment::create([
            'reference_id' => 'PAY-CHIPIN-TEST',
            'gateway' => 'chipin',
            'amount' => 200.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test@example.com'
        ]);

        // Act & Assert
        $this->assertTrue($manualPayment->is_manual_payment);
        $this->assertFalse($chipinPayment->is_manual_payment);
    }

    /** @test */
    public function it_provides_correct_status_badge_classes()
    {
        // Test all status badge classes
        $testCases = [
            [Payment::STATUS_PENDING, 'bg-yellow-100 text-yellow-800'],
            [Payment::STATUS_PAID, 'bg-green-100 text-green-800'],
            [Payment::STATUS_FAILED, 'bg-red-100 text-red-800'],
            [Payment::STATUS_CANCELLED, 'bg-gray-100 text-gray-800'],
            [Payment::STATUS_REFUNDED, 'bg-blue-100 text-blue-800'],
        ];

        foreach ($testCases as [$status, $expectedClass]) {
            // Arrange
            $payment = Payment::create([
                'reference_id' => 'PAY-BADGE-' . strtoupper($status),
                'gateway' => 'manual',
                'amount' => 100.00,
                'currency' => 'MYR',
                'status' => $status,
                'customer_email' => 'test@example.com'
            ]);

            // Act & Assert
            $this->assertEquals($expectedClass, $payment->status_badge);
        }
    }

    /** @test */
    public function it_filters_payments_by_external_reference_scope()
    {
        // Arrange
        $orderId = 'ORDER-SCOPE-TEST';
        $subscriptionId = 'SUB-SCOPE-TEST';

        Payment::create([
            'reference_id' => 'PAY-ORDER-1',
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'external_reference_id' => $orderId,
            'reference_type' => 'order',
            'customer_email' => 'test@example.com'
        ]);

        Payment::create([
            'reference_id' => 'PAY-SUB-1',
            'gateway' => 'manual',
            'amount' => 50.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'external_reference_id' => $subscriptionId,
            'reference_type' => 'subscription',
            'customer_email' => 'test@example.com'
        ]);

        // Act
        $orderPayments = Payment::byExternalReference($orderId, 'order')->get();
        $subscriptionPayments = Payment::byExternalReference($subscriptionId, 'subscription')->get();
        $allOrderPayments = Payment::byExternalReference($orderId)->get();

        // Assert
        $this->assertCount(1, $orderPayments);
        $this->assertCount(1, $subscriptionPayments);
        $this->assertCount(1, $allOrderPayments);

        $this->assertEquals('PAY-ORDER-1', $orderPayments->first()->reference_id);
        $this->assertEquals('PAY-SUB-1', $subscriptionPayments->first()->reference_id);
    }

    /** @test */
    public function it_generates_unique_reference_ids()
    {
        // Act - Create multiple payments
        $payment1 = Payment::create([
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test1@example.com'
        ]);

        $payment2 = Payment::create([
            'gateway' => 'manual',
            'amount' => 200.00,
            'currency' => 'MYR',
            'status' => Payment::STATUS_PENDING,
            'customer_email' => 'test2@example.com'
        ]);

        // Assert
        $this->assertNotEquals($payment1->reference_id, $payment2->reference_id);
        $this->assertStringStartsWith('PAY-', $payment1->reference_id);
        $this->assertStringStartsWith('PAY-', $payment2->reference_id);
    }
}
