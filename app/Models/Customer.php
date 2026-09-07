<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Customers\CustomerStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'legal_name', 'tax_id', 'email', 'phone', 'whatsapp', 'commercial_condition', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }

    /** @param Builder<Customer> $query */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active->value);
    }
}
