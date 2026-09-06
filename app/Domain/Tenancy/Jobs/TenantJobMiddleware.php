<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Jobs;

use App\Domain\Tenancy\Exceptions\UnknownTenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use Closure;

/**
 * Opens the tenant boundary around handle() and closes it afterwards, including
 * on failure.
 *
 * The company is verified to still exist BEFORE the boundary opens. A job whose
 * tenant was deleted between dispatch and execution must fail loudly rather
 * than run under a phantom tenant id, where every scoped query would silently
 * return nothing and the work would look like it succeeded.
 *
 * Company is not tenant-scoped — it defines the boundary — so this lookup needs
 * no context of its own.
 */
final class TenantJobMiddleware
{
    public function handle(object $job, Closure $next): mixed
    {
        $companyId = $job->companyId ?? null;

        if (! is_int($companyId) || $companyId <= 0) {
            throw UnknownTenant::invalidIdentifier($job::class);
        }

        if (! Company::whereKey($companyId)->exists()) {
            throw UnknownTenant::forJob($job::class, $companyId);
        }

        // runAs restores the previous boundary in a finally block, so a worker
        // cannot carry one job's tenant into the next even when this one throws.
        return app(TenantContext::class)->runAs($companyId, fn () => $next($job));
    }
}
