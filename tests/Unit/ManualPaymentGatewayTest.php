<?php

namespace Ejoi8\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Mockery;
use Ejoi8\PaymentGateway\Gateways\ManualPaymentGateway;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\PaymentGatewayServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ManualPaymentGatewayTest extends TestCase
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
        $app['config']->set('payment-gateway.gateways.manual', [
            'enabled' => true,
            'upload_path' => 'payment-proofs',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_file_size' => 5120, // KB
        ]);
    }    

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function getGateway(): ManualPaymentGateway
    {
        $gateway = new ManualPaymentGateway();
        $gateway->setConfig([
            'enabled' => true,
            'upload_path' => 'payment-proofs',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_file_size' => 5120, // KB
        ]);
        return $gateway;
    }    #[Test]
    public function it_returns_correct_gateway_name()
    {
        $this->assertEquals('manual', $this->getGateway()->getName());
    }    #[Test]
    public function it_creates_manual_payment_without_proof()
    {
        // Act
        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'description' => 'Test manual payment'
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['requires_upload']);
        $this->assertArrayHasKey('payment', $result);
        $this->assertInstanceOf(Payment::class, $result['payment']);
        
        // Verify payment was created in database
        $payment = $result['payment'];
        $this->assertEquals('manual', $payment->gateway);
        $this->assertEquals('100.00', $payment->amount);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('test@example.com', $payment->customer_email);
        $this->assertEquals('Test manual payment', $payment->description);
    }    #[Test]
    public function it_creates_manual_payment_with_proof()
    {
        // Create a fake uploaded file
        $fakeFile = UploadedFile::fake()->image('payment-proof.jpg', 800, 600)->size(1024);        // Act
        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'description' => 'Test manual payment with proof',
            'proof_file' => $fakeFile
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertFalse($result['requires_upload']);
        $this->assertArrayHasKey('payment', $result);
        $this->assertInstanceOf(Payment::class, $result['payment']);
        
        // Verify payment was created in database
        $payment = $result['payment'];
        $this->assertEquals('manual', $payment->gateway);
        $this->assertEquals('100.00', $payment->amount);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('test@example.com', $payment->customer_email);
        $this->assertEquals('Test manual payment with proof', $payment->description);
        $this->assertNotNull($payment->proof_file_path);
    }

    #[Test]
    public function it_validates_required_amount_field()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'amount' is required for manual gateway");

        $this->getGateway()->createPayment([
            'customer_email' => 'test@example.com'
            // Missing amount
        ]);
    }    #[Test]
    public function it_rejects_oversized_files()
    {
        // Create oversized file (6MB > 5MB limit)
        $oversizedFile = UploadedFile::fake()->image('large.jpg')->size(6144);

        // Act
        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'proof_file' => $oversizedFile
        ]);// Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File size exceeds maximum allowed', $result['message']);
    }    #[Test]    public function it_rejects_invalid_file_extensions()
    {
        // Create file with invalid extension
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100);

        // Act
        $result = $this->getGateway()->createPayment([
            'amount' => 100.00,
            'customer_email' => 'test@example.com',
            'proof_file' => $invalidFile
        ]);// Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File type not allowed', $result['message']);
        $this->assertStringContainsString('jpg, jpeg, png, pdf', $result['message']);
    }    #[Test]
    public function it_handles_callback_with_error_message()
    {
        // Act
        $result = $this->getGateway()->handleCallback(['test' => 'data']);// Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Manual payments do not support callbacks', $result['message']);
    }    #[Test]
    public function it_handles_proof_upload_for_existing_payment()
    {        // Create a real payment
        $payment = new Payment([
            'reference_id' => 'PAY-TEST-UPLOAD-' . uniqid(),
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'description' => 'Test payment for proof upload',
        ]);
        $payment->save();

        // Create a valid file
        $file = UploadedFile::fake()->create('proof.pdf', 100);        // Act
        $result = $this->getGateway()->handleProofUpload($payment->id, $file);// Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('payment', $result);
        
        // Verify payment was updated in database
        $payment->refresh();
        $this->assertNotNull($payment->proof_file_path);
    }
    
    #[Test]
    public function it_fails_proof_upload_for_non_existent_payment()
    {
        $fakeFile = UploadedFile::fake()->image('payment-proof.jpg');        // Act
        $result = $this->getGateway()->handleProofUpload('non-existent', $fakeFile);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Payment not found', $result['message']);
    }    #[Test]
    public function it_approves_payment_successfully()
    {        // Create a real payment
        $payment = new Payment([
            'reference_id' => 'PAY-TEST-APPROVAL-' . uniqid(),
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'description' => 'Test payment for approval',
            'proof_file_path' => 'payment-proofs/test-proof.jpg', // Add proof file path
        ]);
        $payment->save();        // Act
        $result = $this->getGateway()->approvePayment($payment->id);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Payment approved successfully', $result['message']);
        
        // Verify payment status was updated
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }    #[Test]
    public function it_rejects_payment_with_reason()
    {        // Create a real payment
        $payment = new Payment([
            'reference_id' => 'PAY-TEST-REJECTION-' . uniqid(),
            'gateway' => 'manual',
            'amount' => 100.00,
            'currency' => 'MYR',
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'description' => 'Test payment for rejection',
        ]);
        $payment->save();

        // Act
        $result = $this->getGateway()->rejectPayment($payment->id, 'Invalid proof document');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Payment rejected', $result['message']);
        
        // Verify payment status was updated
        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
    }    #[Test]
    public function it_validates_file_size_correctly()
    {
        $gateway = $this->getGateway();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('validateFileSize');
        $method->setAccessible(true);

        // Test valid file size (1KB)
        $validFile = UploadedFile::fake()->image('test.jpg')->size(1024);
        $this->assertTrue($method->invokeArgs($gateway, [$validFile]));

        // Test invalid file size (6MB - exceeds 5MB limit)
        $invalidFile = UploadedFile::fake()->image('large.jpg')->size(6144);
        $this->assertFalse($method->invokeArgs($gateway, [$invalidFile]));
    }    #[Test]
    public function it_validates_file_extension_correctly()
    {
        $gateway = $this->getGateway();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('validateFileExtension');
        $method->setAccessible(true);

        // Test valid extensions
        $jpgFile = UploadedFile::fake()->image('test.jpg');
        $this->assertTrue($method->invokeArgs($gateway, [$jpgFile]));
        
        $pngFile = UploadedFile::fake()->image('test.png');
        $this->assertTrue($method->invokeArgs($gateway, [$pngFile]));
        
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100);
        $this->assertTrue($method->invokeArgs($gateway, [$pdfFile]));

        // Test invalid extension - create a file with disallowed extension
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100);
        $this->assertFalse($method->invokeArgs($gateway, [$invalidFile]));
    }    #[Test]
    public function it_validates_uploaded_file_comprehensively()
    {
        $gateway = $this->getGateway();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('validateUploadedFile');
        $method->setAccessible(true);

        // Test valid file
        $validFile = UploadedFile::fake()->image('test.jpg')->size(1024);
        $result = $method->invokeArgs($gateway, [$validFile]);
        $this->assertTrue($result['success']);        

        // Test invalid file size
        $invalidSizeFile = UploadedFile::fake()->image('large.jpg')->size(6144);
        $result = $method->invokeArgs($gateway, [$invalidSizeFile]);        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File size exceeds', $result['message']);        

        // Test invalid extension
        $invalidExtFile = UploadedFile::fake()->create('document.txt', 100);
        $result = $method->invokeArgs($gateway, [$invalidExtFile]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File type not allowed', $result['message']);
    }
}
