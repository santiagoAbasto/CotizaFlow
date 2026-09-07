<?php

declare(strict_types=1);

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ChangeCustomerStatus
{
    public function __invoke(User $actor, Customer $customer, CustomerStatus $status): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $customer);
        $customer->status = $status;
        $customer->save();
        Log::info('customer.status_changed', ['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'actor_id' => $actor->id, 'status' => $status->value]);
    }
}
