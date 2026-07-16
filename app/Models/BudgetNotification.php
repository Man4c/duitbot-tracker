<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetNotification extends Model
{
    protected $fillable = ['user_id', 'category', 'period', 'threshold', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
