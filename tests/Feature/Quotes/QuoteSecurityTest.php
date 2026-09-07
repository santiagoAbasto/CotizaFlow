<?php

declare(strict_types=1);

use App\Domain\Money\Currency;
use App\Domain\Products\Actions\ChangeProductStatus;
use App\Domain\Products\ProductStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
    $this->actor = memberOf($this->a);
    app(TenantContext::class)->runAs($this->a, function (): void {
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create(['currency' => Currency::USD]);
        $this->list = PriceList::factory()->create(['currency' => Currency::USD]);
    });
    app(TenantContext::class)->runAs($this->b, function (): void {
        $this->foreignCustomer = Customer::factory()->create();
        $this->foreignProduct = Product::factory()->create(['currency' => Currency::USD]);
        $this->foreignList = PriceList::factory()->create(['currency' => Currency::USD]);
    });
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
    $this->payload = fn (array $replace = []): array => array_replace(['customer_id' => $this->customer->id, 'price_list_id' => $this->list->id, 'currency' => 'USD', 'issued_at' => '2026-09-07', 'expires_at' => null, 'notes' => null, 'terms' => null, 'items' => [['product_id' => $this->product->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0']]], $replace);
});

it('rejects ownership actor price and total tampering', function (): void {
    foreach ([
        ['company_id' => $this->b->id], ['tenant_id' => $this->b->id], ['created_by' => 999], ['total' => '0.01'], ['subtotal' => '0.01'],
    ] as $attack) {
        $field = array_key_first($attack);
        $this->postJson('/quotes', ($this->payload)($attack))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    foreach (['unit_price', 'line_total', 'price_source', 'company_id'] as $field) {
        $items = ($this->payload)()['items'];
        $items[0][$field] = $field === 'price_source' ? 'base_price' : '0.01';
        $this->postJson('/quotes', ($this->payload)(['items' => $items]))->assertUnprocessable()->assertJsonValidationErrors("items.0.$field");
    }
    $items = ($this->payload)()['items'];
    $items[0]['sku_snapshot'] = 'SKU-INJECTED';
    $this->postJson('/quotes', ($this->payload)(['items' => $items]))->assertUnprocessable()->assertJsonValidationErrors('items.0');
    expect(Quote::query()->count())->toBe(0);
});

it('rejects every foreign related id without writing a quote', function (): void {
    $this->postJson('/quotes', ($this->payload)(['customer_id' => $this->foreignCustomer->id]))->assertNotFound();
    $this->postJson('/quotes', ($this->payload)(['price_list_id' => $this->foreignList->id]))->assertNotFound();
    $this->postJson('/quotes', ($this->payload)(['items' => [['product_id' => $this->foreignProduct->id, 'quantity' => '1', 'discount_percent' => '0', 'tax_percent' => '0']]]))->assertNotFound();
    expect(Quote::query()->count())->toBe(0);
    $this->postJson('/quotes/preview', ($this->payload)(['customer_id' => $this->foreignCustomer->id]))->assertNotFound();
});

it('returns 404 for foreign quote show edit update and preview', function (): void {
    $foreignActor = memberOf($this->b);
    $foreignQuote = app(TenantContext::class)->runAs($this->b, function () use ($foreignActor): Quote {
        $quote = Quote::factory()->create(['customer_id' => $this->foreignCustomer->id, 'created_by' => $foreignActor->id]);

        return $quote;
    });
    $this->get('/quotes/'.$foreignQuote->id)->assertNotFound();
    $this->get('/quotes/'.$foreignQuote->id.'/edit')->assertNotFound();
    $this->put('/quotes/'.$foreignQuote->id, ($this->payload)())->assertNotFound();
    $this->post('/quotes/'.$foreignQuote->id.'/preview', ($this->payload)())->assertNotFound();
});

it('enforces cross tenant customer and product relations at the database boundary', function (): void {
    expect(fn () => app(TenantContext::class)->runAs($this->a, fn () => Quote::factory()->create([
        'customer_id' => $this->foreignCustomer->id,
        'created_by' => $this->actor->id,
    ])))->toThrow(QueryException::class);

    $this->post('/quotes', ($this->payload)())->assertRedirect();
    $quote = app(TenantContext::class)->runAs($this->a, fn () => Quote::query()->sole());
    expect(fn () => app(TenantContext::class)->runAs($this->a, fn () => QuoteItem::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $this->foreignProduct->id,
    ])))->toThrow(QueryException::class);
});

it('does not expose foreign or inactive records through quote lookups', function (): void {
    app(TenantContext::class)->runAs($this->a, fn () => app(ChangeProductStatus::class)($this->actor, $this->product, ProductStatus::Inactive));
    $this->getJson('/quotes/customers/search?q='.urlencode($this->foreignCustomer->name))->assertOk()->assertExactJson([]);
    $this->getJson('/quotes/products/search?currency=USD&q='.urlencode($this->foreignProduct->sku))->assertOk()->assertExactJson([]);
    $this->getJson('/quotes/products/search?currency=USD&q='.urlencode($this->product->sku))->assertOk()->assertExactJson([]);
});

it('validates quantities discounts taxes dates and item presence', function (): void {
    foreach ([['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '1.1234567'], ['discount_percent' => '100.1'], ['tax_percent' => '-1']] as $invalid) {
        $items = ($this->payload)()['items'];
        $items[0] = array_replace($items[0], $invalid);
        $field = array_key_first($invalid);
        $this->postJson('/quotes', ($this->payload)(['items' => $items]))->assertUnprocessable()->assertJsonValidationErrors("items.0.$field");
    }
    $this->postJson('/quotes', ($this->payload)(['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->postJson('/quotes', ($this->payload)(['expires_at' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('expires_at');
});
