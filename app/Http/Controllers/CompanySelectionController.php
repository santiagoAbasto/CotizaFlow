<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tenancy\Actions\ActivateCompanyForUser;
use App\Domain\Tenancy\Exceptions\CompanyNotAuthorized;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class CompanySelectionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Auth/Companies', ['companies' => $request->user()->activeMemberships()->with('company:id,name')->get()->map(fn ($membership) => $membership->company->only('id', 'name'))]);
    }

    public function store(Request $request, ActivateCompanyForUser $activate)
    {
        $data = $request->validate(['company_id' => ['required', 'integer', 'min:1']]);
        try {
            $activate($request->user(), (int) $data['company_id']);
        } catch (CompanyNotAuthorized) {
            abort(403, 'No tienes acceso a esta empresa.');
        }
        $request->session()->put(ResolveTenantContext::SESSION_KEY, (int) $data['company_id']);
        $request->session()->regenerate();

        return to_route('customers.index');
    }
}
