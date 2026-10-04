<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Quotes\QuoteStatus;
use App\Domain\Quotes\TransitionQuoteStatus;
use App\Models\Quote;

/** Trusted scheduler-only transition; no request actor can invoke it. */
final class ExpireQuote
{
    public function __construct(private TransitionQuoteStatus $transition) {}

    public function __invoke(Quote $quote): Quote
    {
        return ($this->transition)($quote, QuoteStatus::Expired);
    }
}
