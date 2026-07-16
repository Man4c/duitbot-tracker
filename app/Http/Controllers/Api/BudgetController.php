<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetRequest;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BudgetController extends Controller
{
    public function index(Request $request, BudgetService $service): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => $service->status($request->user(), $data['month'] ?? null)]);
    }

    public function store(StoreBudgetRequest $request, BudgetService $service): JsonResponse
    {
        $request->user()->budgets()->updateOrCreate(['category' => $request->validated('category')], ['monthly_limit' => $request->validated('monthly_limit')]);

        return response()->json(['data' => $service->status($request->user())], 201);
    }

    public function destroy(Request $request, int $budget): Response
    {
        $request->user()->budgets()->findOrFail($budget)->delete();

        return response()->noContent();
    }
}
