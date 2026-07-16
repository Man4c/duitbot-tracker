<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'amount' => $this->amount, 'category' => $this->category->value, 'description' => $this->description, 'source' => $this->source, 'occurred_at' => $this->occurred_at->timezone('Asia/Jakarta')->toIso8601String()];
    }
}
