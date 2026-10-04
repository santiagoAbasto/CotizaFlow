<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Quotes\QuoteStatus;
use App\Models\Quote;
use App\Models\User;

final class QuotePolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->actorForTenantBound($user, $quote->company_id) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->status === QuoteStatus::Draft;
    }

    public function send(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->status === QuoteStatus::Draft;
    }

    public function accept(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->status === QuoteStatus::Sent;
    }

    public function reject(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->status === QuoteStatus::Sent;
    }
}
