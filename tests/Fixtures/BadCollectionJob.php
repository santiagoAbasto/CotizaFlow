<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Customer;
use Illuminate\Support\Collection;

/** Negative fixture: a collection of tenant-scoped models. Not queueable. */
final class BadCollectionJob
{
    /**
     * @param  Collection<int, Customer>  $customers
     */
    public function __construct(public Collection $customers) {}
}
