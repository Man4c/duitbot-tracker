<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Durasi pemrosesan pesan Telegram (milidetik) — hanya angka, tanpa isi pesan.
        Schema::table('telegram_updates', function (Blueprint $table) {
            $table->unsignedInteger('processing_ms')->nullable()->after('processed_at');
        });

        // Kapan terakhir pengguna membuka dashboard, untuk metrik keterlibatan bulanan.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dashboard_last_viewed_at')->nullable()->after('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_updates', function (Blueprint $table) {
            $table->dropColumn('processing_ms');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_last_viewed_at');
        });
    }
};
