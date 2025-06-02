<?php

namespace Ejoi8\PaymentGateway\Gateways;

interface PaymentGatewayInterface
{
    /**
     * Initialize payment and get payment URL
     */
    public function createPayment(array $data): array;
    
    /**
     * Handle payment callback/webhook
     */
    public function handleCallback(array $data): array;
    
    /**
     * Verify payment status
     */
    public function verifyPayment(string $transactionId): array;
    
    /**
     * Get gateway name
     */
    public function getName(): string;
    
    /**
     * Check if gateway is enabled
     */
    public function isEnabled(): bool;
    
    /**
     * Get gateway configuration
     */
    public function getConfig(): array;
}
