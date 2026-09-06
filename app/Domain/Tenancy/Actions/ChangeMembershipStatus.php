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

/** Atomically authorizes and applies a complete membership status transition. */
final class ChangeMembershipStatus
{
    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(User $actor, Membership $membership, MembershipStatus $newStatus): Membership
    {
        return DB::transaction(function () use ($actor, $membership, $newStatus): Membership {
            $companyId = $this->context->id();

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

            Gate::forUser($actor)->authorize('changeStatus', [$target, $newStatus]);

            if ($target->role === MembershipRole::Owner
                && $target->status === MembershipStatus::Active
                && ! $newStatus->grantsAccess()
                && $this->activeOwnerCount($memberships) <= 1) {
                throw new AuthorizationException('The last active owner cannot be suspended.');
            }

            $target->status = $newStatus;
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
