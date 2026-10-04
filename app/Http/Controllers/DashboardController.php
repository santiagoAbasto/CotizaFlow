<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Dashboard\CommercialDashboardQuery;
use App\Models\FollowUp;
use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DashboardController extends Controller
{
    public function __invoke(CommercialDashboardQuery $dashboard)
    {
        Gate::authorize('viewAny', Quote::class);
        Gate::authorize('viewAny', FollowUp::class);

        return Inertia::render('Dashboard/Index', ['dashboard' => $dashboard()]);
    }
}
