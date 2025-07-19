<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TapRefund extends Model
{
    use HasFactory;

    protected $table = 'tap_refunds';

    protected $fillable = [
        'refund_id',
        'charge_id',
        'amount',
        'currency',
        'type',
        'status',
        'description',
        'reason',
        'reference',
        'metadata',
        'tap_response',
        'webhook_url',
        'refund_date'
    ];

    protected $casts = [
        'reference' => 'array',
        'metadata' => 'array',
        'tap_response' => 'array',
        'refund_date' => 'datetime',
        'amount' => 'decimal:3'
    ];

    /**
     * Check if refund is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if refund is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'accepted']);
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['declined', 'failed', 'restricted', 'rejected']);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format(floatval($this->amount), 3, '.', ',') . ' ' . $this->currency;
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'refunded' => 'badge-success',
            'pending', 'accepted' => 'badge-warning',
            'declined', 'failed', 'restricted', 'rejected' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Scope to get refunds by charge ID
     */
    public function scopeByChargeId($query, string $chargeId)
    {
        return $query->where('charge_id', $chargeId);
    }

    /**
     * Scope to get successful refunds
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope to get pending refunds
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'accepted']);
    }

    /**
     * Scope to get failed refunds
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['declined', 'failed', 'restricted', 'rejected']);
    }
}
