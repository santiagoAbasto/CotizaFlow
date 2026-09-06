<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class TenantColumnImmutable extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'company_id is immutable on %s. Moving a record between tenants is not a '
            .'normal update; it must go through TenantOwnershipTransfer, which logs it.',
            $model,
        ));
    }
}
