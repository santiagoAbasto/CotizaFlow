<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\Products\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateProduct
{
    public function __invoke(User $actor, array $data): Product
    {
        Gate::forUser($actor)->authorize('create', Product::class);
        $product = new Product($data);
        $product->status = ProductStatus::Active;
        $product->save();

        return $product;
    }
}
