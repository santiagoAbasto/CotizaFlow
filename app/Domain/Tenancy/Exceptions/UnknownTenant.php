<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class UnknownTenant extends RuntimeException
{
    public static function forJob(string $job, int $companyId): self
    {
        return new self(sprintf(
            'Refusing to run %s: company %d no longer exists.',
            $job,
            $companyId,
        ));
    }

    public static function invalidIdentifier(string $job): self
    {
        return new self(sprintf(
            '%s carries no usable company identifier.',
            $job,
        ));
    }
}
