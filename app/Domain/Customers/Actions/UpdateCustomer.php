<?php

declare(strict_types=1);

namespace App\Domain\Customers\Actions;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateCustomer
{
    public function __invoke(User $actor, Customer $customer, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $customer);
        $customer->fill($data)->save();
    }
}
