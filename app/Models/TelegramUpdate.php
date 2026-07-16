<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramUpdate extends Model
{
    protected $fillable = ['telegram_update_id', 'status', 'processed_at', 'processing_ms'];

    protected function casts(): array
    {
        return ['telegram_update_id' => 'integer', 'processed_at' => 'datetime', 'processing_ms' => 'integer'];
    }

    /** @return HasOne<Transaction, $this> */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
