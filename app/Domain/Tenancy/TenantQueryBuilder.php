<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
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

    /** @param class-string $model */
    public function forTenantModel(string $model, string $table): static
    {
        $this->tenantModel = $model;
        $this->tenantTable = $table;

        return $this;
    }

    /**
     * Apply the boundary at the last moment before SQL execution. This protects
     * reads obtained through getQuery(), where Eloquent global scopes have not
     * yet been applied, as well as every write implemented by the base builder.
     */
    public function applyBeforeQueryCallbacks(): void
    {
        $this->scopeWriteToTenant();

        parent::applyBeforeQueryCallbacks();
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
        $this->where($this->qualifiedTenantColumn(), '=', $tenantId ?? $this->tenantId());
    }

    private function qualifiedTenantColumn(): string
    {
        if (! is_string($this->from) || trim($this->from) === '') {
            throw TenantWriteRejected::unsupported($this->model(), 'write against an unresolved table');
        }

        $segments = preg_split('/\s+(?:as\s+)?/i', trim($this->from));
        $source = str_replace(['`', '"'], '', (string) ($segments[0] ?? ''));

        if ($source !== $this->table()) {
            throw TenantWriteRejected::unsupported($this->model(), 'write after replacing the model table');
        }

        $qualifier = (string) end($segments);

        return $qualifier.'.'.ColumnName::TENANT;
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
