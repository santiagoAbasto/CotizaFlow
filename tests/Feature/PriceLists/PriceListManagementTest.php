<?php

declare(strict_types=1);

use App\Domain\Money\Currency;
use App\Domain\Pricing\Exceptions\PriceResolutionRejected;
use App\Domain\Pricing\PriceSource;
use App\Domain\Pricing\ResolveProductPrice;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
    $this->actor = memberOf($this->a);
    $this->product = app(TenantContext::class)->runAs($this->a, fn () => Product::factory()->create(['sku' => 'SKF-6204-2RS', 'name' => 'Rulemán sellado', 'base_price' => '100.000000', 'currency' => Currency::USD]));
    $this->list = app(TenantContext::class)->runAs($this->a, fn () => PriceList::factory()->create(['name' => 'Mayorista', 'currency' => Currency::USD]));
    $this->foreignProduct = app(TenantContext::class)->runAs($this->b, fn () => Product::factory()->create(['currency' => Currency::USD]));
    $this->foreignList = app(TenantContext::class)->runAs($this->b, fn () => PriceList::factory()->create(['currency' => Currency::USD]));
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
});

it('creates updates searches and transitions a tenant-owned price list', function (): void {
    $this->post('/price-lists', ['name' => ' Distribuidor ', 'currency' => 'BOB'])->assertRedirect();
    $created = DB::table('price_lists')->where('name', 'Distribuidor')->first();
    expect($created->company_id)->toBe($this->a->id)->and($created->currency)->toBe('BOB');
    $this->get('/price-lists?q=Mayor')->assertOk()->assertInertia(fn (Assert $page) => $page->component('PriceLists/Index')->has('priceLists.data', 1)->where('priceLists.data.0.id', $this->list->id));
    $this->get('/price-lists?q='.$this->foreignList->name)->assertOk()->assertInertia(fn (Assert $page) => $page->has('priceLists.data', 0));
    $this->get('/price-lists?q=_')->assertOk()->assertInertia(fn (Assert $page) => $page->has('priceLists.data', 0));
    $this->put('/price-lists/'.$this->list->id, ['name' => 'Mayorista industrial', 'currency' => 'USD'])->assertRedirect();
    foreach (['inactive', 'active'] as $status) {
        $this->patch('/price-lists/'.$this->list->id.'/status', ['status' => $status])->assertRedirect();
    }
    $this->assertDatabaseHas('price_lists', ['id' => $this->list->id, 'company_id' => $this->a->id, 'name' => 'Mayorista industrial', 'status' => 'active']);
});

