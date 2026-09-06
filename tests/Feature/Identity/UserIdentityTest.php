<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Identity is global; role and status belong to the membership. See ADR 0001.
 */
it('refuses two users with the same email', function (): void {
    User::factory()->create(['email' => 'santiago@cotizaflow.test']);

    expect(fn () => User::factory()->create(['email' => 'santiago@cotizaflow.test']))
        ->toThrow(QueryException::class);
});

it('lets one user belong to two companies', function (): void {
    $acme = Company::factory()->create();
    $globex = Company::factory()->create();

    $user = memberOf($acme);
    memberOf($globex, user: $user);

    expect($user->memberships()->count())->toBe(2)
        ->and($user->companies()->pluck('companies.id')->all())
        ->toEqualCanonicalizing([$acme->id, $globex->id]);
});

it('gives the same user a different role per company', function (): void {
    $acme = Company::factory()->create();
    $globex = Company::factory()->create();

    $user = memberOf($acme, MembershipRole::Owner);
    memberOf($globex, MembershipRole::Sales, user: $user);

    expect($user->membershipFor($acme)->role)->toBe(MembershipRole::Owner)
        ->and($user->membershipFor($globex)->role)->toBe(MembershipRole::Sales);
});

it('refuses two memberships for the same user and company', function (): void {
    $acme = Company::factory()->create();
    $user = memberOf($acme);

    expect(fn () => memberOf($acme, user: $user))->toThrow(QueryException::class);
});

it('excludes non-active memberships from activeMemberships', function (): void {
    $acme = Company::factory()->create();
    $globex = Company::factory()->create();
    $suspended = Company::factory()->create();

    $user = memberOf($acme);
    memberOf($globex, status: MembershipStatus::Invited, user: $user);
    memberOf($suspended, status: MembershipStatus::Suspended, user: $user);

    expect($user->activeMemberships()->pluck('company_id')->all())->toBe([$acme->id]);
});

it('keeps membership lookup unaffected by status so refusals stay explicit', function (): void {
    $acme = Company::factory()->create();
    $user = memberOf($acme, status: MembershipStatus::Suspended);

    $membership = $user->membershipFor($acme);

    expect($membership)->not->toBeNull()
        ->and($membership->grantsAccess())->toBeFalse();
});
