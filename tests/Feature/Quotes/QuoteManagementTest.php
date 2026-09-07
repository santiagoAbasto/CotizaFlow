<?php

declare(strict_types=1);

use App\Domain\Customers\Actions\ChangeCustomerStatus;
use App\Domain\Customers\CustomerStatus;
use App\Domain\Money\Currency;
use App\Domain\Pricing\Actions\ChangePriceListStatus;
use App\Domain\Pricing\Actions\SetProductPrice;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Products\Actions\ChangeProductStatus;
use App\Domain\Products\ProductStatus;
use App\Domain\Quotes\Actions\CreateQuote;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
    $this->actor = memberOf($this->a);
    app(TenantContext::class)->runAs($this->a, function (): void {
        $this->customer = Customer::factory()->create(['name' => 'Taller Andino', 'legal_name' => 'Taller Andino SRL', 'tax_id' => 'TA-100']);
        $this->product = Product::factory()->create(['sku' => 'SKF-6204', 'name' => 'Rulemán SKF 6204', 'base_price' => '18.400000', 'currency' => Currency::USD]);
        $this->motor = Product::factory()->create(['sku' => 'WEG-3HP', 'name' => 'Motor WEG 3HP', 'base_price' => '149.000000', 'currency' => Currency::USD]);
        $this->list = PriceList::factory()->create(['name' => 'Mayorista', 'currency' => Currency::USD]);
        app(SetProductPrice::class)($this->actor, $this->list, $this->product, '16.800000');
    });
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
    $this->payload = fn (array $replace = []): array => array_replace([
        'customer_id' => $this->customer->id,
        'price_list_id' => $this->list->id,
        'currency' => 'USD',
        'issued_at' => '2026-09-07',
        'expires_at' => '2026-09-22',
        'notes' => 'Entrega coordinada.',
        'terms' => 'Validez 15 días.',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '15', 'discount_percent' => '0', 'tax_percent' => '0'],
            ['product_id' => $this->motor->id, 'quantity' => '2', 'discount_percent' => '5', 'tax_percent' => '13'],
        ],
    ], $replace);
});

it('creates a complete draft transactionally with resolved prices and totals', function (): void {
    $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index'));
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->sole());

    expect($quote->number)->toBe('QT-000001')
        ->and($quote->created_by)->toBe($this->actor->id)
        ->and($quote->customer_name_snapshot)->toBe('Taller Andino')
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->items[0]->unit_price)->toBe('16.800000')
        ->and($quote->items[0]->price_source->value)->toBe('price_list')
        ->and($quote->subtotal)->toBe('535.100000')
        ->and($quote->discount_total)->toBe('14.900000')
        ->and($quote->tax_total)->toBe('36.800000')
        ->and($quote->total)->toBe('571.900000');
});

it('lists searches filters and paginates quote snapshots', function (): void {
    $create = app(CreateQuote::class);
    app(TenantContext::class)->runAs($this->a, function () use ($create): void {
        foreach (range(1, 22) as $index) {
            $data = ($this->payload)(['notes' => 'Quote '.$index]);
            $create($this->actor, $data);
        }
    });
    $this->get('/quotes?q=Taller')->assertInertia(fn (Assert $page) => $page->component('Quotes/Index')->has('quotes.data', 20)->where('quotes.total', 22));
    $this->get('/quotes?q=QT-000022&page=1')->assertInertia(fn (Assert $page) => $page->has('quotes.data', 1)->where('quotes.data.0.number', 'QT-000022'));
    $this->get('/quotes?q=%25')->assertInertia(fn (Assert $page) => $page->has('quotes.data', 0));
});

