<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class SetTelegramCommands extends Command
{
    protected $signature = 'telegram:set-commands';

    protected $description = 'Daftarkan menu command bot ke Telegram (popup di kolom chat)';

    /**
     * Daftar command yang tampil di menu. Urutan menentukan urutan tampil.
     *
     * @var list<array{command:string, description:string}>
     */
    private const COMMANDS = [
        ['command' => 'start', 'description' => 'Mulai bot & lihat cara pakai'],
        ['command' => 'help', 'description' => 'Bantuan & daftar perintah'],
        ['command' => 'login', 'description' => 'Kirim kode masuk ke dashboard'],
        ['command' => 'hari_ini', 'description' => 'Total pengeluaran hari ini'],
        ['command' => 'ringkasan', 'description' => 'Ringkasan bulan ini'],
        ['command' => 'total', 'description' => 'Ringkasan bulan ini (alias)'],
        ['command' => 'ubah', 'description' => 'Ubah transaksi terakhir'],
        ['command' => 'hapus', 'description' => 'Hapus transaksi terakhir'],
    ];

    public function handle(TelegramService $telegram): int
    {
        try {
            $telegram->setMyCommands(self::COMMANDS);
        } catch (Throwable $e) {
            // Jangan bocorkan token (ada di URL). Tampilkan pesan generik saja.
            $this->components->error('Gagal mendaftarkan menu command Telegram.');

            return self::FAILURE;
        }

        $this->components->info('Menu command Telegram berhasil didaftarkan ('.count(self::COMMANDS).' perintah).');

        return self::SUCCESS;
    }
}
