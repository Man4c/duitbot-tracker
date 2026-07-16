<?php

namespace App\Http\Requests;

use App\Enums\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['amount' => ['sometimes', 'integer', 'min:1', 'max:999999999999'], 'description' => ['sometimes', 'string', 'max:255'], 'category' => ['sometimes', Rule::enum(TransactionCategory::class)]];
    }
}
