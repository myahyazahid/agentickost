<?php

use App\Support\Money\Rupiah;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Model;

it('formats rupiah the Indonesian way', function (int $amount, string $expected) {
    expect(Rupiah::format($amount))->toBe($expected);
})->with([
    'zero' => [0, 'Rp0'],
    'hundreds' => [500, 'Rp500'],
    'thousands' => [440000, 'Rp440.000'],
    'millions' => [1500000, 'Rp1.500.000'],
    'negative' => [-25000, '-Rp25.000'],
]);

it('stores whole rupiah amounts as integers', function (mixed $value, ?int $expected) {
    $model = new class extends Model {};

    expect((new RupiahCast)->set($model, 'rent_amount', $value, []))->toBe($expected);
})->with([
    'integer' => [440000, 440000],
    'numeric string' => ['440000', 440000],
    'negative string' => ['-1500', -1500],
    'null' => [null, null],
]);

it('refuses amounts that are not whole rupiah', function (mixed $value) {
    $model = new class extends Model {};

    (new RupiahCast)->set($model, 'rent_amount', $value, []);
})->with([
    'float' => [440000.5],
    'whole float' => [440000.0],
    'decimal string' => ['440000.50'],
    'formatted string' => ['Rp440.000'],
])->throws(InvalidArgumentException::class);
