<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Pricing\Exceptions\PriceResolutionRejected;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;

final class ResolveProductPrice
{
    public function __invoke(Product $product, ?PriceList $priceList = null): ResolvedProductPrice
    {
        if ($priceList === null) {
            return new ResolvedProductPrice($product->base_price, $product->currency, PriceSource::BasePrice);
        }

        if ($product->company_id !== $priceList->company_id) {
            throw PriceResolutionRejected::tenantMismatch();
        }

        if ($product->currency !== $priceList->currency) {
            throw PriceResolutionRejected::currencyMismatch();
        }

        if ($priceList->status !== PriceListStatus::Active) {
            throw PriceResolutionRejected::inactivePriceList();
        }

        $override = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('price_list_id', $priceList->id)
            ->first();

        return new ResolvedProductPrice(
            $override?->price ?? $product->base_price,
            $product->currency,
            $override === null ? PriceSource::BasePrice : PriceSource::PriceList,
            $priceList->id,
            $priceList->name,
        );
    }
}
