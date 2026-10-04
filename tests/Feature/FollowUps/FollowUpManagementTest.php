<?php

declare(strict_types=1);

use App\Domain\FollowUps\Actions\CreateFollowUp;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\FollowUps\PendingFollowUpValue;
use App\Domain\Money\Currency;
use App\Domain\Quotes\QuoteStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Quote;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    $this->actor = memberOf($this->companyA);
    $this->assignee = memberOf($this->companyA);
    $this->foreignActor = memberOf($this->companyB);
    app(TenantContext::class)->runAs($this->companyA, function (): void {
        $this->customer = Customer::factory()->create(['name' => 'Taller Andino']);
        $this->quote = Quote::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->actor->id,
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'currency' => Currency::USD,
            'total' => '8420.000000',
        ]);
        $this->draft = Quote::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->actor->id,
            'status' => QuoteStatus::Draft,
        ]);
    });
    app(TenantContext::class)->runAs($this->companyB, function (): void {
        $this->foreignCustomer = Customer::factory()->create();
        $this->foreignQuote = Quote::factory()->create([
            'customer_id' => $this->foreignCustomer->id,
            'created_by' => $this->foreignActor->id,
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
        ]);
    });
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->companyA->id]);
    $this->schedule = fn (Quote $quote, array $replace = []) => $this->post('/quotes/'.$quote->id.'/follow-ups', array_replace([
        'scheduled_at' => '2026-10-06T09:30',
        'assigned_to' => $this->assignee->id,
        'note' => 'Llamar para confirmar la decisión.',
    ], $replace));
});

it('creates a tenant-owned follow-up from a sent quote and derives its relations', function (): void {
    ($this->schedule)($this->quote)->assertRedirect('/quotes/'.$this->quote->id);

    $followUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->sole());
    expect($followUp->company_id)->toBe($this->companyA->id)
        ->and($followUp->quote_id)->toBe($this->quote->id)
        ->and($followUp->customer_id)->toBe($this->customer->id)
        ->and($followUp->assigned_to)->toBe($this->assignee->id)
        ->and($followUp->created_by)->toBe($this->actor->id)
        ->and($followUp->status)->toBe(FollowUpStatus::Pending)
        ->and($followUp->pending_quote_id)->toBe($this->quote->id);
});

it('only permits one pending follow-up per sent quote', function (): void {
    ($this->schedule)($this->quote)->assertRedirect();
    ($this->schedule)($this->quote, ['scheduled_at' => '2026-10-07T09:30'])->assertRedirect()->assertSessionHasErrors('status');
    expect(app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->pending()->where('quote_id', $this->quote->id)->count()))->toBe(1);
});

it('has a database backstop for two pending writes to one quote', function (): void {
    ($this->schedule)($this->quote)->assertRedirect();
    $followUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->sole());

    expect(fn () => DB::table('follow_ups')->insert([
        'company_id' => $this->companyA->id,
        'quote_id' => $this->quote->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->actor->id,
        'scheduled_at' => now(),
        'status' => FollowUpStatus::Pending->value,
        'pending_quote_id' => $this->quote->id,
        'created_by' => $this->actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    expect($followUp->pending_quote_id)->toBe($this->quote->id);
});

it('rejects follow-ups for non-sent quotes and request-controlled ownership fields', function (): void {
    ($this->schedule)($this->draft)->assertForbidden();
    ($this->schedule)($this->quote, [
        'company_id' => $this->companyB->id,
        'customer_id' => $this->foreignCustomer->id,
        'created_by' => $this->foreignActor->id,
        'completed_by' => $this->foreignActor->id,
        'status' => 'completed',
    ])->assertRedirect()->assertSessionHasErrors(['company_id', 'customer_id', 'created_by', 'completed_by', 'status']);
});

it('completes a follow-up and schedules its next contact transactionally', function (): void {
    ($this->schedule)($this->quote)->assertRedirect();
    $followUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->sole());

    $this->post('/follow-ups/'.$followUp->id.'/complete', [
        'outcome' => 'customer_reviewing',
        'outcome_note' => 'Pide aprobación interna.',
        'schedule_next' => true,
        'next_scheduled_at' => '2026-10-08T10:00',
        'next_assigned_to' => $this->actor->id,
        'next_note' => 'Confirmar respuesta final.',
    ])->assertRedirect('/follow-ups');

    $followUps = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->orderBy('id')->get());
    expect($followUps)->toHaveCount(2)
        ->and($followUps[0]->status)->toBe(FollowUpStatus::Completed)
        ->and($followUps[0]->completed_by)->toBe($this->actor->id)
        ->and($followUps[1]->status)->toBe(FollowUpStatus::Pending)
        ->and($followUps[1]->previous_follow_up_id)->toBe($followUps[0]->id)
        ->and($followUps[1]->assigned_to)->toBe($this->actor->id);
});

