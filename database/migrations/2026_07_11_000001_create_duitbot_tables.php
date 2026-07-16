<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('telegram_username')->nullable()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_update_id')->unique();
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_update_id')->nullable()->unique()->constrained('telegram_updates')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('category', 32);
            $table->string('description', 255);
            $table->string('source', 16)->default('chat');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'category']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->unsignedBigInteger('monthly_limit');
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::create('budget_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->char('period', 7);
            $table->unsignedTinyInteger('threshold');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'category', 'period', 'threshold'], 'budget_notification_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_notifications');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('login_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_id']);
            $table->dropColumn(['telegram_id', 'telegram_username']);
        });
    }
};
