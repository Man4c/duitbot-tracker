<?php

use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:120,1');

// Pemicu scheduler untuk free-tier (dipicu cron eksternal, diamankan CRON_SECRET).
Route::post('/cron/run', CronController::class)->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::get('summary/monthly', SummaryController::class);
    Route::get('budgets/status', [BudgetController::class, 'index']);
    Route::post('budgets', [BudgetController::class, 'store']);
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy']);
});
