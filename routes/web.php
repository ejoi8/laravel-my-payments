<?php

use Illuminate\Support\Facades\Route;
use Ejoi8\PaymentGateway\Controllers\PaymentController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

// Safe config loading - handle case where config might not be available yet (during testing)
$config = null;
if (function_exists('config')) {
    $config = config('payment-gateway.routes');
}

// Use defaults if config is not available
$prefix = $config['prefix'] ?? 'payment-gateway';
$middleware = $config['middleware'] ?? ['web'];

Route::group([
    'prefix' => $prefix,
    'middleware' => $middleware,
], function () {

    // Payment creation
    Route::post('/create', [PaymentController::class, 'create'])->name('payment-gateway.create');

    // Payment callbacks
    Route::match(['get', 'post'], '/callback/{gateway}', [PaymentController::class, 'callback'])
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('payment-gateway.callback');

    // Payment pages
    Route::get('/success', [PaymentController::class, 'success'])->name('payment-gateway.success');
    Route::get('/failed', [PaymentController::class, 'failed'])->name('payment-gateway.failed');
    Route::get('/payment/{payment}', [PaymentController::class, 'show'])->name('payment-gateway.show');

    // Manual payment upload
    Route::match(['get', 'post'], '/manual-upload/{payment}', [PaymentController::class, 'manualUpload'])
        ->name('payment-gateway.manual.upload');

    // Payment verification
    Route::get('/verify/{gateway}/{transactionId}', [PaymentController::class, 'verify'])
        ->name('payment-gateway.verify');

    // Admin routes for manual payments
    Route::post('/admin/approve/{payment}', [PaymentController::class, 'approveManualPayment'])
        ->name('payment-gateway.admin.approve');
    Route::post('/admin/reject/{payment}', [PaymentController::class, 'rejectManualPayment'])
        ->name('payment-gateway.admin.reject');
});
