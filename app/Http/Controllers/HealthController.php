<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $queueReady = config('queue.default') !== 'database' || Schema::hasTable('jobs');

            return response()->json(['status' => $queueReady ? 'ready' : 'degraded', 'database' => 'ok', 'queue' => $queueReady ? 'ready' : 'missing_table'], $queueReady ? 200 : 503);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable', 'database' => 'error', 'queue' => 'unknown'], 503);
        }
    }
}
