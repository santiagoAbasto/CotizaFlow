<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Quotes\NextQuoteNumber;
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

final class CreateQuote
{
    public function __construct(private NextQuoteNumber $nextNumber, private PrepareQuoteDraft $prepare) {}

    public function __invoke(User $actor, array $data): Quote
    {
        Gate::forUser($actor)->authorize('create', Quote::class);

        return DB::transaction(function () use ($actor, $data): Quote {
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            if ($customer->status->value !== 'active') {
                throw ValidationException::withMessages(['customer_id' => 'El cliente debe estar activo.']);
            }
            $currency = Currency::from($data['currency']);
            $priceList = $this->priceList($data['price_list_id'] ?? null, $currency);
            $prepared = ($this->prepare)($data['items'], $currency, $priceList);
            $number = ($this->nextNumber)();

            $quote = new Quote;
            $quote->customer_id = $customer->id;
            $quote->price_list_id = $priceList?->id;
            $quote->created_by = $actor->id;
            $quote->sequence_number = $number['sequence'];
            $quote->number = $number['number'];
            $quote->currency = $currency;
            $quote->issued_at = $data['issued_at'];
            $quote->expires_at = $data['expires_at'] ?? null;
            $quote->status = QuoteStatus::Draft;
            $quote->customer_name_snapshot = $customer->name;
            $quote->customer_legal_name_snapshot = $customer->legal_name;
            $quote->customer_tax_id_snapshot = $customer->tax_id;
            $quote->customer_email_snapshot = $customer->email;
            $quote->customer_phone_snapshot = $customer->phone;
            $quote->price_list_name_snapshot = $priceList?->name;
            $quote->notes = $data['notes'] ?? null;
            $quote->terms = $data['terms'] ?? null;
            $this->assignTotals($quote, $prepared->totals->toArray());
            $quote->save();
            $this->saveItems($quote, $prepared->items);

            return $quote->load('items');
        });
    }

    private function priceList(?int $id, Currency $currency): ?PriceList
    {
        if ($id === null) {
            return null;
        }
        $list = PriceList::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($list->status !== PriceListStatus::Active || $list->currency !== $currency) {
            throw ValidationException::withMessages(['price_list_id' => 'Selecciona una lista activa en la moneda de la cotización.']);
        }

        return $list;
    }

    /** @param array<string, mixed> $totals */
    private function assignTotals(Quote $quote, array $totals): void
    {
        foreach (['subtotal', 'discount_total', 'tax_total', 'total'] as $field) {
            $quote->{$field} = $totals[$field];
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function saveItems(Quote $quote, array $items): void
    {
        foreach ($items as $data) {
            $item = new QuoteItem;
            foreach ($data as $field => $value) {
                $item->{$field} = $value;
            }
            $quote->items()->save($item);
        }
    }
}
