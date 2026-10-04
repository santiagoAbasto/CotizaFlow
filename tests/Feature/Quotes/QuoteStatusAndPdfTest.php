<?php

declare(strict_types=1);

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Products\ProductUnit;
use App\Domain\Quotes\Exceptions\InvalidQuoteTransition;
use App\Domain\Quotes\QuotePdfDocument;
use App\Domain\Quotes\QuoteStatus;
use App\Domain\Quotes\TransitionQuoteStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->a = Company::factory()->create(['name' => 'Comercial Andina']);
    $this->b = Company::factory()->create(['name' => 'Comercial Pacífico']);
    $this->actor = memberOf($this->a);
    app(TenantContext::class)->runAs($this->a, function (): void {
        $this->customer = Customer::factory()->create(['name' => 'Industrias ABC', 'legal_name' => 'Industrias ABC SRL', 'tax_id' => 'ABC-100']);
        $this->product = Product::factory()->create([
            'sku' => 'SKF-6204', 'name' => 'SKF 6204', 'description' => 'Rodamiento sellado', 'unit' => ProductUnit::Piece,
            'base_price' => '18.400000', 'currency' => Currency::USD,
        ]);
        $this->list = PriceList::factory()->create(['name' => 'Mayorista', 'currency' => Currency::USD, 'status' => PriceListStatus::Active]);
    });
    app(TenantContext::class)->runAs($this->b, function (): void {
        $this->foreignCustomer = Customer::factory()->create();
        $this->foreignProduct = Product::factory()->create(['currency' => Currency::USD]);
    });
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
    $this->payload = fn (array $replace = []): array => array_replace([
        'customer_id' => $this->customer->id,
        'price_list_id' => $this->list->id,
        'currency' => 'USD',
        'issued_at' => '2026-10-04',
        'expires_at' => '2026-10-15',
        'notes' => 'Entrega coordinada.',
        'terms' => 'Validez 15 días.',
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'discount_percent' => '5', 'tax_percent' => '13']],
    ], $replace);
    $this->createDraft = function (array $replace = []): Quote {
        $this->post('/quotes', ($this->payload)($replace))->assertRedirect();

        return app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->latest('id')->firstOrFail());
    };
});

it('transitions a draft through the permitted commercial states and locks its contents', function (): void {
    $quote = ($this->createDraft)();

    $this->post('/quotes/'.$quote->id.'/send')->assertRedirect('/quotes/'.$quote->id);
    $sent = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->findOrFail($quote->id));
    expect($sent->status)->toBe(QuoteStatus::Sent)->and($sent->sent_at)->not->toBeNull();

    $this->put('/quotes/'.$quote->id, ($this->payload)(['notes' => 'Intento de modificación']))->assertForbidden();
    $this->post('/quotes/'.$quote->id.'/accept')->assertRedirect('/quotes/'.$quote->id);
    $accepted = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->findOrFail($quote->id));
    expect($accepted->status)->toBe(QuoteStatus::Accepted)->and($accepted->accepted_at)->not->toBeNull();

    $this->post('/quotes/'.$quote->id.'/send')->assertForbidden();
    $this->post('/quotes/'.$quote->id.'/reject')->assertForbidden();

    $rejected = ($this->createDraft)();
    $this->post('/quotes/'.$rejected->id.'/send')->assertRedirect();
    $this->post('/quotes/'.$rejected->id.'/reject', ['rejection_reason' => 'Presupuesto fuera de rango'])->assertRedirect();
    $rejected = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->findOrFail($rejected->id));
    expect($rejected->status)->toBe(QuoteStatus::Rejected)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->rejection_reason)->toBe('Presupuesto fuera de rango');
});

it('rejects transitions that skip the state machine', function (): void {
    $quote = ($this->createDraft)();

    $this->post('/quotes/'.$quote->id.'/accept')->assertForbidden();
    $this->post('/quotes/'.$quote->id.'/reject')->assertForbidden();
    expect(fn () => app(TransitionQuoteStatus::class)($quote, QuoteStatus::Accepted))->toThrow(InvalidQuoteTransition::class);
    expect(app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->findOrFail($quote->id)->status))->toBe(QuoteStatus::Draft);
});

it('expires only due sent quotes and is idempotent', function (): void {
    Carbon::setTestNow('2026-10-02 12:00:00');
    try {
        $due = ($this->createDraft)(['issued_at' => '2026-10-01', 'expires_at' => '2026-10-03']);
        $current = ($this->createDraft)(['issued_at' => '2026-10-01', 'expires_at' => '2026-10-04']);
        $draft = ($this->createDraft)(['issued_at' => '2026-10-01', 'expires_at' => '2026-10-03']);
        $this->post('/quotes/'.$due->id.'/send')->assertRedirect();
        $this->post('/quotes/'.$current->id.'/send')->assertRedirect();
        $foreignActor = memberOf($this->b);
        $foreignDue = app(TenantContext::class)->runAs($this->b, fn () => Quote::factory()->create([
            'customer_id' => $this->foreignCustomer->id,
            'created_by' => $foreignActor->id,
            'status' => QuoteStatus::Sent,
            'expires_at' => '2026-10-03',
            'sent_at' => now(),
        ]));
        $accepted = app(TenantContext::class)->runAs($this->a, fn () => Quote::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->actor->id,
            'status' => QuoteStatus::Accepted,
            'expires_at' => '2026-10-03',
            'accepted_at' => now(),
        ]));
        $rejected = app(TenantContext::class)->runAs($this->a, fn () => Quote::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->actor->id,
            'status' => QuoteStatus::Rejected,
            'expires_at' => '2026-10-03',
            'rejected_at' => now(),
        ]));

        Carbon::setTestNow('2026-10-04 12:00:00');
        expect(Artisan::call('quotes:expire'))->toBe(0)
            ->and(Artisan::output())->toContain('2 cotizaciones vencidas.')
            ->and(Artisan::call('quotes:expire'))->toBe(0)
            ->and(Artisan::output())->toContain('0 cotizaciones vencidas.');
        $states = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->get()->mapWithKeys(fn (Quote $quote) => [$quote->id => $quote->status]));
        expect($states[$due->id])->toBe(QuoteStatus::Expired)
            ->and($states[$current->id])->toBe(QuoteStatus::Sent)
            ->and($states[$draft->id])->toBe(QuoteStatus::Draft)
            ->and($states[$accepted->id])->toBe(QuoteStatus::Accepted)
            ->and($states[$rejected->id])->toBe(QuoteStatus::Rejected)
            ->and(app(TenantContext::class)->runAs($this->b, fn () => Quote::query()->findOrFail($foreignDue->id)->status))->toBe(QuoteStatus::Expired);
    } finally {
        Carbon::setTestNow();
    }
});

