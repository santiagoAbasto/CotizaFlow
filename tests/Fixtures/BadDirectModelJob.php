<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Customer;

/**
 * Negative fixture for the payload ratchet. Deliberately NOT queueable, so it
 * never appears in a real scan; it exists only to prove the detector fires.
 */
final class BadDirectModelJob
{
    public function __construct(public Customer $customer) {}
}
