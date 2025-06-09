<?php

namespace Ejoi8\PaymentGateway\Gateways;

use Ejoi8\PaymentGateway\Models\Payment;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * ToyyibPay Payment Gateway Integration
 * 
 * This class provides integration with the ToyyibPay payment gateway API.
 * Documentation: https://toyyibpay.com/apireference
 */
class ToyyibpayGateway extends BaseGateway
{
    /**
     * Status code constants from ToyyibPay API
     */
    private const STATUS_SUCCESS = '1';
    private const STATUS_PENDING = '2';
    private const STATUS_FAILED  = '3';
    
    /**
     * Payment channel constants
     */
    private const CHANNEL_FPX         = '0';
    private const CHANNEL_CREDIT_CARD = '1';
    private const CHANNEL_BOTH        = '2';
    
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'toyyibpay';
    }
    
    /**
     * Create a payment bill in ToyyibPay
     * 
     * @param array $data Payment data
     * @return array Response with payment details or error
     */
    public function createPayment(array $data): array
    {
        $this->validateRequiredFields($data, ['amount', 'customer_name', 'customer_email']);

        $payment = $this->createPaymentRecord($data);
        $billData = $this->prepareBillData($data, $payment);
        $response = $this->sendRequest('createBill', $billData);

        if (isset($response[0]['BillCode'])) {
            return $this->handleSuccessfulBillCreation($response, $payment);
        }

        return [
            'success'  => false,
            'message'  => 'Failed to create ToyyibPay bill',
            'response' => $response,
        ];
    }
    
    /**
     * Prepare bill data according to ToyyibPay API requirements
     * 
     * @param array $data Input data
     * @param Payment $payment Payment record
     * @return array Prepared bill data
     */
    private function prepareBillData(array $data, $payment): array
    {
        $billData = [
            'userSecretKey'           => $this->config['secret_key'],
            'categoryCode'            => $this->config['category_code'],
            'billName'                => substr($data['description'] ?? 'Payment', 0, 30), // Max 30 characters
            'billDescription'         => substr($data['description'] ?? 'Payment', 0, 100), // Max 100 characters
            'billPriceSetting'        => 1, // Fixed amount
            'billPayorInfo'           => 1, // Require payer information
            'billAmount'              => (int)($this->formatAmount($data['amount']) * 100), // Convert to cents
            'billReturnUrl'           => $this->generateSuccessUrl() . '?payment_id=' . $payment->id,
            'billCallbackUrl'         => $this->generateCallbackUrl(),
            'billExternalReferenceNo' => $payment->reference_id,
            'billTo'                  => $data['customer_name'],
            'billEmail'               => $data['customer_email'],
            'billPhone'               => $data['customer_phone'] ?? '',
            'billPaymentChannel'      => $data['payment_channel'] ?? self::CHANNEL_FPX, // 0=FPX, 1=Credit Card, 2=Both
        ];

        return $this->addOptionalBillData($billData, $data);
    }

    /**
     * Add optional bill data parameters if provided
     * 
     * @param array $billData Base bill data
     * @param array $data Input data
     * @return array Updated bill data
     */
    private function addOptionalBillData(array $billData, array $data): array
    {
        // Split payment configuration
        if (isset($data['split_payment']) && $data['split_payment']) {
            $billData['billSplitPayment'] = 1;
            $billData['billSplitPaymentArgs'] = json_encode($data['split_payment_args'] ?? []);
        }

        // Additional email content
        if (isset($data['content_email'])) {
            $billData['billContentEmail'] = substr($data['content_email'], 0, 1000); // Max 1000 characters
        }

        // Charge configuration
        if (isset($data['charge_to_customer'])) {
            $billData['billChargeToCustomer'] = $data['charge_to_customer'];
        }

        // Expiry configuration
        if (isset($data['expiry_date'])) {
            $billData['billExpiryDate'] = $data['expiry_date']; // Format: "17-12-2020 17:00:00"
        }

        if (isset($data['expiry_days'])) {
            $billData['billExpiryDays'] = min(max($data['expiry_days'], 1), 100); // 1-100 days
        }
        
        // FPX B2B configuration
        if (isset($data['enable_fpx_b2b']) && $data['enable_fpx_b2b']) {
            $billData['enableFPXB2B'] = '1';
            $billData['chargeFPXB2B'] = $data['charge_fpx_b2b'] ?? '1'; // Default to charge on bill owner
        }
        
        return $billData;
    }

    /**
     * Process successful bill creation
     * 
     * @param array $response API response
     * @param Payment $payment Payment record
     * @return array Success response
     */
    private function handleSuccessfulBillCreation(array $response, $payment): array
    {
        $billCode = $response[0]['BillCode'];
        $paymentUrl = $this->getPaymentUrl($billCode);
        
        $payment->update([
            'payment_url'            => $paymentUrl,
            'gateway_transaction_id' => $billCode,
        ]);

        $this->logGatewayResponse($response);

        return [
            'success'        => true,
            'payment_url'    => $paymentUrl,
            'transaction_id' => $billCode,
            'payment'        => $payment,
        ];
    }
    
    /**
     * Handle callback notification from ToyyibPay
     * 
     * @param array $data Callback data
     * @return array Response with status
     */
    public function handleCallback(array $data): array
    {        // Extract ToyyibPay callback parameters
        $billCode = $data['billcode'] ?? null;
        $status   = $data['status_id'] ?? $data['status'] ?? null;    // ToyyibPay uses 'status_id' in callbacks
        $refno    = $data['refno'] ?? null;
        $reason   = $data['reason'] ?? null;
        
        if (!$billCode) {
            return ['success' => false, 'message' => 'Missing bill code'];
        }

        $payment = $this->findPaymentByBillCode($billCode);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update(['callback_data' => $data]);

        // Process based on status code
        return $this->processPaymentStatus($payment, $status, $refno, $reason, $data);
    }
    
    /**
     * Process payment status from callback or return URL
     * 
     * @param Payment $payment Payment record
     * @param string|null $status Status code
     * @param string|null $refno Reference number
     * @param string|null $reason Reason message
     * @param array $data Full callback data
     * @return array Response with status
     */
    private function processPaymentStatus($payment, ?string $status, ?string $refno, ?string $reason, array $data): array
    {
        switch ($status) {
            case self::STATUS_SUCCESS:
                $payment->markAsPaid($refno ?? $payment->gateway_transaction_id, $data);
                return ['success' => true, 'status' => 'paid', 'payment' => $payment];
                
            case self::STATUS_PENDING:
                $payment->update(['status' => 'pending']);
                return ['success' => true, 'status' => 'pending', 'payment' => $payment];
                
            default: // STATUS_FAILED or any other status
                $payment->markAsFailed($reason ?? 'Payment failed or cancelled', $data);
                return ['success' => true, 'status' => 'failed', 'payment' => $payment];
        }
    }
    
    /**
     * Find payment by ToyyibPay bill code
     * 
     * @param string $billCode Bill code
     * @return Payment|null Payment record or null if not found
     */
    private function findPaymentByBillCode(string $billCode)
    {
        return Payment::where('gateway_transaction_id', $billCode)
            ->where('gateway', $this->getName())
            ->first();
    }
      /**
     * Make API request to ToyyibPay
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws Exception If request fails
     */
    private function sendRequest(string $endpoint, array $data): array
    {
        $url = $this->getApiUrl() . $endpoint;
        
        // Add user secret key if not already present (except for createBill where it's added manually)
        if (!isset($data['userSecretKey']) && $endpoint !== 'createBill') {
            $data['userSecretKey'] = $this->config['secret_key'];
        }

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($url, $data);

            if (!$response->successful()) {
                throw new Exception("ToyyibPay API request failed with HTTP code: {$response->status()}");
            }

            $decodedResponse = $response->json();
            
            if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("ToyyibPay API response JSON decode error: " . json_last_error_msg());
            }

            return $decodedResponse ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new Exception("ToyyibPay API connection failed: {$e->getMessage()}");
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new Exception("ToyyibPay API request failed: {$e->getMessage()}");
        }
    }

    /**
     * Get ToyyibPay API URL based on sandbox mode
     * 
     * @return string API URL
     */
    private function getApiUrl(): string
    {
        return $this->config['sandbox'] 
            ? 'https://dev.toyyibpay.com/index.php/api/' 
            : 'https://toyyibpay.com/index.php/api/';
    }

    /**
     * Get payment URL for a bill
     * 
     * @param string $billCode Bill code
     * @return string Payment URL
     */
    private function getPaymentUrl(string $billCode): string
    {
        return $this->config['sandbox'] 
            ? "https://dev.toyyibpay.com/{$billCode}" 
            : "https://toyyibpay.com/{$billCode}";
    }
    
    /**
     * Verify payment status from ToyyibPay
     * 
     * @param string $transactionId Transaction ID (bill code)
     * @return array Response with status
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->sendRequest('getBillTransactions', [
                'billCode' => $transactionId
            ]);

            if (!empty($response)) {
                return $this->parseVerificationResponse($response);
            }

            return ['success' => false, 'message' => 'Transaction not found'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Parse verification response from ToyyibPay
     * 
     * @param array $response API response
     * @return array Parsed response
     */
    private function parseVerificationResponse(array $response): array
    {
        $transaction = $response[0] ?? [];
        $status = $transaction['billpaymentStatus'] ?? null;
        
        // Map ToyyibPay status codes to our status
        $mappedStatus = 'pending';
        if ($status == self::STATUS_SUCCESS) {
            $mappedStatus = 'paid';
        } elseif ($status == self::STATUS_FAILED) {
            $mappedStatus = 'failed';
        }
        
        return [
            'success' => true,
            'status' => $mappedStatus,
            'data' => $transaction
        ];
    }

    /**
     * Create a new category for bills
     * 
     * @param string $name Category name
     * @param string $description Category description
     * @return array Response with category code or error
     */
    public function createCategory(string $name, string $description): array
    {
        try {
            $response = $this->sendRequest('createCategory', [
                'catname' => $name,
                'catdescription' => $description,
                'userSecretKey' => $this->config['secret_key']
            ]);

            if (!empty($response) && isset($response[0]['CategoryCode'])) {
                return [
                    'success' => true,
                    'category_code' => $response[0]['CategoryCode']
                ];
            }

            return ['success' => false, 'message' => 'Failed to create category'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get category details
     * 
     * @param string $categoryCode Category code
     * @return array Response with category details or error
     */
    public function getCategory(string $categoryCode): array
    {
        try {
            $response = $this->sendRequest('getCategoryDetails', [
                'categoryCode' => $categoryCode,
                'userSecretKey' => $this->config['secret_key']
            ]);

            if (!empty($response)) {
                return [
                    'success' => true,
                    'data' => $response[0] ?? []
                ];
            }

            return ['success' => false, 'message' => 'Category not found'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deactivate a bill
     * 
     * @param string $billCode Bill code
     * @return array Response with status
     */
    public function deactivateBill(string $billCode): array
    {
        try {
            // Note: uses 'secretKey' not 'userSecretKey' for this endpoint
            $response = $this->sendRequest('inactiveBill', [
                'billCode' => $billCode,
                'secretKey' => $this->config['secret_key']
            ]);

            if (isset($response['status'])) {
                return [
                    'success' => $response['status'] === 'success',
                    'message' => $response['result'] ?? 'Unknown response'
                ];
            }

            return ['success' => false, 'message' => 'Invalid response format'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle return URL (GET parameters)
     * 
     * @param array $data Return URL data
     * @return array Response with status
     */
    public function handleReturn(array $data): array
    {
        $statusId = $data['status_id'] ?? null;
        $billCode = $data['billcode'] ?? null;
        
        if (!$billCode) {
            return ['success' => false, 'message' => 'Missing bill code'];
        }

        $payment = $this->findPaymentByBillCode($billCode);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        // Map status codes to our status
        $status = 'pending';
        if ($statusId == self::STATUS_SUCCESS) {
            $status = 'paid';
        } elseif ($statusId == self::STATUS_FAILED) {
            $status = 'failed';
        }

        return [
            'success' => true,
            'status' => $status,
            'payment' => $payment
        ];
    }
}
