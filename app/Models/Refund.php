<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_id',
        'refund_id',
        'amount',
        'currency',
        'description',
        'reason',
        'status',
        'response'
    ];

    protected $casts = [
        'response' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the payment that this refund belongs to.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'charge_id', 'charge_id');
    }

    /**
     * Check if refund is successful
     */
    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    /**
     * Check if refund is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
