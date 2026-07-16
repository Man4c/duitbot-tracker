<?php

namespace App\Services;

use App\Enums\TransactionCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

class SummaryService
{
    /** @return array<string, mixed> */
    public function monthly(User $user, ?string $month = null): array
    {
        $zone = 'Asia/Jakarta';
        $startLocal = $month ? CarbonImmutable::createFromFormat('Y-m', $month, $zone)->startOfMonth() : CarbonImmutable::now($zone)->startOfMonth();
        $endLocal = $startLocal->endOfMonth();
        $rows = $user->transactions()->whereBetween('occurred_at', [$startLocal->utc(), $endLocal->utc()])->get();
        $firstTransaction = $user->transactions()->min('occurred_at');
        $firstTransactionMonth = $firstTransaction
            ? CarbonImmutable::parse($firstTransaction, 'UTC')->timezone($zone)->format('Y-m')
            : null;
        $previousStart = $startLocal->subMonth()->startOfMonth();
        $previousRows = $user->transactions()->whereBetween('occurred_at', [$previousStart->utc(), $previousStart->endOfMonth()->utc()])->get();
        $daily = array_fill(1, $startLocal->daysInMonth, 0);
        $categories = array_fill_keys(TransactionCategory::values(), 0);
        $previousCategories = array_fill_keys(TransactionCategory::values(), 0);
        foreach ($rows as $row) {
            $daily[$row->occurred_at->timezone($zone)->day] += $row->amount;
            $categories[$row->category->value] += $row->amount;
        }
        foreach ($previousRows as $row) {
            $previousCategories[$row->category->value] += $row->amount;
        }
        $total = array_sum($daily);
        $previousTotal = array_sum($previousCategories);

        return ['month' => $startLocal->format('Y-m'), 'first_transaction_month' => $firstTransactionMonth, 'total' => $total, 'transaction_count' => $rows->count(), 'previous_total' => $previousTotal, 'change_percent' => $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100, 1) : null, 'daily' => $daily, 'categories' => $categories, 'previous_categories' => $previousCategories];
    }
}
