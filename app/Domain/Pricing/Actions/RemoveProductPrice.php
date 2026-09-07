<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Exceptions\PriceResolutionRejected;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RemoveProductPrice
{
    public function __invoke(User $actor, PriceList $priceList, Product $product): void
    {
        Gate::forUser($actor)->authorize('managePrices', $priceList);
        if ($priceList->company_id !== $product->company_id) {
            throw PriceResolutionRejected::tenantMismatch();
        }

        ProductPrice::query()
            ->where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->delete();

        Log::info('product_price.removed', ['company_id' => $priceList->company_id, 'price_list_id' => $priceList->id, 'product_id' => $product->id, 'actor_id' => $actor->id]);
    }
}
