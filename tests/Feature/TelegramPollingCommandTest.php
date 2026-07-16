<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('receives telegram updates through local polling once', function () {
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => [[
            'update_id' => 99,
            'message' => ['from' => ['id' => 10], 'chat' => ['id' => 10], 'text' => '/start'],
        ]]]),
    ]);
    Queue::fake();

    $this->artisan('telegram:poll --once')->assertSuccessful();

    expect(TelegramUpdate::where('telegram_update_id', 99)->exists())->toBeTrue();
    Queue::assertPushed(ProcessTelegramUpdate::class, 1);
});

it('does not expose credentials when polling fails', function () {
    config(['services.telegram.bot_token' => 'very-secret-token']);
    Http::fake(fn () => throw new ConnectionException('Request failed for https://api.telegram.org/botvery-secret-token/getUpdates'));

    $this->artisan('telegram:poll --once')
        ->expectsOutputToContain('Koneksi Telegram terputus')
        ->doesntExpectOutputToContain('very-secret-token')
        ->assertFailed();
});
