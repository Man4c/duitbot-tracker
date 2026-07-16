<?php

use App\Enums\TransactionCategory;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => CarbonImmutable::setTestNow());

it('isolates transaction list detail update and delete between users', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();
    Sanctum::actingAs($attacker);
    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/transactions/{$transaction->id}")->assertNotFound();
    $this->putJson("/api/transactions/{$transaction->id}", ['amount' => 1])->assertNotFound();
    $this->deleteJson("/api/transactions/{$transaction->id}")->assertNotFound();
    expect($transaction->refresh()->amount)->not->toBe(1);
});

it('filters transactions and permits the owner to edit and delete', function () {
    $user = User::factory()->create();
    $food = Transaction::factory()->for($user)->create(['description' => 'Nasi', 'category' => TransactionCategory::Food]);
    Transaction::factory()->for($user)->create(['category' => TransactionCategory::Transport]);
    Sanctum::actingAs($user);
    $this->getJson('/api/transactions?search=Nasi&category=Makanan')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson("/api/transactions/{$food->id}", ['amount' => 30000, 'category' => 'Belanja'])->assertOk()->assertJsonPath('data.amount', 30000);
    $this->deleteJson("/api/transactions/{$food->id}")->assertNoContent();
    $this->assertModelMissing($food);
});

it('paginates the complete transaction history', function () {
    $user = User::factory()->create();
    Transaction::factory()->count(26)->for($user)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/transactions?per_page=10&page=3')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.current_page', 3)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.total', 26)
        ->assertJsonPath('meta.from', 21)
        ->assertJsonPath('meta.to', 26);
});

it('scopes summaries across WIB month boundaries', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:00:00', 'Asia/Jakarta'));
    $user = User::factory()->create();
    $other = User::factory()->create();
    Transaction::factory()->for($user)->create(['amount' => 10000, 'occurred_at' => CarbonImmutable::parse('2026-07-31 16:59:59', 'UTC')]);
    Transaction::factory()->for($user)->create(['amount' => 25000, 'occurred_at' => CarbonImmutable::parse('2026-07-31 17:00:00', 'UTC')]);
    Transaction::factory()->for($other)->create(['amount' => 999999, 'occurred_at' => now()]);
    Sanctum::actingAs($user);
    $this->getJson('/api/summary/monthly?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.total', 25000)
        ->assertJsonPath('data.transaction_count', 1)
        ->assertJsonPath('data.first_transaction_month', '2026-07');
});

it('reports per-category totals for the previous month', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:00:00', 'Asia/Jakarta'));
    $user = User::factory()->create();
    // Bulan berjalan (Agustus): Makanan 30rb.
    Transaction::factory()->for($user)->create(['amount' => 30000, 'category' => TransactionCategory::Food, 'occurred_at' => CarbonImmutable::parse('2026-08-10 12:00:00', 'Asia/Jakarta')->utc()]);
    // Bulan lalu (Juli): Makanan 50rb + Transport 20rb.
    Transaction::factory()->for($user)->create(['amount' => 50000, 'category' => TransactionCategory::Food, 'occurred_at' => CarbonImmutable::parse('2026-07-10 12:00:00', 'Asia/Jakarta')->utc()]);
    Transaction::factory()->for($user)->create(['amount' => 20000, 'category' => TransactionCategory::Transport, 'occurred_at' => CarbonImmutable::parse('2026-07-12 12:00:00', 'Asia/Jakarta')->utc()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/summary/monthly?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.total', 30000)
        ->assertJsonPath('data.previous_total', 70000)
        ->assertJsonPath('data.categories.Makanan', 30000)
        ->assertJsonPath('data.previous_categories.Makanan', 50000)
        ->assertJsonPath('data.previous_categories.Transport', 20000);
});

it('isolates budgets and upserts one budget per category', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = $other->budgets()->create(['category' => 'Makanan', 'monthly_limit' => 1]);
    Transaction::factory()->for($user)->create(['amount' => 125000, 'category' => TransactionCategory::Food, 'occurred_at' => CarbonImmutable::parse('2026-06-15 12:00:00', 'Asia/Jakarta')->utc()]);
    Sanctum::actingAs($user);
    $this->postJson('/api/budgets', ['category' => 'Makanan', 'monthly_limit' => 500000])->assertCreated();
    $this->postJson('/api/budgets', ['category' => 'Makanan', 'monthly_limit' => 600000])->assertCreated();
    expect($user->budgets()->count())->toBe(1);
    $this->deleteJson("/api/budgets/{$foreign->id}")->assertNotFound();
    $this->getJson('/api/budgets/status?month=2026-06')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.spent', 125000);
});
