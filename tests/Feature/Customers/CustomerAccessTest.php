<?php

declare(strict_types=1);
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use Inertia\Testing\AssertableInertia as Assert;

it('logs in selects an authorized company and logs out', function (): void {
    $company = Company::factory()->create();
    $user = memberOf($company);
    $this->get('/customers')->assertRedirect('/login');
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/companies/select');
    $this->assertAuthenticatedAs($user);
    $this->get('/companies/select')->assertOk()->assertInertia(fn (Assert $page) => $page->has('companies', 1));
    $this->post('/companies/select', ['company_id' => $company->id])->assertRedirect('/customers')->assertSessionHas(ResolveTenantContext::SESSION_KEY, $company->id);
    $this->get('/customers')->assertOk();
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
it('rejects foreign and suspended company selection', function (): void {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $user = memberOf($a);
    $this->actingAs($user)->post('/companies/select', ['company_id' => $b->id])->assertForbidden();
    $membership = $user->membershipFor($a);
    $membership->status = MembershipStatus::Suspended;
    $membership->save();
    $this->post('/companies/select', ['company_id' => $a->id])->assertForbidden();
    $this->withSession([ResolveTenantContext::SESSION_KEY => $a->id])->getJson('/customers')->assertForbidden();
});
it('rate limits repeated invalid logins', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/login', ['email' => 'bad@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/login', ['email' => 'bad@example.test', 'password' => 'wrong'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Demasiados intentos. Espera un minuto y vuelve a intentar.');
});
