<?php

namespace Ejoi8\PaymentGateway\Gateways;

class ToyyibpayGateway extends BaseGateway
{
    public function getName(): string
    {
        return 'toyyibpay';
    }

    public function createPayment(array $data): array
    {
        $this->validateRequiredFields($data, ['amount', 'customer_name', 'customer_email']);

        $payment = $this->createPaymentRecord($data);

        $billData = [
            'categoryCode' => $this->config['category_code'],
            'billName' => $data['description'] ?? 'Payment',
            'billDescription' => $data['description'] ?? 'Payment',
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $this->formatAmount($data['amount']) * 100, // ToyyibPay uses cents
            'billReturnUrl' => $this->generateSuccessUrl() . '?payment_id=' . $payment->id,
            'billCallbackUrl' => $this->generateCallbackUrl(),
            'billExternalReferenceNo' => $payment->reference_id,
            'billTo' => $data['customer_name'],
            'billEmail' => $data['customer_email'],
            'billPhone' => $data['customer_phone'] ?? '',
        ];

        $response = $this->sendRequest('createBill', $billData);

        if (isset($response[0]['BillCode'])) {
            $billCode = $response[0]['BillCode'];
            $paymentUrl = $this->getPaymentUrl($billCode);
            
            $payment->update([
                'payment_url' => $paymentUrl,
                'gateway_transaction_id' => $billCode,
            ]);

            $this->logGatewayResponse($response);

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'transaction_id' => $billCode,
                'payment' => $payment,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create ToyyibPay bill',
            'response' => $response,
        ];
    }

    public function handleCallback(array $data): array
    {
        $billCode = $data['billcode'] ?? null;
        $status = $data['status_id'] ?? null;

        if (!$billCode) {
            return ['success' => false, 'message' => 'Missing bill code'];
        }

        $payment = \Ejoi8\PaymentGateway\Models\Payment::where('gateway_transaction_id', $billCode)
            ->where('gateway', $this->getName())
            ->first();

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update(['callback_data' => $data]);

        if ($status == '1') { // Successful payment
            $payment->markAsPaid($billCode, $data);
            return ['success' => true, 'status' => 'paid', 'payment' => $payment];
        } else {
            $payment->markAsFailed('Payment failed or cancelled', $data);
            return ['success' => true, 'status' => 'failed', 'payment' => $payment];
        }
    }

    private function sendRequest(string $endpoint, array $data): array
    {
        $url = $this->getApiUrl() . $endpoint;
        
        $postData = array_merge($data, [
            'userSecretKey' => $this->config['secret_key']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("ToyyibPay API request failed with HTTP code: {$httpCode}");
        }

        return json_decode($response, true) ?? [];
    }

    private function getApiUrl(): string
    {
        return $this->config['sandbox'] 
            ? 'https://dev.toyyibpay.com/index.php/api/' 
            : 'https://toyyibpay.com/index.php/api/';
    }

    private function getPaymentUrl(string $billCode): string
    {
        return $this->config['sandbox'] 
            ? "https://dev.toyyibpay.com/{$billCode}" 
            : "https://toyyibpay.com/{$billCode}";
    }

    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->sendRequest('getBillTransactions', [
                'billCode' => $transactionId
            ]);

            if (!empty($response)) {
                $transaction = $response[0] ?? [];
                $status = $transaction['billpaymentStatus'] ?? null;
                
                return [
                    'success' => true,
                    'status' => $status == '1' ? 'paid' : 'pending',
                    'data' => $transaction
                ];
            }

            return ['success' => false, 'message' => 'Transaction not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
