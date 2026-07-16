<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileBudgets implements ShouldQueue
{
    use Queueable;

    public function handle(BudgetService $service): void
    {
        User::whereNotNull('telegram_id')->whereHas('budgets')->chunkById(100, fn ($users) => $users->each(fn ($user) => $service->checkAndNotify($user)));
    }
}
