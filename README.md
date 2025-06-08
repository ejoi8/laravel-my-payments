# Ejoi8 Payment Gateway

A unified payment gateway package for Laravel supporting multiple payment providers.

## Table of Contents

- [Features](#features)
- [Supported Gateways](#supported-gateways)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Payment Integration](#payment-integration)
  - [Creating Payments](#creating-payments)
  - [External Reference Support](#external-reference-support)
  - [Processing Callbacks](#processing-callbacks)
- [Gateway-Specific Examples](#gateway-specific-examples)
  - [Toyyibpay](#toyyibpay)
  - [ChipIn](#chipin)
  - [PayPal](#paypal)
  - [Stripe](#stripe)
  - [Manual Payments](#manual-payments)
- [Required Fields](#required-fields)
- [Configuration](#configuration)
- [Local Development & Testing](#local-development--testing)
  - [Webhook Testing with ngrok](#webhook-testing-with-ngrok)
- [License](#license)

## Features

- ✅ Unified payment interface
- ✅ Single payment table for all gateways
- ✅ Livewire components with Tailwind CSS
- ✅ Automatic callback handling
- ✅ External reference support (for orders, subscriptions, etc.)
- ✅ Open/Closed principle for easy extension
- ✅ Solo developer friendly

## Supported Gateways

- Toyyibpay
- Chip-in.asia 
- PayPal
- Stripe
- Manual Payment (with proof upload)

## Installation

### Step 1: Add Repository

First, add the required repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:CHIPAsia/chip-php-sdk.git"
        }
    ]
}
```

> **Note**: The repository configuration is required because this package depends on the development version of the CHIP PHP SDK for Chip-in.asia gateway support.

### Step 2: Install Package

Then install the package via Composer:

```bash
composer require ejoi8/payment-gateway
```

### Step 3: Publish and Migrate

Publish the configuration and run migrations:

```bash
php artisan vendor:publish --provider="Ejoi8\PaymentGateway\PaymentGatewayServiceProvider"
php artisan migrate
```

> **Note**: If you're upgrading from a previous version, clear your config cache and republish:
> ```bash
> php artisan config:clear
> php artisan vendor:publish --provider="Ejoi8\PaymentGateway\PaymentGatewayServiceProvider" --force
> ```

## Basic Usage

```php
// In your controller
use Ejoi8\PaymentGateway\Services\PaymentService;

$paymentService = new PaymentService();

// Create payment with specific gateway
$result = $paymentService->createPayment([
    'amount' => 100.00,
    'currency' => 'MYR',
    'gateway' => 'toyyibpay', // Specify the gateway here
    'description' => 'Order #123',
    'customer_email' => 'customer@example.com', // Required for most gateways
    'customer_name' => 'John Doe', // Customer's full name
    'customer_phone' => '+60123456789' // Customer's phone number
]);

if ($result['success']) {
    return redirect($result['payment_url']);
} else {
    return back()->withErrors(['payment' => $result['message']]);
}
```

## Payment Integration

### Creating Payments

Create a payment with any supported gateway:

```php
$result = $paymentService->createPayment([
    'amount' => 100.00,
    'currency' => 'MYR',
    'gateway' => 'chipin', // Or 'toyyibpay', 'paypal', 'stripe', 'manual'
    'description' => 'Product Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789'
]);
```

### External Reference Support

The payment gateway supports associating payments with external entities like orders, subscriptions, or invoices using the `external_reference_id` and `reference_type` fields. This powerful feature allows you to link payments to any system in your application.

#### Creating a Payment with External Reference

There are two ways to associate a payment with an external reference:

```php
// Method 1: Using the dedicated method
$result = $paymentService->createPaymentWithExternalReference(
    [
        'amount' => 100.00,
        'currency' => 'MYR',
        'gateway' => 'chipin',
        'description' => 'Payment for Order #1234',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'customer_phone' => '+60123456789'
    ],
    '1234',          // The external reference ID (e.g., order ID)
    'order'          // The reference type
);

// Method 2: Manually include the reference details
$result = $paymentService->createPayment([
    'amount' => 100.00,
    'currency' => 'MYR',
    'gateway' => 'toyyibpay',
    'description' => 'Subscription Renewal',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789',
    'external_reference_id' => 'SUB-2023-456',
    'reference_type' => 'subscription',
]);
```

#### Finding Payments by External Reference

Several methods are available to query payments by their external reference:

```php
// Find all payments for an order
$payments = $paymentService->getPaymentsByExternalReference('1234', 'order');

// Check if an order has been paid
$isPaid = $paymentService->hasSuccessfulPayment('1234', 'order');

// Get the latest payment attempt for an order
$latestPayment = $paymentService->getLatestPaymentByExternalReference('1234', 'order');

// Using the Payment model directly
$orderPayments = \Ejoi8\PaymentGateway\Models\Payment::findByExternalReference('1234', 'order')->get();
```

#### Common Reference Types

You can use any reference type you need, but here are some common ones:
- `order` - For e-commerce orders
- `subscription` - For recurring subscriptions
- `invoice` - For billing systems
- `booking` - For reservation systems
- `application` - For application processes

The external reference system is completely flexible and can be adapted to any business need.

### Processing Callbacks

When handling payment webhooks/callbacks, you can access the external reference to update your order system or other related entities:

```php
// In your webhook handler
$result = $paymentService->handleCallback('chipin', $request->all());

if ($result['success'] && isset($result['payment'])) {
    $payment = $result['payment'];
    
    // Using the external reference to update related systems
    if ($payment->external_reference_id) {
        switch ($payment->reference_type) {
            case 'order':
                // Update order status in your e-commerce system
                $orderService->updateOrderStatus(
                    $payment->external_reference_id,
                    $payment->status === 'paid' ? 'completed' : 'payment_failed'
                );
                break;
                
            case 'subscription':
                // Update subscription status
                $subscriptionService->updateStatus(
                    $payment->external_reference_id,
                    $payment->status === 'paid' ? 'active' : 'payment_failed'
                );
                break;
                
            case 'invoice':
                // Mark invoice as paid
                $invoiceService->updatePaymentStatus(
                    $payment->external_reference_id,
                    $payment->status
                );
                break;
        }
    }
    
    // Send notification to customer
    if ($payment->status === 'paid') {
        // Notify customer about successful payment
    } else if ($payment->status === 'failed') {
        // Notify customer about failed payment
    }
}
```

#### Setting up Callback Routes

For payment gateways to properly notify your application, you need to set up callback routes in your Laravel application:

```php
// In routes/web.php
Route::post('payment-callbacks/{gateway}', [PaymentController::class, 'handleCallback'])
    ->name('payment.callback');
```

```php
// In your PaymentController
public function handleCallback(Request $request, string $gateway)
{
    $paymentService = app(PaymentService::class);
    $result = $paymentService->handleCallback($gateway, $request->all());
    
    // Process the result as needed
    // ...
    
    // Return appropriate response based on gateway requirements
    if ($gateway === 'toyyibpay') {
        return response('OK');
    } elseif ($gateway === 'chipin') {
        return response()->json(['status' => 'success']);
    }
    
    return response('OK');
}
```

## Gateway-Specific Examples

### Toyyibpay

```php
$result = $paymentService->createPayment([
    'amount' => 50.00,
    'currency' => 'MYR',
    'gateway' => 'toyyibpay',
    'description' => 'Product Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789'
]);
```

### ChipIn

#### Basic Payment

```php
$result = $paymentService->createPayment([
    'amount' => 75.00,
    'currency' => 'MYR',
    'gateway' => 'chipin',
    'description' => 'Online Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789',
    'order_id' => 'ORDER-001' // Optional order reference
]);
```

#### With Multiple Products

```php
$result = $paymentService->createPayment([
    'amount' => 115.00, // Total amount
    'currency' => 'MYR',
    'gateway' => 'chipin',
    'description' => 'Multiple Items Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789',
    'order_id' => 'ORDER-12345', // Optional order reference
    'language' => 'en', // Optional: en, ms, zh-cn, zh-tw
    'products' => [
        [
            'name' => 'Premium T-Shirt',
            'price' => 45.00, // Price per unit in MYR
            'quantity' => 2 // 45.00 * 2 = 90.00
        ],
        [
            'name' => 'Coffee Mug',
            'price' => 25.00,
            'quantity' => 1 // 25.00
        ],
        [
            'name' => 'Discount',
            'price' => -15.00, // Negative prices are converted to ChipIn discount field
            'quantity' => 1 // -15.00
        ]
        // Total: 90 + 25 - 15 = 100.00
    ]
]);

if ($result['success']) {
    return redirect($result['payment_url']);
} else {
    return back()->withErrors(['payment' => $result['message']]);
}
```

#### With Product-Level Discounts

```php
$result = $paymentService->createPayment([
    'amount' => 115.00,
    'currency' => 'MYR',
    'gateway' => 'chipin',
    'description' => 'Purchase with Product Discounts',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'Jane Smith',
    'customer_phone' => '+60123456789',
    'products' => [
        [
            'name' => 'Premium T-Shirt',
            'price' => 50.00,
            'discount' => 5.00, // $5 discount per item
            'quantity' => 2 // Net: (50-5) * 2 = 90.00
        ],
        [
            'name' => 'Coffee Mug',
            'price' => 25.00,
            'quantity' => 1 // 25.00
        ]
        // Total: 90 + 25 = 115.00
    ]
]);

if ($result['success']) {
    return redirect($result['payment_url']);
} else {
    return back()->withErrors(['payment' => $result['message']]);
}
```

#### With Total Discount Override

```php
$result = $paymentService->createPayment([
    'amount' => 115.00,
    'currency' => 'MYR',
    'gateway' => 'chipin',
    'description' => 'Purchase with Total Discount',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'Mike Johnson',
    'customer_phone' => '+60123456789',
    'total_discount' => 15.00, // $15 total discount applied to entire purchase
    'products' => [
        [
            'name' => 'Premium T-Shirt',
            'price' => 45.00,
            'quantity' => 2 // 90.00
        ],
        [
            'name' => 'Coffee Mug',
            'price' => 25.00,
            'quantity' => 1 // 25.00
        ],
        [
            'name' => 'Shipping Fee',
            'price' => 15.00,
            'quantity' => 1 // 15.00
        ]
        // Subtotal: 90 + 25 + 15 = 130.00
        // Total discount: 15.00
        // Final total: 115.00
    ]
]);

if ($result['success']) {
    return redirect($result['payment_url']);
} else {
    return back()->withErrors(['payment' => $result['message']]);
}
```

### PayPal

```php
$result = $paymentService->createPayment([
    'amount' => 99.99,
    'currency' => 'USD', // PayPal commonly uses USD
    'gateway' => 'paypal',
    'description' => 'Digital Product Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789'
]);
```

### Stripe

```php
$result = $paymentService->createPayment([
    'amount' => 25.99,
    'currency' => 'USD',
    'gateway' => 'stripe',
    'description' => 'Subscription Payment',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789'
]);
```

### Manual Payments

#### Basic Manual Payment

```php
$result = $paymentService->createPayment([
    'amount' => 200.00,
    'currency' => 'MYR',
    'gateway' => 'manual',
    'description' => 'Manual Bank Transfer',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789'
]);

if ($result['success']) {
    // For manual payments, redirect to upload page
    if ($result['payment']->gateway === 'manual') {
        return redirect()->route('payment-gateway.manual.upload', $result['payment']);
    }
    // For other gateways, redirect to payment URL
    return redirect($result['payment_url']);
}
```

#### Manual Payment with Immediate Proof Upload

You can also create a manual payment with proof uploaded in the same request:

```php
$result = $paymentService->createPayment([
    'amount' => 200.00,
    'currency' => 'MYR',
    'gateway' => 'manual',
    'description' => 'Manual Bank Transfer with Proof',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'customer_phone' => '+60123456789',
    'proof_file' => $request->file('payment_receipt') // Upload the proof file directly
]);

if ($result['success']) {
    // Handling redirection based on response
    if (isset($result['redirect_url'])) {
        // If proof was uploaded successfully, redirect to thank you page
        return redirect($result['redirect_url']);
    } elseif (isset($result['payment_url'])) {
        // If proof upload is still required, redirect to upload page
        return redirect($result['payment_url']);
    }
    
    // Fallback redirect
    return redirect()->route('payment.success');
}
```

When proof is uploaded with the payment creation, the user will be redirected directly to the thank you page instead of having to go through the proof upload page.
```

## Payment Status Mapping

This package provides a unified status system across all payment gateways. Each gateway's specific statuses are mapped to standardized internal statuses for consistency.

### Internal Payment Statuses

The universal statuses used across all gateways:
- `'pending'` - Payment created, awaiting completion
- `'paid'` - Payment successfully completed
- `'failed'` - Payment failed, cancelled, or rejected
- `'cancelled'` - Payment cancelled by user
- `'refunded'` - Payment was refunded

### Gateway Status Mappings

#### ChipIn Gateway
| ChipIn Status | Internal Status | Description |
|---------------|----------------|-------------|
| `'paid'` | `'paid'` | Payment completed successfully |
| `'pending'` | `'pending'` | Payment is pending or awaiting confirmation |
| `'error'` | `'failed'` | An error occurred during payment processing |
| `'cancelled'` | `'failed'` | Payment was cancelled by user |
| `'expired'` | `'failed'` | Payment has expired and is no longer valid |
| `'refunded'` | `'refunded'` | Payment was refunded |
| `'pending_refund'` | `'refunded'` | Refund is being processed |
| `'hold'` | `'pending'` | Payment is on hold |
| `'preauthorized'` | `'pending'` | Payment authorized but not yet captured |
| `'blocked'` | `'failed'` | Payment attempt was blocked |

#### ToyyibPay Gateway
| ToyyibPay Status | Internal Status | Description |
|------------------|----------------|-------------|
| `'1'` (SUCCESS) | `'paid'` | Payment completed successfully |
| `'2'` (PENDING) | `'pending'` | Payment is pending |
| `'3'` (FAILED) | `'failed'` | Payment failed |
| Any other value | `'failed'` | Payment failed |

#### Manual Payment Gateway
| Manual Status | Internal Status | Description |
|---------------|----------------|-------------|
| Created | `'pending'` | Payment record created, awaiting proof upload |
| Proof Uploaded | `'pending'` | Proof uploaded, awaiting admin verification |
| Admin Approved | `'paid'` | Payment approved by administrator |
| Admin Rejected | `'failed'` | Payment rejected by administrator |

### Status Workflow Examples

#### ChipIn Payment Flow
```
1. Payment Created → 'pending'
2. User Pays → 'paid' (success) OR 'failed'/'cancelled'/'expired' (failure)
3. Refund Requested → 'refunded'
```

#### ToyyibPay Payment Flow
```
1. Payment Created → 'pending'
2. API Response → '1' (paid) OR '2' (pending) OR '3' (failed)
```

#### Manual Payment Flow
```
1. Payment Created → 'pending'
2. Proof Uploaded → 'pending' (awaiting review)
3. Admin Action → 'paid' (approved) OR 'failed' (rejected)
```

### Checking Payment Status

You can check payment status using the Payment model:

```php
use Ejoi8\PaymentGateway\Models\Payment;

$payment = Payment::find($paymentId);

// Check specific status
if ($payment->status === Payment::STATUS_PAID) {
    // Payment is completed
}

// Use scope methods
$paidPayments = Payment::paid()->get();
$pendingPayments = Payment::pending()->get();
$failedPayments = Payment::failed()->get();
```

## Required Fields

All payment gateways require the following common fields:

- **`amount`**: The payment amount (required)
- **`gateway`**: The payment gateway to use (required)
- **`currency`**: The currency code (default: 'MYR')
- **`description`**: Description of the payment (recommended)
- **`customer_email`**: Customer's email address (required for most gateways)
- **`customer_name`**: Customer's full name (recommended)
- **`customer_phone`**: Customer's phone number (recommended)

### Gateway-Specific Requirements

#### Toyyibpay
- `customer_email`: Required

#### ChipIn
- `customer_email`: Required
- `customer_name`: Recommended
- `customer_phone`: Recommended

#### PayPal
- `customer_email`: Required
- `currency`: Commonly set to 'USD', but supports multiple currencies

#### Stripe
- `customer_email`: Required

#### Manual Payment
- `customer_email`: Required for notification purposes

#### Advanced ChipIn Examples

For more examples of using ChipIn with products, discounts, and other features, see the [examples folder](examples/) in this repository.
```

#### Complex E-commerce Scenario
```php
$result = $paymentService->createPayment([
    'amount' => 245.50, // Final checkout amount
    'currency' => 'MYR',
    'gateway' => 'chipin',
    'description' => 'E-commerce Checkout',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'Robert Taylor',
    'customer_phone' => '+60123456789',
    'order_id' => 'ECOM-12345',
    'language' => 'en',
    'products' => [
        [
            'name' => 'Wireless Headphones',
            'price' => 199.99,
            'quantity' => 1
        ],
        [
            'name' => 'Phone Case',
            'price' => 29.99,
            'quantity' => 2 // 29.99 * 2 = 59.98
        ],
        [
            'name' => 'Screen Protector',
            'price' => 15.99,
            'discount' => 3.00, // Member discount
            'quantity' => 1 // Net: (15.99-3.00) * 1 = 12.99
        ],
        [
            'name' => 'Standard Shipping',
            'price' => 8.99,
            'quantity' => 1
        ],
        [
            'name' => 'Sales Tax (6%)',
            'price' => 16.74, // Calculated tax
            'quantity' => 1
        ],
        [
            'name' => 'First Purchase Discount',
            'price' => -53.19, // 20% discount on subtotal
            'quantity' => 1
        }
        // Total: 199.99 + 59.98 + 12.99 + 8.99 + 16.74 - 53.19 = 245.50
    ]
});

// Handle payment result
if ($result['success']) {
    return redirect($result['payment_url']);
} else {
    return back()->withErrors(['payment' => $result['message']]);
}
```

## Example Integration

For a complete example of how to integrate this payment gateway with your order system, see the `examples/order-payment-integration.php` file in this repository. This example demonstrates:

1. Creating payments linked to orders
2. Checking if an order has been paid
3. Getting all payment attempts for an order
4. Finding the latest payment for an order
5. Getting payment status for an order
6. Creating payments for different reference types (like subscriptions)
7. Handling payment webhooks and updating order statuses

You can use these examples as a starting point for your own integration.

## Requirements

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0
- Livewire ^3.0

## Configuration

Configure your payment gateway credentials in the published config file:

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

Update your `.env` file with the appropriate gateway credentials:

```env
# Toyyibpay
TOYYIBPAY_USER_SECRET_KEY=your_secret_key
TOYYIBPAY_CATEGORY_CODE=your_category_code

# Chip-in.asia
CHIPIN_BRAND_ID=your_brand_id
CHIPIN_SECRET_KEY=your_secret_key

# PayPal
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret

# Stripe
STRIPE_PUBLISHABLE_KEY=your_publishable_key
STRIPE_SECRET_KEY=your_secret_key
```

## Local Development & Testing

### Webhook Testing with ngrok

When developing and testing payment webhooks locally, you need to expose your local Laravel application to the internet so payment gateways can send callback notifications to your development environment. **ngrok** is the recommended tool for this purpose.

#### Prerequisites

- A local Laravel development environment (Laragon, XAMPP, Valet, etc.)
- ngrok account (free tier available)

#### Step-by-Step Setup

**1. Install ngrok**

Download and install ngrok from [https://ngrok.com/download](https://ngrok.com/download)

**2. Configure Authentication**

After creating an ngrok account, get your authentication token from the [ngrok dashboard](https://dashboard.ngrok.com/get-started/your-authtoken) and add it to ngrok:

```bash
ngrok config add-authtoken YOUR_AUTHTOKEN_HERE
```

**3. Expose Your Local Application**

If you're using Laragon with a local domain like `paymentgatewaypackage.local`, run:

```bash
ngrok http --host-header=paymentgatewaypackage.local 80
```

For other setups, adjust the command accordingly:
- **XAMPP/WAMP**: `ngrok http 80` or `ngrok http localhost:80`
- **Laravel Valet**: `ngrok http 80` (if using .test domains)
- **Artisan serve**: `ngrok http 8000`

**4. Fix URL Generation for ngrok**

Add the following code to your `AppServiceProvider.php` (in the `boot()` method) to ensure Laravel generates the correct URLs when accessed through ngrok:

```php
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

public function boot(): void
{
    // Check if we're accessing via ngrok
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && 
        Str::contains($_SERVER['HTTP_X_FORWARDED_HOST'], 'ngrok')) {
        
        // Force the URL to use the ngrok domain
        $schema = 'https';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        URL::forceRootUrl("{$schema}://{$host}");
        URL::forceScheme('https');
    }
}
```

**5. Configure Webhook URLs**

Use the ngrok HTTPS URL for your webhook endpoints in the payment gateway dashboards:

```
https://abc123.ngrok-free.app/payment-callbacks/chipin
https://abc123.ngrok-free.app/payment-callbacks/toyyibpay
https://abc123.ngrok-free.app/payment-callbacks/paypal
```

#### Testing Your Setup

1. **Start your local Laravel server** (if not using Laragon/Valet)
2. **Run ngrok** with the appropriate command
3. **Copy the HTTPS URL** from ngrok terminal output
4. **Update payment gateway webhook URLs** in their respective dashboards
5. **Test a payment** and monitor the ngrok terminal for incoming webhook requests

#### Troubleshooting

**Common Issues:**

- **Mixed Content Errors**: Always use the HTTPS URL from ngrok, not HTTP
- **Webhook Not Received**: Check that your local server is running and the webhook URL is correct
- **Wrong URL in Routes**: Ensure the `AppServiceProvider` fix is properly implemented
- **Firewall Issues**: Make sure your local development environment can receive external requests

**Testing Webhook Reception:**

You can test if webhooks are being received by adding a simple log in your webhook handler:

```php
public function handleCallback(Request $request, string $gateway)
{
    Log::info('Webhook received', [
        'gateway' => $gateway,
        'data' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    $paymentService = app(PaymentService::class);
    $result = $paymentService->handleCallback($gateway, $request->all());
    
    return response('OK');
}
```

#### Security Notes

- ngrok URLs are publicly accessible - only use for development
- Don't commit ngrok URLs to version control
- Regenerate webhook URLs for each development session
- Use environment-specific webhook configurations

## License

MIT License
