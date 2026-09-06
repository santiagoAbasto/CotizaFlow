<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\CompanyNotAuthorized;
use App\Domain\Tenancy\TenantContext;
use App\Models\Membership;
use App\Models\User;

/**
 * The ONLY sanctioned path from an untrusted, user-supplied company id to a
 * bound tenant context.
 *
 * TenantContext::enter() is a trusted primitive that asks no questions; this
 * action is the authorisation gate in front of it. Anything that accepts a
 * company id from a request, payload, header or CLI argument must go through
 * here, never through the primitive directly.
 */
final class ActivateCompanyForUser
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @throws CompanyNotAuthorized when the user holds no usable membership.
     */
    public function __invoke(User $user, int $requestedCompanyId): Membership
    {
        $membership = $user->membershipFor($requestedCompanyId);

        // Absent and unauthorised are treated identically on purpose.
        if ($membership === null || ! $membership->grantsAccess()) {
            throw CompanyNotAuthorized::make();
        }

        $this->context->enter($membership->company_id);

        return $membership;
    }
}
