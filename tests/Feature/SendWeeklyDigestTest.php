<?php

use App\Enums\TransactionCategory;
use App\Jobs\SendWeeklyDigest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.bot_token' => 'test-token']);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-13 09:00:00', 'Asia/Jakarta'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('sends a weekly digest to users with recent transactions', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $user = User::factory()->create(['telegram_id' => 777]);
    Transaction::factory()->for($user)->create(['amount' => 60000, 'category' => TransactionCategory::Food, 'occurred_at' => CarbonImmutable::parse('2026-07-10 12:00:00', 'Asia/Jakarta')->utc()]);
    Transaction::factory()->for($user)->create(['amount' => 15000, 'category' => TransactionCategory::Transport, 'occurred_at' => CarbonImmutable::parse('2026-07-11 12:00:00', 'Asia/Jakarta')->utc()]);

    app(SendWeeklyDigest::class)->handle(app(TelegramService::class));

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'Ringkasan mingguan')
            && str_contains($request['text'], 'Rp75.000')
            && str_contains($request['text'], '2 transaksi')
            && str_contains($request['text'], 'Makanan');
    });
});

it('skips users without transactions in the window', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $user = User::factory()->create(['telegram_id' => 888]);
    // Transaksi lebih dari 7 hari lalu — di luar jendela.
    Transaction::factory()->for($user)->create(['amount' => 50000, 'occurred_at' => CarbonImmutable::parse('2026-07-01 12:00:00', 'Asia/Jakarta')->utc()]);

    app(SendWeeklyDigest::class)->handle(app(TelegramService::class));

    Http::assertNothingSent();
});
