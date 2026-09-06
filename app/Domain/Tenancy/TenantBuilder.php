<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder for tenant-owned models.
 *
 * Eloquent model events do not fire for builder-level writes, so a
 * `creating`/`updating` guard alone leaves three doors open:
 *
 *   Model::where(...)->update(['COMPANY_ID' => $foreign])
 *   Model::insert([['company_id' => $foreign, ...]])
 *   Model::upsert([['company_id' => $foreign, ...]], ...)
 *
 * All three are closed here, at the layer where the write is actually issued.
 * Every row is validated BEFORE anything is written, so a batch containing one
 * bad row writes nothing at all.
 *
 * There is deliberately no bypass flag on this class. Moving a record between
 * tenants is done by App\Domain\Tenancy\TenantOwnershipTransfer, which does not
 * route through Eloquent at all.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
final class TenantBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        foreach (array_keys($values) as $column) {
            if (ColumnName::isTenantColumn((string) $column)) {
                throw TenantColumnImmutable::forModel($this->getModel()::class);
            }
        }

        return parent::update($values);
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function insert(array $values): bool
    {
        return parent::insert($this->stampRows($values));
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function insertOrIgnore(array $values): int
    {
        return parent::insertOrIgnore($this->stampRows($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $stamped = $this->stampRows([$values]);

        return parent::insertGetId($stamped[0], $sequence);
    }

    /**
     * Guards both surfaces: the columns that would be updated on conflict, and
     * the tenant carried by each inserted row.
     *
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        foreach ($update ?? [] as $key => $column) {
            // $update may be a list of columns or a column => value map.
            $name = is_string($key) ? $key : $column;

            if (is_string($name) && ColumnName::isTenantColumn($name)) {
                throw TenantColumnImmutable::forModel($this->getModel()::class);
            }
        }

        return parent::upsert($this->stampRows($values), $uniqueBy, $update);
    }

    /**
     * Validates and stamps every row against the active tenant.
     *
     * Missing tenant  → stamped from the context.
     * Matching tenant → accepted as written.
     * Foreign tenant  → rejected, and nothing in the batch is written.
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    private function stampRows(array $values): array
    {
        if ($values === []) {
            return $values;
        }

        $tenantId = app(TenantContext::class)->id();

        // A single associative row, rather than a list of rows.
        if (! array_is_list($values)) {
            return $this->stampRow($values, $tenantId, 0);
        }

        foreach ($values as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $values[$index] = $this->stampRow($row, $tenantId, (int) $index);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stampRow(array $row, int $tenantId, int $index): array
    {
        $key = ColumnName::findTenantKey($row);

        if ($key === null) {
            $row[ColumnName::TENANT] = $tenantId;

            return $row;
        }

        if ((int) $row[$key] !== $tenantId) {
            throw TenantWriteRejected::foreignTenant($this->getModel()::class, $index);
        }

        return $row;
    }
}
