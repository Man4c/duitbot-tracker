<?php

namespace App\Services;

use App\Models\BudgetNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(private TelegramService $telegram) {}

    /** @return list<array{id:int, category:string, monthly_limit:int, spent:int, percentage:float|int}> */
    public function status(User $user, ?string $month = null): array
    {
        $zone = 'Asia/Jakarta';
        $period = $month
            ? CarbonImmutable::createFromFormat('Y-m', $month, $zone)
            : CarbonImmutable::now($zone);
        $start = $period->startOfMonth()->utc();
        $end = $period->endOfMonth()->utc();
        $spent = $user->transactions()->whereBetween('occurred_at', [$start, $end])->selectRaw('category, SUM(amount) total')->groupBy('category')->pluck('total', 'category');
        $items = [];
        foreach ($user->budgets()->get() as $budget) {
            $category = $budget->category->value;
            $amount = (int) ($spent[$category] ?? 0);
            $items[] = ['id' => $budget->id, 'category' => $category, 'monthly_limit' => $budget->monthly_limit, 'spent' => $amount, 'percentage' => $budget->monthly_limit > 0 ? round(($amount / $budget->monthly_limit) * 100, 1) : 0];
        }

        return $items;
    }

    public function checkAndNotify(User $user): void
    {
        $period = now('Asia/Jakarta')->format('Y-m');
        foreach ($this->status($user) as $item) {
            foreach ([80, 100] as $threshold) {
                if ($item['percentage'] < $threshold) {
                    continue;
                }
                $notification = BudgetNotification::createOrFirst(['user_id' => $user->id, 'category' => $item['category'], 'period' => $period, 'threshold' => $threshold]);
                $claimed = DB::transaction(function () use ($notification): bool {
                    $locked = BudgetNotification::query()->lockForUpdate()->findOrFail($notification->id);
                    if ($locked->sent_at) {
                        return false;
                    }
                    $locked->update(['sent_at' => now()]);

                    return true;
                });
                if (! $claimed) {
                    continue;
                }
                $this->telegram->sendMessage($user->telegram_id, "⚠️ Budget <b>{$item['category']}</b> sudah mencapai {$item['percentage']}% (Rp".number_format($item['spent'], 0, ',', '.').').');
            }
        }
    }
}
