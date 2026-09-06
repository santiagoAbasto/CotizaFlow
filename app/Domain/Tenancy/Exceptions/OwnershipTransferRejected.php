<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class OwnershipTransferRejected extends RuntimeException
{
    public static function notTenantOwned(string $model): self
    {
        return new self(sprintf('%s is not a tenant-owned model.', $model));
    }

    public static function unknownCompany(int $companyId): self
    {
        return new self(sprintf('Company %d does not exist.', $companyId));
    }

    public static function sameCompany(): self
    {
        return new self('Source and destination company are the same.');
    }

    public static function unsavedModel(string $model): self
    {
        return new self(sprintf('Refusing to transfer an unsaved %s.', $model));
    }
}