it('rejects price list tenant status and unsupported currency injection', function (): void {
    $this->postJson('/price-lists', ['name' => 'Attack', 'currency' => 'USD', 'company_id' => $this->b->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    $this->postJson('/price-lists', ['name' => 'Attack', 'currency' => 'USD', 'status' => 'inactive'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson('/price-lists', ['name' => 'Attack', 'currency' => 'EUR'])->assertUnprocessable()->assertJsonValidationErrors('currency');
});

it('paginates price lists without leaving the tenant boundary', function (): void {
    app(TenantContext::class)->runAs($this->a, fn () => PriceList::factory()->count(24)->create());
    $this->get('/price-lists?status=active')->assertInertia(fn (Assert $page) => $page->has('priceLists.data', 20)->where('priceLists.total', 25));
    $this->get('/price-lists?status=active&page=2')->assertInertia(fn (Assert $page) => $page->has('priceLists.data', 5));
});

it('resolves base price override and fallback with exact provenance', function (): void {
    $resolve = app(ResolveProductPrice::class);
    $base = app(TenantContext::class)->runAs($this->a, fn () => $resolve($this->product));
    expect($base->price)->toBe('100.000000')->and($base->source)->toBe(PriceSource::BasePrice);

    $this->put('/price-lists/'.$this->list->id.'/products/'.$this->product->id, ['price' => '85.123456'])->assertRedirect();
    $override = app(TenantContext::class)->runAs($this->a, fn () => $resolve($this->product, $this->list));
    expect($override->price)->toBe('85.123456')->and($override->source)->toBe(PriceSource::PriceList)->and($override->toArray()['display_price'])->toBe('85.12');

    $this->delete('/price-lists/'.$this->list->id.'/products/'.$this->product->id)->assertRedirect();
    $fallback = app(TenantContext::class)->runAs($this->a, fn () => $resolve($this->product, $this->list));
    expect($fallback->price)->toBe('100.000000')->and($fallback->source)->toBe(PriceSource::BasePrice)->and($fallback->priceListId)->toBe($this->list->id);
});

it('rounds presentation half up without changing the persisted decimal', function (): void {
    $this->put('/price-lists/'.$this->list->id.'/products/'.$this->product->id, ['price' => '85.125000'])->assertRedirect();
    $resolved = app(TenantContext::class)->runAs($this->a, fn () => app(ResolveProductPrice::class)($this->product, $this->list));
    expect($resolved->price)->toBe('85.125000')->and($resolved->toArray()['display_price'])->toBe('85.13');
    $this->assertDatabaseHas('product_prices', ['price' => '85.125000']);
});

it('updates an existing override instead of duplicating it', function (): void {
    $url = '/price-lists/'.$this->list->id.'/products/'.$this->product->id;
    $this->put($url, ['price' => '85.00'])->assertRedirect();
    $this->put($url, ['price' => '80.000001'])->assertRedirect();
    expect(DB::table('product_prices')->where('price_list_id', $this->list->id)->where('product_id', $this->product->id)->count())->toBe(1);
    $this->assertDatabaseHas('product_prices', ['company_id' => $this->a->id, 'price' => '80.000001']);
});

it('rejects currency mismatch inactive entities and manipulated relationship ids', function (): void {
    $bob = app(TenantContext::class)->runAs($this->a, fn () => Product::factory()->create(['currency' => Currency::BOB]));
    $url = '/price-lists/'.$this->list->id.'/products/'.$bob->id;
    $this->putJson($url, ['price' => '80.00'])->assertUnprocessable()->assertJsonValidationErrors('price');
    $this->putJson('/price-lists/'.$this->list->id.'/products/'.$this->product->id, ['price' => '80.00', 'product_id' => $this->foreignProduct->id])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    $this->patch('/products/'.$this->product->id.'/status', ['status' => 'inactive'])->assertRedirect();
    $this->putJson('/price-lists/'.$this->list->id.'/products/'.$this->product->id, ['price' => '80.00'])->assertUnprocessable()->assertJsonValidationErrors('price');
});

it('blocks all foreign list product and assignment routes', function (): void {
    $this->get('/price-lists/'.$this->foreignList->id)->assertNotFound();
    $this->get('/price-lists/'.$this->foreignList->id.'/edit')->assertNotFound();
    $this->put('/price-lists/'.$this->foreignList->id, ['name' => 'Attack'])->assertNotFound();
    $this->patch('/price-lists/'.$this->foreignList->id.'/status', ['status' => 'inactive'])->assertNotFound();
    $this->put('/price-lists/'.$this->list->id.'/products/'.$this->foreignProduct->id, ['price' => '1.00'])->assertNotFound();
    $this->put('/price-lists/'.$this->foreignList->id.'/products/'.$this->product->id, ['price' => '1.00'])->assertNotFound();
});

it('rejects a foreign price list in product price consultation', function (): void {
    $this->get('/products/'.$this->product->id.'?price_list='.$this->foreignList->id)->assertNotFound();
    expect(fn () => app(TenantContext::class)->runAs($this->a, fn () => app(ResolveProductPrice::class)($this->product, $this->foreignList)))
        ->toThrow(PriceResolutionRejected::class);
});

it('enforces cross-tenant relations at the database boundary too', function (): void {
    expect(fn () => app(TenantContext::class)->runAs($this->a, function (): void {
        $price = new ProductPrice(['price' => '1.000000']);
        $price->price_list_id = $this->list->id;
        $price->product_id = $this->foreignProduct->id;
        $price->save();
    }))->toThrow(QueryException::class);
    expect(DB::table('product_prices')->count())->toBe(0);
});

it('refuses currency changes while overrides exist and allows them after removal', function (): void {
    $this->put('/price-lists/'.$this->list->id.'/products/'.$this->product->id, ['price' => '85.00'])->assertRedirect();
    $productPayload = ['sku' => $this->product->sku, 'name' => $this->product->name, 'unit' => 'unit', 'base_cost' => '', 'base_price' => '100.00', 'currency' => 'BOB'];
    $this->putJson('/products/'.$this->product->id, $productPayload)->assertUnprocessable()->assertJsonValidationErrors('currency');
    $this->putJson('/price-lists/'.$this->list->id, ['name' => 'Mayorista', 'currency' => 'BOB'])->assertUnprocessable()->assertJsonValidationErrors('currency');
    $this->delete('/price-lists/'.$this->list->id.'/products/'.$this->product->id)->assertRedirect();
    $this->put('/products/'.$this->product->id, $productPayload)->assertRedirect();
});

it('rejects inactive list resolution rather than silently applying it', function (): void {
    $this->patch('/price-lists/'.$this->list->id.'/status', ['status' => 'inactive'])->assertRedirect();
    $this->list->refresh();
    expect(fn () => app(TenantContext::class)->runAs($this->a, fn () => app(ResolveProductPrice::class)($this->product, $this->list)))
        ->toThrow(PriceResolutionRejected::class);
    $selectable = app(TenantContext::class)->runAs($this->a, fn () => PriceList::query()->selectable()->whereKey($this->list->id)->exists());
    expect($selectable)->toBeFalse();
});
