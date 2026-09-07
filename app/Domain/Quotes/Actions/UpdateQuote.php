<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Quotes\Exceptions\QuoteNotEditable;
use App\Domain\Quotes\PrepareQuoteDraft;
use App\Domain\Quotes\QuoteStatus;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateQuote
{
    public function __construct(private PrepareQuoteDraft $prepare) {}

    public function __invoke(User $actor, Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($actor, $quote, $data): Quote {
            $fresh = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $fresh);
            if ($fresh->status !== QuoteStatus::Draft) {
                throw QuoteNotEditable::make();
            }
            $fresh->load('items');
            $currency = Currency::from($data['currency']);
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            if ($customer->id !== $fresh->customer_id && $customer->status->value !== 'active') {
                throw ValidationException::withMessages(['customer_id' => 'El cliente debe estar activo.']);
            }
            $priceList = $this->priceList($data['price_list_id'] ?? null, $currency, $fresh);
            $prepared = ($this->prepare)($data['items'], $currency, $priceList, $fresh);

            if ($customer->id !== $fresh->customer_id) {
                $fresh->customer_name_snapshot = $customer->name;
                $fresh->customer_legal_name_snapshot = $customer->legal_name;
                $fresh->customer_tax_id_snapshot = $customer->tax_id;
                $fresh->customer_email_snapshot = $customer->email;
                $fresh->customer_phone_snapshot = $customer->phone;
            }
            $fresh->customer_id = $customer->id;
            if ($fresh->price_list_id !== $priceList?->id) {
                $fresh->price_list_name_snapshot = $priceList?->name;
            }
            $fresh->price_list_id = $priceList?->id;
            $fresh->currency = $currency;
            $fresh->issued_at = $data['issued_at'];
            $fresh->expires_at = $data['expires_at'] ?? null;
            $fresh->notes = $data['notes'] ?? null;
            $fresh->terms = $data['terms'] ?? null;
            foreach (['subtotal', 'discount_total', 'tax_total', 'total'] as $field) {
                $fresh->{$field} = $prepared->totals->toArray()[$field];
            }
            $fresh->save();
            $fresh->items()->delete();
            foreach ($prepared->items as $itemData) {
                $item = new QuoteItem;
                foreach ($itemData as $field => $value) {
                    $item->{$field} = $value;
                }
                $fresh->items()->save($item);
            }

            return $fresh->load('items');
        });
    }

    private function priceList(?int $id, Currency $currency, Quote $quote): ?PriceList
    {
        if ($id === null) {
            return null;
        }
        $list = PriceList::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $changed = $list->id !== $quote->price_list_id;
        if (($changed && $list->status !== PriceListStatus::Active) || $list->currency !== $currency) {
            throw ValidationException::withMessages(['price_list_id' => 'Selecciona una lista activa en la moneda de la cotización.']);
        }

        return $list;
    }
}
