<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tableName = config('payment-gateway.table_name', 'payments');
        
        Schema::create($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_id')->unique();
            $table->string('gateway'); // toyyibpay, chipin, paypal, stripe, manual
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');
            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->text('description')->nullable();
            
            // Customer details
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Payment gateway fields
            $table->text('payment_url')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('callback_data')->nullable();
            
            // Manual payment fields
            $table->string('proof_file_path')->nullable();
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // Indexes
            $table->index(['gateway', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('gateway_transaction_id');
        });
    }

    public function down()
    {
        $tableName = config('payment-gateway.table_name', 'payments');
        Schema::dropIfExists($tableName);
    }
};
