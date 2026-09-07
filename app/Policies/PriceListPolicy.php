<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

final class PriceListPolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->actorForTenantBound($user, $priceList->company_id) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->view($user, $priceList);
    }

    public function changeStatus(User $user, PriceList $priceList): bool
    {
        return $this->update($user, $priceList);
    }

    public function managePrices(User $user, PriceList $priceList): bool
    {
        return $this->update($user, $priceList);
    }
}
