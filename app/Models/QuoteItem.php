<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceSource;
use App\Domain\Products\ProductUnit;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quantity', 'discount_percent', 'tax_percent', 'position'])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'currency_snapshot' => Currency::class,
            'unit_snapshot' => ProductUnit::class,
            'price_source' => PriceSource::class,
            'base_cost_snapshot' => 'decimal:6',
            'base_price_snapshot' => 'decimal:6',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'discount_percent' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_percent' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'line_subtotal' => 'decimal:6',
            'line_total' => 'decimal:6',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
