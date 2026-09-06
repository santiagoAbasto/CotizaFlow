<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Domain\Tenancy\Jobs\InteractsWithTenant;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Reference implementation of the tenant-aware job contract: scalar company id
 * plus scalar entity id, resolved inside the boundary.
 */
final class RecordCustomerNameJob implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    /** @var array<int, array{company: int, name: string|null}> */
    public static array $observed = [];

    public function __construct(
        int $companyId,
        private readonly int $customerId,
        private readonly bool $shouldFail = false,
    ) {
        $this->companyId = $companyId;
    }

    public function handle(): void
    {
        $customer = Customer::find($this->customerId);

        self::$observed[] = [
            'company' => $this->tenant()->id(),
            'name' => $customer?->name,
        ];

        if ($this->shouldFail) {
            throw new RuntimeException('simulated job failure');
        }
    }
}
