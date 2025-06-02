<?php

namespace Ejoi8\PaymentGateway\Gateways;

class ChipInGateway extends BaseGateway
{
    public function getName(): string
    {
        return 'chipin';
    }

    public function createPayment(array $data): array
    {
        $this->validateRequiredFields($data, ['amount', 'customer_email']);

        $payment = $this->createPaymentRecord($data);

        $purchaseData = [
            'brand_id' => $this->config['brand_id'],
            'client_reference' => $payment->reference_id,
            'purchase' => [
                'total_override' => $this->formatAmount($data['amount']) * 100, // Chip-in uses cents
                'currency' => $data['currency'] ?? 'MYR',
                'products' => [
                    [
                        'name' => $data['description'] ?? 'Payment',
                        'price' => $this->formatAmount($data['amount']) * 100,
                        'quantity' => 1,
                    ]
                ]
            ],
            'success_callback' => $this->generateCallbackUrl(),
            'success_redirect' => $this->generateSuccessUrl() . '?payment_id=' . $payment->id,
            'failure_redirect' => $this->generateFailedUrl() . '?payment_id=' . $payment->id,
            'cancel_redirect' => $this->generateFailedUrl() . '?payment_id=' . $payment->id,
        ];

        if (!empty($data['customer_email'])) {
            $purchaseData['client'] = [
                'email' => $data['customer_email'],
                'phone' => $data['customer_phone'] ?? '',
                'full_name' => $data['customer_name'] ?? '',
            ];
        }

        $response = $this->sendRequest('purchases/', $purchaseData, 'POST');

        if (isset($response['id']) && isset($response['payment_url'])) {
            $payment->update([
                'payment_url' => $response['payment_url'],
                'gateway_transaction_id' => $response['id'],
            ]);

            $this->logGatewayResponse($response);

            return [
                'success' => true,
                'payment_url' => $response['payment_url'],
                'transaction_id' => $response['id'],
                'payment' => $payment,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create Chip-in purchase',
            'response' => $response,
        ];
    }

    public function handleCallback(array $data): array
    {
        $purchaseId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$purchaseId) {
            return ['success' => false, 'message' => 'Missing purchase ID'];
        }

        $payment = \Ejoi8\PaymentGateway\Models\Payment::where('gateway_transaction_id', $purchaseId)
            ->where('gateway', $this->getName())
            ->first();

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update(['callback_data' => $data]);

        if ($status === 'paid') {
            $payment->markAsPaid($purchaseId, $data);
            return ['success' => true, 'status' => 'paid', 'payment' => $payment];
        } else {
            $payment->markAsFailed('Payment failed or cancelled', $data);
            return ['success' => true, 'status' => 'failed', 'payment' => $payment];
        }
    }

    private function sendRequest(string $endpoint, array $data, string $method = 'GET'): array
    {
        $url = $this->getApiUrl() . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->config['secret_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new \Exception("Chip-in API request failed with HTTP code: {$httpCode}");
        }

        return json_decode($response, true) ?? [];
    }

    private function getApiUrl(): string
    {
        return $this->config['sandbox'] 
            ? 'https://gate.chip-in.asia/api/v1/' 
            : 'https://gate.chip-in.asia/api/v1/';
    }

    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->sendRequest("purchases/{$transactionId}/", [], 'GET');

            if (isset($response['status'])) {
                return [
                    'success' => true,
                    'status' => $response['status'],
                    'data' => $response
                ];
            }

            return ['success' => false, 'message' => 'Purchase not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
