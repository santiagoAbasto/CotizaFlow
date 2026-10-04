<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateFollowUp
{
    use CreatesFollowUps;

    /** @param array{scheduled_at: string, assigned_to?: int|null, note?: string|null} $attributes */
    public function __invoke(User $actor, FollowUp $followUp, array $attributes): FollowUp
    {
        Gate::forUser($actor)->authorize('update', $followUp);

        return DB::transaction(function () use ($followUp, $attributes): FollowUp {
            $fresh = FollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== FollowUpStatus::Pending) {
                throw FollowUpRuleViolation::notPending();
            }

            $assigneeId = $attributes['assigned_to'] ?? $fresh->assigned_to;
            $this->assertActiveAssignee($fresh->quote()->firstOrFail(), $assigneeId);
            $fresh->fill([
                'scheduled_at' => $attributes['scheduled_at'],
                'assigned_to' => $assigneeId,
                'note' => $attributes['note'] ?? null,
            ]);
            $fresh->save();

            return $fresh;
        });
    }
}
