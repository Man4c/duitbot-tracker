<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat index biasa lebih dulu agar foreign key tetap punya index pendukung,
        // baru lepaskan unique — MySQL menolak drop index yang masih dipakai FK.
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('telegram_update_id', 'transactions_telegram_update_id_index');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_telegram_update_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('telegram_update_id', 'transactions_telegram_update_id_unique');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_telegram_update_id_index');
        });
    }
};
