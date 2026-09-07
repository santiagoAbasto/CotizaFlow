<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', Quote::class);

        return Inertia::render('Dashboard/Index');
    }
}
