<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Money\Currency;
use App\Domain\Pricing\ResolveProductPrice;
use App\Domain\Products\ProductStatus;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Validation\ValidationException;

final class PrepareQuoteDraft
{
    public function __construct(
        private ResolveProductPrice $resolvePrice,
        private CalculateQuote $calculateQuote,
    ) {}

    /**
     * @param  list<array{id?: int, product_id: int, quantity: string, discount_percent: string, tax_percent: string}>  $inputItems
     */
    public function __invoke(array $inputItems, Currency $currency, ?PriceList $priceList, ?Quote $existing = null): PreparedQuote
    {
        $existingItems = $existing?->items->keyBy('id') ?? collect();
        $prepared = [];

        foreach ($inputItems as $position => $input) {
            $saved = isset($input['id']) ? $existingItems->get($input['id']) : null;
            if (isset($input['id']) && ! $saved instanceof QuoteItem) {
                throw ValidationException::withMessages(["items.$position.id" => 'La línea no pertenece a esta cotización.']);
            }

            if ($saved instanceof QuoteItem && $saved->product_id === $input['product_id']) {
                if ($saved->currency_snapshot !== $currency) {
                    throw ValidationException::withMessages(['currency' => 'La moneda no puede cambiar mientras existan líneas.']);
                }
                $snapshot = $this->snapshotFromItem($saved);
            } else {
                $product = Product::query()->whereKey($input['product_id'])->firstOrFail();
                if ($product->status !== ProductStatus::Active) {
                    throw ValidationException::withMessages(["items.$position.product_id" => 'El producto debe estar activo.']);
                }
                if ($product->currency !== $currency) {
                    throw ValidationException::withMessages(["items.$position.product_id" => 'La moneda del producto no coincide con la cotización.']);
                }
                $resolved = ($this->resolvePrice)($product, $priceList);
                $snapshot = [
                    'product_id' => $product->id,
                    'sku_snapshot' => $product->sku,
                    'name_snapshot' => $product->name,
                    'description_snapshot' => $product->description,
                    'unit_snapshot' => $product->unit->value,
                    'currency_snapshot' => $product->currency->value,
                    'base_cost_snapshot' => $product->base_cost,
                    'base_price_snapshot' => $product->base_price,
                    'unit_price' => $resolved->price,
                    'price_source' => $resolved->source->value,
                    'source_price_list_id' => $resolved->priceListId,
                    'source_price_list_name_snapshot' => $resolved->priceListName,
                ];
            }

            $prepared[] = $snapshot + [
                'position' => $position + 1,
                'quantity' => $input['quantity'],
                'discount_percent' => $input['discount_percent'],
                'tax_percent' => $input['tax_percent'],
            ];
        }

        $totals = ($this->calculateQuote)(array_map(fn (array $item) => [
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_percent' => $item['discount_percent'],
            'tax_percent' => $item['tax_percent'],
        ], $prepared), $currency);

        foreach ($prepared as $index => $item) {
            $prepared[$index] = $item + $totals->lines[$index]->toArray();
        }

        return new PreparedQuote($prepared, $totals);
    }

    /** @return array<string, mixed> */
    private function snapshotFromItem(QuoteItem $item): array
    {
        return $item->only([
            'product_id', 'sku_snapshot', 'name_snapshot', 'description_snapshot', 'unit_snapshot',
            'currency_snapshot', 'base_cost_snapshot', 'base_price_snapshot', 'unit_price',
            'price_source', 'source_price_list_id', 'source_price_list_name_snapshot',
        ]);
    }
}
