<?php

declare(strict_types=1);

use App\Domain\Dashboard\CommercialDashboardQuery;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\Money\Currency;
use App\Domain\Quotes\QuoteStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Quote;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-10 10:00:00');
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    $this->actor = memberOf($this->companyA);
    $this->assignee = memberOf($this->companyA);
    $this->foreignActor = memberOf($this->companyB);

    app(TenantContext::class)->runAs($this->companyA, function (): void {
        $this->customerA = Customer::factory()->create(['name' => 'Industria A']);
    });
    app(TenantContext::class)->runAs($this->companyB, function (): void {
        $this->customerB = Customer::factory()->create(['name' => 'Industria B']);
    });
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dashboardQuote(
    Company $company,
    Customer $customer,
    int $creatorId,
    QuoteStatus $status,
    Currency $currency,
    string $total,
): Quote {
    return app(TenantContext::class)->runAs($company, fn (): Quote => Quote::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $creatorId,
        'status' => $status,
        'currency' => $currency,
        'total' => $total,
        'subtotal' => $total,
        'sent_at' => $status === QuoteStatus::Sent ? now() : null,
        'accepted_at' => $status === QuoteStatus::Accepted ? now() : null,
        'rejected_at' => $status === QuoteStatus::Rejected ? now() : null,
        'expired_at' => $status === QuoteStatus::Expired ? now() : null,
    ]));
}

function dashboardFollowUp(Company $company, Quote $quote, Customer $customer, int $assigneeId, string $scheduledAt): FollowUp
{
    return app(TenantContext::class)->runAs($company, fn (): FollowUp => FollowUp::factory()->create([
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'assigned_to' => $assigneeId,
        'created_by' => $assigneeId,
        'pending_quote_id' => $quote->id,
        'status' => FollowUpStatus::Pending,
        'scheduled_at' => $scheduledAt,
    ]));
}

it('groups dashboard values by currency and excludes foreign tenant data', function (): void {
    $usd = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '100.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::BOB, '700.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Accepted, Currency::USD, '25.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Expired, Currency::BOB, '99.000000');
    dashboardFollowUp($this->companyA, $usd, $this->customerA, $this->assignee->id, '2026-10-09 09:00:00');

    dashboardQuote($this->companyB, $this->customerB, $this->foreignActor->id, QuoteStatus::Sent, Currency::USD, '999999.000000');

    $snapshot = app(TenantContext::class)->runAs($this->companyA, fn (): array => app(CommercialDashboardQuery::class)());

    expect($snapshot['metrics']['sent'])->toBe([
        'count' => 2,
        'amounts' => [
            ['currency' => 'BOB', 'amount' => '700.000000', 'display_amount' => '700.00'],
            ['currency' => 'USD', 'amount' => '100.000000', 'display_amount' => '100.00'],
        ],
    ])->and($snapshot['attention'])->toBe([
        'count' => 1,
        'amounts' => [['currency' => 'USD', 'amount' => '100.000000', 'display_amount' => '100.00']],
    ])->and(json_encode($snapshot))->not->toContain('999999');
});

it('counts a quote once when its current pending follow-up is due today', function (): void {
    $quote = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '8420.000000');
    dashboardFollowUp($this->companyA, $quote, $this->customerA, $this->assignee->id, '2026-10-10 15:00:00');

    $snapshot = app(TenantContext::class)->runAs($this->companyA, fn (): array => app(CommercialDashboardQuery::class)());

    expect($snapshot['metrics']['today_follow_ups']['count'])->toBe(1)
        ->and($snapshot['attention']['count'])->toBe(1)
        ->and($snapshot['metrics']['without_follow_up']['count'])->toBe(0);
});

it('orders priorities by operational urgency and then quote value', function (): void {
    $lowOverdue = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '100.000000');
    $highOverdue = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '4800.000000');
    $today = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '3620.000000');
    $missing = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '2900.000000');
    $expired = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Expired, Currency::USD, '5000.000000');
    dashboardFollowUp($this->companyA, $lowOverdue, $this->customerA, $this->assignee->id, '2026-10-09 09:00:00');
    dashboardFollowUp($this->companyA, $highOverdue, $this->customerA, $this->assignee->id, '2026-10-08 09:00:00');
    dashboardFollowUp($this->companyA, $today, $this->customerA, $this->assignee->id, '2026-10-10 14:00:00');

    $snapshot = app(TenantContext::class)->runAs($this->companyA, fn (): array => app(CommercialDashboardQuery::class)());
    $priorities = $snapshot['priorities'];

    expect(array_column($priorities, 'quote_id'))->toBe([$highOverdue->id, $lowOverdue->id, $today->id, $missing->id, $expired->id])
        ->and(array_column($priorities, 'kind'))->toBe(['overdue', 'overdue', 'today', 'missing_follow_up', 'expired']);
});

it('calculates close rate from resolved quotes without drafts or open quotes', function (): void {
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Accepted, Currency::USD, '100.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Accepted, Currency::USD, '200.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Rejected, Currency::USD, '50.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Expired, Currency::USD, '40.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Draft, Currency::USD, '10.000000');
    dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '10.000000');

    $snapshot = app(TenantContext::class)->runAs($this->companyA, fn (): array => app(CommercialDashboardQuery::class)());

    expect($snapshot['conversion'])->toBe(['accepted' => 2, 'resolved' => 4, 'rate' => '50.0']);
});

it('renders only active tenant dashboard data and exposes the missing follow-up quote filter', function (): void {
    $local = dashboardQuote($this->companyA, $this->customerA, $this->actor->id, QuoteStatus::Sent, Currency::USD, '100.000000');
    dashboardQuote($this->companyB, $this->customerB, $this->foreignActor->id, QuoteStatus::Sent, Currency::USD, '999999.000000');

    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->companyA->id])
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('dashboard.metrics.sent.count', 1)
            ->where('dashboard.metrics.sent.amounts.0.amount', '100.000000'));

    $this->get('/quotes?status=sent&follow_up=missing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quotes.total', 1)
            ->where('quotes.data.0.id', $local->id)
            ->where('filters.follow_up', 'missing'));
});
