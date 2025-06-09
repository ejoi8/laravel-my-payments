<?php

namespace Ejoi8\PaymentGateway;

use Illuminate\Support\ServiceProvider;
use Ejoi8\PaymentGateway\Services\PaymentService;

/**
 * Payment Gateway Service Provider
 * 
 * Registers the payment gateway services, routes and assets with Laravel.
 */
class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the package services
     *
     * @return void
     */    public function boot(): void
    {
        $this->registerPublishableResources();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
    }

    /**
     * Register publishable resources
     *
     * @return void
     */
    protected function registerPublishableResources(): void
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
    }

    /**
     * Register routes
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register views
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-gateway');
    }

    /**
     * Register migrations
     *
     * @return void
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }    /**
     * Register the package services
     *
     * @return void
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-gateway.php',
            'payment-gateway'
        );

        // Register services
        $this->app->singleton('payment-gateway', function () {
            return new PaymentService();
        });
    }
}
