<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Domain\Money\Currency;
use App\Http\Requests\Concerns\NormalizesDecimalInput;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuoteDraftRequest extends FormRequest
{
    use NormalizesDecimalInput;

    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            ? ($this->user()?->can('update', $quote) ?? false)
            : ($this->user()?->can('create', Quote::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['quantity', 'discount_percent', 'tax_percent'] as $field) {
                    $value = $item[$field] ?? null;
                    if (is_string($value)) {
                        $value = trim($value);
                        if (str_contains($value, ',') && ! str_contains($value, '.')) {
                            $value = str_replace(',', '.', $value);
                        }
                        $items[$index][$field] = $value;
                    }
                }
            }
        }
        $priceList = $this->input('price_list_id');
        $this->merge([
            'items' => $items,
            'price_list_id' => $priceList === '' ? null : $priceList,
            'notes' => is_string($this->input('notes')) ? trim($this->input('notes')) : $this->input('notes'),
            'terms' => is_string($this->input('terms')) ? trim($this->input('terms')) : $this->input('terms'),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1'],
            'price_list_id' => ['nullable', 'integer', 'min:1'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'issued_at' => ['required', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:id,product_id,quantity,discount_percent,tax_percent'],
            'items.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => $this->positiveDecimalRules(),
            'items.*.discount_percent' => $this->percentageRules(),
            'items.*.tax_percent' => $this->percentageRules(),
            'company_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'number' => ['prohibited'],
            'status' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'total' => ['prohibited'],
            'items.*.company_id' => ['prohibited'],
            'items.*.quote_id' => ['prohibited'],
            'items.*.unit_price' => ['prohibited'],
            'items.*.price_source' => ['prohibited'],
            'items.*.discount_amount' => ['prohibited'],
            'items.*.tax_amount' => ['prohibited'],
            'items.*.line_subtotal' => ['prohibited'],
            'items.*.line_total' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Selecciona un cliente.',
            'items.required' => 'Agrega al menos un producto.',
            'items.min' => 'Agrega al menos un producto.',
            'items.*.quantity.regex' => 'Ingresa una cantidad mayor a cero con hasta 6 decimales.',
            'items.*.discount_percent.regex' => 'El descuento debe estar entre 0 y 100.',
            'items.*.tax_percent.regex' => 'El impuesto debe estar entre 0 y 100.',
            '*.prohibited' => 'Este valor es calculado por CotizaFlow y no puede enviarse.',
        ];
    }
}
