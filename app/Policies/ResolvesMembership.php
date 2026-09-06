<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Membership;
use App\Models\User;

/**
 * Shared membership lookup for policies.
 *
 * Two rules are enforced here rather than repeated in every ability:
 *
 *  1. The actor is checked against the company the RESOURCE belongs to, so a
 *     membership in some other company can never satisfy a check.
 *  2. Tenant-bound operations additionally require that the resource lives in
 *     the ACTIVE tenant. A user who belongs to both A and B, with A active,
 *     must switch to B explicitly before administering B.
 */
trait ResolvesMembership
{
    protected function activeMembershipIn(User $user, int $companyId): ?Membership
    {
        $membership = $user->membershipFor($companyId);

        return $membership !== null && $membership->grantsAccess() ? $membership : null;
    }

    /**
     * The actor's membership, only when the resource is inside the active tenant.
     */
    protected function actorForTenantBound(User $user, int $resourceCompanyId): ?Membership
    {
        $context = app(TenantContext::class);

        if (! $context->has() || $context->id() !== $resourceCompanyId) {
            return null;
        }

        return $this->activeMembershipIn($user, $resourceCompanyId);
    }

    protected function hasActiveMembership(User $user): bool
    {
        $context = app(TenantContext::class);

        if (! $context->has()) {
            return false;
        }

        return $this->activeMembershipIn($user, $context->id()) !== null;
    }

    /**
     * Owners are the only role that can dissolve or hand over the tenant, so a
     * company must never be left without one.
     */
    protected function isLastActiveOwner(Membership $membership): bool
    {
        if ($membership->role !== MembershipRole::Owner || ! $membership->grantsAccess()) {
            return false;
        }

        return Membership::query()
            ->where('company_id', $membership->company_id)
            ->where('role', MembershipRole::Owner->value)
            ->where('status', MembershipStatus::Active->value)
            ->count() <= 1;
    }
}
