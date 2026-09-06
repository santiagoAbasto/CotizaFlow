<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class TenantWriteRejected extends RuntimeException
{
    public static function foreignTenant(string $model, int $index): self
    {
        return new self(sprintf(
            'Refusing to write %s row #%d: it carries a company_id other than the active '
            .'tenant. Nothing in this batch was written.',
            $model,
            $index,
        ));
    }

    public static function unsupported(string $model, string $operation): self
    {
        return new self(sprintf(
            'Refusing %s on %s because the operation cannot preserve the tenant write invariant.',
            $operation,
            $model,
        ));
    }
}
