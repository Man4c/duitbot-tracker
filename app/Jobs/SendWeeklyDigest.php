<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWeeklyDigest implements ShouldQueue
{
    use Queueable;

    public function handle(TelegramService $telegram): void
    {
        $zone = 'Asia/Jakarta';
        $end = CarbonImmutable::now($zone)->startOfDay();
        $start = $end->subDays(7);

        User::whereNotNull('telegram_id')
            ->whereHas('transactions', fn ($query) => $query->whereBetween('occurred_at', [$start->utc(), $end->utc()]))
            ->chunkById(100, function ($users) use ($telegram, $start, $end, $zone) {
                foreach ($users as $user) {
                    $rows = $user->transactions()->whereBetween('occurred_at', [$start->utc(), $end->utc()])->get();
                    if ($rows->isEmpty()) {
                        continue;
                    }

                    $total = (int) $rows->sum('amount');
                    $count = $rows->count();
                    $byCategory = $rows->groupBy(fn ($row) => $row->category->value)->map(fn ($group) => (int) $group->sum('amount'));
                    $topCategory = $byCategory->sortDesc()->keys()->first();
                    $topAmount = (int) $byCategory->sortDesc()->first();

                    $rangeLabel = $start->timezone($zone)->translatedFormat('j M').' – '.$end->subDay()->timezone($zone)->translatedFormat('j M');
                    $message = "📅 <b>Ringkasan mingguan</b> ({$rangeLabel})\n"
                        .'Total: <b>Rp'.number_format($total, 0, ',', '.')."</b> dari {$count} transaksi.\n"
                        .'Terbesar: '.$topCategory.' (Rp'.number_format($topAmount, 0, ',', '.').').';

                    $telegram->sendMessage($user->telegram_id, $message);
                }
            });
    }
}
