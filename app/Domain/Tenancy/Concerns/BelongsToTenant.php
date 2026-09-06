<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\Exceptions\TenantWriteRejected;
use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Tenancy\TenantBuilder;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantQueryBuilder;
use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Marks a model as owned by exactly one tenant.
 *
 * Three layers, because each one alone has a hole:
 *   1. TenantScope constrains every read at query-build time.
 *   2. The `creating` hook stamps company_id from the backend context, so a
 *      client-supplied value can never take effect.
 *   3. TenantBuilder rejects company_id in bulk updates, which model events
 *      never observe.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            $tenantId = app(TenantContext::class)->id();
            $supplied = $model->getAttribute('company_id');

            if ($supplied !== null && (int) $supplied !== $tenantId) {
                throw TenantWriteRejected::foreignTenant(static::class, 0);
            }

            $model->setAttribute('company_id', $tenantId);
        });

        // Refuses rather than silently reverting: a caller trying to move a
        // record between tenants has a bug worth surfacing.
        static::updating(function (self $model): void {
            if ($model->isDirty('company_id')) {
                throw TenantColumnImmutable::forModel(static::class);
            }
        });
    }

    /**
     * @param  QueryBuilder  $query
     * @return TenantBuilder<static>
     */
    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }

    /**
     * Install the invariant below Eloquent as well. This is what keeps writes
     * obtained through toBase(), getQuery() and forwarded calls fail-closed.
     */
    protected function newBaseQueryBuilder(): QueryBuilder
    {
        $connection = $this->getConnection();

        return (new TenantQueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor(),
        ))->forTenantModel(static::class, $this->getTable());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
