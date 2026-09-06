<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\OwnershipTransferRejected;
use App\Domain\Tenancy\Exceptions\TenantColumnImmutable;
use App\Domain\Tenancy\TenantBuilder;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantOwnershipTransfer;
use App\Domain\Tenancy\TransferAuthorization;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);
    $this->transfer = app(TenantOwnershipTransfer::class);
    $this->auth = new TransferAuthorization('ops@cotizaflow.test', 'customer moved after company split');

    $this->customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
});

it('moves a record between tenants when authorised', function (): void {
    $this->transfer->transfer($this->customer, $this->globex, $this->auth);

    expect(rawCustomer($this->customer->id)->company_id)
        ->toBe($this->globex->id);

    // Visible in the destination, invisible in the source.
    expect($this->context->runAs($this->globex, fn () => Customer::count()))->toBe(1)
        ->and($this->context->runAs($this->acme, fn () => Customer::count()))->toBe(0);
});

it('requires an explicit performer and reason', function (): void {
    expect(fn () => new TransferAuthorization('', 'reason'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new TransferAuthorization('ops', '  '))->toThrow(InvalidArgumentException::class);
});

it('refuses a transfer to the same company', function (): void {
    expect(fn () => $this->transfer->transfer($this->customer, $this->acme, $this->auth))
        ->toThrow(OwnershipTransferRejected::class, 'same');
});

it('refuses a transfer to a company that does not exist', function (): void {
    expect(fn () => $this->transfer->transfer($this->customer, 999_999, $this->auth))
        ->toThrow(OwnershipTransferRejected::class, 'does not exist');

    expect(rawCustomer($this->customer->id)->company_id)->toBe($this->acme->id);
});

it('refuses a model that is not tenant-owned', function (): void {
    $user = User::factory()->create();

    expect(fn () => $this->transfer->transfer($user, $this->globex, $this->auth))
        ->toThrow(OwnershipTransferRejected::class, 'not a tenant-owned model');
});

it('refuses an unsaved model', function (): void {
    expect(fn () => $this->transfer->transfer(new Customer, $this->globex, $this->auth))
        ->toThrow(OwnershipTransferRejected::class, 'unsaved');
});

it('leaves the record untouched when the transaction fails', function (): void {
    // Force a failure after the write is issued but before commit.
    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'update `customers`')) {
            throw new RuntimeException('simulated failure mid-transfer');
        }
    });

    expect(fn () => $this->transfer->transfer($this->customer, $this->globex, $this->auth))
        ->toThrow(RuntimeException::class);

    expect(rawCustomer($this->customer->id)->company_id)
        ->toBe($this->acme->id);
});

it('keeps ordinary writes protected after a successful transfer', function (): void {
    $this->transfer->transfer($this->customer, $this->globex, $this->auth);

    // No global flag was ever set, so the next ordinary write is still refused.
    expect(fn () => $this->context->runAs($this->globex, fn () => Customer::whereKey($this->customer->id)
        ->update(['company_id' => $this->acme->id])))
        ->toThrow(TenantColumnImmutable::class);
});

it('keeps ordinary writes protected after a failed transfer', function (): void {
    try {
        $this->transfer->transfer($this->customer, 999_999, $this->auth);
    } catch (OwnershipTransferRejected) {
        // expected
    }

    expect(fn () => $this->context->runAs($this->acme, fn () => Customer::whereKey($this->customer->id)
        ->update(['company_id' => $this->globex->id])))
        ->toThrow(TenantColumnImmutable::class);
});

it('exposes no bypass flag on the query builder', function (): void {
    expect(method_exists(TenantBuilder::class, 'permitOwnershipTransfer'))
        ->toBeFalse();
});
