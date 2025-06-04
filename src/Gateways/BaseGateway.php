<?php

namespace Ejoi8\PaymentGateway\Gateways;

use Ejoi8\PaymentGateway\Models\Payment;
use InvalidArgumentException;

/**
 * Base Payment Gateway
 * 
 * Abstract class that provides common functionality for all payment gateways
 */
abstract class BaseGateway implements PaymentGatewayInterface
{
    /**
     * Gateway configuration
     * 
     * @var array
     */
    protected $config;
    
    /**
     * Current payment being processed
     * 
     * @var Payment|null
     */
    protected $payment;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = config("payment-gateway.gateways.{$this->getName()}");
    }

    /**
     * Get gateway configuration
     * 
     * @return array Configuration array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if gateway is enabled
     * 
     * @return bool Whether gateway is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Generate callback URL for gateway
     * 
     * @return string Callback URL
     */
    protected function generateCallbackUrl(): string
    {
        return route('payment-gateway.callback', ['gateway' => $this->getName()]);
    }
    
    /**
     * Generate success URL for payment completion
     * 
     * @return string Success URL
     */
    protected function generateSuccessUrl(): string
    {
        return route(config('payment-gateway.success_route', 'payment-gateway.success'));
    }

    /**
     * Generate failed URL for payment failures
     * 
     * @return string Failed URL
     */
    protected function generateFailedUrl(): string
    {
        return route(config('payment-gateway.failed_route', 'payment-gateway.failed'));
    }    /**
     * Format amount to standard decimal places
     * 
     * @param float $amount Amount to format
     * @return float Formatted amount
     */
    protected function formatAmount(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Validate that required fields are present in data array
     * 
     * @param array $data Input data
     * @param array $required Required field names
     * @return void
     * @throws InvalidArgumentException If a required field is missing
     */
    protected function validateRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Field '{$field}' is required for {$this->getName()} gateway");
            }
        }
    }

    /**
     * Create a new payment record in the database
     * 
     * @param array $data Payment data
     * @return Payment Created payment record
     */
    protected function createPaymentRecord(array $data): Payment
    {
        $payment = new Payment();        
        $paymentData = [
            'reference_id'   => $data['reference_id'] ?? $payment->generateReferenceId(),
            'gateway'        => $this->getName(),
            'amount'         => $this->formatAmount($data['amount']),
            'currency'       => $data['currency'] ?? config('payment-gateway.currency', 'MYR'),
            'status'         => Payment::STATUS_PENDING,
            'description'    => $data['description'] ?? null,
            'customer_name'  => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'metadata'       => $data['metadata'] ?? null,
        ];
        
        // Add external reference data if provided
        if (isset($data['external_reference_id'])) {
            $paymentData['external_reference_id'] = $data['external_reference_id'];
            $paymentData['reference_type']        = $data['reference_type'] ?? 'order';
        }
        
        $payment->fill($paymentData);
        $payment->save();

        $this->payment = $payment;
        return $payment;
    }

    /**
     * Log gateway response to payment record
     * 
     * @param array $response Gateway response data
     * @return void
     */
    protected function logGatewayResponse(array $response): void
    {
        if ($this->payment) {
            $this->payment->update([
                'gateway_response' => $response
            ]);
        }
    }

    /**
     * Default implementation for payment verification
     * Override in specific gateway classes that support verification
     * 
     * @param string $transactionId Transaction ID to verify
     * @return array Verification result
     */
    public function verifyPayment(string $transactionId): array
    {
        return [
            'success' => false,
            'message' => 'Payment verification not supported for ' . $this->getName(),
        ];
    }
}
