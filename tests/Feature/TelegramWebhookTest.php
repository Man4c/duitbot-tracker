<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config(['services.telegram.webhook_secret' => 'test-secret']));

it('rejects a webhook with an invalid secret', function () {
    $this->postJson('/api/telegram/webhook', ['update_id' => 1, 'message' => []])->assertForbidden();
});

it('accepts and dispatches each telegram update only once', function () {
    Queue::fake();
    $payload = ['update_id' => 42, 'message' => ['from' => ['id' => 10], 'chat' => ['id' => 10], 'text' => 'Makan 25rb']];
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    $this->postJson('/api/telegram/webhook', $payload, $headers)->assertOk();
    $this->postJson('/api/telegram/webhook', $payload, $headers)->assertOk();
    expect(TelegramUpdate::count())->toBe(1);
    Queue::assertPushed(ProcessTelegramUpdate::class, 1);
});

it('rejects an unsupported webhook payload without partial data', function () {
    Queue::fake();
    $this->postJson('/api/telegram/webhook', ['update_id' => 2], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertUnprocessable();
    expect(TelegramUpdate::count())->toBe(0);
    Queue::assertNothingPushed();
});
