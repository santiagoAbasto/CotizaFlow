<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

enum PriceSource: string
{
    case BasePrice = 'base_price';
    case PriceList = 'price_list';
}
