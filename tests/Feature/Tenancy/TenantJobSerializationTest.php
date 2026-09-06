<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\RecordCustomerNameJob;

/**
 * The `sync` connection never serialises a payload, so it cannot prove that the
 * tenant survives transport. These tests push onto the database queue and drain
 * it with a real worker, exercising:
 *
 *   dispatch → serialize → store → restore → job middleware → tenant context
 */
beforeEach(function (): void {
    RecordCustomerNameJob::$observed = [];
    config()->set('queue.default', 'database');

    $this->acme = Company::factory()->create();
    $this->globex = Company::factory()->create();
    $this->context = app(TenantContext::class);
});

/** Drains the database queue with a real worker in this process. */
function work(): void
{
    Artisan::call('queue:work', [
        '--once' => true,
        '--tries' => 1,
        '--no-interaction' => true,
    ]);
}

it('carries the tenant across serialization and restores it in the worker', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    dispatch(new RecordCustomerNameJob($this->acme->id, $customer->id));

    // Nothing ran yet: the payload is sitting in the jobs table.
    expect(RecordCustomerNameJob::$observed)->toBe([])
        ->and(Queue::connection('database')->size())->toBe(1);

    // The boundary must come from the restored payload, not from this process.
    $this->context->leave();

    work();

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => 'Cliente Acme'],
    ]);
});

it('keeps two serialized jobs isolated when drained consecutively', function (): void {
    $acmeCustomer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));
    $globexCustomer = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    dispatch(new RecordCustomerNameJob($this->acme->id, $acmeCustomer->id));
    dispatch(new RecordCustomerNameJob($this->globex->id, $globexCustomer->id));

    $this->context->leave();

    work();
    work();

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => 'Cliente Acme'],
        ['company' => $this->globex->id, 'name' => 'Cliente Globex'],
    ]);

    expect($this->context->has())->toBeFalse();
});

it('cannot resolve a foreign entity after transport', function (): void {
    $foreign = $this->context->runAs($this->globex, fn () => Customer::create([
        'name' => 'Cliente Globex', 'email' => 'c@globex.test',
    ]));

    // Dispatched as Acme, but handed a Globex customer id.
    dispatch(new RecordCustomerNameJob($this->acme->id, $foreign->id));

    $this->context->leave();

    work();

    expect(RecordCustomerNameJob::$observed)->toBe([
        ['company' => $this->acme->id, 'name' => null],
    ]);
});

it('does not run a serialized job whose company was deleted in the meantime', function (): void {
    $customer = $this->context->runAs($this->acme, fn () => Customer::create([
        'name' => 'Cliente Acme', 'email' => 'c@acme.test',
    ]));

    dispatch(new RecordCustomerNameJob($this->acme->id, $customer->id));

    $this->acme->delete();
    $this->context->leave();

    work();

    expect(RecordCustomerNameJob::$observed)->toBe([]);
});
