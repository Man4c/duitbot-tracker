<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    private function client(): PendingRequest
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum dikonfigurasi.');
        }

        return Http::baseUrl("https://api.telegram.org/bot{$token}")->timeout(5)->retry(2, 150);
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }
        $this->client()->post('/sendMessage', $payload)->throw();
    }

    /** @return list<array<string, mixed>> */
    public function getUpdates(int $offset = 0, int $timeout = 20): array
    {
        $response = $this->client()
            ->timeout($timeout + 10)
            ->get('/getUpdates', [
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => json_encode(['message', 'callback_query'], JSON_THROW_ON_ERROR),
            ])
            ->throw()
            ->json();

        $updates = [];
        foreach ((array) ($response['result'] ?? []) as $update) {
            if (is_array($update)) {
                $updates[] = $update;
            }
        }

        return $updates;
    }
}
