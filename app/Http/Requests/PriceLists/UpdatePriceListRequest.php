<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceLists;

final class UpdatePriceListRequest extends StorePriceListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('priceList')) ?? false;
    }
}
