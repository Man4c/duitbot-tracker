<?php

use App\Enums\TransactionCategory;
use App\Services\ParsingService;

it('normalizes supported rupiah formats', function (string $text, int $amount) {
    expect((new ParsingService)->parse("Makan {$text}")['amount'])->toBe($amount);
})->with([['25rb', 25000], ['25k', 25000], ['25.000', 25000], ['25000', 25000], ['25 ribu', 25000]]);

it('classifies all standard categories', function (string $text, TransactionCategory $category) {
    expect((new ParsingService)->classify($text))->toBe($category);
})->with([
    ['nasi goreng', TransactionCategory::Food], ['bensin motor', TransactionCategory::Transport], ['belanja baju', TransactionCategory::Shopping],
    ['tagihan listrik', TransactionCategory::Bills], ['nonton bioskop', TransactionCategory::Entertainment], ['beli obat', TransactionCategory::Health],
    ['bayar sekolah', TransactionCategory::Education], ['sewa rumah', TransactionCategory::Housing], ['beli deterjen', TransactionCategory::Household],
    ['potong rambut', TransactionCategory::PersonalCare], ['beli popok bayi', TransactionCategory::Family], ['pakan kucing', TransactionCategory::Pets],
    ['premi asuransi', TransactionCategory::Insurance], ['bayar cicilan', TransactionCategory::Debt], ['bayar pajak', TransactionCategory::Taxes],
    ['dana darurat', TransactionCategory::Savings], ['beli saham', TransactionCategory::Investment], ['donasi', TransactionCategory::Donation],
    ['beli kado', TransactionCategory::Gifts], ['pesan hotel', TransactionCategory::Travel], ['modal usaha', TransactionCategory::Work],
    ['bayar gym', TransactionCategory::Sports], ['beli laptop', TransactionCategory::Technology], ['pengeluaran acak', TransactionCategory::Other],
]);

it('classifies common Indonesian everyday keywords', function (string $text, TransactionCategory $category) {
    expect((new ParsingService)->classify($text))->toBe($category);
})->with([
    ['bakso malang', TransactionCategory::Food], ['seblak pedas', TransactionCategory::Food], ['boba', TransactionCategory::Food],
    ['isi pertalite', TransactionCategory::Transport], ['naik mrt', TransactionCategory::Transport], ['top up etoll', TransactionCategory::Transport],
    ['belanja indomaret', TransactionCategory::Shopping], ['alfamart', TransactionCategory::Shopping], ['checkout lazada', TransactionCategory::Shopping],
    ['bayar indihome', TransactionCategory::Bills], ['isi kuota', TransactionCategory::Bills],
    ['langganan netflix', TransactionCategory::Entertainment], ['nonton', TransactionCategory::Entertainment],
    ['bayar paylater', TransactionCategory::Debt], ['beli emas', TransactionCategory::Investment], ['beli tisu', TransactionCategory::Household],
    ['beli charger', TransactionCategory::Technology], ['main badminton', TransactionCategory::Sports],
]);

it('rejects messages without an amount', function () {
    (new ParsingService)->parse('makan siang');
})->throws(InvalidArgumentException::class);

it('parses multiple transactions separated by commas and newlines', function () {
    $parsed = (new ParsingService)->parseMany("kopi 20rb, parkir 5rb\nbensin 50rb");
    expect($parsed)->toHaveCount(3);
    expect(array_map(fn ($p) => $p['amount'], $parsed))->toBe([20000, 5000, 50000]);
    expect($parsed[0]['category'])->toBe(TransactionCategory::Food);
    expect($parsed[1]['category'])->toBe(TransactionCategory::Transport);
});

it('skips segments without an amount but keeps valid ones', function () {
    $parsed = (new ParsingService)->parseMany('makan 25rb, catatan tanpa angka, ojek 15rb');
    expect($parsed)->toHaveCount(2);
    expect(array_map(fn ($p) => $p['amount'], $parsed))->toBe([25000, 15000]);
});

it('rejects a multi-segment message with no amounts at all', function () {
    (new ParsingService)->parseMany('halo, apa kabar');
})->throws(InvalidArgumentException::class);
