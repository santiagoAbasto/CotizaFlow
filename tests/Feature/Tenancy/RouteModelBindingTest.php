<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

/**
 * Route model binding runs inside SubstituteBindings. If the tenant context is
 * bound after that middleware, binding queries run with no boundary at all —
 * so ordering is a security control, not a detail.
 */
beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();

    Route::middleware('web')->get('/_test/customers/{customer}', fn (Customer $customer): array => [
        'id' => $customer->id,
        'name' => $customer->name,
        'company_id' => $customer->company_id,
    ]);
});

it('binds a customer belonging to the active tenant', function (): void {
    $own = app(TenantContext::class)->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->acme->id])
        ->getJson('/_test/customers/'.$own->id)
        ->assertOk()
        ->assertJsonPath('name', 'Cliente Acme')
        ->assertJsonPath('company_id', $this->acme->id);
});

it('returns 404 and never the model when binding a foreign tenant customer', function (): void {
    $foreign = app(TenantContext::class)->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    $user = memberOf($this->acme);

    $response = $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->acme->id])
        ->getJson('/_test/customers/'.$foreign->id);

    // 404, not 403: a foreign id must be indistinguishable from a missing one.
    $response->assertNotFound();

    expect($response->getContent())->not->toContain('Cliente Globex');
});

it('returns 404 for a customer id that does not exist', function (): void {
    $user = memberOf($this->acme);

    $this->actingAs($user)
        ->withSession([ResolveTenantContext::SESSION_KEY => $this->acme->id])
        ->getJson('/_test/customers/999999')
        ->assertNotFound();
});

it('does not leak a foreign customer to a user with no active membership', function (): void {
    $foreign = app(TenantContext::class)->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    $user = memberOf($this->acme, status: MembershipStatus::Suspended);

    $response = $this->actingAs($user)->getJson('/_test/customers/'.$foreign->id);

    expect($response->getContent())->not->toContain('Cliente Globex')
        ->and($response->status())->not->toBe(200);
});

it('returns 401 instead of 500 when a guest reaches a tenant binding', function (): void {
    $customer = app(TenantContext::class)->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'guest@acme.test',
    ]));

    $response = $this->getJson('/_test/customers/'.$customer->id);

    $response->assertUnauthorized();
    expect($response->getContent())
        ->not->toContain('TenantContextMissing')
        ->not->toContain('No tenant context is bound');
});
