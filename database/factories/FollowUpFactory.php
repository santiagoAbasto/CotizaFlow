<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FollowUp> */
final class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scheduled_at' => now()->addDay(),
            'status' => FollowUpStatus::Pending,
            'note' => fake()->sentence(),
        ];
    }
}
