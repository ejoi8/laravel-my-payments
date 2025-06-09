<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Http;
use Ejoi8\PaymentGateway\Gateways\ToyyibpayGateway;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test class for ToyyibPay gateway focusing on business logic
 */
class ToyyibpayGatewayTest extends TestCase
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
        $app['config']->set('payment-gateway.currency', 'MYR');
        
        // Configure app URL properly for URL generation
        $app['config']->set('app.url', 'https://example.com');
        $app['url'] = 'https://example.com';
        
        // Configure routes for URL generation
        $app['config']->set('payment-gateway.success_route', 'payment-gateway.success');
        $app['config']->set('payment-gateway.failed_route', 'payment-gateway.failed');

        // Configure ToyyibPay gateway settings
        $app['config']->set('payment-gateway.gateways.toyyibpay', [
            'enabled' => true,
            'secret_key' => 'test_secret_key',
            'category_code' => 'test_category',
            'sandbox' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up database tables
        $this->artisan('migrate');
    }
    
    protected function getGateway(): ToyyibpayGateway
    {
        $gateway = new ToyyibpayGateway();
        
        // Set up test configuration using the new setConfig method
        $config = [
            'secret_key' => 'test_secret_key',
            'category_code' => 'test_category',
            'sandbox' => true,
            'enabled' => true
        ];
        
        $gateway->setConfig($config);
        return $gateway;
    }    public function test_create_payment()
    {
        Http::fake([
            'https://dev.toyyibpay.com/index.php/api/createBill' => Http::response([
                [
                    'BillCode' => 't4p4neh3',
                    'BillAmount' => '10000',
                    'BillPaymentStatus' => '3'
                ]
            ], 200)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'description' => 'Test Payment'
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('t4p4neh3', $result['payment_url']);
        $this->assertEquals('t4p4neh3', $result['transaction_id']);
    }    public function test_handle_callback()
    {
        $payment = Payment::create([
            'reference_id' => 'TEST-REF-123',
            'gateway' => 'toyyibpay',
            'amount' => 50.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'gateway_transaction_id' => 't4p4neh3'
        ]);

        $callbackData = [
            'status_id' => '1', // 1 = Successful payment
            'billcode' => 't4p4neh3',
            'amount' => '5000'
        ];

        $result = $this->getGateway()->handleCallback($callbackData);

        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $result['status']);
        
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
    }public function test_verify_payment_status()
    {
        Http::fake([
            'https://dev.toyyibpay.com/index.php/api/getBillTransactions' => Http::response([
                [
                    'billCode' => 't4p4neh3',
                    'billpaymentStatus' => '1' // 1 = Paid
                ]
            ], 200)
        ]);

        $result = $this->getGateway()->verifyPayment('t4p4neh3');

        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $result['status']);
        $this->assertEquals('t4p4neh3', $result['data']['billCode']);
    }public function test_supports_multiple_payment_methods()
    {
        $paymentMethods = [
            ['method' => 'online_banking', 'bank' => 'maybank'],
            ['method' => 'credit_card', 'card_type' => 'visa'],
            ['method' => 'ewallet', 'wallet' => 'grabpay'],
            ['method' => 'fpx', 'bank' => 'cimb']
        ];

        foreach ($paymentMethods as $index => $method) {
            // Reset Http fake completely for each iteration
            Http::swap(new \Illuminate\Http\Client\Factory());
            Http::preventStrayRequests();
            
            // Create a unique response for this specific iteration
            Http::fake([
                'https://dev.toyyibpay.com/index.php/api/createBill' => Http::response([
                    [
                        'BillCode' => 'bill_' . $index,
                        'BillDescription' => 'Payment via ' . $method['method'],
                        'BillPaymentStatus' => '3', // Pending
                        'BillAmount' => '2500', // RM25.00
                        'BillMultiplePayment' => '1',
                        'BillPaymentChannel' => $method['method']
                    ]
                ], 200)
            ]);

            // Act: Create payment for each method
            $result = $this->getGateway()->createPayment([
                'amount' => 25.00,
                'customer_name' => 'Customer ' . ($index + 1),
                'customer_email' => 'customer' . ($index + 1) . '@example.com',
                'description' => 'Payment via ' . $method['method'],
                'payment_method' => $method['method']
            ]);

            // Assert: Each payment method should work
            $this->assertTrue($result['success'], "Payment method {$method['method']} failed");
            $this->assertEquals('bill_' . $index, $result['transaction_id'], 
                "Expected 'bill_{$index}' for method {$method['method']} but got '{$result['transaction_id']}'");
        }
    }    public function test_sandbox_vs_production_environments()
    {
        // Test sandbox environment
        Http::fake([
            'https://dev.toyyibpay.com/index.php/api/createBill' => Http::response([
                ['BillCode' => 'sandbox_bill']
            ], 200)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 10.00,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'description' => 'Test Payment'
        ]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'dev.toyyibpay.com');
        });

        // Test production environment
        Http::fake([
            'https://toyyibpay.com/index.php/api/createBill' => Http::response([
                ['BillCode' => 'prod_bill']
            ], 200)
        ]);

        $prodGateway = new ToyyibpayGateway();
        $prodGateway->setConfig([
            'secret_key' => 'prod_key',
            'category_code' => 'prod_category',
            'sandbox' => false,
            'enabled' => true
        ]);

        $prodResult = $prodGateway->createPayment([
            'amount' => 10.00,
            'customer_name' => 'Prod User',
            'customer_email' => 'prod@example.com',
            'description' => 'Production Payment'
        ]);

        $this->assertTrue($prodResult['success']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'toyyibpay.com') && 
                   !str_contains($request->url(), 'dev.toyyibpay.com');
        });
    }
}
