<?php

namespace Ejoi8\PaymentGateway\Services;

use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\Gateways\PaymentGatewayInterface;

class PaymentService
{
    protected $gateways = [];

    public function __construct()
    {
        $this->loadGateways();
    }

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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }

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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }

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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e
            ];
        }
    }

    public function getAvailableGateways(): array
    {
        return array_filter($this->gateways, function ($gateway) {
            return $gateway->isEnabled();
        });
    }

    public function getGateway(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    public function hasGateway(string $name): bool
    {
        return isset($this->gateways[$name]) && $this->gateways[$name]->isEnabled();
    }

    public function getPayment(string $id): ?Payment
    {
        return Payment::find($id);
    }

    public function getPaymentByReference(string $reference): ?Payment
    {
        return Payment::where('reference_id', $reference)->first();
    }

    public function getPaymentsByStatus(string $status): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('status', $status)->latest()->get();
    }

    public function approveManualPayment(string $paymentId): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->approvePayment($paymentId);
    }

    public function rejectManualPayment(string $paymentId, string $reason = null): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->rejectPayment($paymentId, $reason);
    }

    public function uploadManualPaymentProof(string $paymentId, $file): array
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Manual payment not found'];
        }

        $gateway = $this->getGateway('manual');
        return $gateway->handleProofUpload($paymentId, $file);
    }

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