it('reschedules and cancels only pending follow-ups', function (): void {
    ($this->schedule)($this->quote)->assertRedirect();
    $followUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->sole());
    $this->put('/follow-ups/'.$followUp->id, ['scheduled_at' => '2026-10-09T14:15', 'assigned_to' => $this->actor->id, 'note' => 'Reagendado.'])->assertRedirect('/follow-ups');
    $this->post('/follow-ups/'.$followUp->id.'/cancel', ['cancellation_reason' => 'Cliente ya resolvió por otro canal.'])->assertRedirect('/follow-ups');

    $followUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->findOrFail($followUp->id));
    expect($followUp->status)->toBe(FollowUpStatus::Cancelled)
        ->and($followUp->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-09 14:15')
        ->and($followUp->pending_quote_id)->toBeNull();
    $this->put('/follow-ups/'.$followUp->id, ['scheduled_at' => '2026-10-10T09:00'])->assertForbidden();
});

it('prioritizes and filters pending follow-ups by their derived timing', function (): void {
    Carbon::setTestNow('2026-10-06 12:00:00');
    try {
        ($this->schedule)($this->quote, ['scheduled_at' => '2026-10-05T09:00'])->assertRedirect();
        $overdue = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->sole());
        $overdueNumber = $this->quote->number;
        app(TenantContext::class)->runAs($this->companyA, function (): void {
            $quote = Quote::factory()->create(['customer_id' => $this->customer->id, 'created_by' => $this->actor->id, 'status' => QuoteStatus::Sent, 'sent_at' => now()]);
            $this->todayQuote = $quote;
        });
        ($this->schedule)($this->todayQuote, ['scheduled_at' => '2026-10-06T15:00'])->assertRedirect();

        $this->get('/follow-ups?timing=overdue')->assertOk()->assertSee($overdueNumber)->assertDontSee($this->todayQuote->number);
        $this->get('/follow-ups?timing=today')->assertOk()->assertSee($this->todayQuote->number)->assertDontSee($overdueNumber);
    } finally {
        Carbon::setTestNow();
    }
});

it('reports pending commercial value by currency without double counting quotes', function (): void {
    Carbon::setTestNow('2026-10-06 12:00:00');
    try {
        ($this->schedule)($this->quote, ['scheduled_at' => '2026-10-06T09:00'])->assertRedirect();

        $value = app(TenantContext::class)->runAs($this->companyA, fn (): array => app(PendingFollowUpValue::class)->dueTodayOrOverdue());

        expect($value)->toBe([['currency' => 'USD', 'total' => '8420.00']]);
    } finally {
        Carbon::setTestNow();
    }
});

it('cancels the pending commercial follow-up when a quote is accepted or rejected', function (): void {
    ($this->schedule)($this->quote)->assertRedirect();
    $this->post('/quotes/'.$this->quote->id.'/accept')->assertRedirect();
    $acceptedFollowUp = app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->where('quote_id', $this->quote->id)->sole());
    expect($acceptedFollowUp->status)->toBe(FollowUpStatus::Cancelled)
        ->and($acceptedFollowUp->pending_quote_id)->toBeNull();

    app(TenantContext::class)->runAs($this->companyA, function (): void {
        $this->rejectedQuote = Quote::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->actor->id,
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
        ]);
    });
    ($this->schedule)($this->rejectedQuote)->assertRedirect();
    $this->post('/quotes/'.$this->rejectedQuote->id.'/reject')->assertRedirect();
    expect(app(TenantContext::class)->runAs($this->companyA, fn () => FollowUp::query()->where('quote_id', $this->rejectedQuote->id)->sole()->status))->toBe(FollowUpStatus::Cancelled);
});

it('does not expose or mutate foreign follow-ups, quotes, or assignees', function (): void {
    $foreignFollowUp = app(TenantContext::class)->runAs($this->companyB, fn (): FollowUp => app(CreateFollowUp::class)(
        $this->foreignActor,
        $this->foreignQuote,
        ['scheduled_at' => '2026-10-06T09:30'],
    ));

    $this->get('/follow-ups')->assertOk()->assertDontSee($this->foreignQuote->number);
    $this->post('/quotes/'.$this->foreignQuote->id.'/follow-ups', ['scheduled_at' => '2026-10-06T09:30'])->assertNotFound();
    $this->put('/follow-ups/'.$foreignFollowUp->id, ['scheduled_at' => '2026-10-07T09:30'])->assertNotFound();
    $this->post('/follow-ups/'.$foreignFollowUp->id.'/complete', ['outcome' => 'contacted'])->assertNotFound();
    $this->post('/follow-ups/'.$foreignFollowUp->id.'/cancel')->assertNotFound();
    ($this->schedule)($this->quote, ['assigned_to' => $this->foreignActor->id])->assertRedirect()->assertSessionHasErrors('status');
});
