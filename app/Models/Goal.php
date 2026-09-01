<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'target_amount',
        'saved_amount',
        'target_date',
        'is_archived',
        'milestone_25_notified',
        'milestone_50_notified',
        'milestone_75_notified',
        'milestone_100_notified',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'saved_amount' => 'decimal:2',
        'target_date' => 'date',
        'is_archived' => 'boolean',
        'milestone_25_notified' => 'boolean',
        'milestone_50_notified' => 'boolean',
        'milestone_75_notified' => 'boolean',
        'milestone_100_notified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}