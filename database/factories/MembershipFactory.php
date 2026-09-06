<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'role' => MembershipRole::Sales,
            'status' => MembershipStatus::Active,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => MembershipRole::Owner]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => MembershipRole::Admin]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::Suspended]);
    }

    public function invited(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::Invited]);
    }
}
