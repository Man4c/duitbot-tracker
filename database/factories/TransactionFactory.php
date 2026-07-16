<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'amount' => fake()->numberBetween(5000, 500000), 'category' => fake()->randomElement(TransactionCategory::values()), 'description' => fake()->words(3, true), 'source' => 'chat', 'occurred_at' => now()];
    }
}
