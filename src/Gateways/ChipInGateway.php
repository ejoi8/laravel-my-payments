<?php

namespace Ejoi8\PaymentGateway\Gateways;

use Ejoi8\PaymentGateway\Models\Payment;
use Illuminate\Support\Facades\Log;
use Exception;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Chip-In Payment Gateway Integration
 * 
 * This class provides integration with the Chip-In payment gateway API.
 * It handles payment creation, verification, and callback handling for the Chip-In payment gateway.
 * 
 * @link https://docs.chip-in.asia/docs Official Chip-In API Documentation
 */
class ChipInGateway extends BaseGateway
{
    /**
     * Payment status constants from Chip-In API
     */
    private const STATUS_PAID           = 'paid';
    private const STATUS_PENDING        = 'pending';
    private const STATUS_ERROR          = 'error';
    private const STATUS_CANCELLED      = 'cancelled';
    private const STATUS_EXPIRED        = 'expired';
    private const STATUS_REFUNDED       = 'refunded';
    private const STATUS_PENDING_REFUND = 'pending_refund';
    private const STATUS_HOLD           = 'hold';
    private const STATUS_PREAUTHORIZED  = 'preauthorized';
    private const STATUS_BLOCKED        = 'blocked';
    
    /**
     * API endpoints
     */
    private const API_SANDBOX_URL    = 'https://gate.chip-in.asia/api/v1/';
    private const API_PRODUCTION_URL = 'https://gate.chip-in.asia/api/v1/';
    
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'chipin';
    }    
    
    /**
     * Create a payment purchase in Chip-In
     * 
     * @param array $data Payment data
     * @return array Response with payment details or error
     */
    public function createPayment(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['amount', 'customer_email']);
            $this->validateGatewayConfiguration();
            
            $payment = $this->createPaymentRecord($data);
            $products = $this->createProductsFromData($data);
            $calculatedTotal = $this->calculateProductsTotal($products);
            
            $purchaseData = $this->preparePurchaseData($data, $payment, $products, $calculatedTotal);
              // Log the request data for debugging
            /** @phpstan-ignore-next-line */
            Log::info('ChipIn API Request Data:', [
                'url' => $this->getApiUrl() . 'purchases/',
                'data' => $purchaseData
            ]);
            
            $response = $this->sendRequest('purchases/', $purchaseData, 'POST');

            if (isset($response['id']) && isset($response['checkout_url'])) {
                return $this->handleSuccessfulPaymentCreation($payment, $response);
            }

            return [
                'success' => false,
                'message' => 'Failed to create Chip-in purchase',
                'response' => $response,
            ];
        } catch (\Exception $e) {
            /** @phpstan-ignore-next-line */
            Log::error('Chip payment error: ' . $e->getMessage());
              $errorResponse = [
                'success' => false,
                'message' => 'Chip API error: ' . $e->getMessage(),
                'raw_response' => ['error' => $e->getMessage()],
            ];
            
            // Check if the error message contains debug info (from our custom exception)
            if (strpos($e->getMessage(), 'Debug info:') !== false) {
                $errorResponse['debug_info'] = 'See error message for details';
            }
            
            return $errorResponse;
        }
    }
    
    /**
     * Handle callback notification from Chip-In
     * 
     * @param array $data Callback data
     * @return array Response with status
     */    
    public function handleCallback(array $data): array
    {
        $purchaseId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$purchaseId) {
            return ['success' => false, 'message' => 'Missing purchase ID'];
        }
        
        $payment = $this->findPaymentByTransactionId($purchaseId);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        // Process the payment status directly
        return $this->processPaymentStatus($payment, $status, $purchaseId, $data);
    }
    
    /**
     * Process payment status from callback
     * 
     * @param Payment $payment Payment record
     * @param string|null $status Payment status
     * @param string $purchaseId Purchase ID
     * @param array $data Callback data
     * @return array Response with status
     */
    private function processPaymentStatus(Payment $payment, ?string $status, string $purchaseId, array $data): array
    {
        if ($status === self::STATUS_PAID) {
            // Use the markAsPaid method from the Payment model
            $payment->markAsPaid($purchaseId, $data);
            
            return ['success' => true, 'status' => 'paid', 'payment' => $payment];
        } else {
            // Use the markAsFailed method from the Payment model
            $payment->markAsFailed('Payment failed or cancelled', $data);
            
            return ['success' => true, 'status' => 'failed', 'payment' => $payment];
        }
    }
    
    /**
     * Validate that required configuration values are set
     * 
     * @return void
     * @throws Exception If required configuration is missing
     */
    private function validateGatewayConfiguration(): void
    {
        if (empty($this->config['brand_id'])) {
            throw new Exception('ChipIn brand_id is not configured');
        }
        
        if (empty($this->config['secret_key'])) {
            throw new Exception('ChipIn secret_key is not configured');
        }
    }
    
    /**
     * Handle successful payment creation response
     * 
     * @param Payment $payment Payment record
     * @param array $response API response data
     * @return array Success response
     */    
    private function handleSuccessfulPaymentCreation(Payment $payment, array $response): array
    {
        // Update payment properties and save
        $payment->payment_url = $response['checkout_url'];
        $payment->gateway_transaction_id = $response['id'];
        $payment->save();

        $this->logGatewayResponse($response);

        return [
            'success' => true,
            'payment_url' => $response['checkout_url'],
            'redirect_url' => $response['checkout_url'], // For compatibility
            'transaction_id' => $response['id'],
            'payment_id' => $response['id'],
            'payment' => $payment,
            'raw_response' => $response,
        ];
    }
      
    /**
     * Send a request to the Chip-In API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method ('GET' or 'POST')
     * @return array Response from API
     * @throws Exception If API request fails
     */
    private function sendRequest(string $endpoint, array $data, string $method = 'GET'): array
    {
        $url = $this->getApiUrl() . $endpoint;
        $headers = $this->prepareRequestHeaders();

        $ch = curl_init();
        $this->configureCurlRequest($ch, $url, $headers, $method, $data);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            /** @phpstan-ignore-next-line */
            Log::error('Chip-in cURL error: ' . $curlError);
            throw new Exception("Chip-in API request failed: {$curlError}");
        }
        
        $this->handleHttpErrorResponse($httpCode, $url, $method, $data, $response);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * Get API URL based on sandbox configuration
     * 
     * @return string API URL
     */
    private function getApiUrl(): string
    {
        // Use sandbox URL if in sandbox mode
        if ($this->config['sandbox'] ?? true) {
            return self::API_SANDBOX_URL;
        }
        
        return self::API_PRODUCTION_URL;
    }
    
    /**
     * Prepare HTTP headers for API request
     * 
     * @return array HTTP headers
     */
    private function prepareRequestHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->config['secret_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }
      
    /**
     * Configure cURL request options
     * 
     * @param mixed $ch cURL handle
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param string $method HTTP method
     * @param array $data Request data
     * @return void
     */
    private function configureCurlRequest($ch, string $url, array $headers, string $method, array $data): void
    {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    /**
     * Handle HTTP error responses
     * 
     * @param int $httpCode HTTP response code
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param array $data Request data
     * @param string $response Response body
     * @return void
     * @throws Exception If HTTP response indicates error
     */
    private function handleHttpErrorResponse(int $httpCode, string $url, string $method, array $data, string $response): void
    {
        if ($httpCode !== 200 && $httpCode !== 201) {
            $decodedResponse = json_decode($response, true);
            /** @phpstan-ignore-next-line */
            Log::error("Chip-in API HTTP error: {$httpCode}", [
                'url' => $url,
                'method' => $method,
                'request_data' => $data,
                'response' => $response,
                'decoded_response' => $decodedResponse
            ]);
            
            // Try to extract error message from response
            $errorMessage = "Chip-in API request failed with HTTP code: {$httpCode}";
            if ($decodedResponse && isset($decodedResponse['message'])) {
                $errorMessage .= " - " . $decodedResponse['message'];
            } elseif ($decodedResponse && isset($decodedResponse['error'])) {
                $errorMessage .= " - " . $decodedResponse['error'];
            } elseif ($decodedResponse && isset($decodedResponse['errors'])) {
                $errorMessage .= " - " . json_encode($decodedResponse['errors']);
            }
            
            // Create exception with debug info
            $exception = new Exception($errorMessage);
            
            // PHP doesn't allow dynamic property creation in strict mode
            // so we'll include debug info in the error message
            throw new Exception($errorMessage . "\nDebug info: " . json_encode([
                'http_code' => $httpCode,
                'request_url' => $url,
                'response_body' => $response
            ]));
        }
    }
    
    /**
     * Parse JSON response from API
     * 
     * @param string $response JSON response string
     * @return array Decoded response
     * @throws Exception If JSON cannot be decoded
     */
    private function parseJsonResponse(string $response): array
    {
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            /** @phpstan-ignore-next-line */
            Log::error('Chip-in JSON decode error: ' . json_last_error_msg(), [
                'response' => $response
            ]);
            throw new Exception('Invalid JSON response from Chip-in API');
        }

        return $decodedResponse ?? [];
    }
      
    /**
     * Verify payment status with Chip-In API
     * 
     * @param string $transactionId Transaction/purchase ID to verify
     * @return array Response with verification result
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->sendRequest("purchases/{$transactionId}/", [], 'GET');

            if (isset($response['status'])) {
                return $this->formatVerificationResponse($transactionId, $response);
            }

            return ['success' => false, 'message' => 'Purchase not found'];
        } catch (Exception $e) {
            return $this->formatErrorResponse($e);
        }
    }
    
    /**
     * Format verification response
     * 
     * @param string $transactionId Transaction ID
     * @param array $response API response
     * @return array Formatted verification response
     */
    private function formatVerificationResponse(string $transactionId, array $response): array
    {
        $paymentStatus = $this->mapPaymentStatus($response['status']);
        
        return [
            'success' => $paymentStatus === 'completed',
            'status' => $paymentStatus,
            'transaction_id' => $transactionId,
            'payment_status' => $paymentStatus,
            'data' => $response,
            'message' => $this->getStatusMessage($paymentStatus, $response['status']),
            'raw_response' => $response
        ];
    }
    
    /**
     * Format error response
     * 
     * @param Exception $e Exception
     * @return array Error response
     */
    private function formatErrorResponse(Exception $e): array
    {
        return [
            'success' => false, 
            'message' => $e->getMessage(),
            'payment_status' => 'error',
            'raw_response' => ['error' => $e->getMessage()]
        ];
    }
    
    /**
     * Calculate total amount from products array (in cents)
     * Includes both regular prices and discounts
     */
    private function calculateProductsTotal(array $products): int
    {
        $total = 0;
        foreach ($products as $product) {
            $subtotal = ($product['price'] * $product['quantity']);
            
            // Subtract discounts if present
            if (isset($product['discount'])) {
                $subtotal -= ($product['discount'] * $product['quantity']);
            }
            
            $total += $subtotal;
        }
        return $total;
    }    
    
    /**
     * Create products array from payment data
     */
    private function createProductsFromData(array $data): array
    {
        // If products are provided in the data, use them
        if (!empty($data['products']) && is_array($data['products'])) {
            $products = [];
            foreach ($data['products'] as $product) {
                $price = $product['price'] ?? 0;
                $discount = $product['discount'] ?? 0; // Explicit discount field
                
                // Handle negative prices as discounts using ChipIn's discount field
                if ($price < 0) {
                    $products[] = [
                        'name' => $product['name'] ?? 'Discount',
                        'price' => 0, // Set price to 0 for discount items
                        'discount' => (int)(abs($price) * 100), // Convert absolute value to cents
                        'quantity' => $product['quantity'] ?? 1,
                    ];
                } else {
                    $productData = [
                        'name' => $product['name'] ?? 'Product',
                        'price' => (int)($price * 100), // Convert to cents
                        'quantity' => $product['quantity'] ?? 1,
                    ];
                    
                    // Add explicit discount if provided
                    if ($discount > 0) {
                        $productData['discount'] = (int)($discount * 100); // Convert to cents
                    }
                    
                    $products[] = $productData;
                }
            }
            
            return $products;
        }

        // Fallback to single product based on payment amount
        return [
            [
                'name' => $data['description'] ?? 'Payment',
                'price' => $this->formatAmount($data['amount']) * 100, // Convert to cents
                'quantity' => 1,
            ]
        ];
    }    
    
    /**
     * Prepare purchase data for Chip-In API
     * 
     * @param array $data Input data
     * @param Payment $payment Payment record
     * @param array $products Product data
     * @param int $calculatedTotal Calculated total from products
     * @return array Prepared purchase data
     */
    private function preparePurchaseData(array $data, Payment $payment, array $products, int $calculatedTotal): array
    {
        /** @phpstan-ignore-next-line */
        $uuid = $payment->id ?? uniqid();
        /** @phpstan-ignore-next-line */
        $reference = $payment->reference_id ?? $this->getName() . '-' . time();
        
        $purchaseData = [
            'brand_id' => $this->config['brand_id'],
            'client_reference' => $reference,
            'purchase' => [
                'currency' => $data['currency'] ?? 'MYR',
                'products' => $products,
            ],
            'success_callback' => $this->generateCallbackUrl(),
            'success_redirect' => $this->generateSuccessUrl() . '?payment_id=' . $uuid,
            'failure_redirect' => $this->generateFailedUrl() . '?payment_id=' . $uuid,
            'cancel_redirect' => $this->generateFailedUrl() . '?payment_id=' . $uuid,
        ];
        
        // Handle total discount override if provided
        if (!empty($data['total_discount'])) {
            $purchaseData['purchase']['total_discount_override'] = $this->formatAmount($data['total_discount']) * 100;
        }
        
        // Only use total_override if the provided amount differs from calculated products total
        $providedAmount = $this->formatAmount($data['amount']) * 100;
        if ($providedAmount != $calculatedTotal) {
            $purchaseData['purchase']['total_override'] = $providedAmount;
        }

        // Add client information if provided - this is required for ChipIn
        if (!empty($data['customer_email'])) {
            $purchaseData['client'] = [
                'email' => $data['customer_email'],
            ];
            
            // Add optional client fields if provided
            if (!empty($data['customer_phone'])) {
                $purchaseData['client']['phone'] = $data['customer_phone'];
            }
            
            if (!empty($data['customer_name'])) {
                $purchaseData['client']['full_name'] = $data['customer_name'];
            }
        }
        
        // Add optional fields
        if (!empty($data['language'])) {
            $purchaseData['purchase']['language'] = $data['language'];
        }
        
        if (!empty($data['order_id'])) {
            $purchaseData['purchase']['notes'] = 'Order ID: ' . $data['order_id'];
            $purchaseData['reference'] = $data['order_id'];
        }
        
        return $purchaseData;
    }

    /**
     * Map Chip payment status to standardized status
     */
    private function mapPaymentStatus(string $chipStatus): string
    {
        return match ($chipStatus) {
            'paid' => 'completed',
            'refunded', 'pending_refund' => 'refunded',
            'error', 'cancelled', 'expired', 'blocked' => 'failed',
            'hold', 'preauthorized' => 'authorized',
            default => 'pending',
        };
    }

    /**
     * Get a human-readable message for a payment status
     */
    private function getStatusMessage(string $internalStatus, ?string $gatewayStatus = null): string
    {
        $message = match ($internalStatus) {
            'completed'  => 'Payment completed successfully.',
            'authorized' => 'Payment authorized but not yet captured.',
            'pending'    => 'Payment is pending or awaiting confirmation.',
            'refunded'   => 'Payment was refunded.',
            'failed'     => 'Payment failed, was rejected, or cancelled.',
            'error'      => 'An error occurred during payment processing.',
            default      => "Payment status: {$internalStatus}" . ($gatewayStatus && $gatewayStatus !== $internalStatus ? " (Gateway: {$gatewayStatus})" : ""),
        };

        // Provide detailed messages for specific Chip statuses
        if ($internalStatus === $gatewayStatus || !$gatewayStatus) {
            $detailedMessage = match ($internalStatus) {
                'sent'          => 'Invoice for this purchase was sent.',
                'viewed'        => 'Client has viewed the payform/invoice.',
                'overdue'       => 'Purchase is overdue but payment may still be possible.',
                'expired'       => 'Purchase has expired and payment is not possible.',      'blocked' => 'Payment attempt was blocked (e.g., fraud).',
                'cleared'       => 'Funds for this purchase have been cleared.',
                'settled'       => 'Settlement was issued for this purchase.',
                'chargeback'    => 'A chargeback was registered for this purchase.',
                'preauthorized' => 'Card preauthorization was successful.',
                'released'      => 'Funds on hold were released back to the customer.',
                default         => $message,
            };
            return $detailedMessage;
        }

        return $message;
    }    
    
    /**
     * Find payment by Chip-In transaction ID
     * 
     * @param string $transactionId Chip-In purchase ID
     * @return Payment|null Found payment or null
     */
    private function findPaymentByTransactionId(string $transactionId): ?Payment
    {
        /** @phpstan-ignore-next-line */
        return Payment::where('gateway_transaction_id', $transactionId)
            ->where('gateway', $this->getName())
            ->first();
    }
}
