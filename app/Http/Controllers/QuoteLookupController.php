<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Money\Currency;
use App\Domain\Pricing\ResolveProductPrice;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class QuoteLookupController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        Gate::authorize('create', Quote::class);
        $q = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $query = Customer::query()->selectable();
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->whereRaw("(name LIKE ? ESCAPE '!' OR legal_name LIKE ? ESCAPE '!' OR tax_id LIKE ? ESCAPE '!')", array_fill(0, 3, $pattern));
        }

        return response()->json($query->orderBy('name')->limit(12)->get(['id', 'name', 'legal_name', 'tax_id']));
    }

    public function products(Request $request, ResolveProductPrice $resolve): JsonResponse
    {
        Gate::authorize('create', Quote::class);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'price_list_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $currency = Currency::from($data['currency']);
        $priceList = empty($data['price_list_id']) ? null : PriceList::query()->selectable()->where('currency', $currency->value)->whereKey($data['price_list_id'])->firstOrFail();
        $q = trim($data['q'] ?? '');
        $products = Product::query()->selectable()->where('currency', $currency->value);
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $products->whereRaw("(sku LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!')", [$pattern, $pattern]);
        }

        return response()->json($products->orderBy('name')->limit(12)->get()->map(function (Product $product) use ($resolve, $priceList): array {
            $price = $resolve($product, $priceList);

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit->value,
                'unit_label' => $product->unit->label(),
                ...$price->toArray(),
            ];
        }));
    }
}
