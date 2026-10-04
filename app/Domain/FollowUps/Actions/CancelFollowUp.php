<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CancelFollowUp
{
    public function __invoke(User $actor, FollowUp $followUp, ?string $reason = null): FollowUp
    {
        Gate::forUser($actor)->authorize('cancel', $followUp);

        return DB::transaction(function () use ($actor, $followUp, $reason): FollowUp {
            $fresh = FollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== FollowUpStatus::Pending) {
                throw FollowUpRuleViolation::notPending();
            }

            $fresh->status = FollowUpStatus::Cancelled;
            $fresh->cancelled_at = now();
            $fresh->cancelled_by = $actor->id;
            $fresh->cancellation_reason = $reason;
            $fresh->pending_quote_id = null;
            $fresh->save();

            return $fresh;
        });
    }
}
