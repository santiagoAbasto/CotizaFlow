<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: a queue worker handling jobs for several
        // tenants must not carry one job's boundary into the next.
        $this->app->scoped(TenantContext::class, fn (): TenantContext => new TenantContext);
    }

    public function boot(): void
    {
        // Fail loudly on silently-ignored mass assignment and on lazy loading
        // outside production, where both are bugs rather than conveniences.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
