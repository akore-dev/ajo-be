<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Circle extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_id',
        'name',
        'description',
        'contribution_amount',
        'currency',
        'frequency',
        'max_members',
        'current_members',
        'draw_method',
        'status',
        'start_date',
        'next_payout_date',
        'current_cycle',
        'total_cycles',
        'completed_at',
    ];

    protected $casts = [
        'contribution_amount' => 'integer',
        'max_members' => 'integer',
        'current_members' => 'integer',
        'current_cycle' => 'integer',
        'total_cycles' => 'integer',
        'start_date' => 'date',
        'next_payout_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function payoutRecords(): HasMany
    {
        return $this->hasMany(PayoutRecord::class);
    }
}

