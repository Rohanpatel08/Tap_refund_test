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
    ];
}
