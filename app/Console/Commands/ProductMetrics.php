<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\TelegramUpdate;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProductMetrics extends Command
{
    protected $signature = 'metrics:product';

    protected $description = 'Tampilkan metrik keberhasilan produk (§7 PRD) tanpa membuka isi pesan pengguna';

    public function handle(): int
    {
        $zone = 'Asia/Jakarta';
        $now = CarbonImmutable::now($zone);
        $weekAgo = $now->subDays(7);
        $monthAgo = $now->subDays(30);

        $totalUsers = User::whereNotNull('telegram_id')->count();
        $activeUsers = User::whereNotNull('telegram_id')
            ->whereHas('transactions', fn ($q) => $q->where('occurred_at', '>=', $weekAgo->utc()))
            ->count();
        $txThisWeek = Transaction::where('occurred_at', '>=', $weekAgo->utc())->count();
        $txPerActiveUser = $activeUsers > 0 ? round($txThisWeek / $activeUsers, 1) : 0;

        $dashboardMonthly = User::whereNotNull('telegram_id')
            ->where('dashboard_last_viewed_at', '>=', $monthAgo->utc())
            ->count();
        $dashboardRate = $totalUsers > 0 ? round(($dashboardMonthly / $totalUsers) * 100, 1) : 0;

        $usersWithBudget = User::whereNotNull('telegram_id')->whereHas('budgets')->count();
        $budgetRate = $totalUsers > 0 ? round(($usersWithBudget / $totalUsers) * 100, 1) : 0;
        $budgetCount = Budget::count();

        // Durasi pemrosesan pesan (target PRD: < 2000 ms). Hanya angka, tanpa isi pesan.
        $processed = TelegramUpdate::whereNotNull('processing_ms')->where('processed_at', '>=', $weekAgo->utc());
        $sampleSize = (clone $processed)->count();
        $avgMs = (int) round((float) (clone $processed)->avg('processing_ms'));
        $maxMs = (int) (clone $processed)->max('processing_ms');
        $under2s = (clone $processed)->where('processing_ms', '<=', 2000)->count();
        $under2sRate = $sampleSize > 0 ? round(($under2s / $sampleSize) * 100, 1) : 0;

        $this->components->info('Metrik Produk DuitBot — 7 hari terakhir (WIB)');
        $this->table(['Metrik', 'Nilai'], [
            ['Pengguna Telegram terdaftar', (string) $totalUsers],
            ['Pengguna aktif mencatat (7 hari)', (string) $activeUsers],
            ['Transaksi tercatat (7 hari)', (string) $txThisWeek],
            ['Rata-rata transaksi / pengguna aktif', (string) $txPerActiveUser],
            ['Buka dashboard (30 hari)', "{$dashboardMonthly} ({$dashboardRate}%)"],
            ['Pengguna mengaktifkan budget', "{$usersWithBudget} ({$budgetRate}%)"],
            ['Total budget diatur', (string) $budgetCount],
            ['Sampel pemrosesan pesan (7 hari)', (string) $sampleSize],
            ['Rata-rata waktu proses', $sampleSize > 0 ? "{$avgMs} ms" : '—'],
            ['Waktu proses maksimum', $sampleSize > 0 ? "{$maxMs} ms" : '—'],
            ['Proses ≤ 2 detik (target PRD)', $sampleSize > 0 ? "{$under2s} ({$under2sRate}%)" : '—'],
        ]);

        return self::SUCCESS;
    }
}
