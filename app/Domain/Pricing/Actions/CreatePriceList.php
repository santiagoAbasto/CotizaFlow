<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\PriceListStatus;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreatePriceList
{
    public function __invoke(User $actor, array $data): PriceList
    {
        Gate::forUser($actor)->authorize('create', PriceList::class);
        $priceList = new PriceList($data);
        $priceList->status = PriceListStatus::Active;
        $priceList->save();

        return $priceList;
    }
}
