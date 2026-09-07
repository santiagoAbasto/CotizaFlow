<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\Products\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ChangeProductStatus
{
    public function __invoke(User $actor, Product $product, ProductStatus $status): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $product);
        $product->status = $status;
        $product->save();
        Log::info('product.status_changed', ['company_id' => $product->company_id, 'product_id' => $product->id, 'actor_id' => $actor->id, 'status' => $status->value]);
    }
}
