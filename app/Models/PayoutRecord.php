<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'circle_id',
        'cycle',
        'recipient_user_id',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'disbursed_at',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'fee_amount' => 'integer',
        'net_amount' => 'integer',
        'cycle' => 'integer',
        'disbursed_at' => 'datetime',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

