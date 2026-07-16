<?php

use App\Models\Transaction;
use App\Models\User;

it('runs the product metrics command successfully', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Transaction::factory()->count(3)->for($user)->create(['occurred_at' => now()]);
    $user->budgets()->create(['category' => 'Makanan', 'monthly_limit' => 500000]);

    $this->artisan('metrics:product')
        ->expectsOutputToContain('Metrik Produk DuitBot')
        ->assertSuccessful();
});

it('handles no data without dividing by zero', function () {
    $this->artisan('metrics:product')->assertSuccessful();
});

it('records the first dashboard visit of the day', function () {
    $user = User::factory()->create(['telegram_id' => 222]);
    expect($user->dashboard_last_viewed_at)->toBeNull();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    expect($user->refresh()->dashboard_last_viewed_at)->not->toBeNull();
});
