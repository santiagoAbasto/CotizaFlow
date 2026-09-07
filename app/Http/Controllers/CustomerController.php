<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\ChangeCustomerStatus;
use App\Domain\Customers\Actions\CreateCustomer;
use App\Domain\Customers\Actions\UpdateCustomer;
use App\Domain\Customers\CustomerStatus;
use App\Http\Requests\Customers\ChangeCustomerStatusRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

final class CustomerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(CustomerStatus::class)]]);
        $q = trim($filters['q'] ?? '');
        $query = Customer::query();
        if ($q !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            // One grouped, parameterized predicate. Never interpolate user input
            // into SQL or create a second tenant builder for a boolean group.
            $columns = ['name', 'legal_name', 'tax_id', 'email', 'phone', 'whatsapp'];
            $query->whereRaw('('.implode(' OR ', array_map(fn ($field) => $field." LIKE ? ESCAPE '!'", $columns)).')', array_fill(0, count($columns), $pattern));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Customers/Index', [
            'customers' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => ['q' => $q, 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Customers/Form', ['customer' => null]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $create)
    {
        $customer = $create($request->user(), $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('customers.show', $customer)->with('success', 'Cliente creado');
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);

        return Inertia::render('Customers/Show', ['customer' => $customer]);
    }

    public function edit(Customer $customer)
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Customers/Form', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $update)
    {
        $update($request->user(), $customer, $request->safe()->except(['company_id', 'tenant_id', 'status']));

        return to_route('customers.show', $customer)->with('success', 'Cliente actualizado');
    }

    public function status(ChangeCustomerStatusRequest $request, Customer $customer, ChangeCustomerStatus $change)
    {
        $change($request->user(), $customer, CustomerStatus::from($request->validated('status')));

        return to_route('customers.show', $customer)->with('success', 'Estado del cliente actualizado');
    }
}
