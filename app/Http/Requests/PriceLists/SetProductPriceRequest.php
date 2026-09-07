<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceLists;

use App\Domain\Pricing\PriceListStatus;
use App\Domain\Products\ProductStatus;
use App\Http\Requests\Concerns\NormalizesDecimalInput;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SetProductPriceRequest extends FormRequest
{
    use NormalizesDecimalInput;

    public function authorize(): bool
    {
        return $this->user()?->can('managePrices', $this->route('priceList')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDecimal('price');
    }

    public function rules(): array
    {
        return ['price' => $this->decimalRules(), 'q' => ['nullable', 'string', 'max:100'], 'company_id' => ['prohibited'], 'tenant_id' => ['prohibited'], 'product_id' => ['prohibited'], 'price_list_id' => ['prohibited']];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $priceList = $this->route('priceList');
            $product = $this->route('product');
            if (! $priceList instanceof PriceList || ! $product instanceof Product) {
                return;
            }
            if ($priceList->company_id !== $product->company_id) {
                $validator->errors()->add('price', 'El producto y la lista deben pertenecer a la misma empresa.');
            } elseif ($priceList->currency !== $product->currency) {
                $validator->errors()->add('price', 'La moneda del producto no coincide con la lista.');
            } elseif ($priceList->status !== PriceListStatus::Active || $product->status !== ProductStatus::Active) {
                $validator->errors()->add('price', 'El producto y la lista deben estar activos.');
            }
        }];
    }
}
