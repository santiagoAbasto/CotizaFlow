<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * A user asked to enter a company they may not enter.
 *
 * The message is deliberately identical whether the company does not exist or
 * merely belongs to someone else, so the response cannot be used to enumerate
 * other tenants.
 */
final class CompanyNotAuthorized extends RuntimeException
{
    public static function make(): self
    {
        return new self('The requested company is not available to this user.');
    }
}
