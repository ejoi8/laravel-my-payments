<?php

namespace Ejoi8\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Mockery;
use Ejoi8\PaymentGateway\Services\PaymentService;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\Gateways\PaymentGatewayInterface;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;
    protected $mockGateway;

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
            'test' => [
                'name' => 'Test Gateway',
                'enabled' => true,
                'class' => \Ejoi8\PaymentGateway\Tests\Fixtures\TestGateway::class,
            ],
            'disabled' => [
                'name' => 'Disabled Gateway',
                'enabled' => false,
                'class' => \Ejoi8\PaymentGateway\Tests\Fixtures\TestGateway::class,
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->paymentService = new PaymentService();
        
        // Use reflection to inject mock gateway
        $reflection = new \ReflectionClass($this->paymentService);
        $gatewaysProperty = $reflection->getProperty('gateways');
        $gatewaysProperty->setAccessible(true);
        $gatewaysProperty->setValue($this->paymentService, [
            'test_gateway' => $this->mockGateway
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_payment_with_valid_gateway()
    {
        // Arrange
        $paymentData = [
            'gateway' => 'test_gateway',
            'amount' => 100.00,
            'currency' => 'MYR',
            'customer_email' => 'test@example.com'
        ];

        $expectedResponse = [
            'success' => true,
            'payment_url' => 'https://payment.example.com/12345',
            'payment' => new Payment()
        ];

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);
        $this->mockGateway->shouldReceive('createPayment')
            ->with($paymentData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->paymentService->createPayment($paymentData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('https://payment.example.com/12345', $result['payment_url']);
        $this->assertInstanceOf(Payment::class, $result['payment']);
    }

    #[Test]
    public function it_fails_when_gateway_not_found()
    {
        // Arrange
        $paymentData = [
            'gateway' => 'non_existent_gateway',
            'amount' => 100.00
        ];

        // Act
        $result = $this->paymentService->createPayment($paymentData);        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString("Gateway 'non_existent_gateway' not found", $result['message']);
    }

    #[Test]
    public function it_fails_when_gateway_disabled()
    {
        // Arrange
        $paymentData = [
            'gateway' => 'test_gateway',
            'amount' => 100.00
        ];

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(false);

        // Act
        $result = $this->paymentService->createPayment($paymentData);        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString("not found or not enabled", $result['message']);
    }

    #[Test]
    public function it_handles_gateway_exceptions_gracefully()
    {
        // Arrange
        $paymentData = [
            'gateway' => 'test_gateway',
            'amount' => 100.00
        ];

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);
        $this->mockGateway->shouldReceive('createPayment')
            ->andThrow(new Exception('Gateway error'));

        // Act
        $result = $this->paymentService->createPayment($paymentData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Gateway error', $result['message']);
        $this->assertInstanceOf(Exception::class, $result['error']);
    }

    #[Test]
    public function it_handles_callback_successfully()
    {
        // Arrange
        $callbackData = ['transaction_id' => '12345', 'status' => 'paid'];
        $expectedResponse = [
            'success' => true,
            'status' => 'paid',
            'payment' => new Payment()
        ];

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);
        $this->mockGateway->shouldReceive('handleCallback')
            ->with($callbackData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->paymentService->handleCallback('test_gateway', $callbackData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $result['status']);
    }

    #[Test]
    public function it_verifies_payment_successfully()
    {
        // Arrange
        $transactionId = 'TXN123456';
        $expectedResponse = [
            'success' => true,
            'status' => 'paid',
            'verified' => true
        ];

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);
        $this->mockGateway->shouldReceive('verifyPayment')
            ->with($transactionId)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->paymentService->verifyPayment('test_gateway', $transactionId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['verified']);
    }

    #[Test]
    public function it_checks_if_gateway_exists_and_enabled()
    {
        // Arrange
        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->paymentService->hasGateway('test_gateway'));
        $this->assertFalse($this->paymentService->hasGateway('non_existent'));
    }

    #[Test]
    public function it_gets_available_gateways_only_enabled_ones()
    {
        // Arrange
        $disabledGateway = Mockery::mock(PaymentGatewayInterface::class);
        $disabledGateway->shouldReceive('isEnabled')->andReturn(false);
        
        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);

        // Add disabled gateway via reflection
        $reflection = new \ReflectionClass($this->paymentService);
        $gatewaysProperty = $reflection->getProperty('gateways');
        $gatewaysProperty->setAccessible(true);
        $gatewaysProperty->setValue($this->paymentService, [
            'test_gateway' => $this->mockGateway,
            'disabled_gateway' => $disabledGateway
        ]);

        // Act
        $availableGateways = $this->paymentService->getAvailableGateways();

        // Assert
        $this->assertCount(1, $availableGateways);
        $this->assertArrayHasKey('test_gateway', $availableGateways);
        $this->assertArrayNotHasKey('disabled_gateway', $availableGateways);
    }    #[Test]
    public function it_creates_payment_with_external_reference()
    {
        // Arrange
        $paymentData = [
            'amount' => 100.00,
            'currency' => 'MYR',
            'customer_email' => 'test@example.com',
            'gateway' => 'test' // Use the test gateway that's configured
        ];
        $externalReferenceId = 'ORDER-123';
        $referenceType = 'order';

        $expectedData = array_merge($paymentData, [
            'external_reference_id' => $externalReferenceId,
            'reference_type' => $referenceType,
        ]);

        $this->mockGateway->shouldReceive('isEnabled')->andReturn(true);
        $this->mockGateway->shouldReceive('createPayment')
            ->with(Mockery::on(function ($data) use ($externalReferenceId, $referenceType) {
                return $data['external_reference_id'] === $externalReferenceId &&
                       $data['reference_type'] === $referenceType;
            }))
            ->andReturn(['success' => true]);        // Inject the mock gateway for the test
        $reflection = new \ReflectionClass($this->paymentService);
        $gatewaysProperty = $reflection->getProperty('gateways');
        $gatewaysProperty->setAccessible(true);
        $gatewaysProperty->setValue($this->paymentService, [
            'test' => $this->mockGateway
        ]);

        // Act
        $result = $this->paymentService->createPaymentWithExternalReference(
            $paymentData, 
            $externalReferenceId, 
            $referenceType
        );

        // Assert
        $this->assertTrue($result['success']);
    }
}
