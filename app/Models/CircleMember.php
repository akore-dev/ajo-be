<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircleMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'circle_id',
        'user_id',
        'role',
        'payout_position',
        'payout_status',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'payout_position' => 'integer',
        'joined_at' => 'datetime',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'member_id');
    }
}

