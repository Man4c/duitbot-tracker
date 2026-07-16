<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', Rule::enum(TransactionCategory::class)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'per_page' => ['nullable', 'integer', 'between:5,100']]);
        $query = $request->user()->transactions()->latest('occurred_at');
        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"));
        $query->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v));
        $query->when($filters['from'] ?? null, fn ($q, $v) => $q->where('occurred_at', '>=', now('Asia/Jakarta')->parse($v)->startOfDay()->utc()));
        $query->when($filters['to'] ?? null, fn ($q, $v) => $q->where('occurred_at', '<=', now('Asia/Jakarta')->parse($v)->endOfDay()->utc()));

        return TransactionResource::collection($query->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, int $transaction): TransactionResource
    {
        return new TransactionResource($request->user()->transactions()->findOrFail($transaction));
    }

    public function update(UpdateTransactionRequest $request, int $transaction): TransactionResource
    {
        $model = $request->user()->transactions()->findOrFail($transaction);
        $model->update($request->validated());

        return new TransactionResource($model->refresh());
    }

    public function destroy(Request $request, int $transaction): Response
    {
        $request->user()->transactions()->findOrFail($transaction)->delete();

        return response()->noContent();
    }
}
