<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pricing\Actions\ChangePriceListStatus;
use App\Domain\Pricing\Actions\CreatePriceList;
use App\Domain\Pricing\Actions\RemoveProductPrice;
use App\Domain\Pricing\Actions\SetProductPrice;
use App\Domain\Pricing\Actions\UpdatePriceList;
use App\Domain\Pricing\PriceListStatus;
use App\Http\Requests\PriceLists\ChangePriceListStatusRequest;
use App\Http\Requests\PriceLists\SetProductPriceRequest;
use App\Http\Requests\PriceLists\StorePriceListRequest;
use App\Http\Requests\PriceLists\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class PriceListController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PriceList::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(PriceListStatus::class)]]);
        $q = trim($filters['q'] ?? '');
        $query = PriceList::query()->withCount('prices');
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('PriceLists/Index', [
            'priceLists' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()->through(fn (PriceList $list) => $this->serialize($list)),
            'filters' => ['q' => $q, 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', PriceList::class);

        return Inertia::render('PriceLists/Form', ['priceList' => null]);
    }

    public function store(StorePriceListRequest $request, CreatePriceList $create)
    {
        $priceList = $create($request->user(), $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('price-lists.show', $priceList)->with('success', 'Lista de precios creada');
    }

    public function show(Request $request, PriceList $priceList)
    {
        Gate::authorize('view', $priceList);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim($filters['q'] ?? '');
        $products = Product::query()
            ->where('currency', $priceList->currency->value)
            ->with(['prices' => fn ($query) => $query->where('price_list_id', $priceList->id)]);
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $products->whereRaw("(sku LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!' OR category LIKE ? ESCAPE '!')", array_fill(0, 3, $pattern));
        }

        return Inertia::render('PriceLists/Show', [
            'priceList' => $this->serialize($priceList),
            'products' => $products->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()->through(function (Product $product): array {
                $override = $product->prices->first();

                return [
                    ...$product->only(['id', 'sku', 'name', 'base_price']),
                    'status' => $product->status->value,
                    'currency' => $product->currency->value,
                    'display_base_price' => $product->currency->format($product->base_price),
                    'override_price' => $override?->price,
                    'display_override_price' => $override === null ? null : $product->currency->format($override->price),
                ];
            }),
            'filters' => ['q' => $q],
        ]);
    }

    public function edit(PriceList $priceList)
    {
        Gate::authorize('update', $priceList);

        return Inertia::render('PriceLists/Form', ['priceList' => $this->serialize($priceList)]);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList, UpdatePriceList $update)
    {
        $update($request->user(), $priceList, $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('price-lists.show', $priceList)->with('success', 'Lista de precios actualizada');
    }

    public function status(ChangePriceListStatusRequest $request, PriceList $priceList, ChangePriceListStatus $change)
    {
        $change($request->user(), $priceList, PriceListStatus::from($request->validated('status')));

        return to_route('price-lists.show', $priceList)->with('success', 'Estado de la lista actualizado');
    }

    public function setProductPrice(SetProductPriceRequest $request, PriceList $priceList, Product $product, SetProductPrice $set)
    {
        $set($request->user(), $priceList, $product, $request->validated('price'));

        return to_route('price-lists.show', [$priceList, 'q' => $request->validated('q', '')])->with('success', 'Precio de lista guardado');
    }

    public function removeProductPrice(Request $request, PriceList $priceList, Product $product, RemoveProductPrice $remove)
    {
        Gate::authorize('managePrices', $priceList);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $remove($request->user(), $priceList, $product);

        return to_route('price-lists.show', [$priceList, 'q' => $filters['q'] ?? ''])->with('success', 'Override removido; se usará el precio base');
    }

    /** @return array<string, mixed> */
    private function serialize(PriceList $priceList): array
    {
        return [
            ...$priceList->only(['id', 'name', 'prices_count', 'updated_at']),
            'currency' => $priceList->currency->value,
            'status' => $priceList->status->value,
        ];
    }
}
