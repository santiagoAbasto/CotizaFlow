<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Membership;

beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);
});

/** Creates a membership belonging to a company, held by a fresh user. */
function membershipIn(Company $company, MembershipRole $role, MembershipStatus $status = MembershipStatus::Active): Membership
{
    return Membership::factory()->create([
        'company_id' => $company->getKey(),
        'role' => $role,
        'status' => $status,
    ]);
}

// ------------------------------------------------------- owner protection ---

it('forbids an admin from changing an owner role', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $owner = membershipIn($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($admin, $owner): void {
        expect($admin->can('changeRole', [$owner, MembershipRole::Sales]))->toBeFalse();
    });
});

it('forbids an admin from suspending an owner', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $owner = membershipIn($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($admin, $owner): void {
        expect($admin->can('changeStatus', [$owner, MembershipStatus::Suspended]))->toBeFalse();
    });
});

it('forbids an admin from deleting an owner', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $owner = membershipIn($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($admin, $owner): void {
        expect($admin->can('delete', $owner))->toBeFalse();
    });
});

it('forbids an admin from promoting anyone to owner', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $sales = membershipIn($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($admin, $sales): void {
        expect($admin->can('promoteToOwner', $sales))->toBeFalse()
            ->and($admin->can('changeRole', [$sales, MembershipRole::Owner]))->toBeFalse();
    });
});

it('lets an owner promote another member to owner', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $sales = membershipIn($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($owner, $sales): void {
        expect($owner->can('promoteToOwner', $sales))->toBeTrue()
            ->and($owner->can('changeRole', [$sales, MembershipRole::Owner]))->toBeTrue();
    });
});

// ---------------------------------------------------------- last owner -----

it('protects the last active owner from deletion', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $ownMembership = $owner->membershipFor($this->acme);

    $this->context->runAs($this->acme, function () use ($owner, $ownMembership): void {
        expect($owner->can('delete', $ownMembership))->toBeFalse();
    });
});

it('protects the last active owner from suspension and demotion', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $ownMembership = $owner->membershipFor($this->acme);

    $this->context->runAs($this->acme, function () use ($owner, $ownMembership): void {
        expect($owner->can('changeStatus', [$ownMembership, MembershipStatus::Suspended]))->toBeFalse()
            ->and($owner->can('changeRole', [$ownMembership, MembershipRole::Admin]))->toBeFalse();
    });
});

it('allows demoting an owner once a second active owner exists', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $second = membershipIn($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($owner, $second): void {
        expect($owner->can('changeRole', [$second, MembershipRole::Admin]))->toBeTrue()
            ->and($owner->can('delete', $second))->toBeTrue();
    });
});

it('does not count a suspended owner as protecting the tenant', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $suspendedOwner = membershipIn($this->acme, MembershipRole::Owner, MembershipStatus::Suspended);

    $this->context->runAs($this->acme, function () use ($owner, $suspendedOwner): void {
        // The suspended one is not the last ACTIVE owner, so it may be removed.
        expect($owner->can('delete', $suspendedOwner))->toBeTrue();
    });
});

// ------------------------------------------------------- tenant binding ----

it('forbids administering a membership of a company that is not active', function (): void {
    // The actor is an owner in BOTH companies, but only Acme is active.
    $user = memberOf($this->acme, MembershipRole::Owner);
    memberOf($this->globex, MembershipRole::Owner, user: $user);

    $globexMembership = membershipIn($this->globex, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($user, $globexMembership): void {
        expect($user->can('view', $globexMembership))->toBeFalse()
            ->and($user->can('changeRole', [$globexMembership, MembershipRole::Admin]))->toBeFalse()
            ->and($user->can('changeStatus', [$globexMembership, MembershipStatus::Suspended]))->toBeFalse()
            ->and($user->can('delete', $globexMembership))->toBeFalse();
    });
});

it('allows the same actor once the other company is made active', function (): void {
    $user = memberOf($this->acme, MembershipRole::Owner);
    memberOf($this->globex, MembershipRole::Owner, user: $user);

    $globexMembership = membershipIn($this->globex, MembershipRole::Sales);

    $this->context->runAs($this->globex, function () use ($user, $globexMembership): void {
        expect($user->can('changeRole', [$globexMembership, MembershipRole::Admin]))->toBeTrue();
    });
});

it('forbids everything without an active tenant', function (): void {
    $owner = memberOf($this->acme, MembershipRole::Owner);
    $target = membershipIn($this->acme, MembershipRole::Sales);

    expect($owner->can('view', $target))->toBeFalse()
        ->and($owner->can('invite', Membership::class))->toBeFalse()
        ->and($owner->can('delete', $target))->toBeFalse();
});

it('forbids a suspended actor from administering members', function (): void {
    $suspended = memberOf($this->acme, MembershipRole::Owner, MembershipStatus::Suspended);
    $target = membershipIn($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($suspended, $target): void {
        expect($suspended->can('delete', $target))->toBeFalse();
    });
});

it('forbids a salesperson from administering members', function (): void {
    $sales = memberOf($this->acme, MembershipRole::Sales);
    $target = membershipIn($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($sales, $target): void {
        expect($sales->can('invite', Membership::class))->toBeFalse()
            ->and($sales->can('changeRole', [$target, MembershipRole::Admin]))->toBeFalse()
            ->and($sales->can('delete', $target))->toBeFalse();
    });
});

it('lets an admin administer ordinary members', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $target = membershipIn($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($admin, $target): void {
        expect($admin->can('invite', Membership::class))->toBeTrue()
            ->and($admin->can('changeRole', [$target, MembershipRole::Admin]))->toBeTrue()
            ->and($admin->can('changeStatus', [$target, MembershipStatus::Suspended]))->toBeTrue()
            ->and($admin->can('delete', $target))->toBeTrue();
    });
});
