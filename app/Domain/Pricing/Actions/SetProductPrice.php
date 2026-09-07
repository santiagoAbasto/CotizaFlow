<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Exceptions\PriceResolutionRejected;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Products\ProductStatus;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class SetProductPrice
{
    public function __invoke(User $actor, PriceList $priceList, Product $product, string $price): ProductPrice
    {
        $productPrice = DB::transaction(function () use ($actor, $priceList, $product, $price): ProductPrice {
            $freshList = PriceList::query()->whereKey($priceList->id)->lockForUpdate()->firstOrFail();
            $freshProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('managePrices', $freshList);
            $this->assertCompatible($freshList, $freshProduct);
            $now = now();
            ProductPrice::query()->upsert([[
                'price_list_id' => $freshList->id,
                'product_id' => $freshProduct->id,
                'price' => $price,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['company_id', 'price_list_id', 'product_id'], ['price', 'updated_at']);

            return ProductPrice::query()
                ->where('price_list_id', $freshList->id)
                ->where('product_id', $freshProduct->id)
                ->firstOrFail();
        });

        Log::info('product_price.set', ['company_id' => $priceList->company_id, 'price_list_id' => $priceList->id, 'product_id' => $product->id, 'actor_id' => $actor->id]);

        return $productPrice;
    }

    private function assertCompatible(PriceList $priceList, Product $product): void
    {
        if ($priceList->company_id !== $product->company_id) {
            throw PriceResolutionRejected::tenantMismatch();
        }
        if ($priceList->currency !== $product->currency) {
            throw PriceResolutionRejected::currencyMismatch();
        }
        if ($priceList->status !== PriceListStatus::Active) {
            throw PriceResolutionRejected::inactivePriceList();
        }
        if ($product->status !== ProductStatus::Active) {
            throw PriceResolutionRejected::inactiveProduct();
        }
    }
}
