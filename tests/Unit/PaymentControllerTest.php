<?php

namespace Ejoi8\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Mockery;
use Ejoi8\PaymentGateway\Controllers\PaymentController;
use Ejoi8\PaymentGateway\Services\PaymentService;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentController $controller;
    protected $mockPaymentService;

    protected function getPackageProviders($app)
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
        $app['config']->set('payment-gateway.success_route', 'payment-gateway.success');
        $app['config']->set('payment-gateway.failed_route', 'payment-gateway.failed');

        // Configure app URL for route generation
        $app['config']->set('app.url', 'https://example.com');
    }

    protected function defineRoutes($router)
    {
        // Define test routes needed for controller tests
        $router->get('/payment-gateway/success', function () {
            return 'Success Page';
        })->name('payment-gateway.success');
        
        $router->get('/payment-gateway/failed', function () {
            return 'Failed Page';
        })->name('payment-gateway.failed');
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up database tables
        $this->artisan('migrate');
        
        // Create mock payment service
        $this->mockPaymentService = Mockery::mock(PaymentService::class);
        $this->controller = new PaymentController($this->mockPaymentService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_payment_successfully()
    {
        // Arrange
        $requestData = [
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'description' => 'Test payment',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+60123456789',
            'metadata' => ['order_id' => '12345']
        ];

        $mockResult = [
            'success' => true,
            'payment_url' => 'https://payment.example.com/pay/12345',
            'payment' => new Payment()
        ];

        $this->mockPaymentService
            ->shouldReceive('createPayment')
            ->with($requestData)
            ->andReturn($mockResult);

        $request = Request::create('/payment/create', 'POST', $requestData);

        // Act
        $response = $this->controller->create($request);

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://payment.example.com/pay/12345', $response->getTargetUrl());
    }

    /** @test */
    public function it_redirects_to_upload_page_for_manual_payments()
    {
        // Arrange
        $requestData = [
            'gateway' => 'manual',
            'amount' => 100.00,
            'customer_email' => 'john@example.com'
        ];

        $mockResult = [
            'success' => true,
            'requires_upload' => true,
            'payment_url' => 'https://example.com/manual-upload/12345',
            'payment' => new Payment()
        ];

        $this->mockPaymentService
            ->shouldReceive('createPayment')
            ->with($requestData)
            ->andReturn($mockResult);

        $request = Request::create('/payment/create', 'POST', $requestData);

        // Act
        $response = $this->controller->create($request);

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com/manual-upload/12345', $response->getTargetUrl());
    }

    /** @test */
    public function it_returns_back_with_errors_when_payment_creation_fails()
    {
        // Arrange
        $requestData = [
            'gateway' => 'invalid_gateway',
            'amount' => 100.00,
            'customer_email' => 'john@example.com'
        ];

        $mockResult = [
            'success' => false,
            'message' => 'Gateway not found'
        ];

        $this->mockPaymentService
            ->shouldReceive('createPayment')
            ->with($requestData)
            ->andReturn($mockResult);

        $request = Request::create('/payment/create', 'POST', $requestData);

        // Act
        $response = $this->controller->create($request);

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('errors'));
    }

    /** @test */
    public function it_validates_required_fields_for_payment_creation()
    {
        // Arrange
        $request = Request::create('/payment/create', 'POST', [
            // Missing required 'gateway' and 'amount'
            'customer_email' => 'john@example.com'
        ]);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->controller->create($request);
    }

    /** @test */
    public function it_handles_callback_with_paid_status()
    {
        // Arrange
        $callbackData = [
            'transaction_id' => 'TXN123',
            'status' => 'paid',
            'amount' => 100.00
        ];

        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-TEST-123',
            'amount' => 100.00,
            'status' => 'paid'
        ]);

        $mockResult = [
            'success' => true,
            'status' => 'paid',
            'payment' => $payment
        ];

        $this->mockPaymentService
            ->shouldReceive('handleCallback')
            ->with('manual', $callbackData)
            ->andReturn($mockResult);        $request = Request::create('/payment/callback/manual', 'POST', $callbackData);

        // Act
        $response = $this->controller->callback($request, 'manual');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/payment-gateway/success', $response->getTargetUrl());
    }

    /** @test */
    public function it_handles_callback_with_failed_status()
    {
        // Arrange
        $callbackData = [
            'transaction_id' => 'TXN123',
            'status' => 'failed'
        ];

        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-TEST-123',
            'status' => 'failed'
        ]);

        $mockResult = [
            'success' => true,
            'status' => 'failed',
            'payment' => $payment
        ];

        $this->mockPaymentService
            ->shouldReceive('handleCallback')
            ->with('manual', $callbackData)
            ->andReturn($mockResult);        $request = Request::create('/payment/callback/manual', 'POST', $callbackData);

        // Act
        $response = $this->controller->callback($request, 'manual');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/payment-gateway/failed', $response->getTargetUrl());
    }

    /** @test */
    public function it_returns_json_error_when_callback_fails()
    {
        // Arrange
        $callbackData = ['invalid' => 'data'];

        $mockResult = [
            'success' => false,
            'message' => 'Invalid callback data'
        ];

        $this->mockPaymentService
            ->shouldReceive('handleCallback')
            ->with('manual', $callbackData)
            ->andReturn($mockResult);

        $request = Request::create('/payment/callback/manual', 'POST', $callbackData);

        // Act
        $response = $this->controller->callback($request, 'manual');

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid callback data', $responseData['error']);
    }

    /** @test */
    public function it_logs_callback_requests()
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with('Payment callback received', Mockery::type('array'));

        $this->mockPaymentService
            ->shouldReceive('handleCallback')
            ->andReturn(['success' => false, 'message' => 'Test']);

        $request = Request::create('/payment/callback/manual', 'POST', ['test' => 'data']);

        // Act
        $this->controller->callback($request, 'manual');

        // Assert - Log assertion is handled by the shouldReceive expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_displays_success_page_with_payment_from_request()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-SUCCESS-123',
            'amount' => 100.00,
            'status' => 'paid'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/payment/success?payment_id=1');

        // Act
        $response = $this->controller->success($request);

        // Assert
        $this->assertEquals('payment-gateway::pages.thank-you', $response->getName());
        $this->assertEquals($payment, $response->getData()['payment']);
    }

    /** @test */
    public function it_displays_success_page_with_payment_from_session()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-SUCCESS-123',
            'amount' => 100.00,
            'status' => 'paid'
        ]);

        Session::put('payment', $payment);
        $request = Request::create('/payment/success');

        // Act
        $response = $this->controller->success($request);

        // Assert
        $this->assertEquals('payment-gateway::pages.thank-you', $response->getName());
        $this->assertEquals($payment, $response->getData()['payment']);
    }

    /** @test */
    public function it_displays_failed_page()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-FAILED-123',
            'amount' => 100.00,
            'status' => 'failed'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/payment/failed?payment_id=1');

        // Act
        $response = $this->controller->failed($request);

        // Assert
        $this->assertEquals('payment-gateway::pages.payment-failed', $response->getName());
        $this->assertEquals($payment, $response->getData()['payment']);
    }

    /** @test */
    public function it_shows_payment_details()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-SHOW-123',
            'amount' => 100.00,
            'status' => 'pending'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('PAY-SHOW-123')
            ->andReturn($payment);

        // Act
        $response = $this->controller->show('PAY-SHOW-123');

        // Assert
        $this->assertEquals('payment-gateway::pages.payment', $response->getName());
        $this->assertEquals($payment, $response->getData()['payment']);
    }

    /** @test */
    public function it_returns_404_when_payment_not_found()
    {
        // Arrange
        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('NON-EXISTENT')
            ->andReturn(null);

        // Act & Assert
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->controller->show('NON-EXISTENT');
    }

    /** @test */
    public function it_displays_manual_upload_form()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-MANUAL-123',
            'gateway' => 'manual',
            'amount' => 100.00,
            'status' => 'pending'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/payment/manual-upload/1', 'GET');

        // Act
        $response = $this->controller->manualUpload($request, '1');

        // Assert
        $this->assertEquals('payment-gateway::pages.manual-upload', $response->getName());
        $this->assertEquals($payment, $response->getData()['payment']);
    }

    /** @test */
    public function it_processes_manual_upload_successfully()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'reference_id' => 'PAY-MANUAL-123',
            'gateway' => 'manual',
            'amount' => 100.00,
            'status' => 'pending'
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $this->mockPaymentService
            ->shouldReceive('uploadManualPaymentProof')
            ->with('1', $file)
            ->andReturn([
                'success' => true,
                'payment' => $payment,
                'message' => 'Proof uploaded successfully'
            ]);        $request = Request::create('/payment/manual-upload/1', 'POST', [], [], ['proof_file' => $file]);

        // Act
        $response = $this->controller->manualUpload($request, '1');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/payment-gateway/success', $response->getTargetUrl());
    }

    /** @test */
    public function it_validates_upload_file_requirements()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'gateway' => 'manual',
            'status' => 'pending'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/payment/manual-upload/1', 'POST', [], [], [
            // Missing proof_file
        ]);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->controller->manualUpload($request, '1');
    }

    /** @test */
    public function it_returns_404_for_non_manual_payment_upload()
    {
        // Arrange
        $payment = new Payment([
            'id' => 1,
            'gateway' => 'chipin', // Not manual
            'status' => 'pending'
        ]);

        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/payment/manual-upload/1', 'GET');

        // Act & Assert
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->controller->manualUpload($request, '1');
    }

    /** @test */
    public function it_verifies_payment_status()
    {
        // Arrange
        $mockResult = [
            'success' => true,
            'status' => 'paid',
            'transaction_id' => 'TXN123'
        ];

        $this->mockPaymentService
            ->shouldReceive('verifyPayment')
            ->with('manual', 'TXN123')
            ->andReturn($mockResult);

        $request = Request::create('/payment/verify/manual/TXN123');

        // Act
        $response = $this->controller->verify($request, 'manual', 'TXN123');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('paid', $responseData['status']);
    }

    /** @test */
    public function it_approves_manual_payment()
    {
        // Arrange
        $mockResult = [
            'success' => true,
            'message' => 'Payment approved'
        ];

        $this->mockPaymentService
            ->shouldReceive('approveManualPayment')
            ->with('1')
            ->andReturn($mockResult);

        $request = Request::create('/payment/approve/1', 'POST');

        // Act
        $response = $this->controller->approveManualPayment($request, '1');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_handles_manual_payment_approval_failure()
    {
        // Arrange
        $mockResult = [
            'success' => false,
            'message' => 'Payment not found'
        ];

        $this->mockPaymentService
            ->shouldReceive('approveManualPayment')
            ->with('999')
            ->andReturn($mockResult);

        $request = Request::create('/payment/approve/999', 'POST');

        // Act
        $response = $this->controller->approveManualPayment($request, '999');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('errors'));
    }

    /** @test */
    public function it_rejects_manual_payment_with_reason()
    {
        // Arrange
        $mockResult = [
            'success' => true,
            'message' => 'Payment rejected'
        ];

        $this->mockPaymentService
            ->shouldReceive('rejectManualPayment')
            ->with('1', 'Invalid proof document')
            ->andReturn($mockResult);

        $request = Request::create('/payment/reject/1', 'POST', [
            'reason' => 'Invalid proof document'
        ]);

        // Act
        $response = $this->controller->rejectManualPayment($request, '1');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_rejects_manual_payment_without_reason()
    {
        // Arrange
        $mockResult = [
            'success' => true,
            'message' => 'Payment rejected'
        ];

        $this->mockPaymentService
            ->shouldReceive('rejectManualPayment')
            ->with('1', null)
            ->andReturn($mockResult);

        $request = Request::create('/payment/reject/1', 'POST');

        // Act
        $response = $this->controller->rejectManualPayment($request, '1');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_handles_manual_payment_rejection_failure()
    {
        // Arrange
        $mockResult = [
            'success' => false,
            'message' => 'Payment not found'
        ];

        $this->mockPaymentService
            ->shouldReceive('rejectManualPayment')
            ->with('999', 'Test reason')
            ->andReturn($mockResult);

        $request = Request::create('/payment/reject/999', 'POST', [
            'reason' => 'Test reason'
        ]);

        // Act
        $response = $this->controller->rejectManualPayment($request, '999');

        // Assert
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('errors'));
    }

    /** @test */
    public function it_gets_payment_from_request_parameter()
    {
        // Arrange
        $payment = new Payment(['id' => 1, 'reference_id' => 'PAY-TEST-123']);
        
        $this->mockPaymentService
            ->shouldReceive('getPayment')
            ->with('1')
            ->andReturn($payment);

        $request = Request::create('/?payment_id=1');

        // Act
        $result = $this->invokeMethod($this->controller, 'getPaymentFromRequestOrSession', [$request]);

        // Assert
        $this->assertEquals($payment, $result);
    }

    /** @test */
    public function it_gets_payment_from_session()
    {
        // Arrange
        $payment = new Payment(['id' => 1, 'reference_id' => 'PAY-SESSION-123']);
        Session::put('payment', $payment);
        
        $request = Request::create('/');

        // Act
        $result = $this->invokeMethod($this->controller, 'getPaymentFromRequestOrSession', [$request]);

        // Assert
        $this->assertEquals($payment, $result);
    }

    /** @test */
    public function it_returns_null_when_no_payment_found()
    {
        // Arrange
        $request = Request::create('/');

        // Act
        $result = $this->invokeMethod($this->controller, 'getPaymentFromRequestOrSession', [$request]);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Helper method to invoke protected/private methods
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
