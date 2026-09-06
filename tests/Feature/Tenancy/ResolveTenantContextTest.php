<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Exceptions\ActiveCompanyUnavailable;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();

    Route::middleware('web')->get('/_test/tenant', fn (): array => [
        'tenant' => app(TenantContext::class)->id(),
        'customers' => Customer::pluck('name')->all(),
    ]);

    Route::middleware('web')->post('/_test/customers', function (): array {
        $customer = Customer::create(['name' => 'Creado', 'email' => 'creado@test.test']);

        return ['company_id' => $customer->company_id];
    });
});

/** Acts as the user with an explicitly selected active company. */
function actingInCompany(User $user, Company $company): array
{
    return [ResolveTenantContext::SESSION_KEY => $company->id];
}

// ------------------------------------------------------- happy path --------

it('binds the tenant from the selected company in the session', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession(actingInCompany($user, $this->acme))
        ->getJson('/_test/tenant')
        ->assertOk()
        ->assertJsonPath('tenant', $this->acme->id);
});

it('ignores a company_id supplied in the query string', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession(actingInCompany($user, $this->acme))
        ->getJson('/_test/tenant?company_id='.$this->globex->id)
        ->assertOk()
        ->assertJsonPath('tenant', $this->acme->id);
});

it('ignores a company_id supplied in a header', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession(actingInCompany($user, $this->acme))
        ->getJson('/_test/tenant', ['X-Company-Id' => (string) $this->globex->id])
        ->assertOk()
        ->assertJsonPath('tenant', $this->acme->id);
});

it('honours a second company the user is also an active member of', function (): void {
    $user = memberOf($this->acme);
    memberOf($this->globex, user: $user);

    $this->actingAs($user)
        ->withSession(actingInCompany($user, $this->globex))
        ->getJson('/_test/tenant')
        ->assertOk()
        ->assertJsonPath('tenant', $this->globex->id);
});

it('never exposes another tenant customers', function (): void {
    $user = memberOf($this->acme);

    app(TenantContext::class)->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));
    app(TenantContext::class)->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    $this->actingAs($user)
        ->withSession(actingInCompany($user, $this->acme))
        ->getJson('/_test/tenant')
        ->assertOk()
        ->assertJsonPath('customers', ['Cliente Acme']);
});

// -------------------------------------------------- no silent fallback -----

it('refuses a forged company planted in the session instead of falling back', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->globex->id])
        ->getJson('/_test/tenant')
        ->assertStatus(409)
        ->assertJsonPath('code', ActiveCompanyUnavailable::ACTIVE_MEMBERSHIP_INVALID);
});

it('refuses when the selected company membership was suspended', function (): void {
    $user = memberOf($this->acme);
    memberOf($this->globex, status: MembershipStatus::Suspended, user: $user);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->globex->id])
        ->getJson('/_test/tenant')
        ->assertStatus(409)
        ->assertJsonPath('code', ActiveCompanyUnavailable::ACTIVE_MEMBERSHIP_INVALID);
});

/**
 * The dangerous case: a write aimed at company B, whose membership is no longer
 * valid, must never execute inside company A.
 */
it('never executes a mutating request inside a different tenant', function (): void {
    $user = memberOf($this->acme);
    memberOf($this->globex, status: MembershipStatus::Suspended, user: $user);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->globex->id])
        ->postJson('/_test/customers')
        ->assertStatus(409)
        ->assertJsonPath('code', ActiveCompanyUnavailable::ACTIVE_MEMBERSHIP_INVALID);

    // Nothing was written anywhere, least of all in Acme.
    expect(rawCustomerCount())->toBe(0);
});

it('requires an explicit selection when the session holds none', function (): void {
    $this->actingAs(memberOf($this->acme))
        ->getJson('/_test/tenant')
        ->assertStatus(409)
        ->assertJsonPath('code', ActiveCompanyUnavailable::ACTIVE_COMPANY_REQUIRED);
});

it('clears the stale selection from the session', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->globex->id])
        ->getJson('/_test/tenant')
        ->assertStatus(409);

    $this->assertNull(session(ResolveTenantContext::SESSION_KEY));
});

// ------------------------------------------------ no active membership -----

it('returns 403 rather than 500 for a user with no active membership', function (): void {
    $user = memberOf($this->acme, status: MembershipStatus::Suspended);

    $this->actingAs($user)
        ->getJson('/_test/tenant')
        ->assertStatus(403)
        ->assertJsonPath('code', ActiveCompanyUnavailable::NO_ACTIVE_MEMBERSHIP);
});

it('returns 403 for a user with no memberships at all', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/_test/tenant')
        ->assertStatus(403)
        ->assertJsonPath('code', ActiveCompanyUnavailable::NO_ACTIVE_MEMBERSHIP);
});

it('returns 403 rather than 500 on a mutating request with no membership', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/_test/customers')
        ->assertStatus(403)
        ->assertJsonPath('code', ActiveCompanyUnavailable::NO_ACTIVE_MEMBERSHIP);

    expect(rawCustomerCount())->toBe(0);
});

// ------------------------------------------------------------- guests ------

it('leaves guests untouched', function (): void {
    Route::middleware('web')->get('/_test/guest', fn (): array => [
        'bound' => app(TenantContext::class)->has(),
    ]);

    $this->getJson('/_test/guest')->assertOk()->assertJsonPath('bound', false);
});

it('lets an exempt selection route through with no tenant bound', function (): void {
    Route::middleware('web')->get('/_test/select', fn (): array => [
        'bound' => app(TenantContext::class)->has(),
    ])->name('tenancy.select');

    $this->actingAs(memberOf($this->acme))
        ->getJson('/_test/select')
        ->assertOk()
        ->assertJsonPath('bound', false);
});
