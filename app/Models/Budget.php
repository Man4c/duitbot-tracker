<?php

namespace App\Models;

use App\Enums\TransactionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property TransactionCategory $category
 * @property int $monthly_limit
 */
class Budget extends Model
{
    protected $fillable = ['category', 'monthly_limit'];

    protected function casts(): array
    {
        return ['category' => TransactionCategory::class, 'monthly_limit' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
