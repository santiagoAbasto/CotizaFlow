<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Currency;
use App\Domain\Quotes\QuoteStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'price_list_id', 'currency', 'issued_at', 'expires_at', 'notes', 'terms'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'issued_at' => 'date:Y-m-d',
            'expires_at' => 'date:Y-m-d',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:6',
            'discount_total' => 'decimal:6',
            'tax_total' => 'decimal:6',
            'total' => 'decimal:6',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }
}
