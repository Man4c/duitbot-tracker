<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Webhook secret tidak valid.');
        }
        $payload = $request->validate(['update_id' => ['required', 'integer', 'min:0'], 'message' => ['required_without:callback_query', 'array'], 'callback_query' => ['required_without:message', 'array']]);
        $record = TelegramUpdate::firstOrCreate(['telegram_update_id' => $payload['update_id']]);
        if ($record->wasRecentlyCreated) {
            ProcessTelegramUpdate::dispatch($record->id, $request->all())->afterCommit();
        }

        return response()->json(['ok' => true]);
    }
}
