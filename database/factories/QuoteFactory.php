<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Money\Currency;
use App\Domain\Quotes\QuoteStatus;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quote> */
final class QuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sequence_number' => fake()->unique()->numberBetween(1, 999999),
            'number' => 'QT-'.fake()->unique()->numerify('######'),
            'currency' => Currency::USD,
            'issued_at' => now()->toDateString(),
            'status' => QuoteStatus::Draft,
            'customer_name_snapshot' => fake()->company(),
            'subtotal' => '10.000000',
            'discount_total' => '0.000000',
            'tax_total' => '0.000000',
            'total' => '10.000000',
        ];
    }
}