it('shows edits and updates a draft without repricing unchanged lines', function (): void {
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->sole());
    app(TenantContext::class)->runAs($this->a, fn () => app(SetProductPrice::class)($this->actor, $this->list, $this->product, '20.000000'));
    $items = $quote->items->map(fn ($item) => ['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->product_id === $this->product->id ? '10' : $item->quantity, 'discount_percent' => $item->discount_percent, 'tax_percent' => $item->tax_percent])->all();
    $this->get('/quotes/'.$quote->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Quotes/Show')->where('quote.number', 'QT-000001'));
    $this->get('/quotes/'.$quote->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Quotes/Form'));
    $this->put('/quotes/'.$quote->id, ($this->payload)(['items' => $items]))->assertRedirect();

    $fresh = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->findOrFail($quote->id));
    expect($fresh->items->firstWhere('product_id', $this->product->id)->unit_price)->toBe('16.800000');
});

it('applies a changed price list only to newly added products', function (): void {
    $this->post('/quotes', ($this->payload)(['items' => [['product_id' => $this->product->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0']]]))->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->sole());
    $otherList = app(TenantContext::class)->runAs($this->a, fn () => PriceList::factory()->create(['name' => 'Especial', 'currency' => Currency::USD]));
    app(TenantContext::class)->runAs($this->a, function () use ($otherList): void {
        app(SetProductPrice::class)($this->actor, $otherList, $this->product, '14.000000');
        app(SetProductPrice::class)($this->actor, $otherList, $this->motor, '120.000000');
    });
    $saved = $quote->items->first();
    $items = [
        ['id' => $saved->id, 'product_id' => $saved->product_id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0'],
        ['product_id' => $this->motor->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0'],
    ];
    $this->put('/quotes/'.$quote->id, ($this->payload)(['price_list_id' => $otherList->id, 'items' => $items]))->assertRedirect();
    $prices = app(TenantContext::class)->runAs($this->a, fn () => Quote::findOrFail($quote->id)->items->pluck('unit_price', 'product_id'));
    expect($prices[$this->product->id])->toBe('16.800000')->and($prices[$this->motor->id])->toBe('120.000000');
});

it('refuses to edit a non draft quote', function (): void {
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->sole());
    DB::table('quotes')->where('id', $quote->id)->update(['status' => 'sent']);
    $this->get('/quotes/'.$quote->id.'/edit')->assertForbidden();
    $this->put('/quotes/'.$quote->id, ($this->payload)())->assertForbidden();
});

it('keeps customer and product snapshots stable after live records change', function (): void {
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->sole());
    app(TenantContext::class)->runAs($this->a, function (): void {
        $this->customer->update(['name' => 'Nuevo nombre']);
        $this->product->update(['name' => 'SKF Premium', 'base_price' => '25.000000']);
    });
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->findOrFail($quote->id));
    expect($quote->customer_name_snapshot)->toBe('Taller Andino')
        ->and($quote->items[0]->name_snapshot)->toBe('Rulemán SKF 6204')
        ->and($quote->items[0]->unit_price)->toBe('16.800000');
});

it('updates the customer snapshot only when the draft customer changes', function (): void {
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->with('items')->sole());
    $new = app(TenantContext::class)->runAs($this->a, fn () => Customer::factory()->create(['name' => 'Industrias del Sur']));
    $items = $quote->items->map(fn ($item) => ['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->quantity, 'discount_percent' => $item->discount_percent, 'tax_percent' => $item->tax_percent])->all();
    $this->put('/quotes/'.$quote->id, ($this->payload)(['customer_id' => $new->id, 'items' => $items]))->assertRedirect();
    expect(app(TenantContext::class)->runAs($this->a, fn () => Quote::findOrFail($quote->id)->customer_name_snapshot))->toBe('Industrias del Sur');
});

it('rejects inactive selections and leaves no partial quote', function (): void {
    app(TenantContext::class)->runAs($this->a, fn () => app(ChangeProductStatus::class)($this->actor, $this->motor, ProductStatus::Inactive));
    $this->postJson('/quotes', ($this->payload)())->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
    expect(DB::table('quotes')->count())->toBe(0)->and(DB::table('quote_items')->count())->toBe(0);

    app(TenantContext::class)->runAs($this->a, fn () => app(ChangeCustomerStatus::class)($this->actor, $this->customer, CustomerStatus::Inactive));
    $this->postJson('/quotes', ($this->payload)(['items' => [['product_id' => $this->product->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0']]]))->assertUnprocessable()->assertJsonValidationErrors('customer_id');

    app(TenantContext::class)->runAs($this->a, fn () => app(ChangeCustomerStatus::class)($this->actor, $this->customer, CustomerStatus::Active));
    app(TenantContext::class)->runAs($this->a, fn () => app(ChangeProductStatus::class)($this->actor, $this->motor, ProductStatus::Active));
    app(TenantContext::class)->runAs($this->a, fn () => app(ChangePriceListStatus::class)($this->actor, $this->list, PriceListStatus::Inactive));
    $this->postJson('/quotes', ($this->payload)())->assertUnprocessable()->assertJsonValidationErrors('price_list_id');
});

it('resolves preview totals in the backend', function (): void {
    $this->postJson('/quotes/preview', ($this->payload)())->assertOk()
        ->assertJsonPath('display_total', '571.90')
        ->assertJsonPath('lines.0.display_unit_price', '16.80');
});

it('generates tenant-local sequential numbers and locks the sequence row', function (): void {
    $sql = [];
    DB::listen(function ($query) use (&$sql): void {
        $sql[] = strtolower($query->sql);
    });
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $this->post('/quotes', ($this->payload)())->assertRedirect();
    expect(app(TenantContext::class)->runAs($this->a, fn () => Quote::orderBy('id')->pluck('number')->all()))->toBe(['QT-000001', 'QT-000002'])
        ->and(collect($sql)->contains(fn (string $query) => str_contains($query, 'quote_sequences') && str_contains($query, 'for update')))->toBeTrue();

    $actorB = memberOf($this->b);
    app(TenantContext::class)->runAs($this->b, function () use ($actorB): void {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['currency' => Currency::USD]);
        $data = ['customer_id' => $customer->id, 'price_list_id' => null, 'currency' => 'USD', 'issued_at' => '2026-09-07', 'expires_at' => null, 'notes' => null, 'terms' => null, 'items' => [['product_id' => $product->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0']]];
        expect(app(CreateQuote::class)($actorB, $data)->number)->toBe('QT-000001');
    });
});
