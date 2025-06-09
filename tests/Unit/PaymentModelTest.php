<?php

namespace Ejoi8\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Ejoi8\PaymentGateway\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Model;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Configure database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure payment gateway settings
        $app['config']->set('payment-gateway.table_name', 'payments');
        $app['config']->set('payment-gateway.gateways', [
            'manual' => [
                'name' => 'Manual Payment',
                'enabled' => true,
                'class' => \Ejoi8\PaymentGateway\Gateways\ManualPaymentGateway::class,
            ],
        ]);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        // Arrange & Act
        $payment = new Payment();
        $fillable = $payment->getFillable();

        // Assert
        $expectedFillable = [
            'reference_id',
            'gateway',
            'amount',
            'currency',
            'status',
            'description',
            'customer_name',
            'customer_email',
            'customer_phone',
            'payment_url',
            'gateway_transaction_id',
            'gateway_response',
            'callback_data',
            'proof_file_path',
            'paid_at',
            'failed_at',
            'metadata',
            'external_reference_id',
            'reference_type',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        // Arrange & Act
        $payment = new Payment();
        $casts = $payment->getCasts();

        // Assert
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('array', $casts['gateway_response']);
        $this->assertEquals('array', $casts['callback_data']);
        $this->assertEquals('array', $casts['metadata']);
        $this->assertEquals('datetime', $casts['paid_at']);
        $this->assertEquals('datetime', $casts['failed_at']);
    }

    /** @test */
    public function it_generates_unique_reference_id()
    {
        // Arrange
        $payment = new Payment();

        // Act
        $referenceId1 = $payment->generateReferenceId();
        $referenceId2 = $payment->generateReferenceId();

        // Assert
        $this->assertNotEquals($referenceId1, $referenceId2);
        $this->assertStringStartsWith('PAY-', $referenceId1);
        $this->assertStringStartsWith('PAY-', $referenceId2);
    }

    /** @test */
    public function it_identifies_manual_payment_correctly()
    {
        // Arrange
        $manualPayment = new Payment(['gateway' => 'manual']);
        $chipinPayment = new Payment(['gateway' => 'chipin']);

        // Act & Assert
        $this->assertTrue($manualPayment->is_manual_payment);
        $this->assertFalse($chipinPayment->is_manual_payment);
    }

    /** @test */
    public function it_formats_amount_with_currency()
    {
        // Arrange
        $payment = new Payment([
            'amount' => 123.45,
            'currency' => 'MYR'
        ]);

        // Act
        $formattedAmount = $payment->formatted_amount;

        // Assert
        $this->assertEquals('123.45 MYR', $formattedAmount);
    }

    /** @test */
    public function it_provides_correct_status_badge_classes()
    {
        // Arrange & Act & Assert
        $this->assertEquals(
            'bg-yellow-100 text-yellow-800',
            (new Payment(['status' => Payment::STATUS_PENDING]))->status_badge
        );

        $this->assertEquals(
            'bg-green-100 text-green-800',
            (new Payment(['status' => Payment::STATUS_PAID]))->status_badge
        );

        $this->assertEquals(
            'bg-red-100 text-red-800',
            (new Payment(['status' => Payment::STATUS_FAILED]))->status_badge
        );

        $this->assertEquals(
            'bg-gray-100 text-gray-800',
            (new Payment(['status' => Payment::STATUS_CANCELLED]))->status_badge
        );

        $this->assertEquals(
            'bg-blue-100 text-blue-800',
            (new Payment(['status' => Payment::STATUS_REFUNDED]))->status_badge
        );
    }

    /** @test */
    public function it_marks_payment_as_paid_correctly()
    {
        // Arrange
        $payment = new Payment([
            'status' => Payment::STATUS_PENDING,
            'gateway_transaction_id' => null,
            'paid_at' => null
        ]);

        // Mock the update method since we're not using a real database
        $payment = $this->createPartialMock(Payment::class, ['update']);
        $payment->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === Payment::STATUS_PAID &&
                       $data['gateway_transaction_id'] === 'TXN123' &&
                       isset($data['paid_at']) &&
                       isset($data['callback_data']);
            }))
            ->willReturn(true);

        // Act
        $result = $payment->markAsPaid('TXN123', ['gateway_response' => 'success']);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_marks_payment_as_failed_correctly()
    {
        // Arrange
        $payment = $this->createPartialMock(Payment::class, ['update']);
        $payment->metadata = [];

        $payment->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === Payment::STATUS_FAILED &&
                       isset($data['failed_at']) &&
                       $data['metadata']['failure_reason'] === 'Test failure';
            }))
            ->willReturn(true);

        // Act
        $result = $payment->markAsFailed('Test failure', ['error' => 'Gateway error']);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_has_status_constants()
    {
        // Assert
        $this->assertEquals('pending', Payment::STATUS_PENDING);
        $this->assertEquals('paid', Payment::STATUS_PAID);
        $this->assertEquals('failed', Payment::STATUS_FAILED);
        $this->assertEquals('cancelled', Payment::STATUS_CANCELLED);
        $this->assertEquals('refunded', Payment::STATUS_REFUNDED);
    }

    /** @test */
    public function it_builds_external_reference_query()
    {
        // This test would require a real database connection to fully test
        // We'll test the method signature and basic logic
        
        // Arrange
        $payment = new Payment();
        
        // Act & Assert - Test that the method exists and can be called
        $this->assertTrue(method_exists($payment, 'scopeByExternalReference'));
        
        // Test the static method
        $this->assertTrue(method_exists(Payment::class, 'findByExternalReference'));
    }

    /** @test */
    public function it_uses_configured_table_name()
    {
        // Arrange
        $payment = new Payment();
        
        // Act
        $tableName = $payment->getTableName();
        
        // Assert
        $this->assertIsString($tableName);
        // Default should be 'payments' if config is not available
        $this->assertEquals('payments', $tableName);
    }
}
