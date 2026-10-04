<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Shared operational calendar boundary for follow-up lists and commercial metrics. */
final readonly class FollowUpTiming
{
    public function __construct(
        public Carbon $startOfToday,
        public Carbon $startOfTomorrow,
    ) {}

    public static function current(): self
    {
        $startOfToday = now()->startOfDay();

        return new self($startOfToday, $startOfToday->copy()->addDay());
    }

    /** @param Builder<FollowUp> $query */
    public function apply(Builder $query, string $timing): void
    {
        $scheduledAt = $query->qualifyColumn('scheduled_at');

        match ($timing) {
            'overdue' => $query->where($scheduledAt, '<', $this->startOfToday),
            'today' => $query->whereBetween($scheduledAt, [$this->startOfToday, $this->startOfTomorrow]),
            'upcoming' => $query->where($scheduledAt, '>=', $this->startOfTomorrow),
        };
    }

    /** @param Builder<FollowUp> $query */
    public function applyDueTodayOrOverdue(Builder $query): void
    {
        $query->where($query->qualifyColumn('scheduled_at'), '<', $this->startOfTomorrow);
    }

    public function classify(FollowUp $followUp): string
    {
        if ($followUp->status !== FollowUpStatus::Pending) {
            return $followUp->status->value;
        }

        if ($followUp->scheduled_at->lt($this->startOfToday)) {
            return 'overdue';
        }

        return $followUp->scheduled_at->lt($this->startOfTomorrow) ? 'today' : 'upcoming';
    }
}
