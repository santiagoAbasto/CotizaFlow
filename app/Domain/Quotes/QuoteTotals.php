<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class QuoteTotals
{
    /** @param list<QuoteLineCalculation> $lines */
    public function __construct(
        public array $lines,
        public string $subtotal,
        public string $discountTotal,
        public string $taxTotal,
        public string $total,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (QuoteLineCalculation $line) => $line->toArray(), $this->lines),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'tax_total' => $this->taxTotal,
            'total' => $this->total,
        ];
    }
}
