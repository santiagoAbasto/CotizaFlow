<?php

declare(strict_types=1);

namespace Tests;

use App\Testing\EffectiveDatabaseConfig;
use App\Testing\TestDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's setUpTheTestEnvironment() calls refreshApplication() and only
     * then setUpTraits(), which is where RefreshDatabase issues migrate:fresh.
     *
     * Hooking here is therefore the earliest point at which the effective
     * configuration exists AND the latest point that is still before any
     * destructive operation.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        TestDatabaseGuard::assertSafe(EffectiveDatabaseConfig::fromApplication($this->app));
    }
}
