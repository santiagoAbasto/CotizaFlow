<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Domain\Money\Currency;
use App\Domain\Products\ProductUnit;
use App\Domain\Tenancy\TenantContext;
use App\Http\Requests\Concerns\NormalizesDecimalInput;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use NormalizesDecimalInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['sku', 'name', 'description', 'category'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        $this->normalizeDecimal('base_cost');
        $this->normalizeDecimal('base_price');
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $sku = Rule::unique('products', 'sku')
            ->where(fn ($query) => $query->where('company_id', app(TenantContext::class)->id()));
        if ($product instanceof Product) {
            $sku->ignore($product->id);
        }

        return [
            'sku' => ['required', 'string', 'max:100', $sku],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'base_cost' => $this->decimalRules(nullable: true),
            'base_price' => $this->decimalRules(),
            'currency' => ['required', Rule::enum(Currency::class)],
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'Ingresa el SKU del producto.',
            'sku.unique' => 'Ya existe un producto con este SKU en la empresa.',
            'name.required' => 'Ingresa el nombre del producto.',
            'base_price.required' => 'Ingresa el precio base.',
            '*.regex' => 'Usa un importe positivo o cero con hasta 6 decimales.',
            '*.enum' => 'Selecciona una opción válida.',
            '*.max' => 'El texto supera la longitud permitida.',
            '*.prohibited' => 'Este campo no se puede modificar desde este formulario.',
        ];
    }
}
