<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

enum PriceListStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
