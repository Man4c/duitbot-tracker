<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class PollTelegramUpdates extends Command
{
    protected $signature = 'telegram:poll {--once : Ambil update satu kali lalu berhenti}';

    protected $description = 'Terima update Telegram melalui long polling untuk development lokal';

    public function handle(TelegramService $telegram): int
    {
        $this->components->info('Telegram polling aktif. Tekan Ctrl+C untuk berhenti.');
        $offset = (int) cache()->get('telegram_polling_offset', 0);

        do {
            try {
                $updates = $telegram->getUpdates($offset, $this->option('once') ? 0 : 20);
                foreach ($updates as $payload) {
                    $updateId = (int) ($payload['update_id'] ?? 0);
                    if ($updateId < 1) {
                        continue;
                    }

                    $record = TelegramUpdate::firstOrCreate(['telegram_update_id' => $updateId]);
                    if ($record->wasRecentlyCreated) {
                        ProcessTelegramUpdate::dispatch($record->id, $payload)->afterCommit();
                    }

                    $offset = max($offset, $updateId + 1);
                    cache()->forever('telegram_polling_offset', $offset);
                }
            } catch (Throwable) {
                $this->components->warn('Koneksi Telegram terputus. Mencoba kembali dalam 3 detik…');
                if ($this->option('once')) {
                    return self::FAILURE;
                }
                sleep(3);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
