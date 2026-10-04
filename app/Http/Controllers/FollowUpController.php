<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\FollowUps\Actions\CancelFollowUp;
use App\Domain\FollowUps\Actions\CompleteFollowUp;
use App\Domain\FollowUps\Actions\CreateFollowUp;
use App\Domain\FollowUps\Actions\UpdateFollowUp;
use App\Domain\FollowUps\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUps\FollowUpOutcome;
use App\Domain\FollowUps\FollowUpPresenter;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\FollowUps\FollowUpTiming;
use App\Domain\FollowUps\PendingFollowUpValue;
use App\Http\Requests\FollowUps\CancelFollowUpRequest;
use App\Http\Requests\FollowUps\CompleteFollowUpRequest;
use App\Http\Requests\FollowUps\StoreFollowUpRequest;
use App\Http\Requests\FollowUps\UpdateFollowUpRequest;
use App\Models\FollowUp;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class FollowUpController extends Controller
{
    public function index(Request $request, FollowUpPresenter $presenter, PendingFollowUpValue $pendingFollowUpValue)
    {
        Gate::authorize('viewAny', FollowUp::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'cancelled', 'all'])],
            'timing' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'all'])],
        ]);
        $q = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'pending';
        $timing = $filters['timing'] ?? 'all';
        $timingWindow = FollowUpTiming::current();

        $query = FollowUp::query()->with([
            'quote:id,company_id,number,customer_name_snapshot,total,currency,status',
            'customer:id,company_id,name',
            'assignee:id,name',
            'completedBy:id,name',
            'cancelledBy:id,name',
        ]);
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->where(function ($followUps) use ($pattern): void {
                $followUps->whereHas('quote', function ($quotes) use ($pattern): void {
                    $quotes->whereRaw("(number LIKE ? ESCAPE '!' OR customer_name_snapshot LIKE ? ESCAPE '!')", [$pattern, $pattern]);
                })->orWhereHas('customer', function ($customers) use ($pattern): void {
                    $customers->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
                });
            });
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($timing !== 'all') {
            $query->pending();
            $timingWindow->apply($query, $timing);
        }

        $query->orderByRaw('CASE WHEN status = ? AND scheduled_at < ? THEN 0 WHEN status = ? AND scheduled_at < ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END', [
            FollowUpStatus::Pending->value, $timingWindow->startOfToday, FollowUpStatus::Pending->value, $timingWindow->startOfTomorrow, FollowUpStatus::Pending->value,
        ])->orderBy('scheduled_at')->orderByDesc('id');

        return Inertia::render('FollowUps/Index', [
            'followUps' => $query->paginate(20)->withQueryString()->through(fn (FollowUp $followUp) => $presenter($followUp)),
            'filters' => compact('q', 'status', 'timing'),
            'assignees' => $presenter->assignees(),
            'outcomes' => collect(FollowUpOutcome::cases())->map(fn (FollowUpOutcome $outcome) => ['value' => $outcome->value, 'label' => $outcome->label()]),
            'dueValue' => $pendingFollowUpValue->dueTodayOrOverdue(),
        ]);
    }

    public function store(StoreFollowUpRequest $request, Quote $quote, CreateFollowUp $create): RedirectResponse
    {
        try {
            $create($request->user(), $quote, $request->validated());
        } catch (FollowUpRuleViolation $exception) {
            $this->ruleViolation($exception);
        }

        return to_route('quotes.show', $quote)->with('success', 'Seguimiento programado');
    }

    public function update(UpdateFollowUpRequest $request, FollowUp $followUp, UpdateFollowUp $update): RedirectResponse
    {
        try {
            $update($request->user(), $followUp, $request->validated());
        } catch (FollowUpRuleViolation $exception) {
            $this->ruleViolation($exception);
        }

        return to_route('follow-ups.index')->with('success', 'Seguimiento reprogramado');
    }

    public function complete(CompleteFollowUpRequest $request, FollowUp $followUp, CompleteFollowUp $complete): RedirectResponse
    {
        try {
            $complete($request->user(), $followUp, $request->validated());
        } catch (FollowUpRuleViolation $exception) {
            $this->ruleViolation($exception);
        }

        return to_route('follow-ups.index')->with('success', 'Seguimiento completado');
    }

    public function cancel(CancelFollowUpRequest $request, FollowUp $followUp, CancelFollowUp $cancel): RedirectResponse
    {
        try {
            $cancel($request->user(), $followUp, $request->validated('cancellation_reason'));
        } catch (FollowUpRuleViolation $exception) {
            $this->ruleViolation($exception);
        }

        return to_route('follow-ups.index')->with('success', 'Seguimiento cancelado');
    }

    private function ruleViolation(FollowUpRuleViolation $exception): never
    {
        throw ValidationException::withMessages(['status' => $exception->getMessage()]);
    }
}
