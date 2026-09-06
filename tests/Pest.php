<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Creates a user holding one membership in the given company.
 */
function memberOf(
    Company $company,
    MembershipRole $role = MembershipRole::Sales,
    MembershipStatus $status = MembershipStatus::Active,
    ?User $user = null,
): User {
    $user ??= User::factory()->create();

    Membership::factory()->create([
        'user_id' => $user->getKey(),
        'company_id' => $company->getKey(),
        'role' => $role,
        'status' => $status,
    ]);

    return $user->refresh();
}

/** Privileged cross-tenant inspection for isolation assertions only. */
function rawCustomer(int $id): ?object
{
    return DB::table('customers')->where('id', $id)->first();
}

function rawCustomerCount(?int $companyId = null): int
{
    $query = DB::table('customers');

    if ($companyId !== null) {
        $query->where('company_id', $companyId);
    }

    return $query->count();
}
