<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class TenantContextChanged extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'A query for %s cannot be executed under a different tenant context than the one that created it.',
            $model,
        ));
    }
}
