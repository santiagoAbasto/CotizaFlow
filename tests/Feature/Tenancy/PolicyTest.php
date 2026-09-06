<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;

/**
 * Isolation and authorisation are separate layers. These cover the second one:
 * what a user may DO inside a tenant they legitimately belong to.
 */
beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);

    $this->customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
});

it('lets any active member view a customer of their company', function (): void {
    $user = memberOf($this->acme, MembershipRole::Sales);

    $this->context->runAs($this->acme, function () use ($user): void {
        expect($user->can('view', $this->customer))->toBeTrue()
            ->and($user->can('update', $this->customer))->toBeTrue();
    });
});

it('refuses a member of another company', function (): void {
    $outsider = memberOf($this->globex, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($outsider): void {
        expect($outsider->can('view', $this->customer))->toBeFalse()
            ->and($outsider->can('update', $this->customer))->toBeFalse()
            ->and($outsider->can('delete', $this->customer))->toBeFalse();
    });
});

it('refuses a suspended member of the right company', function (): void {
    $suspended = memberOf($this->acme, status: MembershipStatus::Suspended);

    $this->context->runAs($this->acme, function () use ($suspended): void {
        expect($suspended->can('view', $this->customer))->toBeFalse();
    });
});

it('reserves customer deletion for owners and admins', function (): void {
    $sales = memberOf($this->acme, MembershipRole::Sales);
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $owner = memberOf($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($sales, $admin, $owner): void {
        expect($sales->can('delete', $this->customer))->toBeFalse()
            ->and($admin->can('delete', $this->customer))->toBeTrue()
            ->and($owner->can('delete', $this->customer))->toBeTrue();
    });
});

it('reserves company deletion for the owner alone', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $owner = memberOf($this->acme, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($admin, $owner): void {
        expect($admin->can('delete', $this->acme))->toBeFalse()
            ->and($owner->can('delete', $this->acme))->toBeTrue();
    });
});

it('reserves company updates for owners and admins', function (): void {
    $sales = memberOf($this->acme, MembershipRole::Sales);
    $admin = memberOf($this->acme, MembershipRole::Admin);

    $this->context->runAs($this->acme, function () use ($sales, $admin): void {
        expect($sales->can('update', $this->acme))->toBeFalse()
            ->and($admin->can('update', $this->acme))->toBeTrue();
    });
});

it('refuses company access to a member of another company', function (): void {
    $outsider = memberOf($this->globex, MembershipRole::Owner);

    $this->context->runAs($this->acme, function () use ($outsider): void {
        expect($outsider->can('view', $this->acme))->toBeFalse();
    });
});
