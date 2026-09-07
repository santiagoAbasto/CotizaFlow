<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
    $this->actor = memberOf($this->a);
    $this->own = app(TenantContext::class)->runAs($this->a, fn () => Product::factory()->create(['sku' => 'SKF-6204-2RS', 'name' => 'Rulemán sellado 6204', 'base_price' => '18.400000']));
    $this->foreign = app(TenantContext::class)->runAs($this->b, fn () => Product::factory()->create(['sku' => 'FOREIGN-6204', 'name' => 'Foreign motor']));
    $this->actingAs($this->actor)->withSession([ResolveTenantContext::SESSION_KEY => $this->a->id]);
});

it('lists searches filters and paginates only products from the active tenant', function (): void {
    foreach (['sku', 'name', 'description', 'category'] as $field) {
        app(TenantContext::class)->runAs($this->a, fn () => $this->own->update([
            'sku' => 'SKF-6204-2RS', 'name' => 'Rulemán sellado', 'description' => null, 'category' => null,
            $field => 'needle-6204',
        ]));
        $this->get('/products?q=needle-6204')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Products/Index')->has('products.data', 1)->where('products.data.0.id', $this->own->id));
    }
    $this->get('/products?q=Foreign')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    $this->get('/products?q=%25')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    app(TenantContext::class)->runAs($this->a, fn () => Product::factory()->count(24)->create());
    $this->get('/products?status=active')->assertInertia(fn (Assert $page) => $page->has('products.data', 20)->where('products.total', 25));
    $this->get('/products?status=active&page=2')->assertInertia(fn (Assert $page) => $page->has('products.data', 5));
});

it('creates exact decimal prices under the active tenant and normalizes decimal comma', function (): void {
    $this->post('/products', [
        'sku' => ' WEG-3HP ', 'name' => ' Motor WEG 3HP ', 'unit' => 'piece',
        'base_cost' => '120,123456', 'base_price' => '149,000001', 'currency' => 'USD',
    ])->assertRedirect();

    $product = DB::table('products')->where('sku', 'WEG-3HP')->first();
    expect($product)->not->toBeNull()
        ->and($product->company_id)->toBe($this->a->id)
        ->and($product->base_cost)->toBe('120.123456')
        ->and($product->base_price)->toBe('149.000001');
});

it('enforces tenant-aware SKU uniqueness without blocking another company', function (): void {
    $this->postJson('/products', ['sku' => 'SKF-6204-2RS', 'name' => 'Duplicate', 'unit' => 'unit', 'base_price' => '1.00', 'currency' => 'USD'])
        ->assertUnprocessable()->assertJsonValidationErrors('sku');
    app(TenantContext::class)->runAs($this->b, fn () => Product::factory()->create(['sku' => 'SKF-6204-2RS']));
    expect(DB::table('products')->where('sku', 'SKF-6204-2RS')->count())->toBe(2);
});

it('rejects tenant status and unsupported money payloads', function (): void {
    $base = ['sku' => 'ATTACK', 'name' => 'Attack', 'unit' => 'unit', 'base_price' => '10.00', 'currency' => 'USD'];
    $this->postJson('/products', $base + ['company_id' => $this->b->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    $this->postJson('/products', $base + ['status' => 'inactive'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson('/products', array_replace($base, ['currency' => 'EUR']))->assertUnprocessable()->assertJsonValidationErrors('currency');
    foreach (['-1.00', '1e3', '1.1234567', '999999999999999.00'] as $invalid) {
        $this->postJson('/products', array_replace($base, ['base_price' => $invalid]))->assertUnprocessable()->assertJsonValidationErrors('base_price');
    }
});

it('updates product data without changing ownership and transitions status', function (): void {
    $payload = ['sku' => 'SKF-6204', 'name' => 'Rulemán SKF 6204', 'description' => 'Sellado', 'category' => 'Rodamientos', 'unit' => 'piece', 'base_cost' => '', 'base_price' => '19.50', 'currency' => 'USD'];
    $this->put('/products/'.$this->own->id, $payload)->assertRedirect();
    foreach (['inactive', 'active'] as $status) {
        $this->patch('/products/'.$this->own->id.'/status', ['status' => $status])->assertRedirect();
        $this->get('/products/'.$this->own->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.status', $status));
    }
    $this->assertDatabaseHas('products', ['id' => $this->own->id, 'company_id' => $this->a->id, 'sku' => 'SKF-6204']);
});

it('excludes inactive products from the quote-selection contract', function (): void {
    $this->patch('/products/'.$this->own->id.'/status', ['status' => 'inactive'])->assertRedirect();
    $selectable = app(TenantContext::class)->runAs($this->a, fn () => Product::query()->selectable()->whereKey($this->own->id)->exists());
    expect($selectable)->toBeFalse();
});

it('returns 404 for every foreign product route and leaves it unchanged', function (): void {
    $url = '/products/'.$this->foreign->id;
    $this->get($url)->assertNotFound();
    $this->get($url.'/edit')->assertNotFound();
    $this->put($url, ['sku' => 'ATTACK'])->assertNotFound();
    $this->patch($url.'/status', ['status' => 'inactive'])->assertNotFound();
    $this->assertDatabaseHas('products', ['id' => $this->foreign->id, 'company_id' => $this->b->id, 'name' => 'Foreign motor', 'status' => 'active']);
});
