<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'circle_id',
        'member_id',
        'cycle',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'payment_reference',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'cycle' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * The circle associated with this contribution.
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * The circle member who made this contribution.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CircleMember::class, 'member_id');
    }
}
