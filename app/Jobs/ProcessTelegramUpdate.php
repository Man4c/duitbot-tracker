<?php

namespace App\Jobs;

use App\Enums\TransactionCategory;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\OtpService;
use App\Services\ParsingService;
use App\Services\SummaryService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $payload */
    public function __construct(public int $updateRecordId, public array $payload) {}

    public function handle(ParsingService $parser, TelegramService $telegram, OtpService $otp, SummaryService $summary, BudgetService $budgets): void
    {
        $record = TelegramUpdate::find($this->updateRecordId);
        if (! $record || $record->processed_at) {
            return;
        }

        $startedAt = microtime(true);
        try {
            if (isset($this->payload['callback_query'])) {
                $this->handleCallback($this->payload['callback_query'], $telegram);
            } else {
                $message = $this->payload['message'] ?? null;
                if (! is_array($message) || ! isset($message['from']['id'], $message['chat']['id'], $message['text'])) {
                    throw new InvalidArgumentException('Update tidak didukung.');
                }
                $user = User::updateOrCreate(['telegram_id' => (int) $message['from']['id']], ['name' => trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')) ?: 'Pengguna Telegram', 'telegram_username' => $message['from']['username'] ?? null]);
                $this->handleMessage($user, (int) $message['chat']['id'], trim($message['text']), $record, $parser, $telegram, $otp, $summary, $budgets);
            }
            $record->update(['status' => 'processed', 'processed_at' => now(), 'processing_ms' => $this->elapsedMs($startedAt)]);
        } catch (InvalidArgumentException $e) {
            $chat = $this->payload['message']['chat']['id'] ?? null;
            if ($chat) {
                $telegram->sendMessage($chat, '❌ '.$e->getMessage());
            }
            $record->update(['status' => 'invalid', 'processed_at' => now(), 'processing_ms' => $this->elapsedMs($startedAt)]);
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function handleMessage(User $user, int $chatId, string $text, TelegramUpdate $record, ParsingService $parser, TelegramService $telegram, OtpService $otp, SummaryService $summary, BudgetService $budgets): void
    {
        $command = strtolower(explode(' ', $text, 2)[0]);
        if (in_array($command, ['/start', '/help'], true)) {
            $telegram->sendMessage($chatId, "👋 Halo, <b>{$user->name}</b>!\nKirim pengeluaran seperti <code>Makan siang 25rb</code>.\nBeberapa sekaligus juga bisa: <code>kopi 20rb, parkir 5rb, bensin 50rb</code>.\n\nPerintah: /login, /hari_ini, /ringkasan, /total, /ubah, /hapus");

            return;
        }
        if ($command === '/login') {
            $otp->issue($user);

            return;
        }
        if (in_array($command, ['/ringkasan', '/total'], true)) {
            $telegram->sendMessage($chatId, $this->monthlyInsight($summary->monthly($user)));

            return;
        }
        if ($command === '/hari_ini') {
            $start = now('Asia/Jakarta')->startOfDay();
            $total = $user->transactions()->whereBetween('occurred_at', [$start->utc(), $start->copy()->endOfDay()->utc()])->sum('amount');
            $telegram->sendMessage($chatId, '🗓 Total hari ini: <b>Rp'.number_format((int) $total, 0, ',', '.').'</b>');

            return;
        }
        if ($command === '/hapus') {
            $last = $user->transactions()->latest('id')->first();
            if (! $last) {
                $telegram->sendMessage($chatId, 'Belum ada transaksi untuk dihapus.');

                return;
            }
            $summaryLine = e($last->description).'</b> — Rp'.number_format($last->amount, 0, ',', '.');
            $last->delete();
            $telegram->sendMessage($chatId, '🗑 Transaksi terakhir dihapus: <b>'.$summaryLine.'.');
            $budgets->checkAndNotify($user);

            return;
        }
        if ($command === '/ubah') {
            $argument = trim((string) (explode(' ', $text, 2)[1] ?? ''));
            $last = $user->transactions()->latest('id')->first();
            if (! $last) {
                $telegram->sendMessage($chatId, 'Belum ada transaksi untuk diubah.');

                return;
            }
            if ($argument === '') {
                $telegram->sendMessage($chatId, 'Kirim <code>/ubah &lt;detail baru&gt;</code>, contoh: <code>/ubah Makan siang 30rb</code>.');

                return;
            }
            $parsed = $parser->parse($argument);
            $last->update(['amount' => $parsed['amount'], 'quantity' => $parsed['quantity'], 'description' => $parsed['description'], 'category' => $parsed['category']]);
            $telegram->sendMessage($chatId, '✏️ Transaksi terakhir diubah: <b>'.e($last->description).'</b> — Rp'.number_format($last->amount, 0, ',', '.').' — '.$last->category->value);
            $budgets->checkAndNotify($user);

            return;
        }

        $parsedList = $parser->parseMany($text);
        // telegram_update_id tidak lagi unik, jadi idempotency retry dijaga dengan
        // menghapus transaksi lama dari update ini lalu membuat ulang seluruh batch.
        $transactions = DB::transaction(function () use ($user, $record, $parsedList) {
            $user->transactions()->where('telegram_update_id', $record->id)->delete();

            return array_map(fn ($parsed) => $user->transactions()->create(['telegram_update_id' => $record->id, 'amount' => $parsed['amount'], 'quantity' => $parsed['quantity'], 'description' => $parsed['description'], 'category' => $parsed['category'], 'source' => 'chat', 'occurred_at' => now()]), $parsedList);
        });

        if (count($transactions) === 1) {
            $transaction = $transactions[0];
            $keyboard = $parsedList[0]['ambiguous'] ? $this->categoryKeyboard($transaction->id) : null;
            $qtyNote = $transaction->quantity > 1 ? ' (×'.$transaction->quantity.')' : '';
            $telegram->sendMessage($chatId, '✅ Tercatat: <b>'.e($transaction->description).'</b>'.$qtyNote.' — Rp'.number_format($transaction->amount, 0, ',', '.').' — '.$transaction->category->value.($keyboard ? "\nPilih kategori yang lebih tepat:" : ''), $keyboard);
        } else {
            $total = array_sum(array_map(fn ($t) => $t->amount, $transactions));
            $lines = array_map(fn ($t) => '• <b>'.e($t->description).'</b> — Rp'.number_format($t->amount, 0, ',', '.').' — '.$t->category->value, $transactions);
            $telegram->sendMessage($chatId, '✅ Tercatat '.count($transactions)." transaksi:\n".implode("\n", $lines)."\n\nTotal: <b>Rp".number_format((int) $total, 0, ',', '.').'</b>');
            foreach ($transactions as $index => $transaction) {
                if ($parsedList[$index]['ambiguous']) {
                    $telegram->sendMessage($chatId, 'Pilih kategori yang lebih tepat untuk <b>'.e($transaction->description).'</b>:', $this->categoryKeyboard($transaction->id));
                }
            }
        }
        $budgets->checkAndNotify($user);
    }

    /**
     * Rangkai ringkasan bulanan menjadi pesan insight: total, kategori terbesar,
     * perbandingan bulan lalu, dan proyeksi akhir bulan (hanya untuk bulan berjalan).
     *
     * @param  array<string, mixed>  $data
     */
    private function monthlyInsight(array $data): string
    {
        $total = (int) $data['total'];
        if ($total <= 0) {
            return '📊 Belum ada pengeluaran tercatat bulan ini. Kirim pengeluaran seperti <code>Makan siang 25rb</code> untuk memulai.';
        }

        $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');
        $lines = ['📊 <b>Ringkasan bulan ini</b>', 'Total: <b>'.$rupiah($total).'</b> dari '.(int) $data['transaction_count'].' transaksi.'];

        $categories = array_filter((array) $data['categories'], fn ($amount) => $amount > 0);
        if ($categories !== []) {
            arsort($categories);
            $topCategory = (string) array_key_first($categories);
            $topAmount = (int) $categories[$topCategory];
            $share = (int) round(($topAmount / $total) * 100);
            $lines[] = 'Terbesar: '.$topCategory.' ('.$rupiah($topAmount).', '.$share.'% dari total).';
        }

        $change = $data['change_percent'];
        if ($change !== null) {
            $lines[] = $change >= 0
                ? 'Naik '.abs($change).'% dibanding bulan lalu.'
                : 'Turun '.abs($change).'% dibanding bulan lalu.';
        }

        $now = now('Asia/Jakarta');
        if ($data['month'] === $now->format('Y-m')) {
            $projection = (int) round(($total / $now->day) * $now->daysInMonth);
            $lines[] = 'Proyeksi akhir bulan: <b>'.$rupiah($projection).'</b> jika pola ini bertahan.';
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function categoryKeyboard(int $transactionId): array
    {
        return ['inline_keyboard' => array_chunk(array_map(fn ($category) => ['text' => $category->value, 'callback_data' => "cat:{$transactionId}:{$category->name}"], TransactionCategory::cases()), 2)];
    }

    /** @param array<string, mixed> $callback */
    private function handleCallback(array $callback, TelegramService $telegram): void
    {
        if (! preg_match('/^cat:(\d+):([A-Za-z]+)$/', $callback['data'] ?? '', $parts)) {
            throw new InvalidArgumentException('Pilihan tidak valid.');
        }
        $user = User::where('telegram_id', $callback['from']['id'] ?? 0)->firstOrFail();
        $transaction = $user->transactions()->findOrFail((int) $parts[1]);
        $category = constant(TransactionCategory::class.'::'.$parts[2]);
        $transaction->update(['category' => $category]);
        $telegram->sendMessage($callback['message']['chat']['id'], "✅ Kategori diubah menjadi <b>{$transaction->category->value}</b>.");
    }

    public function failed(?Throwable $exception): void
    {
        TelegramUpdate::whereKey($this->updateRecordId)->whereNull('processed_at')->update(['status' => 'failed']);
    }
}
