<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;

/**
 * Reproduces, verbatim, the three bypasses Codex demonstrated against the
 * previous implementation. Kept as a named regression so a future refactor
 * cannot quietly reopen them.
 */
it('blocks all three reported write bypasses', function (): void {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $ctx = app(TenantContext::class);

    $customer = $ctx->runAs($a, fn () => Customer::create(['name' => 'X', 'email' => 'x@a.test']));

    $blocked = fn (callable $attempt): string => rescue(
        function () use ($attempt): string {
            $attempt();

            return 'no';
        },
        'yes',
        report: false,
    );

    $results = [
        'uppercase_update_blocked' => $blocked(fn () => $ctx->runAs($a, fn () => Customer::whereKey($customer->id)
            ->update(['COMPANY_ID' => $b->id]))),

        'raw_insert_foreign_tenant_blocked' => $blocked(fn () => $ctx->runAs($a, fn () => Customer::insert([
            ['name' => 'Raw', 'email' => 'raw@b.test', 'company_id' => $b->id],
        ]))),

        'upsert_foreign_tenant_blocked' => $blocked(fn () => $ctx->runAs($a, fn () => Customer::upsert([
            ['name' => 'Ups', 'email' => 'ups@b.test', 'company_id' => $b->id],
        ], ['email'], ['name']))),
    ];

    expect($results)->toBe([
        'uppercase_update_blocked' => 'yes',
        'raw_insert_foreign_tenant_blocked' => 'yes',
        'upsert_foreign_tenant_blocked' => 'yes',
    ]);

    // Nothing reached the other tenant by any route.
    expect(rawCustomerCount($b->id))->toBe(0)
        ->and(rawCustomer($customer->id)->company_id)->toBe($a->id);
});
