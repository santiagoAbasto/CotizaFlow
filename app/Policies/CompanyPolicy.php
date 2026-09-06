<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

final class CompanyPolicy
{
    use ResolvesMembership;

    public function view(User $user, Company $company): bool
    {
        return $this->actorForTenantBound($user, $company->getKey()) !== null;
    }

    public function update(User $user, Company $company): bool
    {
        $membership = $this->actorForTenantBound($user, $company->getKey());

        return $membership !== null && $membership->role->canManageCompany();
    }

    public function delete(User $user, Company $company): bool
    {
        $membership = $this->actorForTenantBound($user, $company->getKey());

        return $membership !== null && $membership->role->canDeleteCompany();
    }
}
