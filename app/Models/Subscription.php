<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'starts_at',
        'expires_at',
        'provider',
        'provider_subscription_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPremium(): bool
{
    if ($this->plan !== 'premium') {
        return false;
    }

    if ($this->status !== 'active') {
        return false;
    }

    $now = now();

    if (
        $this->starts_at !== null &&
        $this->starts_at->isFuture()
    ) {
        return false;
    }

    if (
        $this->expires_at !== null &&
        $this->expires_at->isPast()
    ) {
        return false;
    }

    return true;
}
}