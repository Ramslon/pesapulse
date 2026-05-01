<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class Expense extends Model
{
  protected $fillable = [
    'title',
    'amount',
    'category',
    'expense_date',
    'description',
  ];
}
