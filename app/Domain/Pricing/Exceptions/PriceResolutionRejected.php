<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exceptions;

use DomainException;

final class PriceResolutionRejected extends DomainException
{
    public static function tenantMismatch(): self
    {
        return new self('Product and price list must belong to the same company.');
    }

    public static function currencyMismatch(): self
    {
        return new self('Product and price list currencies must match.');
    }

    public static function inactivePriceList(): self
    {
        return new self('Inactive price lists cannot resolve a commercial price.');
    }

    public static function inactiveProduct(): self
    {
        return new self('Inactive products cannot receive new price overrides.');
    }
}
