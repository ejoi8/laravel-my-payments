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
{    /**
     * Gateway configuration
     * 
     * @var array|null
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
        // Don't load config in constructor for better testability
        // Config will be loaded lazily when first accessed
    }    /**
     * Get gateway configuration
     * 
     * @return array Configuration array
     */
    public function getConfig(): array
    {
        if ($this->config === null) {
            $this->loadConfig();
        }
        
        return $this->config;
    }

    /**
     * Load gateway configuration
     * 
     * @return void
     */
    protected function loadConfig(): void
    {
        if (function_exists('config')) {
            $this->config = config("payment-gateway.gateways.{$this->getName()}") ?? [];
        } else {
            $this->config = [];
        }
    }

    /**
     * Set gateway configuration (useful for testing)
     * 
     * @param array $config Configuration array
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }    /**
     * Check if gateway is enabled
     * 
     * @return bool Whether gateway is enabled
     */
    public function isEnabled(): bool
    {
        $config = $this->getConfig();
        return $config['enabled'] ?? false;
    }    /**
     * Generate callback URL for gateway
     * 
     * @return string Callback URL
     */
    protected function generateCallbackUrl(): string
    {
        try {
            return route('payment-gateway.callback', ['gateway' => $this->getName()]);
        } catch (\Exception|\TypeError $e) {
            // Fallback for testing environments where routes might not be available
            return config('app.url', 'http://localhost') . '/payment-gateway/callback/' . $this->getName();
        }
    }
    
    /**
     * Generate success URL for payment completion
     * 
     * @return string Success URL
     */
    protected function generateSuccessUrl(): string
    {
        try {
            return route(config('payment-gateway.success_route', 'payment-gateway.success'));
        } catch (\Exception|\TypeError $e) {
            // Fallback for testing environments where routes might not be available
            return config('app.url', 'http://localhost') . '/payment-gateway/success';
        }
    }

    /**
     * Generate failed URL for payment failures
     * 
     * @return string Failed URL
     */
    protected function generateFailedUrl(): string
    {
        try {
            return route(config('payment-gateway.failed_route', 'payment-gateway.failed'));
        } catch (\Exception|\TypeError $e) {
            // Fallback for testing environments where routes might not be available
            return config('app.url', 'http://localhost') . '/payment-gateway/failed';
        }
    }/**
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
