<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class TenantContextMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No tenant context is bound. Tenant-scoped models require an authenticated '
            .'company or an explicit TenantContext::runAs() boundary.'
        );
    }
}
