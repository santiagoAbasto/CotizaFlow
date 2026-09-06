<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\TenantContextChanged;
use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;

beforeEach(function (): void {
    $this->acme = Company::factory()->create(['name' => 'Acme']);
    $this->globex = Company::factory()->create(['name' => 'Globex']);
    $this->context = app(TenantContext::class);

    $this->context->runAs($this->acme, function (): void {
        Customer::factory()->create(['name' => 'Alpha', 'email' => 'alpha@test.invalid']);
        Customer::factory()->create(['name' => 'Beta', 'email' => 'beta@test.invalid']);
    });

    $this->context->runAs($this->globex, fn () => Customer::factory()->create([
        'name' => 'Foreign',
        'email' => 'foreign@test.invalid',
    ]));
});

it('keeps one tenant predicate and stable bindings when a builder is reused', function (): void {
    $this->context->runAs($this->acme, function (): void {
        $query = Customer::query();
        $base = $query->getQuery();
        $initialSql = $base->toSql();
        $initialBindings = $base->getBindings();

        expect($query->count())->toBe(2)
            ->and($query->count())->toBe(2)
            ->and($query->count())->toBe(2)
            ->and(substr_count(mb_strtolower($base->toSql()), 'company_id'))->toBe(1)
            ->and($base->toSql())->toBe($initialSql)
            ->and($base->getBindings())->toBe($initialBindings)
            ->and($initialBindings)->toBe([$this->acme->id]);
    });
});

it('fails closed when a builder is reused under another tenant', function (): void {
    $query = $this->context->runAs($this->acme, fn () => Customer::query());

    expect($this->context->runAs($this->acme, fn () => $query->count()))->toBe(2);

    expect(fn () => $this->context->runAs($this->globex, fn () => $query->get()))
        ->toThrow(TenantContextChanged::class);
});

it('supports union without duplicated predicates or misaligned bindings', function (): void {
    $this->context->runAs($this->acme, function (): void {
        $query = Customer::query()->select('name')->where('name', 'Alpha');
        $union = Customer::query()->select('name')->where('name', 'Beta');
        $combined = $query->union($union);

        expect($combined->pluck('name')->sort()->values()->all())->toBe(['Alpha', 'Beta'])
            ->and(substr_count(mb_strtolower($combined->toSql()), 'company_id'))->toBe(2)
            ->and($combined->getBindings())->toBe([
                $this->acme->id,
                'Alpha',
                $this->acme->id,
                'Beta',
            ]);
    });
});

it('supports union all while preserving tenant isolation', function (): void {
    $this->context->runAs($this->acme, function (): void {
        $combined = Customer::query()->select('name')->where('name', 'Alpha')
            ->unionAll(Customer::query()->select('name')->where('name', 'Beta'));

        expect($combined->pluck('name')->sort()->values()->all())->toBe(['Alpha', 'Beta'])
            ->and($combined->pluck('name'))->not->toContain('Foreign');
    });
});

it('rejects a union containing a builder bound to another tenant', function (): void {
    $local = $this->context->runAs($this->acme, fn () => Customer::query()->where('name', 'Alpha'));
    $foreign = $this->context->runAs($this->globex, fn () => Customer::query()->where('name', 'Foreign'));

    expect(fn () => $this->context->runAs($this->acme, fn () => $local->union($foreign)->get()))
        ->toThrow(TenantContextChanged::class);
});

it('resolves lowercase and uppercase root aliases', function (string $from): void {
    $this->context->runAs($this->acme, function () use ($from): void {
        $query = Customer::query()->from($from);

        expect($query->count())->toBe(2)
            ->and($query->orderBy('c.name')->pluck('c.name')->all())->toBe(['Alpha', 'Beta'])
            ->and(substr_count(
                str_replace('`', '', mb_strtolower($query->toSql())),
                'c.company_id',
            ))->toBe(1);
    });
})->with(['customers as c', 'customers AS c']);

it('supports an aliased root with where and aggregate operations', function (): void {
    $this->context->runAs($this->acme, function (): void {
        $query = Customer::query()->from('customers as c')->where('c.name', 'Alpha');

        expect($query->count())->toBe(1)
            ->and($query->max('c.id'))->toBeInt();
    });
});

it('keeps the aliased root isolated in a representative join', function (): void {
    $this->context->runAs($this->acme, function (): void {
        $rows = Customer::query()
            ->from('customers as c')
            ->join('companies as co', 'co.id', '=', 'c.company_id')
            ->orderBy('c.name')
            ->pluck('c.name');

        expect($rows->all())->toBe(['Alpha', 'Beta'])
            ->and($rows)->not->toContain('Foreign');
    });
});

it('preserves isolation without global scopes and through base builders', function (): void {
    $this->context->runAs($this->acme, function (): void {
        expect(Customer::withoutGlobalScopes()->pluck('name'))->not->toContain('Foreign')
            ->and(Customer::withoutGlobalScope(TenantScope::class)->pluck('name'))->not->toContain('Foreign')
            ->and((new Customer)->newQueryWithoutScopes()->pluck('name'))->not->toContain('Foreign')
            ->and(Customer::query()->toBase()->pluck('name'))->not->toContain('Foreign')
            ->and(Customer::query()->getQuery()->pluck('name'))->not->toContain('Foreign');
    });
});

it('keeps disjunctive predicates inside the tenant boundary', function (): void {
    $this->context->runAs($this->acme, function (): void {
        expect(Customer::query()->where('name', 'Alpha')->orWhere('name', 'Foreign')->pluck('name')->all())
            ->toBe(['Alpha']);

        $base = Customer::query()->getQuery()->where('name', 'Alpha')->orWhere('name', 'Foreign');
        $sql = $base->toSql();
        $bindings = $base->getBindings();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            expect($base->pluck('name')->all())->toBe(['Alpha'])
                ->and($base->toSql())->toBe($sql)
                ->and($base->getBindings())->toBe($bindings);
        }

        expect((clone $base)->update(['email' => 'updated@test.invalid']))->toBe(1)
            ->and((clone $base)->delete())->toBe(1);
    });

    expect($this->context->runAs($this->globex, fn () => Customer::where('name', 'Foreign')->value('email')))
        ->toBe('foreign@test.invalid');
});

it('preserves the boundary through clones and tenant subqueries', function (): void {
    $query = $this->context->runAs($this->acme, function () {
        $query = Customer::query()->whereIn('id', Customer::query()->select('id'));

        expect((clone $query)->count())->toBe(2)
            ->and($query->count())->toBe(2);

        return clone $query;
    });

    expect(fn () => $this->context->runAs($this->globex, fn () => $query->get()))
        ->toThrow(TenantContextChanged::class);
});

it('fails closed without tenant context before any aliased query executes', function (): void {
    expect(fn () => Customer::query()->from('customers as c')->count())
        ->toThrow(TenantContextMissing::class);
});
