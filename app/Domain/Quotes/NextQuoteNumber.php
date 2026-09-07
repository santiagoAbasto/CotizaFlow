<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Models\QuoteSequence;

final class NextQuoteNumber
{
    /** @return array{sequence: int, number: string} */
    public function __invoke(): array
    {
        QuoteSequence::query()->insertOrIgnore(['next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $sequence = QuoteSequence::query()->lockForUpdate()->firstOrFail();
        $next = (int) $sequence->next_number;
        $sequence->next_number = $next + 1;
        $sequence->save();

        return ['sequence' => $next, 'number' => 'QT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT)];
    }
}
