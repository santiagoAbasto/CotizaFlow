<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * The global scope guarantees a user never SEES another tenant's customers.
 * This policy decides what they may DO with their own tenant's customers.
 * Both layers are required: isolation is not authorisation.
 */
final class CustomerPolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->actorForTenantBound($user, $customer->company_id) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->actorForTenantBound($user, $customer->company_id) !== null;
    }

    public function delete(User $user, Customer $customer): bool
    {
        $membership = $this->actorForTenantBound($user, $customer->company_id);

        return $membership !== null && $membership->role->canManageCompany();
    }

    public function changeStatus(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
