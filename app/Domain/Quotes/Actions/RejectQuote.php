<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\FollowUps\Actions\CancelPendingFollowUpsForQuote;
use App\Domain\Quotes\QuoteStatus;
use App\Domain\Quotes\TransitionQuoteStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RejectQuote
{
    public function __construct(
        private TransitionQuoteStatus $transition,
        private CancelPendingFollowUpsForQuote $cancelPendingFollowUps,
    ) {}

    public function __invoke(User $actor, Quote $quote, ?string $rejectionReason = null): Quote
    {
        Gate::forUser($actor)->authorize('reject', $quote);

        return DB::transaction(function () use ($quote, $rejectionReason): Quote {
            $rejected = ($this->transition)($quote, QuoteStatus::Rejected, $rejectionReason);
            ($this->cancelPendingFollowUps)($rejected, 'La cotización fue marcada como rechazada.');

            return $rejected;
        });
    }
}
