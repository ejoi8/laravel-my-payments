<?php

namespace Ejoi8\PaymentGateway\Gateways;

use Ejoi8\PaymentGateway\Models\Payment;

abstract class BaseGateway implements PaymentGatewayInterface
{
    protected $config;
    protected $payment;

    public function __construct()
    {
        $this->config = config("payment-gateway.gateways.{$this->getName()}");
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    protected function generateCallbackUrl(): string
    {
        return route('payment-gateway.callback', ['gateway' => $this->getName()]);
    }

    protected function generateSuccessUrl(): string
    {
        return route(config('payment-gateway.success_route', 'payment.success'));
    }

    protected function generateFailedUrl(): string
    {
        return route(config('payment-gateway.failed_route', 'payment.failed'));
    }

    protected function formatAmount(float $amount): float
    {
        return round($amount, 2);
    }

    protected function validateRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required for {$this->getName()} gateway");
            }
        }
    }

    protected function createPaymentRecord(array $data): Payment
    {
        $payment = new Payment();
        $payment->fill([
            'reference_id' => $data['reference_id'] ?? $payment->generateReferenceId(),
            'gateway' => $this->getName(),
            'amount' => $this->formatAmount($data['amount']),
            'currency' => $data['currency'] ?? config('payment-gateway.currency', 'MYR'),
            'status' => Payment::STATUS_PENDING,
            'description' => $data['description'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
        $payment->save();

        $this->payment = $payment;
        return $payment;
    }

    protected function logGatewayResponse(array $response): void
    {
        if ($this->payment) {
            $this->payment->update([
                'gateway_response' => $response
            ]);
        }
    }

    // Default implementation for gateways that don't support verification
    public function verifyPayment(string $transactionId): array
    {
        return [
            'success' => false,
            'message' => 'Payment verification not supported for ' . $this->getName(),
        ];
    }
}
