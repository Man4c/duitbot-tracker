<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\OtpService;
use App\Services\ParsingService;
use App\Services\SummaryService;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function runUpdate(int $updateId, string $text): TelegramUpdate
{
    $record = TelegramUpdate::create(['telegram_update_id' => $updateId]);
    $payload = ['update_id' => $updateId, 'message' => ['from' => ['id' => 555, 'first_name' => 'Rian'], 'chat' => ['id' => 555], 'text' => $text]];
    (new ProcessTelegramUpdate($record->id, $payload))->handle(
        app(ParsingService::class),
        app(TelegramService::class),
        app(OtpService::class),
        app(SummaryService::class),
        app(BudgetService::class),
    );

    return $record->refresh();
}

it('records multiple transactions from one message', function () {
    $record = runUpdate(1001, "kopi 20rb, parkir 5rb\nbensin 50rb");

    $user = User::where('telegram_id', 555)->firstOrFail();
    expect($user->transactions()->count())->toBe(3);
    expect((int) $user->transactions()->sum('amount'))->toBe(75000);
    expect($record->status)->toBe('processed');
});

it('replaces prior transactions when the same update is retried', function () {
    runUpdate(1002, 'kopi 20rb, parkir 5rb');
    // Retry manual dengan record yang sama tidak boleh menggandakan transaksi.
    $record = TelegramUpdate::where('telegram_update_id', 1002)->firstOrFail();
    $record->update(['status' => 'received', 'processed_at' => null]);
    $payload = ['update_id' => 1002, 'message' => ['from' => ['id' => 555, 'first_name' => 'Rian'], 'chat' => ['id' => 555], 'text' => 'kopi 20rb, parkir 5rb']];
    (new ProcessTelegramUpdate($record->id, $payload))->handle(
        app(ParsingService::class),
        app(TelegramService::class),
        app(OtpService::class),
        app(SummaryService::class),
        app(BudgetService::class),
    );

    $user = User::where('telegram_id', 555)->firstOrFail();
    expect($user->transactions()->count())->toBe(2);
});

it('records a single transaction the classic way', function () {
    runUpdate(1003, 'Makan siang 25rb');

    $user = User::where('telegram_id', 555)->firstOrFail();
    expect($user->transactions()->count())->toBe(1);
    expect((int) $user->transactions()->sum('amount'))->toBe(25000);
});

it('deletes the most recent transaction with /hapus', function () {
    runUpdate(2001, 'Kopi 20rb');
    runUpdate(2002, 'Bensin 50rb');
    runUpdate(2003, '/hapus');

    $user = User::where('telegram_id', 555)->firstOrFail();
    expect($user->transactions()->count())->toBe(1);
    expect($user->transactions()->latest('id')->first()->description)->toBe('Kopi');
});

it('warns when there is nothing to delete', function () {
    $record = runUpdate(2004, '/hapus');

    $user = User::where('telegram_id', 555)->firstOrFail();
    expect($user->transactions()->count())->toBe(0);
    expect($record->status)->toBe('processed');
});

it('edits the most recent transaction with /ubah', function () {
    runUpdate(2005, 'Makan 25rb');
    runUpdate(2006, '/ubah Makan malam 40rb');

    $user = User::where('telegram_id', 555)->firstOrFail();
    $last = $user->transactions()->latest('id')->first();
    expect($last->description)->toBe('Makan malam');
    expect($last->amount)->toBe(40000);
    expect($user->transactions()->count())->toBe(1);
});

it('asks for detail when /ubah has no argument', function () {
    runUpdate(2007, 'Makan 25rb');
    runUpdate(2008, '/ubah');

    $user = User::where('telegram_id', 555)->firstOrFail();
    $last = $user->transactions()->latest('id')->first();
    expect($last->amount)->toBe(25000);
});

it('replies to /ringkasan with a rich insight message', function () {
    // Kunci waktu di tengah bulan agar proyeksi deterministik: tanggal 10 dari 31 hari.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 09:00:00', 'Asia/Jakarta'));
    runUpdate(3001, 'Makan 100rb');
    runUpdate(3002, 'Bensin 50rb');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    runUpdate(3003, '/ringkasan');

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'Ringkasan bulan ini')
            && str_contains($request['text'], 'Rp150.000')
            && str_contains($request['text'], 'Terbesar: Makanan')
            && str_contains($request['text'], 'Proyeksi akhir bulan');
    });
    CarbonImmutable::setTestNow();
});

it('replies to /ringkasan gracefully when there are no transactions', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    runUpdate(3004, '/ringkasan');

    Http::assertSent(fn ($request) => str_contains($request['text'], 'Belum ada pengeluaran tercatat'));
});
