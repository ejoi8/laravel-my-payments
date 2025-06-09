<?php

namespace Ejoi8\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Payment Model
 * 
 * Represents a payment transaction in the payment gateway system.
 */
class Payment extends Model
{
    use HasUuids;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
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
        'external_reference_id',
        'reference_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'callback_data'    => 'array',
        'metadata'         => 'array',
        'paid_at'          => 'datetime',
        'failed_at'        => 'datetime',
    ];

    /**
     * Get the table name from config
     *
     * @return string
     */
    public function getTableName(): string
    {
        return config('payment-gateway.table_name', 'payments');
    }

    /**
     * Constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);        
        $this->setTable($this->getTableName());
    }

    /**
     * Payment status constants
     */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include paid payments.
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope a query to only include failed payments.
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Check if payment is a manual payment
     *
     * @return bool
     */
    public function getIsManualPaymentAttribute(): bool
    {
        return $this->gateway === 'manual';
    }

    /**
     * Get formatted amount with currency
     *
     * @return string
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float)$this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get CSS class for status badge
     *
     * @return string
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            self::STATUS_PENDING   => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PAID      => 'bg-green-100 text-green-800',
            self::STATUS_FAILED    => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            self::STATUS_REFUNDED  => 'bg-blue-100 text-blue-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Mark payment as paid
     *
     * @param string|null $gatewayTransactionId Transaction ID from payment gateway
     * @param array|null $gatewayResponse Response from payment gateway
     * @return bool
     */
    public function markAsPaid($gatewayTransactionId = null, $gatewayResponse = null): bool
    {
        return $this->update([
            'status'                 => self::STATUS_PAID,
            'gateway_transaction_id' => $gatewayTransactionId ?? $this->gateway_transaction_id,
            'callback_data'          => $gatewayResponse,
            'paid_at'                => now(),
        ]);
    }

    /**
     * Mark payment as failed
     *
     * @param string|null $reason Failure reason
     * @param array|null $gatewayResponse Response from payment gateway
     * @return bool
     */
    public function markAsFailed($reason = null, $gatewayResponse = null): bool
    {
        return $this->update([
            'status'           => self::STATUS_FAILED,
            'gateway_response' => $gatewayResponse,
            'failed_at'        => now(),
            'metadata'         => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    /**
     * Find payment by external reference
     *
     * @param string $externalReferenceId External reference ID (e.g. order ID)
     * @param string|null $referenceType Reference type (e.g. 'order', 'subscription')
     * @return \Illuminate\Database\Eloquent\Builder
     */    public static function findByExternalReference($externalReferenceId, $referenceType = null)
    {
        $query = static::where('external_reference_id', $externalReferenceId);
        
        if ($referenceType) {
            $query->where('reference_type', $referenceType);
        }
        
        return $query;
    }    /**
     * Scope for filtering by external reference
     */
    public function scopeByExternalReference(Builder $query, string $externalReferenceId, ?string $referenceType = null): void
    {
        $query->where('external_reference_id', $externalReferenceId);
        
        if ($referenceType) {
            $query->where('reference_type', $referenceType);
        }
    }

    /**
     * Generate a unique reference ID for payment
     *
     * @return string
     */
    public function generateReferenceId(): string
    {
        return 'PAY-' . strtoupper(uniqid()) . '-' . time();
    }
}
