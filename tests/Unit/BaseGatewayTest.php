<?php

namespace Ejoi8\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Mockery;
use Ejoi8\PaymentGateway\Gateways\BaseGateway;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BaseGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected $gateway;protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }    protected function getEnvironmentSetUp($app)
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
        $app['config']->set('payment-gateway.routes.prefix', 'payment-gateway');
        $app['config']->set('payment-gateway.routes.middleware', ['web']);
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up database tables
        $this->artisan('migrate');
        
        // Create a concrete implementation of BaseGateway for testing
        $this->gateway = new class extends BaseGateway {
            public function getName(): string {
                return 'test_gateway';
            }
            
            public function createPayment(array $data): array {
                return ['success' => true];
            }
            
            public function handleCallback(array $data): array {
                return ['success' => true];
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_formats_amount_correctly()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('formatAmount');
        $method->setAccessible(true);

        // Test various amounts
        $this->assertEquals(100.00, $method->invokeArgs($this->gateway, [100]));
        $this->assertEquals(99.99, $method->invokeArgs($this->gateway, [99.99]));
        $this->assertEquals(0.01, $method->invokeArgs($this->gateway, [0.01]));
        $this->assertEquals(123.46, $method->invokeArgs($this->gateway, [123.456])); // Rounded
    }

    /** @test */
    public function it_validates_required_fields_successfully()
    {
        // Arrange
        $data = [
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'description' => 'Test payment'
        ];
        $required = ['amount', 'customer_email'];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateRequiredFields');
        $method->setAccessible(true);

        // Act & Assert - should not throw exception
        $this->expectNotToPerformAssertions();
        $method->invokeArgs($this->gateway, [$data, $required]);
    }

    /** @test */
    public function it_throws_exception_for_missing_required_fields()
    {
        // Arrange
        $data = [
            'amount' => 100.00,
            // Missing customer_email
        ];
        $required = ['amount', 'customer_email'];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateRequiredFields');
        $method->setAccessible(true);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'customer_email' is required for test_gateway gateway");
        $method->invokeArgs($this->gateway, [$data, $required]);
    }

    /** @test */
    public function it_throws_exception_for_empty_required_fields()
    {
        // Arrange
        $data = [
            'amount' => 100.00,
            'customer_email' => '', // Empty string
        ];
        $required = ['amount', 'customer_email'];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateRequiredFields');
        $method->setAccessible(true);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'customer_email' is required for test_gateway gateway");
        $method->invokeArgs($this->gateway, [$data, $required]);
    }    /** @test */
    public function it_generates_callback_url_correctly()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('generateCallbackUrl');
        $method->setAccessible(true);

        // Act
        $url = $method->invoke($this->gateway);

        // Assert - Should contain callback path and gateway name
        $this->assertStringContainsString('callback', $url);
        $this->assertStringContainsString('test_gateway', $url);
        $this->assertStringStartsWith('https://example.com', $url);
    }

    /** @test */
    public function it_generates_success_url_correctly()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('generateSuccessUrl');
        $method->setAccessible(true);

        // Act
        $url = $method->invoke($this->gateway);

        // Assert - Should contain success path
        $this->assertStringContainsString('success', $url);
        $this->assertStringStartsWith('https://example.com', $url);
    }

    /** @test */
    public function it_generates_failed_url_correctly()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('generateFailedUrl');
        $method->setAccessible(true);

        // Act
        $url = $method->invoke($this->gateway);

        // Assert - Should contain failed path
        $this->assertStringContainsString('failed', $url);
        $this->assertStringStartsWith('https://example.com', $url);
    }
    
    /** @test */
    public function it_creates_payment_record_with_defaults()
    {
        // Arrange
        $data = [
            'amount' => 150.00,
            'customer_email' => 'test@example.com',
            'customer_name' => 'John Doe'
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('createPaymentRecord');
        $method->setAccessible(true);

        // Act
        $result = $method->invokeArgs($this->gateway, [$data]);

        // Assert
        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals(150.00, $result->amount);
        $this->assertEquals('test@example.com', $result->customer_email);
        $this->assertEquals('John Doe', $result->customer_name);
        $this->assertEquals('MYR', $result->currency);
        $this->assertNotNull($result->reference_id);
    }    
    
    /** @test */
    public function it_creates_payment_record_with_external_reference()
    {
        // Arrange
        $data = [
            'amount' => 200.00,
            'customer_email' => 'test@example.com',
            'external_reference_id' => 'ORDER-123',
            'reference_type' => 'order'
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('createPaymentRecord');
        $method->setAccessible(true);

        // Act
        $result = $method->invokeArgs($this->gateway, [$data]);

        // Assert
        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals(200.00, $result->amount);
        $this->assertEquals('test@example.com', $result->customer_email);
        $this->assertEquals('ORDER-123', $result->external_reference_id);
        $this->assertEquals('order', $result->reference_type);
        $this->assertNotNull($result->reference_id);
    }

    /** @test */    public function it_logs_gateway_response_when_payment_exists()
    {
        // Arrange
        $response = ['transaction_id' => 'TXN123', 'status' => 'success'];
          // Create a real payment record
        $payment = new Payment();
        $payment->amount = 100.00;
        $payment->currency = 'MYR';
        $payment->customer_email = 'test@example.com';
        $payment->reference_id = 'PAY-TEST-123';
        $payment->gateway = 'test_gateway';
        $payment->save();

        // Set payment on gateway using reflection
        $reflection = new \ReflectionClass($this->gateway);
        $paymentProperty = $reflection->getProperty('payment');
        $paymentProperty->setAccessible(true);
        $paymentProperty->setValue($this->gateway, $payment);

        $method = $reflection->getMethod('logGatewayResponse');
        $method->setAccessible(true);

        // Act
        $method->invokeArgs($this->gateway, [$response]);

        // Assert - Refresh payment and check if gateway_response was updated
        $payment->refresh();
        $this->assertEquals($response, $payment->gateway_response);
    }

    /** @test */
    public function it_does_not_log_when_no_payment_exists()
    {
        // Arrange
        $response = ['transaction_id' => 'TXN123'];

        // Ensure no payment is set
        $reflection = new \ReflectionClass($this->gateway);
        $paymentProperty = $reflection->getProperty('payment');
        $paymentProperty->setAccessible(true);
        $paymentProperty->setValue($this->gateway, null);

        $method = $reflection->getMethod('logGatewayResponse');
        $method->setAccessible(true);

        // Act & Assert (should not throw exception)
        $this->expectNotToPerformAssertions();
        $method->invokeArgs($this->gateway, [$response]);
    }

    /** @test */
    public function it_returns_default_verification_response()
    {
        // Act
        $result = $this->gateway->verifyPayment('TXN123');        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment verification not supported', $result['message']);
        $this->assertStringContainsString('test_gateway', $result['message']);
    }

    /** @test */
    public function it_checks_if_enabled_from_config()
    {
        // Mock config
        $reflection = new \ReflectionClass($this->gateway);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        
        // Test enabled
        $configProperty->setValue($this->gateway, ['enabled' => true]);
        $this->assertTrue($this->gateway->isEnabled());
        
        // Test disabled
        $configProperty->setValue($this->gateway, ['enabled' => false]);
        $this->assertFalse($this->gateway->isEnabled());
        
        // Test default (no enabled key)
        $configProperty->setValue($this->gateway, []);
        $this->assertFalse($this->gateway->isEnabled());
    }

    /** @test */
    public function it_returns_gateway_config()
    {
        // Arrange
        $config = ['enabled' => true, 'api_key' => 'test_key'];
        
        $reflection = new \ReflectionClass($this->gateway);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($this->gateway, $config);

        // Act
        $result = $this->gateway->getConfig();

        // Assert
        $this->assertEquals($config, $result);
    }
}
