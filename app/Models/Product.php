<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Currency;
use App\Domain\Products\ProductStatus;
use App\Domain\Products\ProductUnit;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'description', 'category', 'unit', 'base_cost', 'base_price', 'currency'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'base_cost' => 'decimal:6',
            'base_price' => 'decimal:6',
            'currency' => Currency::class,
            'status' => ProductStatus::class,
        ];
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /** @param Builder<Product> $query */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('status', ProductStatus::Active->value);
    }
}
