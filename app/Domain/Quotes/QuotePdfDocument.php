<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Models\Quote;

/** Maps an already-persisted commercial snapshot to the PDF view contract. */
final class QuotePdfDocument
{
    /** @return array<string, mixed> */
    public function __invoke(Quote $quote): array
    {
        $quote->loadMissing(['company', 'items']);

        return [
            'companyName' => $quote->company_name_snapshot ?? $quote->company?->name ?? 'CotizaFlow',
            'quote' => $quote,
            'items' => $quote->items->sortBy('position')->values(),
            'isDraft' => $quote->status === QuoteStatus::Draft,
        ];
    }
}
