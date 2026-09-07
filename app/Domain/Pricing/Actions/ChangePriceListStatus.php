<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\PriceListStatus;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ChangePriceListStatus
{
    public function __invoke(User $actor, PriceList $priceList, PriceListStatus $status): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $priceList);
        $priceList->status = $status;
        $priceList->save();
        Log::info('price_list.status_changed', ['company_id' => $priceList->company_id, 'price_list_id' => $priceList->id, 'actor_id' => $actor->id, 'status' => $status->value]);
    }
}
