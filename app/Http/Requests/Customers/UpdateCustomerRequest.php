<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

final class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }
}
