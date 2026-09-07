<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Quotes\PrepareQuoteDraft;
use App\Http\Requests\Quotes\QuoteDraftRequest;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class QuotePreviewController extends Controller
{
    public function create(QuoteDraftRequest $request, PrepareQuoteDraft $prepare): JsonResponse
    {
        return $this->respond($request, $prepare, null);
    }

    public function update(QuoteDraftRequest $request, Quote $quote, PrepareQuoteDraft $prepare): JsonResponse
    {
        return $this->respond($request, $prepare, $quote);
    }

    private function respond(QuoteDraftRequest $request, PrepareQuoteDraft $prepare, ?Quote $quote): JsonResponse
    {
        $customer = Customer::query()->whereKey($request->validated('customer_id'))->firstOrFail();
        if (($quote === null || $customer->id !== $quote->customer_id) && $customer->status->value !== 'active') {
            throw ValidationException::withMessages(['customer_id' => 'El cliente debe estar activo.']);
        }
        $currency = Currency::from($request->validated('currency'));
        $priceList = $this->priceList($request->validated('price_list_id'), $currency, $quote);
        $quote?->load('items');
        $prepared = $prepare($request->validated('items'), $currency, $priceList, $quote);
        $data = $prepared->totals->toArray();
        $data['lines'] = array_map(fn (array $line) => $line + [
            'display_unit_price' => $currency->format($line['unit_price']),
            'display_discount_amount' => $currency->format($line['discount_amount']),
            'display_tax_amount' => $currency->format($line['tax_amount']),
            'display_subtotal' => $currency->format($line['line_subtotal']),
            'display_total' => $currency->format($line['line_total']),
        ], $data['lines']);
        foreach (['subtotal', 'discount_total', 'tax_total', 'total'] as $field) {
            $data['display_'.$field] = $currency->format($data[$field]);
        }

        return response()->json($data);
    }

    private function priceList(?int $id, Currency $currency, ?Quote $quote): ?PriceList
    {
        if ($id === null) {
            return null;
        }
        $list = PriceList::query()->whereKey($id)->firstOrFail();
        if (($quote?->price_list_id !== $list->id && $list->status !== PriceListStatus::Active) || $list->currency !== $currency) {
            throw ValidationException::withMessages(['price_list_id' => 'Selecciona una lista activa en la moneda de la cotización.']);
        }

        return $list;
    }
}
