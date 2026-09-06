<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use InvalidArgumentException;

/**
 * Explicit internal authorisation for an ownership transfer.
 *
 * Its only purpose is to make the operation impossible to perform by accident
 * and impossible to reach from a request: nothing binds this out of HTTP input,
 * and constructing it forces the caller to state who is doing it and why. The
 * reason is carried into the audit record.
 */
final readonly class TransferAuthorization
{
    public function __construct(
        public string $performedBy,
        public string $reason,
    ) {
        if (trim($performedBy) === '') {
            throw new InvalidArgumentException('An ownership transfer must record who performed it.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('An ownership transfer must record why it happened.');
        }
    }
}
