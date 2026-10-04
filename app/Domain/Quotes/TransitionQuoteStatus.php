<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Quotes\Exceptions\InvalidQuoteTransition;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * Owns the locked, deterministic part of a commercial quote transition.
 * Authorisation remains with the actor-specific Actions that call this service.
 */
final class TransitionQuoteStatus
{
    public function __invoke(Quote $quote, QuoteStatus $target, ?string $rejectionReason = null): Quote
    {
        return DB::transaction(function () use ($quote, $target, $rejectionReason): Quote {
            $fresh = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! $fresh->status->canTransitionTo($target)) {
                throw InvalidQuoteTransition::between($fresh->status, $target);
            }

            if (in_array($target, [QuoteStatus::Sent, QuoteStatus::Accepted, QuoteStatus::Rejected], true) && $this->isDue($fresh)) {
                throw InvalidQuoteTransition::validityEnded();
            }

            if ($target === QuoteStatus::Expired && ! $this->isDue($fresh)) {
                throw InvalidQuoteTransition::notDue();
            }

            $fresh->status = $target;
            match ($target) {
                QuoteStatus::Sent => $this->markSent($fresh),
                QuoteStatus::Accepted => $fresh->accepted_at = now(),
                QuoteStatus::Rejected => $this->markRejected($fresh, $rejectionReason),
                QuoteStatus::Expired => $fresh->expired_at = now(),
                QuoteStatus::Draft => null,
            };
            $fresh->save();

            return $fresh;
        });
    }

    public function isDue(Quote $quote): bool
    {
        return $quote->expires_at !== null && $quote->expires_at->lt(today());
    }

    private function markSent(Quote $quote): void
    {
        $quote->sent_at = now();
        if ($quote->company_name_snapshot === null) {
            $quote->loadMissing('company');
            $quote->company_name_snapshot = $quote->company?->name;
        }
    }

    private function markRejected(Quote $quote, ?string $rejectionReason): void
    {
        $quote->rejected_at = now();
        $quote->rejection_reason = $rejectionReason;
    }
}
