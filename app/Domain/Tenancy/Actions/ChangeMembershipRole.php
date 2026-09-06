<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Atomically authorizes and applies a complete membership role transition. */
final class ChangeMembershipRole
{
    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(User $actor, Membership $membership, MembershipRole $newRole): Membership
    {
        return DB::transaction(function () use ($actor, $membership, $newRole): Membership {
            $companyId = $this->context->id();

            // Every role/status transition locks the same company row first. This
            // serializes competing owner transitions before they inspect owners.
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $memberships = Membership::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->get();

            /** @var Membership|null $target */
            $target = $memberships->firstWhere('id', $membership->getKey());

            if ($target === null) {
                throw new AuthorizationException('The membership is not part of the active company.');
            }

            Gate::forUser($actor)->authorize('changeRole', [$target, $newRole]);

            if ($target->role === MembershipRole::Owner
                && $target->status === MembershipStatus::Active
                && $newRole !== MembershipRole::Owner
                && $this->activeOwnerCount($memberships) <= 1) {
                throw new AuthorizationException('The last active owner cannot be demoted.');
            }

            $target->role = $newRole;
            $target->save();

            return $target->refresh();
        });
    }

    private function activeOwnerCount(iterable $memberships): int
    {
        return collect($memberships)->filter(
            fn (Membership $item): bool => $item->role === MembershipRole::Owner
                && $item->status === MembershipStatus::Active,
        )->count();
    }
}
