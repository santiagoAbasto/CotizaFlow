<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPrice> */
final class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return ['price' => sprintf('%d.%06d', fake()->numberBetween(1, 999), fake()->numberBetween(0, 999999))];
    }
}
