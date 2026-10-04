<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\Quotes\QuoteStatus;
use App\Models\FollowUp;
use App\Models\Quote;
use App\Models\User;

final class FollowUpPolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        return $this->actorForTenantBound($user, $followUp->company_id) !== null;
    }

    public function create(User $user, Quote $quote): bool
    {
        return $this->actorForTenantBound($user, $quote->company_id) !== null
            && $quote->status === QuoteStatus::Sent;
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return $this->view($user, $followUp) && $followUp->status === FollowUpStatus::Pending;
    }

    public function complete(User $user, FollowUp $followUp): bool
    {
        return $this->update($user, $followUp);
    }

    public function cancel(User $user, FollowUp $followUp): bool
    {
        return $this->update($user, $followUp);
    }
}
