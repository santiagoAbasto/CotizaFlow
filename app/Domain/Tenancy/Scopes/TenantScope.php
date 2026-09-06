<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\TenantQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the active company.
 *
 * The constraint is applied at query build time rather than verified after
 * the fact, so a cross-tenant row is never loaded into memory at all.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $query = $builder->getQuery();

        if (! $query instanceof TenantQueryBuilder) {
            throw new \LogicException(sprintf(
                '%s uses TenantScope without TenantQueryBuilder.',
                $model::class,
            ));
        }

        $query->enforceTenantBoundary();
    }
}
