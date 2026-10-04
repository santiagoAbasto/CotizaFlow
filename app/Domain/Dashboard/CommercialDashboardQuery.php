<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\FollowUps\FollowUpTiming;
use App\Domain\Money\Currency;
use App\Domain\Quotes\QuoteStatus;
use App\Models\FollowUp;
use App\Models\Quote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/** Builds the current tenant's operational commercial snapshot without FX conversion. */
final class CommercialDashboardQuery
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $timing = FollowUpTiming::current();
        $sent = $this->quoteSummary(fn (Builder $quotes) => $quotes->where('status', QuoteStatus::Sent->value));
        $accepted = $this->quoteSummary(fn (Builder $quotes) => $quotes->where('status', QuoteStatus::Accepted->value));
        $expired = $this->quoteSummary(fn (Builder $quotes) => $quotes->where('status', QuoteStatus::Expired->value));
        $overdue = $this->followUpQuoteSummary($timing, 'overdue');
        $today = $this->followUpQuoteSummary($timing, 'today');
        $attention = $this->quoteSummary(function (Builder $quotes) use ($timing): void {
            $quotes->where('status', QuoteStatus::Sent->value)
                ->whereHas('followUps', function (Builder $followUps) use ($timing): void {
                    $followUps->pending();
                    $timing->applyDueTodayOrOverdue($followUps);
                });
        });
        $withoutFollowUp = $this->quoteSummary(fn (Builder $quotes) => $quotes
            ->where('status', QuoteStatus::Sent->value)
            ->whereDoesntHave('followUps', fn (Builder $followUps) => $followUps->pending()));

        $overduePriorities = $this->followUpPriorities($timing, 'overdue');
        $todayPriorities = $this->followUpPriorities($timing, 'today');

        $pipeline = collect(QuoteStatus::cases())
            ->map(fn (QuoteStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'summary' => $this->quoteSummary(fn (Builder $quotes) => $quotes->where('status', $status->value)),
            ])
            ->all();
        $resolved = collect($pipeline)
            ->whereIn('status', [QuoteStatus::Accepted->value, QuoteStatus::Rejected->value, QuoteStatus::Expired->value])
            ->sum(fn (array $entry): int => $entry['summary']['count']);
        $acceptedCount = $accepted['count'];

        return [
            'has_activity' => collect($pipeline)->sum(fn (array $entry): int => $entry['summary']['count']) > 0,
            'attention' => $attention,
            'metrics' => [
                'sent' => $sent,
                'accepted' => $accepted,
                'expired' => $expired,
                'overdue_follow_ups' => $overdue,
                'today_follow_ups' => $today,
                'without_follow_up' => $withoutFollowUp,
            ],
            'pipeline' => $pipeline,
            'conversion' => [
                'accepted' => $acceptedCount,
                'resolved' => $resolved,
                'rate' => $resolved === 0
                    ? null
                    : (string) BigDecimal::of($acceptedCount)->dividedBy($resolved, 3, RoundingMode::HalfUp)->multipliedBy(100)->toScale(1, RoundingMode::HalfUp),
            ],
            'priorities' => array_slice(array_merge(
                $overduePriorities,
                $todayPriorities,
                $this->quotePriorities('missing_follow_up'),
                $this->quotePriorities('expired'),
            ), 0, 8),
            'today_follow_ups' => array_slice($todayPriorities, 0, 5),
        ];
    }

    /** @param Closure(Builder<Quote>): void $constraint
     *  @return array{count: int, amounts: list<array{currency: string, amount: string, display_amount: string}>} */
    private function quoteSummary(Closure $constraint): array
    {
        $quotes = Quote::query();
        $constraint($quotes);

        $groupedAmounts = $quotes
            ->select('currency')
            ->selectRaw('COUNT(*) as aggregate_count, SUM(total) as aggregate_amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (Quote $quote): array => [
                'count' => (int) $quote->aggregate_count,
                ...$this->amount($quote->currency, (string) $quote->aggregate_amount),
            ])
            ->all();

        return [
            'count' => array_sum(array_column($groupedAmounts, 'count')),
            'amounts' => array_map(fn (array $amount): array => [
                'currency' => $amount['currency'],
                'amount' => $amount['amount'],
                'display_amount' => $amount['display_amount'],
            ], $groupedAmounts),
        ];
    }

    /** @return array{count: int, amounts: list<array{currency: string, amount: string, display_amount: string}>} */
    private function followUpQuoteSummary(FollowUpTiming $timing, string $state): array
    {
        return $this->quoteSummary(function (Builder $quotes) use ($timing, $state): void {
            $quotes->where('status', QuoteStatus::Sent->value)
                ->whereHas('followUps', function (Builder $followUps) use ($timing, $state): void {
                    $followUps->pending();
                    $timing->apply($followUps, $state);
                });
        });
    }

    /** @return list<array<string, mixed>> */
    private function followUpPriorities(FollowUpTiming $timing, string $state): array
    {
        $followUps = FollowUp::query()
            ->pending()
            ->join('quotes', function ($join): void {
                $join->on('quotes.id', '=', 'follow_ups.quote_id')
                    ->on('quotes.company_id', '=', 'follow_ups.company_id');
            })
            ->where('quotes.status', QuoteStatus::Sent->value)
            ->select('follow_ups.*')
            ->with(['quote:id,company_id,number,customer_name_snapshot,total,currency', 'assignee:id,name']);
        $timing->apply($followUps, $state);

        return $followUps
            ->orderByDesc('quotes.total')
            ->orderBy('follow_ups.scheduled_at')
            ->limit(8)
            ->get()
            ->map(function (FollowUp $followUp) use ($state): array {
                return [
                    'kind' => $state,
                    'quote_id' => $followUp->quote_id,
                    'follow_up_id' => $followUp->id,
                    'number' => $followUp->quote->number,
                    'customer_name' => $followUp->quote->customer_name_snapshot,
                    'currency' => $followUp->quote->currency->value,
                    'amount' => (string) $followUp->quote->total,
                    'display_amount' => $followUp->quote->currency->format((string) $followUp->quote->total),
                    'reason' => $state === 'overdue' ? 'Seguimiento vencido' : 'Seguimiento para hoy',
                    'scheduled_at' => $followUp->scheduled_at->format('d/m/Y H:i'),
                    'assignee_name' => $followUp->assignee->name,
                    'cta_label' => 'Abrir',
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function quotePriorities(string $kind): array
    {
        $quotes = Quote::query();
        if ($kind === 'missing_follow_up') {
            $quotes->where('status', QuoteStatus::Sent->value)
                ->whereDoesntHave('followUps', fn (Builder $followUps) => $followUps->pending());
        } else {
            $quotes->where('status', QuoteStatus::Expired->value);
        }

        return $quotes->orderByDesc('total')->orderByDesc('id')->limit(8)->get()
            ->map(fn (Quote $quote): array => [
                'kind' => $kind,
                'quote_id' => $quote->id,
                'follow_up_id' => null,
                'number' => $quote->number,
                'customer_name' => $quote->customer_name_snapshot,
                'currency' => $quote->currency->value,
                'amount' => (string) $quote->total,
                'display_amount' => $quote->currency->format((string) $quote->total),
                'reason' => $kind === 'missing_follow_up' ? 'Sin seguimiento programado' : 'Cotización vencida',
                'scheduled_at' => $quote->expires_at?->format('d/m/Y') ?? 'Sin fecha de vigencia',
                'assignee_name' => null,
                'cta_label' => $kind === 'missing_follow_up' ? 'Programar' : 'Abrir',
            ])->all();
    }

    /** @return array{currency: string, amount: string, display_amount: string} */
    private function amount(Currency $currency, string $amount): array
    {
        return ['currency' => $currency->value, 'amount' => $amount, 'display_amount' => $currency->format($amount)];
    }
}
