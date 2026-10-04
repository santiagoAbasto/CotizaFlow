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

final class AcceptQuote
{
    public function __construct(
        private TransitionQuoteStatus $transition,
        private CancelPendingFollowUpsForQuote $cancelPendingFollowUps,
    ) {}

    public function __invoke(User $actor, Quote $quote): Quote
    {
        Gate::forUser($actor)->authorize('accept', $quote);

        return DB::transaction(function () use ($quote): Quote {
            $accepted = ($this->transition)($quote, QuoteStatus::Accepted);
            ($this->cancelPendingFollowUps)($accepted, 'La cotización fue marcada como aceptada.');

            return $accepted;
        });
    }
}
