<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\FollowUp;
use App\Models\Membership;

/** Keeps the Inertia contracts readable without leaking domain rules into React. */
final class FollowUpPresenter
{
    /** @return list<array{id: int, name: string}> */
    public function assignees(): array
    {
        return Membership::query()
            ->where('company_id', app(TenantContext::class)->id())
            ->where('status', MembershipStatus::Active->value)
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Membership $membership): array => ['id' => $membership->user_id, 'name' => $membership->user->name])
            ->all();
    }

    /** @return array<string, mixed> */
    public function __invoke(FollowUp $followUp): array
    {
        $followUp->loadMissing(['quote', 'customer', 'assignee', 'completedBy', 'cancelledBy']);
        $timing = FollowUpTiming::current()->classify($followUp);

        return [
            ...$followUp->only(['id', 'quote_id', 'customer_id', 'assigned_to', 'note', 'outcome_note', 'cancellation_reason', 'previous_follow_up_id']),
            'status' => $followUp->status->value,
            'status_label' => $followUp->status->label(),
            'outcome' => $followUp->outcome?->value,
            'outcome_label' => $followUp->outcome?->label(),
            'scheduled_at' => $followUp->scheduled_at->format('d/m/Y H:i'),
            'scheduled_at_input' => $followUp->scheduled_at->format('Y-m-d\\TH:i'),
            'completed_at' => $followUp->completed_at?->format('d/m/Y H:i'),
            'cancelled_at' => $followUp->cancelled_at?->format('d/m/Y H:i'),
            'timing' => $timing,
            'timing_label' => match ($timing) {
                'overdue' => 'Vencido',
                'today' => 'Hoy',
                'upcoming' => 'Próximo',
                default => null,
            },
            'quote_number' => $followUp->quote->number,
            'quote_status' => $followUp->quote->status->value,
            'quote_total' => $followUp->quote->currency->format($followUp->quote->total),
            'quote_currency' => $followUp->quote->currency->value,
            'customer_name' => $followUp->quote->customer_name_snapshot ?? $followUp->customer->name,
            'assignee_name' => $followUp->assignee->name,
            'completed_by_name' => $followUp->completedBy?->name,
            'cancelled_by_name' => $followUp->cancelledBy?->name,
        ];
    }
}
