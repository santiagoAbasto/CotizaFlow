<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceList> */
final class PriceListFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'currency' => Currency::USD, 'status' => PriceListStatus::Active];
    }
}
