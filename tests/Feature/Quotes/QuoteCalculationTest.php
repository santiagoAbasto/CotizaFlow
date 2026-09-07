<?php

declare(strict_types=1);

use App\Domain\Money\Currency;
use App\Domain\Quotes\CalculateQuote;
use App\Domain\Quotes\CalculateQuoteLine;

it('calculates a simple line with exact decimal arithmetic', function (): void {
    $line = app(CalculateQuoteLine::class)('15', '16.80', '0', '0', Currency::USD);

    expect($line->toArray())->toMatchArray([
        'quantity' => '15.000000',
        'unit_price' => '16.800000',
        'discount_amount' => '0.00',
        'tax_amount' => '0.00',
        'line_subtotal' => '252.00',
        'line_total' => '252.00',
    ]);
});

it('applies line discount then tax and sums visible lines', function (): void {
    $totals = app(CalculateQuote::class)([
        ['quantity' => '15', 'unit_price' => '16.80', 'discount_percent' => '0', 'tax_percent' => '0'],
        ['quantity' => '2', 'unit_price' => '149', 'discount_percent' => '5', 'tax_percent' => '13'],
    ], Currency::USD);

    expect($totals->lines[1]->discountAmount)->toBe('14.90')
        ->and($totals->lines[1]->subtotal)->toBe('283.10')
        ->and($totals->lines[1]->taxAmount)->toBe('36.80')
        ->and($totals->lines[1]->total)->toBe('319.90')
        ->and($totals->subtotal)->toBe('535.10')
        ->and($totals->discountTotal)->toBe('14.90')
        ->and($totals->taxTotal)->toBe('36.80')
        ->and($totals->total)->toBe('571.90');
});

it('rounds deliberate decimal edges half up at the line boundary', function (): void {
    $line = app(CalculateQuoteLine::class)('1.333333', '10.123456', '7.125000', '13.000000', Currency::USD);

    expect($line->discountAmount)->toBe('0.96')
        ->and($line->subtotal)->toBe('12.54')
        ->and($line->taxAmount)->toBe('1.63')
        ->and($line->total)->toBe('14.17');
});
