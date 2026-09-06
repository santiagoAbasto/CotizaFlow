<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantContextChanged;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The write boundary underneath a tenant-owned Eloquent model.
 *
 * Eloquent exposes its base builder through toBase(), getQuery() and forwarded
 * calls. Protecting only TenantBuilder therefore leaves legitimate framework
 * APIs able to write without either the global scope or ownership checks. This
 * builder fails closed and adds the active tenant predicate at the final layer
 * where every supported write is issued.
 *
 * Deliberate raw access through DB::table() remains trusted infrastructure code;
 * TenantOwnershipTransfer is the only supported domain API that uses it.
 */
final class TenantQueryBuilder extends Builder
{
    /** @var class-string */
    private string $tenantModel;

    private string $tenantTable;

    private ?int $boundTenantId = null;

    private ?int $tenantWhereIndex = null;

    private ?int $tenantBindingIndex = null;

    /** @param class-string $model */
    public function forTenantModel(string $model, string $table): static
    {
        $this->tenantModel = $model;
        $this->tenantTable = $table;

        return $this;
    }

    /** Set and safely scope the root table at builder construction time. */
    public function from($table, $as = null)
    {
        parent::from($table, $as);
        $this->enforceTenantBoundary();

        return $this;
    }

    /**
     * Idempotently installs the tenant predicate and binds this builder to the
     * current tenant. TenantScope delegates here so aliases and base/Eloquent
     * queries share one source of truth.
     */
    public function enforceTenantBoundary(): void
    {
        $tenantId = $this->tenantId();
        $column = TenantTableReference::resolve($this->from, $this->table(), $this->model())
            ->tenantColumn();

        if ($this->boundTenantId !== null && $this->boundTenantId !== $tenantId) {
            throw TenantContextChanged::forModel($this->model());
        }

        $this->boundTenantId ??= $tenantId;

        if ($this->tenantWhereIndex === null) {
            $this->tenantWhereIndex = count($this->wheres);
            $this->tenantBindingIndex = count($this->bindings['where']);
            parent::where($column, '=', $tenantId);

            return;
        }

        $this->assertTenantPredicateIntact();
        $this->wheres[$this->tenantWhereIndex]['column'] = $column;

        // SQL AND has higher precedence than OR. Keep all caller conditions
        // inside one group when a top-level disjunction would escape the root
        // tenant predicate. Grouping changes no binding order or values.
        foreach ($this->wheres as $where) {
            if (str_contains($where['boolean'] ?? '', 'or')) {
                $conditions = $this->wheres;
                $tenant = array_splice($conditions, $this->tenantWhereIndex, 1)[0];
                $nested = new Builder($this->connection, $this->grammar, $this->processor);
                $nested->wheres = $conditions;
                $this->wheres = [$tenant, ['type' => 'Nested', 'query' => $nested, 'boolean' => 'and']];
                $this->tenantWhereIndex = 0;

                break;
            }
        }
    }

    /** Verify context stability without mutating SQL or bindings. */
    public function applyBeforeQueryCallbacks(): void
    {
        parent::applyBeforeQueryCallbacks();

        $this->enforceTenantBoundary();

        foreach ($this->unions ?? [] as $union) {
            $query = $union['query'] ?? null;

            if ($query instanceof EloquentBuilder) {
                $query = $query->getQuery();
            }

            if ($query instanceof self) {
                $query->enforceTenantBoundary();
            }
        }

    }

    public function insert(array $values): bool
    {
        return parent::insert($this->stampRows($values));
    }

    public function insertOrIgnore(array $values): int
    {
        return parent::insertOrIgnore($this->stampRows($values));
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null): Collection
    {
        return parent::insertOrIgnoreReturning($this->stampRows($values), $returning, $uniqueBy);
    }

    public function insertGetId(array $values, $sequence = null): int
    {
        return parent::insertGetId($this->stampRow($values, $this->tenantId(), 0), $sequence);
    }

    public function insertUsing(array $columns, $query): int
    {
        throw TenantWriteRejected::unsupported($this->model(), 'insertUsing');
    }

    public function insertOrIgnoreUsing(array $columns, $query): int
    {
        throw TenantWriteRejected::unsupported($this->model(), 'insertOrIgnoreUsing');
    }

    public function update(array $values): int
    {
        $this->rejectTenantColumns(array_keys($values));
        $this->scopeWriteToTenant();

        return parent::update($values);
    }

