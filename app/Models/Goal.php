<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = [
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}