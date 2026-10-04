<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\FollowUps\FollowUpPresenter;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Quotes\Actions\CreateQuote;
use App\Domain\Quotes\Actions\UpdateQuote;
use App\Domain\Quotes\QuoteStatus;
use App\Http\Requests\Quotes\QuoteDraftRequest;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class QuoteController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Quote::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(QuoteStatus::class)]]);
        $q = trim($filters['q'] ?? '');
        $query = Quote::query();
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->whereRaw("(number LIKE ? ESCAPE '!' OR customer_name_snapshot LIKE ? ESCAPE '!')", [$pattern, $pattern]);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Quotes/Index', [
            'quotes' => $query->latest('issued_at')->latest('id')->paginate(20)->withQueryString()->through(fn (Quote $quote) => $this->summary($quote)),
            'filters' => ['q' => $q, 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Quote::class);

        $initialCustomer = null;
        if ($request->integer('customer')) {
            $customer = Customer::query()->selectable()->whereKey($request->integer('customer'))->firstOrFail();
            $initialCustomer = $customer->only(['id', 'name', 'legal_name', 'tax_id']);
        }

        return Inertia::render('Quotes/Form', [
            'quote' => null,
            'initialCustomer' => $initialCustomer,
            'priceLists' => $this->priceLists(),
            'today' => now()->toDateString(),
            'defaultExpiry' => now()->addDays(15)->toDateString(),
        ]);
    }

    public function store(QuoteDraftRequest $request, CreateQuote $create)
    {
        $quote = $create($request->user(), $request->safe()->except($this->serverFields()));

        return to_route('quotes.show', $quote)->with('success', 'Cotización guardada como borrador');
    }

    public function show(Quote $quote, FollowUpPresenter $followUps)
    {
        Gate::authorize('view', $quote);
        $quote->load(['items', 'creator', 'followUps.quote', 'followUps.customer', 'followUps.assignee', 'followUps.completedBy', 'followUps.cancelledBy']);

        return Inertia::render('Quotes/Show', [
            'quote' => $this->detail($quote),
            'followUps' => $quote->followUps->sortByDesc('scheduled_at')->values()->map($followUps)->all(),
            'followUpAssignees' => $followUps->assignees(),
        ]);
    }

    public function edit(Quote $quote)
    {
        Gate::authorize('update', $quote);
        $quote->load('items');

        return Inertia::render('Quotes/Form', [
            'quote' => $this->detail($quote),
            'initialCustomer' => [
                'id' => $quote->customer_id,
                'name' => $quote->customer_name_snapshot,
                'legal_name' => $quote->customer_legal_name_snapshot,
                'tax_id' => $quote->customer_tax_id_snapshot,
            ],
            'priceLists' => $this->priceLists($quote),
            'today' => now()->toDateString(),
            'defaultExpiry' => now()->addDays(15)->toDateString(),
        ]);
    }

    public function update(QuoteDraftRequest $request, Quote $quote, UpdateQuote $update)
    {
        $updated = $update($request->user(), $quote, $request->safe()->except($this->serverFields()));

        return to_route('quotes.show', $updated)->with('success', 'Borrador actualizado');
    }

    /** @return list<string> */
    private function serverFields(): array
    {
        return ['company_id', 'tenant_id', 'created_by', 'number', 'status', 'subtotal', 'discount_total', 'tax_total', 'total'];
    }

    /** @return array<int, array<string, mixed>> */
    private function priceLists(?Quote $quote = null): array
    {
        return PriceList::query()
            ->where(function ($query) use ($quote): void {
                $query->where('status', PriceListStatus::Active->value);
                if ($quote?->price_list_id !== null) {
                    $query->orWhereKey($quote->price_list_id);
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn (PriceList $list) => ['id' => $list->id, 'name' => $list->name, 'currency' => $list->currency->value, 'status' => $list->status->value])
            ->all();
    }

    /** @return array<string, mixed> */
    private function summary(Quote $quote): array
    {
        return [
            ...$quote->only(['id', 'number', 'customer_name_snapshot', 'subtotal', 'discount_total', 'tax_total', 'total', 'updated_at']),
            'currency' => $quote->currency->value,
            'issued_at' => $quote->issued_at->format('Y-m-d'),
            'expires_at' => $quote->expires_at?->format('Y-m-d'),
            'status' => $quote->status->value,
            'status_label' => $quote->status->label(),
            'display_total' => $quote->currency->format($quote->total),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Quote $quote): array
    {
        $items = $quote->items->sortBy('position')->values()->map(fn (QuoteItem $item) => [
            ...$item->only([
                'id', 'product_id', 'position', 'sku_snapshot', 'name_snapshot', 'description_snapshot',
                'base_cost_snapshot', 'base_price_snapshot', 'quantity', 'unit_price', 'source_price_list_id',
                'source_price_list_name_snapshot', 'discount_percent', 'discount_amount', 'tax_percent',
                'tax_amount', 'line_subtotal', 'line_total',
            ]),
            'unit_snapshot' => $item->unit_snapshot->value,
            'unit_label' => $item->unit_snapshot->label(),
            'currency_snapshot' => $item->currency_snapshot->value,
            'price_source' => $item->price_source->value,
            'display_unit_price' => $quote->currency->format($item->unit_price),
            'display_quantity' => $this->trimDecimal($item->quantity),
            'display_discount_percent' => $this->trimDecimal($item->discount_percent),
            'display_tax_percent' => $this->trimDecimal($item->tax_percent),
            'display_discount_amount' => $quote->currency->format($item->discount_amount),
            'display_tax_amount' => $quote->currency->format($item->tax_amount),
            'display_line_subtotal' => $quote->currency->format($item->line_subtotal),
            'display_line_total' => $quote->currency->format($item->line_total),
        ])->all();

        return $this->summary($quote) + [
            ...$quote->only([
                'customer_id', 'price_list_id', 'created_by', 'customer_name_snapshot', 'customer_legal_name_snapshot',
                'customer_tax_id_snapshot', 'customer_email_snapshot', 'customer_phone_snapshot',
                'price_list_name_snapshot', 'notes', 'terms', 'rejection_reason',
            ]),
            'expires_at' => $quote->expires_at?->format('Y-m-d'),
            'sent_at' => $quote->sent_at?->format('d/m/Y H:i'),
            'accepted_at' => $quote->accepted_at?->format('d/m/Y H:i'),
            'rejected_at' => $quote->rejected_at?->format('d/m/Y H:i'),
            'expired_at' => $quote->expired_at?->format('d/m/Y H:i'),
            'creator_name' => $quote->relationLoaded('creator') ? $quote->creator?->name : null,
            'display_subtotal' => $quote->currency->format($quote->subtotal),
            'display_discount_total' => $quote->currency->format($quote->discount_total),
            'display_tax_total' => $quote->currency->format($quote->tax_total),
            'items' => $items,
        ];
    }

    private function trimDecimal(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
