<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Quote;
use App\Models\User;

trait CreatesFollowUps
{
    private function assertActiveAssignee(Quote $quote, int $assigneeId): void
    {
        $isActive = Membership::query()
            ->where('company_id', $quote->company_id)
            ->where('user_id', $assigneeId)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        if (! $isActive) {
            throw FollowUpRuleViolation::assigneeIsUnavailable();
        }
    }

    /** @param array{scheduled_at: string, assigned_to?: int|null, note?: string|null} $attributes */
    private function followUpAttributes(Quote $quote, User $actor, array $attributes): array
    {
        $assigneeId = $attributes['assigned_to'] ?? $actor->id;
        $this->assertActiveAssignee($quote, $assigneeId);

        return [
            'assigned_to' => $assigneeId,
            'scheduled_at' => $attributes['scheduled_at'],
            'note' => $attributes['note'] ?? null,
        ];
    }
}
