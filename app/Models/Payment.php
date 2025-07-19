<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'charge_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'tap_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tap_response' => 'array',
    ];

    /**
     * Get the refunds for this payment.
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'charge_id', 'charge_id');
    }

    /**
     * Get the user that owns the payment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get total refunded amount for this payment
     */
    public function getTotalRefundedAttribute(): string
    {
        return $this->refunds()
            ->where('status', 'succeeded')
            ->sum('amount');
    }

    /**
     * Check if payment is fully refunded
     */
    public function isFullyRefunded(): bool
    {
        return $this->total_refunded >= $this->amount;
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmountAttribute(): string
    {
        return $this->amount - $this->total_refunded;
    }
}