    public function updateFrom(array $values): int
    {
        $this->rejectTenantColumns(array_keys($values));
        $this->scopeWriteToTenant();

        return parent::updateFrom($values);
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $tenantId = $this->tenantId();
        $attributes = $this->stampRow($attributes, $tenantId, 0);
        $this->scopeWriteToTenant($tenantId);
        $exists = $this->where($attributes)->exists();

        if ($values instanceof \Closure) {
            $values = $values($exists);
        }

        $this->rejectTenantColumns(array_keys($values));

        if (! $exists) {
            return $this->insert(array_merge($attributes, $values));
        }

        if ($values === []) {
            return true;
        }

        return (bool) $this->limit(1)->update($values);
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null): int
    {
        $values = $this->stampRows($values);

        if ($update === null && $values !== []) {
            $first = array_is_list($values) ? $values[0] : $values;
            $update = array_values(array_filter(
                array_keys($first),
                static fn (string|int $column): bool => ! ColumnName::isTenantColumn((string) $column),
            ));
        } else {
            foreach ($update ?? [] as $key => $column) {
                $name = is_string($key) ? $key : $column;

                if (is_string($name) && ColumnName::isTenantColumn($name)) {
                    throw TenantColumnImmutable::forModel($this->model());
                }
            }
        }

        return parent::upsert($values, $this->tenantUniqueBy($uniqueBy), $update);
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        $this->rejectTenantColumns([(string) $column, ...array_keys($extra)]);

        return parent::increment($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        $this->rejectTenantColumns([...array_keys($columns), ...array_keys($extra)]);

        return parent::incrementEach($columns, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $this->rejectTenantColumns([(string) $column, ...array_keys($extra)]);

        return parent::decrement($column, $amount, $extra);
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        $this->rejectTenantColumns([...array_keys($columns), ...array_keys($extra)]);

        return parent::decrementEach($columns, $extra);
    }

    public function delete($id = null): int
    {
        $this->scopeWriteToTenant();

        return parent::delete($id);
    }

    public function truncate(): never
    {
        throw TenantWriteRejected::unsupported($this->model(), 'truncate');
    }

    public function newQuery(): static
    {
        return parent::newQuery()->forTenantModel($this->model(), $this->table());
    }

    /** @param list<string|int> $columns */
    private function rejectTenantColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (ColumnName::isTenantColumn((string) $column)) {
                throw TenantColumnImmutable::forModel($this->model());
            }
        }
    }

    private function scopeWriteToTenant(?int $tenantId = null): void
    {
        if ($tenantId !== null && $tenantId !== $this->tenantId()) {
            throw TenantContextChanged::forModel($this->model());
        }

        $this->enforceTenantBoundary();
    }

    private function assertTenantPredicateIntact(): void
    {
        $where = $this->wheres[$this->tenantWhereIndex] ?? null;
        $binding = $this->bindings['where'][$this->tenantBindingIndex] ?? null;

        if (! is_array($where)
            || ($where['type'] ?? null) !== 'Basic'
            || ($where['operator'] ?? null) !== '='
            || ($where['boolean'] ?? null) !== 'and'
            || (int) ($where['value'] ?? 0) !== $this->boundTenantId
            || (int) $binding !== $this->boundTenantId) {
            throw TenantWriteRejected::unsupported($this->model(), 'query with a removed tenant predicate');
        }
    }

    private function tenantId(): int
    {
        return app(TenantContext::class)->id();
    }

    /** @param array<int|string, mixed> $values */
    private function stampRows(array $values): array
    {
        if ($values === []) {
            return $values;
        }

        $tenantId = $this->tenantId();

        if (! array_is_list($values)) {
            return $this->stampRow($values, $tenantId, 0);
        }

        foreach ($values as $index => $row) {
            if (! is_array($row)) {
                throw TenantWriteRejected::unsupported($this->model(), 'non-row insert payload');
            }

            $values[$index] = $this->stampRow($row, $tenantId, (int) $index);
        }

        return $values;
    }

    /** @param array<string, mixed> $row */
    private function stampRow(array $row, int $tenantId, int $index): array
    {
        $key = ColumnName::findTenantKey($row);

        if ($key === null) {
            $row[ColumnName::TENANT] = $tenantId;

            return $row;
        }

        if ((int) $row[$key] !== $tenantId) {
            throw TenantWriteRejected::foreignTenant($this->model(), $index);
        }

        return $row;
    }

    private function tenantUniqueBy(array|string $uniqueBy): array
    {
        $columns = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];

        if (! collect($columns)->contains(fn (string $column): bool => ColumnName::isTenantColumn($column))) {
            array_unshift($columns, ColumnName::TENANT);
        }

        return $columns;
    }

    /** @return class-string */
    private function model(): string
    {
        return $this->tenantModel ?? 'tenant-owned model';
    }

    private function table(): string
    {
        return $this->tenantTable ?? '';
    }
}
