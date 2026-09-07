<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class PreparedQuote
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(public array $items, public QuoteTotals $totals) {}
}
