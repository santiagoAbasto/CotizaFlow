<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\ChangeMembershipRole;
use App\Domain\Tenancy\Actions\ChangeMembershipStatus;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Membership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->context = app(TenantContext::class);
    $this->changeRole = app(ChangeMembershipRole::class);
    $this->changeStatus = app(ChangeMembershipStatus::class);
});

it('requires the destination role and status in policy calls', function (): void {
    $owner = memberOf($this->company, MembershipRole::Owner);
    $membership = $owner->membershipFor($this->company);

    $this->context->runAs($this->company, function () use ($owner, $membership): void {
        expect(fn () => $owner->can('changeRole', $membership))->toThrow(ArgumentCountError::class)
            ->and(fn () => $owner->can('changeStatus', $membership))->toThrow(ArgumentCountError::class);
    });
});

it('rejects demoting the only active owner', function (): void {
    $owner = memberOf($this->company, MembershipRole::Owner);
    $membership = $owner->membershipFor($this->company);

    expect(fn () => $this->context->runAs(
        $this->company,
        fn () => ($this->changeRole)($owner, $membership, MembershipRole::Admin),
    ))->toThrow(AuthorizationException::class);

    expect($membership->refresh()->role)->toBe(MembershipRole::Owner);
});

it('rejects suspending the only active owner', function (): void {
    $owner = memberOf($this->company, MembershipRole::Owner);
    $membership = $owner->membershipFor($this->company);

    expect(fn () => $this->context->runAs(
        $this->company,
        fn () => ($this->changeStatus)($owner, $membership, MembershipStatus::Suspended),
    ))->toThrow(AuthorizationException::class);

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active);
});

it('allows one of two active owners to be demoted', function (): void {
    $actor = memberOf($this->company, MembershipRole::Owner);
    $other = memberOf($this->company, MembershipRole::Owner);
    $target = $other->membershipFor($this->company);

    $changed = $this->context->runAs(
        $this->company,
        fn () => ($this->changeRole)($actor, $target, MembershipRole::Admin),
    );

    expect($changed->role)->toBe(MembershipRole::Admin)
        ->and(Membership::query()->where('company_id', $this->company->id)
            ->where('role', MembershipRole::Owner->value)
            ->where('status', MembershipStatus::Active->value)->count())->toBe(1);
});

it('rechecks the invariant after a competing transition has completed', function (): void {
    $first = memberOf($this->company, MembershipRole::Owner);
    $second = memberOf($this->company, MembershipRole::Owner);
    $firstMembership = $first->membershipFor($this->company);
    $secondMembership = $second->membershipFor($this->company);

    $this->context->runAs(
        $this->company,
        fn () => ($this->changeRole)($first, $firstMembership, MembershipRole::Admin),
    );

    expect(fn () => $this->context->runAs(
        $this->company,
        fn () => ($this->changeStatus)($second, $secondMembership, MembershipStatus::Suspended),
    ))->toThrow(AuthorizationException::class);

    expect(Membership::query()->where('company_id', $this->company->id)
        ->where('role', MembershipRole::Owner->value)
        ->where('status', MembershipStatus::Active->value)->count())->toBe(1);
});

it('locks the company and memberships before applying a transition', function (): void {
    $actor = memberOf($this->company, MembershipRole::Owner);
    $other = memberOf($this->company, MembershipRole::Owner);
    $target = $other->membershipFor($this->company);
    $sql = [];

    DB::listen(function ($query) use (&$sql): void {
        $sql[] = strtolower($query->sql);
    });

    $this->context->runAs(
        $this->company,
        fn () => ($this->changeRole)($actor, $target, MembershipRole::Admin),
    );

    $locks = array_values(array_filter($sql, fn (string $query): bool => str_contains($query, 'for update')));

    expect($locks)->toHaveCount(2)
        ->and($locks[0])->toContain('companies')
        ->and($locks[1])->toContain('memberships');
});

it('rejects a transition for a membership outside the active company', function (): void {
    $actor = memberOf($this->company, MembershipRole::Owner);
    $foreignCompany = Company::factory()->create();
    $foreignUser = memberOf($foreignCompany, MembershipRole::Owner);
    $foreignMembership = $foreignUser->membershipFor($foreignCompany);

    expect(fn () => $this->context->runAs(
        $this->company,
        fn () => ($this->changeRole)($actor, $foreignMembership, MembershipRole::Admin),
    ))->toThrow(AuthorizationException::class);

    expect($foreignMembership->refresh()->role)->toBe(MembershipRole::Owner);
});
