<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceLists;

use App\Domain\Pricing\PriceListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangePriceListStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('priceList')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(PriceListStatus::class)], 'company_id' => ['prohibited'], 'tenant_id' => ['prohibited']];
    }
}
