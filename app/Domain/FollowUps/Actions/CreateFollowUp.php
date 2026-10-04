<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\Quotes\QuoteStatus;
use App\Models\FollowUp;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateFollowUp
{
    use CreatesFollowUps;

    /** @param array{scheduled_at: string, assigned_to?: int|null, note?: string|null} $attributes */
    public function __invoke(User $actor, Quote $quote, array $attributes, ?int $previousFollowUpId = null): FollowUp
    {
        Gate::forUser($actor)->authorize('create', [FollowUp::class, $quote]);

        return DB::transaction(function () use ($actor, $quote, $attributes, $previousFollowUpId): FollowUp {
            $freshQuote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if ($freshQuote->status !== QuoteStatus::Sent) {
                throw FollowUpRuleViolation::quoteIsNotSent();
            }

            if (FollowUp::query()->pending()->where('quote_id', $freshQuote->id)->exists()) {
                throw FollowUpRuleViolation::pendingAlreadyExists();
            }

            $followUp = new FollowUp;
            $followUp->fill($this->followUpAttributes($freshQuote, $actor, $attributes));
            $followUp->quote_id = $freshQuote->id;
            $followUp->customer_id = $freshQuote->customer_id;
            $followUp->status = FollowUpStatus::Pending;
            $followUp->pending_quote_id = $freshQuote->id;
            $followUp->previous_follow_up_id = $previousFollowUpId;
            $followUp->created_by = $actor->id;
            $followUp->save();

            return $followUp;
        });
    }
}
