<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Money\Currency;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CalculateQuoteLine
{
    public function __invoke(string $quantity, string $unitPrice, string $discountPercent, string $taxPercent, Currency $currency): QuoteLineCalculation
    {
        $scale = $currency->displayExponent();
        $gross = BigDecimal::of($quantity)->multipliedBy($unitPrice);
        $discountExact = $gross->multipliedBy($discountPercent)->dividedBy('100', 20, RoundingMode::Unnecessary);
        $subtotal = $gross->minus($discountExact)->toScale($scale, RoundingMode::HalfUp);
        $discount = $discountExact->toScale($scale, RoundingMode::HalfUp);
        $tax = $subtotal->multipliedBy($taxPercent)->dividedBy('100', 20, RoundingMode::Unnecessary)->toScale($scale, RoundingMode::HalfUp);
        $total = $subtotal->plus($tax)->toScale($scale, RoundingMode::HalfUp);

        return new QuoteLineCalculation(
            (string) BigDecimal::of($quantity)->toScale(6),
            (string) BigDecimal::of($unitPrice)->toScale(6),
            (string) BigDecimal::of($discountPercent)->toScale(6),
            (string) $discount,
            (string) BigDecimal::of($taxPercent)->toScale(6),
            (string) $tax,
            (string) $subtotal,
            (string) $total,
        );
    }
}
