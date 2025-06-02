# Ejoi8 Payment Gateway

A unified payment gateway package for Laravel supporting multiple payment providers.

## Supported Gateways

- Toyyibpay
- Chip-in.asia 
- PayPal
- Stripe
- Manual Payment (with proof upload)

## Features

- ✅ Unified payment interface
- ✅ Single payment table for all gateways
- ✅ Livewire components with Tailwind CSS
- ✅ Automatic callback handling
- ✅ Open/Closed principle for easy extension
- ✅ Solo developer friendly

## Installation

```bash
composer require ejoi8/payment-gateway
php artisan vendor:publish --provider="Ejoi8\PaymentGateway\PaymentGatewayServiceProvider"
php artisan migrate
```

## Quick Start

```php
// In your controller
use Ejoi8\PaymentGateway\Services\PaymentService;

$paymentService = new PaymentService();
$payment = $paymentService->createPayment([
    'amount' => 100.00,
    'currency' => 'MYR',
    'gateway' => 'toyyibpay',
    'description' => 'Order #123'
]);

return redirect($payment->payment_url);
```

## Configuration

Publish and configure the package:

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

## License

MIT License
