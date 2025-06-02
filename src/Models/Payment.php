<?php

namespace Ejoi8\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference_id',
        'gateway',
        'amount',
        'currency',
        'status',
        'description',
        'customer_name',
        'customer_email',
        'customer_phone',
        'payment_url',
        'gateway_transaction_id',
        'gateway_response',
        'callback_data',
        'proof_file_path',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'callback_data' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function getTableName()
    {
        return config('payment-gateway.table_name', 'payments');
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable($this->getTableName());
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // Accessors
    public function getIsManualPaymentAttribute()
    {
        return $this->gateway === 'manual';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PAID => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            self::STATUS_REFUNDED => 'bg-blue-100 text-blue-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    // Methods
    public function markAsPaid($gatewayTransactionId = null, $gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed($reason = null, $gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_response' => $gatewayResponse,
            'failed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    public function generateReferenceId()
    {
        return 'PAY-' . strtoupper(uniqid()) . '-' . time();
    }
}
