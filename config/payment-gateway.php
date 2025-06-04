<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'toyyibpay'),
    
    'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'MYR'),
      'success_route' => 'payment-gateway.success',
    'failed_route' => 'payment-gateway.failed',
    'callback_route' => 'payment-gateway.callback',
    
    'gateways' => [
        'toyyibpay' => [
            'enabled' => env('TOYYIBPAY_ENABLED', false),
            'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
            'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
            'sandbox' => env('TOYYIBPAY_SANDBOX', true),
        ],
        
        'chipin' => [
            'enabled' => env('CHIPIN_ENABLED', false),
            'secret_key' => env('CHIPIN_SECRET_KEY'),
            'brand_id' => env('CHIPIN_BRAND_ID'),
            'sandbox' => env('CHIPIN_SANDBOX', true),
        ],
        
        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],
        
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        
        'manual' => [
            'enabled' => env('MANUAL_PAYMENT_ENABLED', true),
            'upload_path' => 'payment-proofs',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_file_size' => 5120, // KB
        ],
    ],
    
    'table_name' => 'payments',
    
    'routes' => [
        'prefix' => 'payment-gateway',
        'middleware' => ['web'],
    ],
];
