<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class QuoteLineCalculation
{
    public function __construct(
        public string $quantity,
        public string $unitPrice,
        public string $discountPercent,
        public string $discountAmount,
        public string $taxPercent,
        public string $taxAmount,
        public string $subtotal,
        public string $total,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_percent' => $this->discountPercent,
            'discount_amount' => $this->discountAmount,
            'tax_percent' => $this->taxPercent,
            'tax_amount' => $this->taxAmount,
            'line_subtotal' => $this->subtotal,
            'line_total' => $this->total,
        ];
    }
}
