<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            // Dengan QUEUE_CONNECTION=sync (free-tier) dispatch memproses langsung, jadi
            // kegagalan pemrosesan (mis. Telegram API down) bisa merambat ke response.
            // Tangkap agar webhook selalu balas 200 → mencegah retry-storm dari Telegram
            // (update idempoten & sudah tercatat). Pada worker async, dispatch hanya
            // meng-enqueue sehingga blok ini tak berpengaruh & retry worker tetap utuh.
            try {
                ProcessTelegramUpdate::dispatch($record->id, $request->all())->afterCommit();
            } catch (Throwable $e) {
                Log::error('Gagal memproses update Telegram secara sinkron.', ['exception' => $e]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
