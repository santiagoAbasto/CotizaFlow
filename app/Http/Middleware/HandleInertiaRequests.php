<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => ['user' => $request->user()?->only('id', 'name', 'email')],
            'company' => fn () => app(TenantContext::class)->has() ? Company::find(app(TenantContext::class)->id())?->only('id', 'name') : null,
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
