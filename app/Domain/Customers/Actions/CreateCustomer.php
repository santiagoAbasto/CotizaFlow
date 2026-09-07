<?php

declare(strict_types=1);

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateCustomer
{
    public function __invoke(User $actor, array $data): Customer
    {
        Gate::forUser($actor)->authorize('create', Customer::class);
        $customer = new Customer($data);
        $customer->status = CustomerStatus::Active;
        $customer->save();

        return $customer;
    }
}
