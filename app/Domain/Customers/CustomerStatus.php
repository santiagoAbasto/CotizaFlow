<?php

declare(strict_types=1);

namespace App\Domain\Customers;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
