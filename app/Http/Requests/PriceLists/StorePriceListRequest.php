<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceLists;

use App\Domain\Money\Currency;
use App\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PriceList::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
