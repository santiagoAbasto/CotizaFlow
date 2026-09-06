<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\ActivateCompanyForUser;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Exceptions\CompanyNotAuthorized;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;

/**
 * The only sanctioned path from an untrusted company id to a bound context.
 */
beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->activate = app(ActivateCompanyForUser::class);
    $this->context = app(TenantContext::class);
});

it('binds a company the user is an active member of', function (): void {
    $user = memberOf($this->acme, MembershipRole::Owner);

    $membership = ($this->activate)($user, $this->acme->id);

    expect($this->context->id())->toBe($this->acme->id)
        ->and($membership->role)->toBe(MembershipRole::Owner);
});

it('refuses a company the user has no membership in', function (): void {
    $user = memberOf($this->acme);

    expect(fn () => ($this->activate)($user, $this->globex->id))
        ->toThrow(CompanyNotAuthorized::class);

    expect($this->context->has())->toBeFalse();
});

it('refuses a suspended membership', function (): void {
    $user = memberOf($this->acme, status: MembershipStatus::Suspended);

    expect(fn () => ($this->activate)($user, $this->acme->id))
        ->toThrow(CompanyNotAuthorized::class);

    expect($this->context->has())->toBeFalse();
});

it('refuses a merely invited membership', function (): void {
    $user = memberOf($this->acme, status: MembershipStatus::Invited);

    expect(fn () => ($this->activate)($user, $this->acme->id))
        ->toThrow(CompanyNotAuthorized::class);
});

it('refuses a company that does not exist', function (): void {
    $user = memberOf($this->acme);

    expect(fn () => ($this->activate)($user, 999_999))
        ->toThrow(CompanyNotAuthorized::class);
});

it('does not reveal whether the company exists', function (): void {
    $user = memberOf($this->acme);

    $missing = '';
    $foreign = '';

    try {
        ($this->activate)($user, 999_999);
    } catch (CompanyNotAuthorized $e) {
        $missing = $e->getMessage();
    }

    try {
        ($this->activate)($user, $this->globex->id);
    } catch (CompanyNotAuthorized $e) {
        $foreign = $e->getMessage();
    }

    // Identical wording, so the response cannot enumerate other tenants.
    expect($missing)->toBe($foreign)->and($missing)->not->toBeEmpty();
});
