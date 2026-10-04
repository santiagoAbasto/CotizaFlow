<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Quotes\QuoteStatus;
use App\Domain\Quotes\TransitionQuoteStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class RejectQuote
{
    public function __construct(private TransitionQuoteStatus $transition) {}

    public function __invoke(User $actor, Quote $quote, ?string $rejectionReason = null): Quote
    {
        Gate::forUser($actor)->authorize('reject', $quote);

        return ($this->transition)($quote, QuoteStatus::Rejected, $rejectionReason);
    }
}
