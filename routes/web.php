<?php

use App\Enums\TransactionCategory;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/health', HealthController::class)->name('health');

Route::get('/telegram-login', fn () => inertia('auth/TelegramLogin', [
    'botUsername' => config('services.telegram.bot_username'),
]))->middleware('guest')->name('telegram.login');
Route::prefix('api/auth')->middleware('web')->group(function () {
    Route::post('check-identifier', [AuthController::class, 'check'])->middleware('throttle:20,1');
    Route::post('request-otp', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('telegram-login', [AuthController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function (Request $request) {
        // Catat kunjungan dashboard untuk metrik keterlibatan (maksimal sekali per hari).
        $user = $request->user();
        if (! $user->dashboard_last_viewed_at || ! $user->dashboard_last_viewed_at->isToday()) {
            $user->forceFill(['dashboard_last_viewed_at' => now()])->saveQuietly();
        }

        return inertia('Dashboard', ['categories' => TransactionCategory::values()]);
    })->name('dashboard');
});

require __DIR__.'/settings.php';
