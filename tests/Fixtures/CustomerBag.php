<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Customer;

/** Negative fixture: a DTO wrapping a tenant-scoped model. */
final class CustomerBag
{
    public function __construct(public Customer $customer) {}
}
