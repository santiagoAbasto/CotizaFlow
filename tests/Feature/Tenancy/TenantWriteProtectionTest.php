<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;

/**
 * Regression cover for the three write bypasses reproduced against the previous
 * implementation: uppercase update, raw insert and upsert insert values.
 */
beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);

    $this->customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
});

function rowsIn(Company $company): int
{
    return rawCustomerCount($company->id);
}

// ---------------------------------------------------------------- update ----

it('blocks a bulk update that moves a record to another tenant', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::whereKey($this->customer->id)
        ->update(['company_id' => $this->globex->id])))
        ->toThrow(TenantColumnImmutable::class);

    expect(rawCustomer($this->customer->id)->company_id)->toBe($this->acme->id);
});

it('blocks an uppercase column name in a bulk update', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::whereKey($this->customer->id)
        ->update(['COMPANY_ID' => $this->globex->id])))
        ->toThrow(TenantColumnImmutable::class);

    expect(rawCustomer($this->customer->id)->company_id)
        ->toBe($this->acme->id);
});

it('blocks a qualified and uppercased column name in a bulk update', function (): void {
    foreach (['customers.company_id', 'CUSTOMERS.COMPANY_ID', '`customers`.`company_id`'] as $column) {
        expect(fn () => $this->context->runAs($this->acme, fn () => Customer::whereKey($this->customer->id)
            ->update([$column => $this->globex->id])))
            ->toThrow(TenantColumnImmutable::class);
    }

    expect(rawCustomer($this->customer->id)->company_id)
        ->toBe($this->acme->id);
});

it('blocks a model-level tenant reassignment', function (): void {
    expect(fn () => $this->context->runAs($this->acme, function (): void {
        $this->customer->company_id = $this->globex->id;
        $this->customer->save();
    }))->toThrow(TenantColumnImmutable::class);

    expect(rawCustomer($this->customer->id)->company_id)
        ->toBe($this->acme->id);
});

it('still allows ordinary bulk updates that do not touch company_id', function (): void {
    $affected = $this->context->runAs($this->acme, fn () => Customer::whereKey($this->customer->id)
        ->update(['name' => 'Renombrado']));

    expect($affected)->toBe(1)->and(rawCustomer($this->customer->id)->name)->toBe('Renombrado');
});

// ---------------------------------------------------------------- insert ----

it('rejects a raw insert carrying a foreign tenant', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'Raw', 'email' => 'raw@b.test', 'company_id' => $this->globex->id],
    ])))->toThrow(TenantWriteRejected::class);

    expect(rowsIn($this->globex))->toBe(0);
});

it('rejects a raw insert with an uppercase foreign tenant column', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'Raw', 'email' => 'raw@b.test', 'COMPANY_ID' => $this->globex->id],
    ])))->toThrow(TenantWriteRejected::class);

    expect(rowsIn($this->globex))->toBe(0);
});

it('stamps the active tenant on a raw insert that omits company_id', function (): void {
    $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'Raw', 'email' => 'raw@a.test'],
    ]));

    expect(rowsIn($this->acme))->toBe(2)->and(rowsIn($this->globex))->toBe(0);
});

it('accepts a raw insert that names the active tenant explicitly', function (): void {
    $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'Raw', 'email' => 'raw@a.test', 'company_id' => $this->acme->id],
    ]));

    expect(rowsIn($this->acme))->toBe(2);
});

it('stamps every row of a multi-row insert', function (): void {
    $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'One', 'email' => 'one@a.test'],
        ['name' => 'Two', 'email' => 'two@a.test'],
    ]));

    expect(rowsIn($this->acme))->toBe(3);
});

it('writes nothing when a mixed-tenant batch contains one foreign row', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::insert([
        ['name' => 'Good', 'email' => 'good@a.test'],
        ['name' => 'Bad', 'email' => 'bad@b.test', 'company_id' => $this->globex->id],
    ])))->toThrow(TenantWriteRejected::class);

    // Fail-before-write: the valid row must not have landed either.
    expect(rowsIn($this->acme))->toBe(1)->and(rowsIn($this->globex))->toBe(0);
});

// ---------------------------------------------------------------- upsert ----

it('rejects an upsert whose insert values carry a foreign tenant', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::upsert([
        ['name' => 'Ups', 'email' => 'ups@b.test', 'company_id' => $this->globex->id],
    ], ['email'], ['name'])))->toThrow(TenantWriteRejected::class);

    expect(rowsIn($this->globex))->toBe(0);
});

it('rejects an upsert that lists company_id among the updated columns', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::upsert([
        ['name' => 'Ups', 'email' => 'ups@a.test'],
    ], ['email'], ['name', 'COMPANY_ID'])))->toThrow(TenantColumnImmutable::class);

    expect(rowsIn($this->globex))->toBe(0);
});

it('stamps the active tenant on an upsert that omits company_id', function (): void {
    $this->context->runAs($this->acme, fn () => Customer::upsert([
        ['name' => 'Ups', 'email' => 'ups@a.test'],
    ], ['email'], ['name']));

    expect(rowsIn($this->acme))->toBe(2)->and(rowsIn($this->globex))->toBe(0);
});

it('writes nothing when a mixed-tenant upsert batch contains one foreign row', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::upsert([
        ['name' => 'Good', 'email' => 'good@a.test'],
        ['name' => 'Bad', 'email' => 'bad@b.test', 'company_id' => $this->globex->id],
    ], ['email'], ['name'])))->toThrow(TenantWriteRejected::class);

    expect(rowsIn($this->acme))->toBe(1)->and(rowsIn($this->globex))->toBe(0);
});

it('rejects insertGetId carrying a foreign tenant', function (): void {
    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::insertGetId([
        'name' => 'Raw', 'email' => 'raw@b.test', 'company_id' => $this->globex->id,
    ])))->toThrow(TenantWriteRejected::class);

    expect(rowsIn($this->globex))->toBe(0);
});
