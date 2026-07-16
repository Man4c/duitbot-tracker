<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function __invoke(Request $request, SummaryService $summary): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => $summary->monthly($request->user(), $data['month'] ?? null)]);
    }
}
