<?php

use Ejoi8\PaymentGateway\Services\PaymentService;

/**
 * This example demonstrates how to integrate the payment gateway with an order management system
 * using the external reference fields to associate payments with orders.
 */

// Initialize the payment service
$paymentService = app(PaymentService::class);

/**
 * Example 1: Creating a new payment for an order
 */
function createOrderPayment($orderId, $amount, $customerEmail, $customerName)
{
    global $paymentService;
    
    // Basic payment data
    $paymentData = [
        'amount' => $amount,
        'gateway' => 'chipin', // Or any other available gateway
        'description' => "Payment for Order #{$orderId}",
        'customer_email' => $customerEmail,
        'customer_name' => $customerName,
    ];
    
    // Create payment with external reference to the order
    $result = $paymentService->createPaymentWithExternalReference(
        $paymentData, 
        $orderId, 
        'order'  // The reference type
    );
    
    return $result;
}

/**
 * Example 2: Check if an order has been paid
 */
function isOrderPaid($orderId)
{
    global $paymentService;
    
    // This will return true if there's at least one successful payment for this order
    return $paymentService->hasSuccessfulPayment($orderId, 'order');
}

/**
 * Example 3: Get all payments for an order
 */
function getOrderPayments($orderId)
{
    global $paymentService;
    
    // Get all payments associated with this order
    return $paymentService->getPaymentsByExternalReference($orderId, 'order');
}

/**
 * Example 4: Find the latest payment attempt for an order
 */
function getLatestOrderPaymentAttempt($orderId)
{
    global $paymentService;
    
    // Get the most recent payment attempt for this order
    return $paymentService->getLatestPaymentByExternalReference($orderId, 'order');
}

/**
 * Example 5: Get payment status for an order
 */
function getOrderPaymentStatus($orderId)
{
    global $paymentService;
    
    $latestPayment = $paymentService->getLatestPaymentByExternalReference($orderId, 'order');
    
    if (!$latestPayment) {
        return 'No payment attempts found';
    }
    
    return $latestPayment->status;
}

/**
 * Example 6: Create a payment for a subscription renewal
 */
function createSubscriptionRenewalPayment($subscriptionId, $amount, $customerEmail, $customerName)
{
    global $paymentService;
    
    // Basic payment data
    $paymentData = [
        'amount' => $amount,
        'gateway' => 'stripe', // Or any other available gateway
        'description' => "Renewal payment for Subscription #{$subscriptionId}",
        'customer_email' => $customerEmail,
        'customer_name' => $customerName,
    ];
    
    // Create payment with external reference to the subscription
    $result = $paymentService->createPaymentWithExternalReference(
        $paymentData, 
        $subscriptionId, 
        'subscription'  // Different reference type
    );
    
    return $result;
}

/**
 * Example 7: Implementing a payment webhook handler with reference lookup
 */
function handlePaymentWebhook($gatewayName, $callbackData)
{
    global $paymentService;
    
    // Process the payment callback
    $result = $paymentService->handleCallback($gatewayName, $callbackData);
    
    if ($result['success'] && isset($result['payment'])) {
        $payment = $result['payment'];
        
        // If this payment has an external reference, update the related entity
        if ($payment->external_reference_id) {
            switch ($payment->reference_type) {
                case 'order':
                    updateOrderStatus($payment->external_reference_id, $payment->status);
                    break;
                    
                case 'subscription':
                    updateSubscriptionStatus($payment->external_reference_id, $payment->status);
                    break;
                    
                // Handle other reference types...
                
                default:
                    // Log unknown reference type
                    break;
            }
        }
    }
    
    return $result;
}

/**
 * Example helper function to update order status in your system
 */
function updateOrderStatus($orderId, $paymentStatus)
{
    // This would be implemented in your order management system
    // For example:
    // Order::find($orderId)->update(['payment_status' => $paymentStatus]);
    
    echo "Updated order #{$orderId} status to {$paymentStatus}";
}

/**
 * Example helper function to update subscription status in your system
 */
function updateSubscriptionStatus($subscriptionId, $paymentStatus)
{
    // This would be implemented in your subscription management system
    // For example:
    // Subscription::find($subscriptionId)->update(['payment_status' => $paymentStatus]);
    
    echo "Updated subscription #{$subscriptionId} status to {$paymentStatus}";
}
