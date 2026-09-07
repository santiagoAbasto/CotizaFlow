<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Money\Currency;
use Brick\Math\BigDecimal;

final class CalculateQuote
{
    public function __construct(private CalculateQuoteLine $calculateLine) {}

    /**
     * @param  list<array{quantity: string, unit_price: string, discount_percent: string, tax_percent: string}>  $items
     */
    public function __invoke(array $items, Currency $currency): QuoteTotals
    {
        $lines = array_map(fn (array $item) => ($this->calculateLine)(
            $item['quantity'],
            $item['unit_price'],
            $item['discount_percent'],
            $item['tax_percent'],
            $currency,
        ), $items);

        $sum = fn (string $field): string => (string) array_reduce(
            $lines,
            fn (BigDecimal $total, QuoteLineCalculation $line) => $total->plus($line->{$field}),
            BigDecimal::zero(),
        )->toScale($currency->displayExponent());

        return new QuoteTotals($lines, $sum('subtotal'), $sum('discountAmount'), $sum('taxAmount'), $sum('total'));
    }
}
