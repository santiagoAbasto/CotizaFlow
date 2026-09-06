<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);
    $this->customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Acme customer',
        'email' => 'shared@test.invalid',
    ]));
});

it('fails closed for every public write surface when context is missing', function (): void {
    $operations = [
        'query read' => fn () => Customer::query()->get(),
        'update' => fn () => Customer::query()->update(['name' => 'changed']),
        'insert' => fn () => Customer::query()->insert(['name' => 'insert']),
        'upsert' => fn () => Customer::query()->upsert([['name' => 'upsert', 'email' => 'u@test.invalid']], ['email']),
        'updateOrInsert' => fn () => Customer::query()->updateOrInsert(['email' => 'uoi@test.invalid'], ['name' => 'uoi']),
        'increment' => fn () => Customer::query()->increment('id'),
        'decrement' => fn () => Customer::query()->decrement('id'),
        'incrementEach' => fn () => Customer::query()->incrementEach(['id' => 1]),
        'decrementEach' => fn () => Customer::query()->decrementEach(['id' => 1]),
        'toBase update' => fn () => Customer::query()->toBase()->update(['name' => 'changed']),
        'getQuery update' => fn () => Customer::query()->getQuery()->update(['name' => 'changed']),
        'delete' => fn () => Customer::query()->getQuery()->delete(),
    ];

    foreach ($operations as $operation) {
        expect($operation)->toThrow(TenantContextMissing::class);
    }
});

it('allows ordinary updates only inside the active tenant', function (): void {
    $affected = $this->context->runAs(
        $this->acme,
        fn () => Customer::whereKey($this->customer->id)->update(['name' => 'Renamed']),
    );

    expect($affected)->toBe(1)
        ->and($this->context->runAs($this->acme, fn () => $this->customer->fresh()->name))->toBe('Renamed');
});

it('rejects tenant mutation through update and arithmetic methods for every casing', function (): void {
    foreach (['company_id', 'COMPANY_ID', 'Company_Id'] as $column) {
        $operations = [
            fn () => Customer::whereKey($this->customer->id)->update([$column => $this->globex->id]),
            fn () => Customer::whereKey($this->customer->id)->increment($column),
            fn () => Customer::whereKey($this->customer->id)->decrement($column),
            fn () => Customer::whereKey($this->customer->id)->incrementEach([$column => 1]),
            fn () => Customer::whereKey($this->customer->id)->decrementEach([$column => 1]),
        ];

        foreach ($operations as $operation) {
            expect(fn () => $this->context->runAs($this->acme, $operation))
                ->toThrow(TenantColumnImmutable::class);
        }
    }

    expect(rawCustomer($this->customer->id)->company_id)->toBe($this->acme->id);
});

it('closes the exact toBase and getQuery ownership bypasses', function (): void {
    foreach ([
        fn () => Customer::whereKey($this->customer->id)->toBase()->update(['company_id' => $this->globex->id]),
        fn () => Customer::whereKey($this->customer->id)->getQuery()->update(['company_id' => $this->globex->id]),
    ] as $operation) {
        expect(fn () => $this->context->runAs($this->acme, $operation))
            ->toThrow(TenantColumnImmutable::class);
    }

    expect(rawCustomer($this->customer->id)->company_id)->toBe($this->acme->id);
});

it('rejects foreign ownership in insert and upsert payloads', function (): void {
    $foreignRows = [
        fn () => Customer::insert(['name' => 'insert', 'company_id' => $this->globex->id]),
        fn () => Customer::upsert([
            ['name' => 'upsert', 'email' => 'upsert@test.invalid', 'company_id' => $this->globex->id],
        ], ['email'], ['name']),
    ];

    foreach ($foreignRows as $operation) {
        expect(fn () => $this->context->runAs($this->acme, $operation))
            ->toThrow(TenantWriteRejected::class);
    }

    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::updateOrInsert(
        ['email' => 'uoi@test.invalid'],
        ['name' => 'uoi', 'company_id' => $this->globex->id],
    )))->toThrow(TenantColumnImmutable::class);
});

it('does not let forwarded updateOrInsert modify a foreign existing record', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Globex original',
        'email' => 'foreign@test.invalid',
    ]));

    $this->context->runAs($this->acme, fn () => Customer::query()->updateOrInsert(
        ['email' => 'foreign@test.invalid'],
        ['name' => 'Acme version'],
    ));

    expect(rawCustomer($foreign->id)->name)->toBe('Globex original')
        ->and(DB::table('customers')->where('company_id', $this->acme->id)
            ->where('email', 'foreign@test.invalid')->value('name'))->toBe('Acme version');
});

it('does not let base-builder writes affect a foreign existing record', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Globex original',
        'email' => 'globex@test.invalid',
    ]));

    $results = $this->context->runAs($this->acme, fn () => [
        Customer::whereKey($foreign->id)->getQuery()->update(['name' => 'Hijacked']),
        Customer::whereKey($foreign->id)->getQuery()->increment('id', 1000),
        Customer::whereKey($foreign->id)->getQuery()->decrement('id', 1),
    ]);

    expect($results)->toBe([0, 0, 0])
        ->and(rawCustomer($foreign->id)->name)->toBe('Globex original');
});

it('keeps getQuery reads scoped and fail-closed', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Globex hidden',
        'email' => 'hidden@test.invalid',
    ]));

    $visible = $this->context->runAs(
        $this->acme,
        fn () => Customer::query()->getQuery()->where('id', $foreign->id)->get(),
    );

    expect($visible)->toHaveCount(0);
    expect(fn () => Customer::query()->getQuery()->get())->toThrow(TenantContextMissing::class);
});

it('rejects relationship creation for a company other than the active tenant', function (): void {
    expect(fn () => $this->context->runAs(
        $this->acme,
        fn () => $this->globex->customers()->create(['name' => 'Wrong relation']),
    ))->toThrow(TenantWriteRejected::class);
});

it('allows relationship createMany for the active company and stamps every row', function (): void {
    $created = $this->context->runAs($this->acme, fn () => $this->acme->customers()->createMany([
        ['name' => 'One'],
        ['name' => 'Two'],
    ]));

    expect($created)->toHaveCount(2)
        ->and($created->pluck('company_id')->unique()->all())->toBe([$this->acme->id]);
});

it('keeps firstOrCreate and updateOrCreate scoped to the active tenant', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Foreign',
        'email' => 'helpers@test.invalid',
    ]));

    $local = $this->context->runAs($this->acme, function (): Customer {
        Customer::firstOrCreate(['email' => 'helpers@test.invalid'], ['name' => 'Local']);

        return Customer::updateOrCreate(['email' => 'helpers@test.invalid'], ['name' => 'Local updated']);
    });

    expect($local->company_id)->toBe($this->acme->id)
        ->and($local->name)->toBe('Local updated')
        ->and(rawCustomer($foreign->id)->name)->toBe('Foreign');
});

it('rejects insertUsing and truncate on a tenant model builder', function (): void {
    expect(fn () => $this->context->runAs(
        $this->acme,
        fn () => Customer::query()->getQuery()->insertUsing(['name'], Company::query()->select('name')),
    ))->toThrow(TenantWriteRejected::class);

    expect(fn () => $this->context->runAs(
        $this->acme,
        fn () => Customer::query()->getQuery()->truncate(),
    ))->toThrow(TenantWriteRejected::class);
});

it('rejects replacing the model table before a base-builder write', function (): void {
    expect(fn () => $this->context->runAs(
        $this->acme,
        fn () => Customer::query()->getQuery()->from('memberships')->update(['status' => 'suspended']),
    ))->toThrow(TenantWriteRejected::class);
});
