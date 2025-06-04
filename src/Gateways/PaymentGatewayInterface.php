<?php

namespace Ejoi8\PaymentGateway\Gateways;

/**
 * Payment Gateway Interface
 * 
 * This interface defines the contract that all payment gateway classes must implement.
 */
interface PaymentGatewayInterface
{
    /**
     * Initialize payment and get payment URL
     * 
     * @param array $data Payment data including amount, customer info, etc.
     * @return array Response with payment URL and status
     */
    public function createPayment(array $data): array;
    
    /**
     * Handle payment callback/webhook from payment provider
     * 
     * @param array $data Callback data from payment provider
     * @return array Response with payment status
     */
    public function handleCallback(array $data): array;
    
    /**
     * Verify payment status with payment provider
     * 
     * @param string $transactionId Transaction ID to verify
     * @return array Response with verification result
     */
    public function verifyPayment(string $transactionId): array;
    
    /**
     * Get gateway identifier name
     * 
     * @return string Gateway name
     */
    public function getName(): string;
    
    /**
     * Check if gateway is enabled in configuration
     * 
     * @return bool Whether gateway is enabled
     */
    public function isEnabled(): bool;
    
    /**
     * Get gateway configuration
     * 
     * @return array Gateway configuration
     */
    public function getConfig(): array;
}
