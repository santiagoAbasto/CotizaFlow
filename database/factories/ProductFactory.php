<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Money\Currency;
use App\Domain\Products\ProductStatus;
use App\Domain\Products\ProductUnit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->word(),
            'unit' => ProductUnit::Unit,
            'base_cost' => sprintf('%d.%06d', fake()->numberBetween(1, 500), fake()->numberBetween(0, 999999)),
            'base_price' => sprintf('%d.%06d', fake()->numberBetween(1, 999), fake()->numberBetween(0, 999999)),
            'currency' => Currency::USD,
            'status' => ProductStatus::Active,
        ];
    }
}