it('registers quote expiration with the scheduler', function (): void {
    $commands = collect(app(Schedule::class)->events())->pluck('command');

    expect($commands->contains(fn (string $command) => str_contains($command, 'quotes:expire')))->toBeTrue();
});

it('does not issue or resolve a quote whose calendar validity has already ended', function (): void {
    Carbon::setTestNow('2026-10-04 12:00:00');
    try {
        $quote = ($this->createDraft)(['issued_at' => '2026-10-01', 'expires_at' => '2026-10-03']);
        $this->postJson('/quotes/'.$quote->id.'/send')->assertUnprocessable()->assertJsonValidationErrors('status');

        app(TenantContext::class)->runAs($this->a, function () use ($quote): void {
            $quote->status = QuoteStatus::Sent;
            $quote->sent_at = now()->subDay();
            $quote->save();
        });
        $this->postJson('/quotes/'.$quote->id.'/accept')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/quotes/'.$quote->id.'/reject')->assertUnprocessable()->assertJsonValidationErrors('status');
        expect(app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->findOrFail($quote->id)->status))->toBe(QuoteStatus::Sent);
    } finally {
        Carbon::setTestNow();
    }
});

it('does not allow a regular quote request to inject status timestamps or ownership', function (): void {
    $quote = ($this->createDraft)();
    $this->putJson('/quotes/'.$quote->id, ($this->payload)([
        'status' => 'accepted', 'sent_at' => '2026-10-04 10:00:00', 'accepted_at' => '2026-10-04 10:01:00',
        'expired_at' => '2026-10-04 10:02:00', 'company_id' => $this->b->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['status', 'sent_at', 'accepted_at', 'expired_at', 'company_id']);
});

it('serves PDFs only to authenticated users in the current tenant', function (): void {
    $quote = ($this->createDraft)();
    $this->get('/quotes/'.$quote->id.'/pdf')->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=cotizacion-'.$quote->number.'.pdf');
    $this->get('/quotes/'.$quote->id.'/pdf?download=1')->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=cotizacion-'.$quote->number.'.pdf');

    $foreignActor = memberOf($this->b);
    $foreignQuote = app(TenantContext::class)->runAs($this->b, fn () => Quote::factory()->create(['customer_id' => $this->foreignCustomer->id, 'created_by' => $foreignActor->id]));
    $this->get('/quotes/'.$foreignQuote->id.'/pdf')->assertNotFound();
    $this->post('/quotes/'.$foreignQuote->id.'/send')->assertNotFound();
    $this->post('/quotes/'.$foreignQuote->id.'/accept')->assertNotFound();
    $this->post('/quotes/'.$foreignQuote->id.'/reject')->assertNotFound();
    $this->post('/logout')->assertRedirect('/login');
    $this->get('/quotes/'.$quote->id.'/pdf')->assertRedirect('/login');
});

it('renders PDF data from persisted snapshots and visibly marks drafts', function (): void {
    $quote = ($this->createDraft)();
    app(TenantContext::class)->runAs($this->a, function (): void {
        $this->customer->update(['name' => 'Empresa XYZ']);
        $this->product->update(['name' => 'SKF Nuevo', 'base_price' => '25.000000']);
    });
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->findOrFail($quote->id));
    $html = view('quotes.pdf', app(QuotePdfDocument::class)($quote))->render();

    expect($html)->toContain('BORRADOR')->toContain('Industrias ABC')->toContain('SKF 6204')->toContain('USD 18.40')
        ->not->toContain('Empresa XYZ')->not->toContain('SKF Nuevo')->not->toContain('USD 25.00');
});

it('escapes untrusted snapshot text in the PDF template and handles a multi-page quote', function (): void {
    app(TenantContext::class)->runAs($this->a, fn () => $this->product->update(['name' => '<script>window.xss = true</script>']));
    $quote = ($this->createDraft)();
    app(TenantContext::class)->runAs($this->a, function () use ($quote): void {
        foreach (range(2, 40) as $position) {
            $quote->items()->save(QuoteItem::factory()->make(['position' => $position]));
        }
    });
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->findOrFail($quote->id));
    $html = view('quotes.pdf', app(QuotePdfDocument::class)($quote))->render();

    expect($html)->toContain('&lt;script&gt;window.xss = true&lt;/script&gt;')->not->toContain('<script>window.xss = true</script>');
    $response = $this->get('/quotes/'.$quote->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(preg_match_all('/\/Type\s*\/Page\b/', (string) $response->getContent()))->toBeGreaterThanOrEqual(2);
});
