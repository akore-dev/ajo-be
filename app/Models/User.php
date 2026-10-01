<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function createdCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'creator_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_members')
            ->withPivot(['role', 'payout_position', 'payout_status', 'status', 'joined_at']);
    }

    public function contributions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Contribution::class,
            CircleMember::class,
            'user_id',
            'member_id',
            'id',
            'id'
        );
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PayoutRecord::class, 'recipient_user_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}

