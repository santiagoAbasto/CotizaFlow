<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateProduct
{
    public function __invoke(User $actor, Product $product, array $data): void
    {
        DB::transaction(function () use ($actor, $product, $data): void {
            $fresh = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $fresh);
            if (($data['currency'] ?? $fresh->currency->value) !== $fresh->currency->value && $fresh->prices()->exists()) {
                throw ValidationException::withMessages(['currency' => 'Remueve los precios por lista antes de cambiar la moneda.']);
            }
            $fresh->fill($data)->save();
        });
    }
}
