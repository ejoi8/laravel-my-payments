<?php

namespace Ejoi8\PaymentGateway\Services;

use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\Gateways\PaymentGatewayInterface;
use Exception;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment Service
 * 
 * This service provides a central access point for all payment gateway operations.
 */
class PaymentService
{
    /**
     * Registered payment gateways
     * 
     * @var array<string, PaymentGatewayInterface>
     */
    protected $gateways = [];

    /**
     * Constructor - loads available payment gateways
     */
    public function __construct()
    {
        $this->loadGateways();
    }    /**
     * Create a new payment
     * 
     * @param array $data Payment data
     * @return array Response with payment details or error
     */
    public function createPayment(array $data): array
    {
        $gatewayName = $data['gateway'] ?? config('payment-gateway.default_gateway');
        
        if (!$this->hasGateway($gatewayName)) {
            return [
                'success' => false,
                'message' => "Gateway '{$gatewayName}' not found or not enabled"
            ];
        }

        $gateway = $this->getGateway($gatewayName);
        
        try {
            return $gateway->createPayment($data);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }

    /**
     * Handle callback from payment gateway
     * 
     * @param string $gatewayName Gateway name
     * @param array $data Callback data
     * @return array Response with payment status
     */
    public function handleCallback(string $gatewayName, array $data): array
    {
        if (!$this->hasGateway($gatewayName)) {
            return [
                'success' => false,
                'message' => "Gateway '{$gatewayName}' not found"
            ];
        }

        $gateway = $this->getGateway($gatewayName);
        
        try {
            return $gateway->handleCallback($data);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }

    /**
     * Verify payment status with payment provider
     * 
     * @param string $gatewayName Gateway name
     * @param string $transactionId Transaction ID to verify
     * @return array Response with verification result
     */
    public function verifyPayment(string $gatewayName, string $transactionId): array
    {
        if (!$this->hasGateway($gatewayName)) {
            return [
                'success' => false,
                'message' => "Gateway '{$gatewayName}' not found"
            ];
        }

        $gateway = $this->getGateway($gatewayName);
        
        try {
            return $gateway->verifyPayment($transactionId);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }    /**
     * Get all available and enabled gateways
     * 
     * @return array<string, PaymentGatewayInterface> List of available gateways
     */
    public function getAvailableGateways(): array
    {
        return array_filter($this->gateways, function ($gateway) {
            return $gateway->isEnabled();
        });
    }

    /**
     * Get a specific payment gateway by name
     * 
     * @param string $name Gateway name
     * @return PaymentGatewayInterface|null Payment gateway or null if not found
     */
    public function getGateway(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    /**
     * Check if a gateway exists and is enabled
     * 
     * @param string $name Gateway name
     * @return bool Whether gateway exists and is enabled
     */
    public function hasGateway(string $name): bool
    {
        return isset($this->gateways[$name]) && $this->gateways[$name]->isEnabled();
    }

    /**
     * Get payment by ID
     * 
     * @param string $id Payment ID
     * @return Payment|null Payment record or null if not found
     */
    public function getPayment(string $id): ?Payment
    {
        return Payment::find($id);
    }

    /**
     * Get payment by reference ID
     * 
     * @param string $reference Reference ID
     * @return Payment|null Payment record or null if not found
     */
    public function getPaymentByReference(string $reference): ?Payment
    {
        return Payment::where('reference_id', $reference)->first();
    }

    /**
     * Get payments by status
     * 
     * @param string $status Payment status
     * @return Collection Collection of payment records
     */
    public function getPaymentsByStatus(string $status): Collection
    {
        return Payment::where('status', $status)->latest()->get();
    }

    /**
     * Find payments by external reference ID
     * 
     * @param string $externalReferenceId External reference ID (e.g. order ID)
     * @param string|null $referenceType Reference type (e.g. 'order', 'subscription')
     * @return Collection Collection of payment records
     */
    public function getPaymentsByExternalReference(string $externalReferenceId, ?string $referenceType = null): Collection
    {
        $query = Payment::where('external_reference_id', $externalReferenceId);
        
        if ($referenceType) {
            $query->where('reference_type', $referenceType);
        }
        
        return $query->get();
    }
    
    /**
     * Create a payment with an external reference
     * 
     * @param array $data Payment data
     * @param string $externalReferenceId External reference ID (e.g. order ID)
     * @param string $referenceType Reference type (e.g. 'order', 'subscription')
     * @return array Response with payment details or error
     */
    public function createPaymentWithExternalReference(array $data, string $externalReferenceId, string $referenceType = 'order'): array
    {
        // Add external reference data to the payment data
        $data['external_reference_id'] = $externalReferenceId;
        $data['reference_type'] = $referenceType;
        
        return $this->createPayment($data);
    }
    
    /**
     * Find the latest payment for an external reference
     * 
     * @param string $externalReferenceId External reference ID (e.g. order ID)
     * @param string|null $referenceType Reference type (e.g. 'order', 'subscription')
     * @return Payment|null Latest payment record or null if not found
     */
    public function getLatestPaymentByExternalReference(string $externalReferenceId, ?string $referenceType = null): ?Payment
    {
        $query = Payment::where('external_reference_id', $externalReferenceId);
        
        if ($referenceType) {
            $query->where('reference_type', $referenceType);
        }
        
        return $query->latest()->first();
    }
    
    /**
     * Check if a successful payment exists for an external reference
     * 
     * @param string $externalReferenceId External reference ID (e.g. order ID)
     * @param string|null $referenceType Reference type (e.g. 'order', 'subscription')
     * @return bool Whether a successful payment exists
     */
    public function hasSuccessfulPayment(string $externalReferenceId, ?string $referenceType = null): bool
    {
        $query = Payment::where('external_reference_id', $externalReferenceId)
            ->where('status', Payment::STATUS_PAID);
        
        if ($referenceType) {
            $query->where('reference_type', $referenceType);
        }
        
        return $query->exists();
    }
    
    /**
     * Approve a manual payment
     * 
     * @param string $paymentId Payment ID
     * @return array Response with status
     */
    public function approveManualPayment(string $paymentId): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->approvePayment($paymentId);
    }

    /**
     * Reject a manual payment
     * 
     * @param string $paymentId Payment ID
     * @param string|null $reason Rejection reason
     * @return array Response with status
     */
    public function rejectManualPayment(string $paymentId, string $reason = null): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->rejectPayment($paymentId, $reason);
    }

    /**
     * Upload proof for a manual payment
     * 
     * @param string $paymentId Payment ID
     * @param mixed $file Uploaded file
     * @return array Response with status
     */
    public function uploadManualPaymentProof(string $paymentId, $file): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->handleProofUpload($paymentId, $file);
    }

    /**
     * Load available payment gateways
     * 
     * @return void
     */
    private function loadGateways(): void
    {
        $gatewayClasses = [
            'toyyibpay' => \Ejoi8\PaymentGateway\Gateways\ToyyibpayGateway::class,
            'chipin' => \Ejoi8\PaymentGateway\Gateways\ChipInGateway::class,
            'paypal' => \Ejoi8\PaymentGateway\Gateways\PaypalGateway::class,
            'stripe' => \Ejoi8\PaymentGateway\Gateways\StripeGateway::class,
            'manual' => \Ejoi8\PaymentGateway\Gateways\ManualPaymentGateway::class,
        ];

        foreach ($gatewayClasses as $name => $class) {
            if (class_exists($class)) {
                $this->gateways[$name] = new $class();
            }
        }
    }
}
