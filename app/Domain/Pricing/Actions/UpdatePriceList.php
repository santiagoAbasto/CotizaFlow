<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdatePriceList
{
    public function __invoke(User $actor, PriceList $priceList, array $data): void
    {
        DB::transaction(function () use ($actor, $priceList, $data): void {
            $fresh = PriceList::query()->whereKey($priceList->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $fresh);
            if (($data['currency'] ?? $fresh->currency->value) !== $fresh->currency->value && $fresh->prices()->exists()) {
                throw ValidationException::withMessages(['currency' => 'Remueve los precios asignados antes de cambiar la moneda.']);
            }
            $fresh->fill($data)->save();
        });
    }
}
