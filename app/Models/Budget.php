<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
     protected $fillable = [
        'user_id',
        'amount',
        'month',
        'year',
    ];
    
   public function user()
{
    return $this->belongsTo(User::class);
} 
}
