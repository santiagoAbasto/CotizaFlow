<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceSource;
use App\Domain\Products\ProductUnit;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteItem> */
final class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position' => 1,
            'sku_snapshot' => fake()->unique()->bothify('SKU-####'),
            'name_snapshot' => fake()->words(3, true),
            'unit_snapshot' => ProductUnit::Unit,
            'currency_snapshot' => Currency::USD,
            'base_price_snapshot' => '10.000000',
            'quantity' => '1.000000',
            'unit_price' => '10.000000',
            'price_source' => PriceSource::BasePrice,
            'discount_percent' => '0.000000',
            'discount_amount' => '0.000000',
            'tax_percent' => '0.000000',
            'tax_amount' => '0.000000',
            'line_subtotal' => '10.000000',
            'line_total' => '10.000000',
        ];
    }
}
