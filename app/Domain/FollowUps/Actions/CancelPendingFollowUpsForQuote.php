<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Quote;

/** Trusted quote-transition consequence; no request may invoke it directly. */
final class CancelPendingFollowUpsForQuote
{
    public function __invoke(Quote $quote, string $reason): void
    {
        FollowUp::query()
            ->pending()
            ->where('quote_id', $quote->id)
            ->lockForUpdate()
            ->each(function (FollowUp $followUp) use ($reason): void {
                $followUp->status = FollowUpStatus::Cancelled;
                $followUp->cancelled_at = now();
                $followUp->cancellation_reason = $reason;
                $followUp->pending_quote_id = null;
                $followUp->save();
            });
    }
}
