<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Models\Company;

/**
 * Holds the tenant boundary for the current request, job or command.
 *
 * TRUST BOUNDARY: this class is a trusted primitive. It performs no
 * authorisation and asks no questions — whoever calls enter() is asserting the
 * id has already been verified. A company id that originated from a request,
 * payload, header or CLI argument must reach the context only through
 * App\Domain\Tenancy\Actions\ActivateCompanyForUser, which checks membership
 * and status first.
 *
 * The same primitive serves web, API, queued jobs and console commands, so
 * tenant resolution is not tied to the web middleware group.
 * See docs/architecture/TENANCY.md.
 */
final class TenantContext
{
    private ?int $companyId = null;

    /**
     * Bind an already-verified tenant. Trusted callers only.
     */
    public function enter(Company|int $company): void
    {
        $this->companyId = $company instanceof Company ? $company->getKey() : $company;
    }

    public function leave(): void
    {
        $this->companyId = null;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * The active tenant id, or a hard failure.
     *
     * Failing closed is deliberate: an unresolved tenant must never degrade
     * into an unscoped query.
     */
    public function id(): int
    {
        return $this->companyId ?? throw TenantContextMissing::make();
    }

    /**
     * Run a callback inside an explicit tenant boundary, restoring the previous
     * one afterwards — including when the callback throws.
     *
     * This is the reusable primitive behind jobs, listeners, imports, exports,
     * commands and seeders. It nests, and it never leaves a boundary behind.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Company|int $company, callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->enter($company);

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }

    /**
     * Executes a callback with NO active tenant.
     *
     * This is NOT a cross-tenant bypass: the global scope stays installed, so
     * any tenant-scoped query inside the callback fails closed with
     * TenantContextMissing. Its purpose is to prove that a piece of code does
     * not depend on an ambient boundary, and to drop the boundary before work
     * that must establish its own.
     *
     * There is deliberately no generic bypass API. The one operation that must
     * cross the boundary — moving a record between tenants — is
     * TenantOwnershipTransfer, which issues its write on the base query builder
     * and requires an explicit TransferAuthorization.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runWithoutTenant(callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = null;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
