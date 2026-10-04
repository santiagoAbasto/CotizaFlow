<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Domain\Quotes\QuoteStatus;
use App\Models\Quote;

/** Summarizes commercial value without combining totals from different currencies. */
final class PendingFollowUpValue
{
    /** @return list<array{currency: string, total: string}> */
    public function dueTodayOrOverdue(): array
    {
        $timing = FollowUpTiming::current();

        return Quote::query()
            ->where('status', QuoteStatus::Sent->value)
            ->whereHas('followUps', function ($followUps) use ($timing): void {
                $followUps->pending();
                $timing->applyDueTodayOrOverdue($followUps);
            })
            ->select('currency')
            ->selectRaw('SUM(total) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn (Quote $quote): array => [
                'currency' => $quote->currency->value,
                'total' => $quote->currency->format((string) $quote->total),
            ])
            ->all();
    }
}
