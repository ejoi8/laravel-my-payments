<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Http;
use Ejoi8\PaymentGateway\Gateways\ChipInGateway;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test class for ChipIn gateway focusing on business logic
 */
class ChipInGatewayTest extends TestCase
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
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function getGateway()
    {
        $gateway = new ChipInGateway();
        
        // Mock configuration
        $config = [
            'brand_id' => 'test_brand_123',
            'secret_key' => 'test_secret_key_456',
            'sandbox' => true,
            'enabled' => true
        ];
          $gateway->setConfig($config);
        return $gateway;
    }

    public function test_create_payment()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response([
                'id' => 'purchase_123456789',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123456789',
                'status' => 'pending'
            ], 201)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'description' => 'Test Payment'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('https://gate.chip-in.asia/checkout/purchase_123456789', $result['payment_url']);        
        $this->assertEquals('purchase_123456789', $result['transaction_id']);
    }

    public function test_handle_api_errors()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response([
                'message' => 'Invalid brand_id'
            ], 400)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com'
        ]);

        $this->assertFalse($result['success']);        
        $this->assertStringContainsString('Chip-in API request failed with HTTP code: 400', $result['message']);
    }

    public function test_verify_payment()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/purchase_123456789/' => Http::response([
                'id' => 'purchase_123456789',
                'status' => 'paid',
                'amount' => 10000
            ], 200)
        ]);

        $result = $this->getGateway()->verifyPayment('purchase_123456789');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);        
        $this->assertEquals('purchase_123456789', $result['transaction_id']);
    }

    public function test_environment_selection()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response(['id' => 'test'], 201)
        ]);

        $this->getGateway()->createPayment([
            'amount' => 60.00,
            'customer_email' => 'test@example.com'
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gate.chip-in.asia');        
        });
    }

    public function test_handle_connection_failures()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });

        $result = $this->getGateway()->createPayment([
            'amount' => 75.00,
            'customer_email' => 'test@example.com'
        ]);

        $this->assertFalse($result['success']);        
        $this->assertStringContainsString('Chip-in API connection failed', $result['message']);
    }

    public function test_handle_invalid_json_response()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response('Invalid JSON response', 200)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 40.00,
            'customer_email' => 'test@example.com'
        ]);

        $this->assertFalse($result['success']);        
        $this->assertStringContainsString('Invalid JSON response from Chip-in API', $result['message']);
    }

    public function test_request_headers()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response(['id' => 'test'], 201)
        ]);

        $this->getGateway()->createPayment([
            'amount' => 50.00,
            'customer_email' => 'test@example.com'
        ]);

        Http::assertSent(function ($request) {
            $headers = $request->headers();
            return isset($headers['Authorization'][0]) &&
                   $headers['Authorization'][0] === 'Bearer test_secret_key_456' &&
                   isset($headers['Content-Type'][0]) &&
                   $headers['Content-Type'][0] === 'application/json';        
                });
    }

    public function test_multiple_payment_scenarios()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::sequence()
                ->push(['id' => 'purchase_1', 'checkout_url' => 'https://checkout.example.com/1'], 201)
                ->push(['message' => 'Server error'], 500)
        ]);

        $result1 = $this->getGateway()->createPayment([
            'amount' => 25.00,
            'customer_email' => 'test1@example.com'
        ]);
        $this->assertTrue($result1['success']);

        $result2 = $this->getGateway()->createPayment([
            'amount' => 30.00,
            'customer_email' => 'test2@example.com'
        ]);        
        $this->assertFalse($result2['success']);
    }

    public function test_handle_callback()
    {
        $payment = Payment::create([
            'amount' => 115.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'gateway' => 'chipin',
            'gateway_transaction_id' => 'purchase-123',
            'customer_email' => 'customer@example.com'
        ]);
        
        $callbackData = [
            'id' => 'purchase-123',
            'status' => 'paid',
            'purchase' => ['total' => 11500, 'currency' => 'MYR']
        ];

        $result = $this->getGateway()->handleCallback($callbackData);

        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $result['status']);
        
        $payment->refresh();        
        
        $this->assertEquals('paid', $payment->status);
    }

    public function test_callback_payment_update()
    {
        $payment = Payment::create([
            'amount' => 115.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'gateway' => 'chipin',
            'gateway_transaction_id' => 'e6d02e9e-8997-4ca4-aeaf-cbcc503c285b',
            'customer_email' => 'customer@example.com'
        ]);
        
        $callbackData = [
            'id' => 'e6d02e9e-8997-4ca4-aeaf-cbcc503c285b',
            'status' => 'paid'
        ];

        $result = $this->getGateway()->handleCallback($callbackData);

        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $result['status']);
        
        $payment->refresh();        
        $this->assertEquals('paid', $payment->status);
    }

    public function test_test_environment()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response([
                "id" => "test-purchase-123",
                "checkout_url" => "https://gate.chip-in.asia/p/test-purchase-123/"
            ], 201)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 50.00,
            'customer_email' => 'test@example.com'
        ]);

        $this->assertTrue($result['success']);        
        $this->assertEquals('test-purchase-123', $result['transaction_id']);
    }

    public function test_products_handling()
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response([
                "id" => "product-test-123",
                "checkout_url" => "https://gate.chip-in.asia/p/product-test-123/"
            ], 201)
        ]);

        $result = $this->getGateway()->createPayment([
            'amount' => 115.00,
            'customer_email' => 'test@example.com',
            'products' => [
                ['name' => 'Premium T-Shirt', 'price' => 45.00, 'quantity' => 2],
                ['name' => 'Coffee Mug', 'price' => 25.00, 'quantity' => 1]
            ]
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('product-test-123', $result['transaction_id']);
    }
}
