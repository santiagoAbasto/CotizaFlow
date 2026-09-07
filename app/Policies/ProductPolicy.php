<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->actorForTenantBound($user, $product->company_id) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    public function changeStatus(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
