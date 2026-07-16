<?php

namespace App\Http\Requests;

use App\Enums\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['category' => ['required', Rule::enum(TransactionCategory::class)], 'monthly_limit' => ['required', 'integer', 'min:1', 'max:999999999999']];
    }
}
