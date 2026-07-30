<?php

namespace App\Models;

use App\Enums\TransactionCategory;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $amount
 * @property int $quantity
 * @property TransactionCategory $category
 * @property string $description
 * @property string $source
 * @property Carbon $occurred_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = ['amount', 'quantity', 'category', 'description', 'source', 'occurred_at', 'telegram_update_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'quantity' => 'integer', 'category' => TransactionCategory::class, 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TelegramUpdate, $this> */
    public function telegramUpdate(): BelongsTo
    {
        return $this->belongsTo(TelegramUpdate::class);
    }
}
