<?php

namespace Ejoi8\PaymentGateway\Gateways;

class StripeGateway extends BaseGateway
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function createPayment(array $data): array
    {
        // TODO: Implement Stripe payment creation
        // This is a placeholder implementation
        
        $this->validateRequiredFields($data, ['amount', 'customer_email']);
        $payment = $this->createPaymentRecord($data);

        return [
            'success' => false,
            'message' => 'Stripe gateway is not yet implemented',
            'payment' => $payment,
        ];
    }

    public function handleCallback(array $data): array
    {
        // TODO: Implement Stripe webhook handling
        return [
            'success' => false,
            'message' => 'Stripe webhook handling is not yet implemented'
        ];
    }

    public function verifyPayment(string $transactionId): array
    {
        // TODO: Implement Stripe payment verification
        return [
            'success' => false,
            'message' => 'Stripe payment verification is not yet implemented'
        ];
    }
}
