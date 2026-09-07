<?php

declare(strict_types=1);
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
    $this->actor = memberOf($this->a);
    $this->own = app(TenantContext::class)->runAs($this->a, fn () => Customer::factory()->create(['name' => 'Acme buyer']));
    $this->foreign = app(TenantContext::class)->runAs($this->b, fn () => Customer::factory()->create(['name' => 'Foreign buyer']));
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
});
it('lists paginated own clients and searches all contact fields', function (): void {
    foreach (['name', 'legal_name', 'tax_id', 'email', 'phone', 'whatsapp'] as $field) {
        app(TenantContext::class)->runAs($this->a, fn () => $this->own->update([
            'name' => 'Acme buyer', 'legal_name' => null, 'tax_id' => null,
            'email' => null, 'phone' => null, 'whatsapp' => null,
            $field => 'needle@example.test',
        ]));
        $this->get('/customers?q=needle')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Customers/Index')->has('customers.data', 1)->where('customers.data.0.id', $this->own->id));
    }
    $this->get('/customers?q=Foreign')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
});

it('does not mass assign unknown fields or permit status ownership injection', function (): void {
    $this->post('/customers', ['name' => 'Allowed', 'role' => 'owner', 'user_id' => 999])->assertRedirect();
    $this->patchJson('/customers/'.$this->own->id.'/status', [
        'status' => 'inactive', 'company_id' => $this->b->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    $this->putJson('/customers/'.$this->own->id, ['name' => 'Attack', 'status' => 'inactive'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('customers', ['id' => $this->own->id, 'company_id' => $this->a->id, 'status' => 'active']);
});

it('treats SQL wildcard characters in search as literal customer text', function (): void {
    $this->get('/customers?q=%25')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
});
it('creates normalizes and permits shared emails', function (): void {
    foreach ([1, 2] as $i) {
        $this->post('/customers', ['name' => ' Buyer '.$i.' ', 'email' => 'SHARED@EXAMPLE.TEST', 'phone' => '+591 123', 'notes' => '<script>alert(1)</script>'])
            ->assertRedirect();
    }
    $this->assertDatabaseCount('customers', 4);
    $this->assertDatabaseHas('customers', ['company_id' => $this->a->id, 'name' => 'Buyer 1', 'email' => 'shared@example.test', 'status' => 'active']);
});
it('rejects ownership injection and invalid data', function (): void {
    $this->postJson('/customers', ['name' => 'Attack', 'company_id' => $this->b->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    $this->postJson('/customers', ['name' => '', 'email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    $this->putJson('/customers/'.$this->own->id, ['name' => 'Attack', 'company_id' => $this->b->id])->assertUnprocessable();
    $this->patchJson('/customers/'.$this->own->id.'/status', ['status' => 'deleted'])->assertUnprocessable();
});
it('updates details and retains ownership while status transitions remain accessible', function (): void {
    $this->put('/customers/'.$this->own->id, ['name' => 'Renamed', 'commercial_condition' => '30 días'])->assertRedirect();
    foreach (['inactive', 'active'] as $status) {
        $this->patch('/customers/'.$this->own->id.'/status', ['status' => $status])->assertRedirect();
        $this->get('/customers/'.$this->own->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('customer.status', $status));
    }
    $this->assertDatabaseHas('customers', ['id' => $this->own->id, 'company_id' => $this->a->id, 'name' => 'Renamed']);
});
it('rejects foreign show edit update and status through real route bindings', function (): void {
    $url = '/customers/'.$this->foreign->id;
    $this->get($url)->assertNotFound();
    $this->get($url.'/edit')->assertNotFound();
    $this->put($url, ['name' => 'Attack'])->assertNotFound();
    $this->patch($url.'/status', ['status' => 'inactive'])->assertNotFound();
    $this->assertDatabaseHas('customers', ['id' => $this->foreign->id, 'name' => 'Foreign buyer', 'status' => 'active']);
});
it('filters and paginates with query parameters', function (): void {
    app(TenantContext::class)->runAs($this->a, fn () => Customer::factory()->count(24)->create(['name' => 'Buyer']));
    $this->get('/customers?q=Buyer&status=active')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 20)->where('customers.total', 25)->where('filters.status', 'active'));
    $this->get('/customers?q=Buyer&status=active&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 5));
});
