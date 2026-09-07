<?php

declare(strict_types=1);

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

enum Currency: string
{
    case BOB = 'BOB';
    case USD = 'USD';
    case ARS = 'ARS';

    public function displayExponent(): int
    {
        return 2;
    }

    public function format(string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale($this->displayExponent(), RoundingMode::HalfUp);
    }
}
