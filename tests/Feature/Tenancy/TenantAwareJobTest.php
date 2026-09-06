<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\UnknownTenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use Tests\Fixtures\RecordCustomerNameJob;

beforeEach(function (): void {
    RecordCustomerNameJob::$observed = [];
    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);
});

/**
 * Dispatches over the sync queue connection rather than dispatchNow(), because
 * only the queue path runs job middleware — which is where the tenant boundary
 * is opened, and how a real worker executes the job.
 */
function runJob(RecordCustomerNameJob $job): void
{
    dispatch($job);
}

it('binds the job tenant and resolves its own entity', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    runJob(new RecordCustomerNameJob($this->acme->id, $customer->id));

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => 'Cliente Acme'],
    ]);
});

it('does not leak context between consecutive jobs for different tenants', function (): void {
    $acmeCustomer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
    $globexCustomer = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    runJob(new RecordCustomerNameJob($this->acme->id, $acmeCustomer->id));
    runJob(new RecordCustomerNameJob($this->globex->id, $globexCustomer->id));

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => 'Cliente Acme'],
        ['company' => $this->globex->id, 'name' => 'Cliente Globex'],
    ]);
});

it('cannot resolve an entity belonging to another tenant', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    // Job runs as Acme but is handed a Globex customer id.
    runJob(new RecordCustomerNameJob($this->acme->id, $foreign->id));

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => null],
    ]);
});

it('releases the tenant boundary after the job finishes', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    runJob(new RecordCustomerNameJob($this->acme->id, $customer->id));

    expect($this->context->has())->toBeFalse();
});

// ------------------------------------------------- company validation ------

it('refuses to run under a company that does not exist', function (): void {
    expect(fn () => runJob(new RecordCustomerNameJob(999_999, 1)))
        ->toThrow(UnknownTenant::class, 'no longer exists');

    expect(RecordCustomerNameJob::$observed)->toBe([]);
});

it('refuses to run under a company deleted after dispatch', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    $job = new RecordCustomerNameJob($this->acme->id, $customer->id);
    $this->acme->delete();

    expect(fn () => runJob($job))
        ->toThrow(UnknownTenant::class);

    expect(RecordCustomerNameJob::$observed)->toBe([]);
});

it('refuses a job carrying no usable company identifier', function (): void {
    expect(fn () => runJob(new RecordCustomerNameJob(0, 1)))
        ->toThrow(UnknownTenant::class, 'no usable company identifier');
});

// ---------------------------------------------------- failure cleanup ------

it('releases the boundary when a job throws, leaving the next job clean', function (): void {
    $acmeCustomer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
    $globexCustomer = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    try {
        runJob(new RecordCustomerNameJob($this->acme->id, $acmeCustomer->id, shouldFail: true));
    } catch (Throwable) {
        // expected
    }

    expect($this->context->has())->toBeFalse();

    runJob(new RecordCustomerNameJob($this->globex->id, $globexCustomer->id));

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => 'Cliente Acme'],
        ['company' => $this->globex->id, 'name' => 'Cliente Globex'],
    ]);
});
