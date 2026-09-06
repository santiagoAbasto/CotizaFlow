<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Jobs;

use App\Domain\Tenancy\TenantContext;

/**
 * Contract for background work that belongs to exactly one tenant.
 *
 * Jobs, queued listeners, imports, exports and commands carry a scalar
 * company id — never a serialized tenant-scoped model. A queued model is
 * rehydrated by Laravel through a fresh query with NO tenant context bound,
 * which either fails closed or, worse, silently reintroduces a boundary the
 * worker never verified.
 *
 * The payload rule is therefore: company id + scalar entity ids. Resolve the
 * entities inside the boundary, not before it.
 */
trait InteractsWithTenant
{
    /**
     * Set by the job's constructor. Public so TenantJobMiddleware can read it
     * without reflection.
     */
    public int $companyId;

    /**
     * Wraps handle() in the tenant boundary and always releases it, so a
     * worker cannot carry one job's tenant into the next job it picks up.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new TenantJobMiddleware];
    }

    public function forCompany(int $companyId): static
    {
        $this->companyId = $companyId;

        return $this;
    }

    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }
}
