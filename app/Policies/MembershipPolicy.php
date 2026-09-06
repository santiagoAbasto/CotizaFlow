<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Membership;
use App\Models\User;

/**
 * Authorisation for membership administration, separated by intention.
 *
 * A single `update` ability cannot express the rules that matter here: an admin
 * may legitimately change a salesperson's role while being forbidden from
 * touching an owner, and demoting the last owner must fail regardless of who
 * asks. Each mutation therefore has its own ability.
 *
 * Every ability is tenant-bound: the membership must belong to the ACTIVE
 * company, so belonging to the target company is not by itself enough.
 */
final class MembershipPolicy
{
    use ResolvesMembership;

    public function viewAny(User $user): bool
    {
        return $this->hasActiveMembership($user);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->actorForTenantBound($user, $membership->company_id) !== null;
    }

    public function invite(User $user): bool
    {
        $actor = $this->actorInActiveTenant($user);

        return $actor !== null && $actor->role->canManageMembers();
    }

    /**
     * Only an owner may create another owner.
     */
    public function promoteToOwner(User $user, Membership $membership): bool
    {
        $actor = $this->actorForTenantBound($user, $membership->company_id);

        return $actor !== null && $actor->role === MembershipRole::Owner;
    }

    public function changeRole(User $user, Membership $membership, MembershipRole $newRole): bool
    {
        $actor = $this->actorForTenantBound($user, $membership->company_id);

        if ($actor === null || ! $actor->role->canManageMembers()) {
            return false;
        }

        // An admin may never touch an owner's membership.
        if ($membership->role === MembershipRole::Owner && $actor->role !== MembershipRole::Owner) {
            return false;
        }

        // Only an owner may hand out the owner role.
        if ($newRole === MembershipRole::Owner && $actor->role !== MembershipRole::Owner) {
            return false;
        }

        // Demoting the last active owner would strand the tenant.
        if ($newRole !== MembershipRole::Owner && $this->isLastActiveOwner($membership)) {
            return false;
        }

        return true;
    }

    public function changeStatus(User $user, Membership $membership, MembershipStatus $newStatus): bool
    {
        $actor = $this->actorForTenantBound($user, $membership->company_id);

        if ($actor === null || ! $actor->role->canManageMembers()) {
            return false;
        }

        if ($membership->role === MembershipRole::Owner && $actor->role !== MembershipRole::Owner) {
            return false;
        }

        // Suspending or revoking the last active owner would strand the tenant.
        if (! $newStatus->grantsAccess() && $this->isLastActiveOwner($membership)) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Membership $membership): bool
    {
        $actor = $this->actorForTenantBound($user, $membership->company_id);

        if ($actor === null || ! $actor->role->canManageMembers()) {
            return false;
        }

        if ($membership->role === MembershipRole::Owner && $actor->role !== MembershipRole::Owner) {
            return false;
        }

        return ! $this->isLastActiveOwner($membership);
    }

    private function actorInActiveTenant(User $user): ?Membership
    {
        $context = app(TenantContext::class);

        return $context->has() ? $this->activeMembershipIn($user, $context->id()) : null;
    }
}
