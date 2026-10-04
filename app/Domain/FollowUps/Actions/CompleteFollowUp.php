<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUps\FollowUpOutcome;
use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CompleteFollowUp
{
    public function __construct(private CreateFollowUp $createFollowUp) {}

    /**
     * @param  array{outcome: FollowUpOutcome, outcome_note?: string|null, schedule_next?: bool, next_scheduled_at?: string|null, next_assigned_to?: int|null, next_note?: string|null}  $attributes
     */
    public function __invoke(User $actor, FollowUp $followUp, array $attributes): FollowUp
    {
        Gate::forUser($actor)->authorize('complete', $followUp);

        return DB::transaction(function () use ($actor, $followUp, $attributes): FollowUp {
            $fresh = FollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== FollowUpStatus::Pending) {
                throw FollowUpRuleViolation::notPending();
            }

            $fresh->status = FollowUpStatus::Completed;
            $fresh->outcome = $attributes['outcome'];
            $fresh->outcome_note = $attributes['outcome_note'] ?? null;
            $fresh->completed_at = now();
            $fresh->completed_by = $actor->id;
            $fresh->pending_quote_id = null;
            $fresh->save();

            if (($attributes['schedule_next'] ?? false) === true) {
                $quote = Quote::query()->whereKey($fresh->quote_id)->firstOrFail();
                ($this->createFollowUp)($actor, $quote, [
                    'scheduled_at' => $attributes['next_scheduled_at'],
                    'assigned_to' => $attributes['next_assigned_to'] ?? $actor->id,
                    'note' => $attributes['next_note'] ?? null,
                ], $fresh->id);
            }

            return $fresh;
        });
    }
}
