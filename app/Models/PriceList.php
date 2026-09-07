<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Currency;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'currency'])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['currency' => Currency::class, 'status' => PriceListStatus::class];
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /** @param Builder<PriceList> $query */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('status', PriceListStatus::Active->value);
    }
}
