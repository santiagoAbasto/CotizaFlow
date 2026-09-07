<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pricing\ResolveProductPrice;
use App\Domain\Products\Actions\ChangeProductStatus;
use App\Domain\Products\Actions\CreateProduct;
use App\Domain\Products\Actions\UpdateProduct;
use App\Domain\Products\ProductStatus;
use App\Domain\Products\ProductUnit;
use App\Http\Requests\Products\ChangeProductStatusRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class ProductController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Product::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(ProductStatus::class)]]);
        $q = trim($filters['q'] ?? '');
        $query = Product::query();
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->whereRaw("(sku LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!' OR category LIKE ? ESCAPE '!')", array_fill(0, 4, $pattern));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Products/Index', [
            'products' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()->through(fn (Product $product) => $this->serialize($product)),
            'filters' => ['q' => $q, 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('Products/Form', ['product' => null, 'units' => $this->units()]);
    }

    public function store(StoreProductRequest $request, CreateProduct $create)
    {
        $product = $create($request->user(), $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('products.show', $product)->with('success', 'Producto creado');
    }

    public function show(Request $request, Product $product, ResolveProductPrice $resolve)
    {
        Gate::authorize('view', $product);
        $data = $request->validate(['price_list' => ['nullable', 'integer', 'min:1']]);
        $priceLists = PriceList::query()->selectable()->where('currency', $product->currency->value)->orderBy('name')->get(['id', 'name', 'currency']);
        $selected = null;
        if (! empty($data['price_list'])) {
            $selected = PriceList::query()->selectable()
                ->where('currency', $product->currency->value)
                ->whereKey((int) $data['price_list'])
                ->firstOrFail();
        }
        $product->load('prices.priceList');

        return Inertia::render('Products/Show', [
            'product' => $this->serialize($product) + ['description' => $product->description],
            'priceLists' => $priceLists->map->only(['id', 'name', 'currency']),
            'selectedPriceList' => $selected?->id,
            'resolvedPrice' => $resolve($product, $selected)->toArray(),
            'overrides' => $product->prices->map(fn ($price) => [
                'price_list_id' => $price->price_list_id,
                'price_list_name' => $price->priceList->name,
                'status' => $price->priceList->status->value,
                'price' => $price->price,
                'display_price' => $product->currency->format($price->price),
            ])->values(),
        ]);
    }

    public function edit(Product $product)
    {
        Gate::authorize('update', $product);

        return Inertia::render('Products/Form', ['product' => $this->serialize($product) + ['description' => $product->description], 'units' => $this->units()]);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $update)
    {
        $update($request->user(), $product, $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('products.show', $product)->with('success', 'Producto actualizado');
    }

    public function status(ChangeProductStatusRequest $request, Product $product, ChangeProductStatus $change)
    {
        $change($request->user(), $product, ProductStatus::from($request->validated('status')));

        return to_route('products.show', $product)->with('success', 'Estado del producto actualizado');
    }

    /** @return array<string, mixed> */
    private function serialize(Product $product): array
    {
        return [
            ...$product->only(['id', 'sku', 'name', 'category', 'base_cost', 'base_price', 'updated_at']),
            'unit' => $product->unit->value,
            'unit_label' => $product->unit->label(),
            'currency' => $product->currency->value,
            'status' => $product->status->value,
            'display_base_cost' => $product->base_cost === null ? null : $product->currency->format($product->base_cost),
            'display_base_price' => $product->currency->format($product->base_price),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function units(): array
    {
        return array_map(fn (ProductUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()], ProductUnit::cases());
    }
}
