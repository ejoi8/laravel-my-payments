<?php

namespace Ejoi8\PaymentGateway;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ejoi8\PaymentGateway\Livewire\PaymentForm;
use Ejoi8\PaymentGateway\Livewire\PaymentStatus;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'payment-gateway-migrations');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/payment-gateway.php' => config_path('payment-gateway.php'),
        ], 'payment-gateway-config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/payment-gateway'),
        ], 'payment-gateway-views');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-gateway');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register Livewire components
        Livewire::component('payment-form', PaymentForm::class);
        Livewire::component('payment-status', PaymentStatus::class);
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-gateway.php',
            'payment-gateway'
        );

        // Register services
        $this->app->singleton('payment-gateway', function () {
            return new \Ejoi8\PaymentGateway\Services\PaymentService();
        });
    }
}
