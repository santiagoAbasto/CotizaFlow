<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * The invariant from docs/architecture/TENANCY.md: a user of one company can
 * never read, modify, search or enumerate another company's data.
 */
beforeEach(function (): void {
    $this->acme = Company::factory()->create(['name' => 'Acme']);
    $this->globex = Company::factory()->create(['name' => 'Globex']);
    $this->context = app(TenantContext::class);
});

it('stamps the active tenant on create', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme',
        'email' => 'cliente@acme.test',
    ]));

    expect($customer->company_id)->toBe($this->acme->id);
});

it('rejects a client-supplied company_id on create instead of silently dropping it', function (): void {
    $create = fn () => Customer::create([
        'name' => 'Cliente Acme',
        'email' => 'cliente@acme.test',
        'company_id' => $this->globex->id,
    ]);

    // company_id is not fillable, and silent discarding is disabled, so the
    // attempt is loud rather than quietly ignored.
    expect(fn () => $this->context->runAs($this->acme, $create))
        ->toThrow(MassAssignmentException::class);

    expect(rawCustomerCount())->toBe(0);
});

it('hides another tenant rows from reads', function (): void {
    $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    $visible = $this->context->runAs($this->acme, fn () => Customer::all());

    expect($visible)->toHaveCount(0);
});

it('cannot read another tenant row by its exact id', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    $found = $this->context->runAs($this->acme, fn () => Customer::find($foreign->id));

    expect($found)->toBeNull();
});

it('cannot update another tenant row', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Original', 'email' => 'c@globex.test',
    ]));

    $affected = $this->context->runAs(
        $this->acme,
        fn () => Customer::whereKey($foreign->id)->update(['name' => 'Hijacked']),
    );

    expect($affected)->toBe(0)
        ->and(rawCustomer($foreign->id)->name)->toBe('Original');
});

it('cannot delete another tenant row', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    $deleted = $this->context->runAs(
        $this->acme,
        fn () => Customer::whereKey($foreign->id)->delete(),
    );

    expect($deleted)->toBe(0)
        ->and(rawCustomer($foreign->id))->not->toBeNull();
});

it('cannot find another tenant row through a search', function (): void {
    $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Secreto Globex', 'email' => 'c@globex.test',
    ]));

    $results = $this->context->runAs(
        $this->acme,
        fn () => Customer::where('name', 'like', '%Secreto%')->get(),
    );

    expect($results)->toHaveCount(0);
});

it('fails closed when no tenant context is bound', function (): void {
    expect(fn () => Customer::all())->toThrow(TenantContextMissing::class);
});
