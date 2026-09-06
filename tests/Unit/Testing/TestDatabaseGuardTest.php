<?php

declare(strict_types=1);

use App\Testing\EffectiveDatabaseConfig;
use App\Testing\TestDatabaseGuard;

/**
 * The guard is the last line of defence for the development database.
 *
 * Every case below is expressed against a plain value object, so no test ever
 * builds a real config cache or opens a connection to a real database. What is
 * proven here is that the guard REFUSES — and it refuses before RefreshDatabase
 * is ever reached, because TestCase::refreshApplication() calls it first.
 */
function effectiveConfig(
    bool $cached = false,
    string $env = 'testing',
    string $connection = 'mysql',
    string $database = 'cotizaflow_testing',
    string $url = '',
): EffectiveDatabaseConfig {
    return new EffectiveDatabaseConfig($cached, $env, $connection, $database, $url);
}

it('accepts the dedicated testing database with no config cache', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig());
})->throwsNoExceptions();

it('aborts when a configuration cache is active during tests', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(cached: true));
})->throws(RuntimeException::class, 'Laravel configuration cache is active during tests.');

it('uses the exact expected abort headline for a config cache', function (): void {
    $message = '';

    try {
        TestDatabaseGuard::assertSafe(effectiveConfig(cached: true));
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('TEST DATABASE SAFETY ABORTED:')
        ->and($message)->toContain('Laravel configuration cache is active during tests.');
});

/**
 * The exact P0 Codex reproduced: phpunit.xml forces a safe DB_DATABASE into the
 * process env, while a cache built from the development .env resolves the
 * connection to `cotizaflow` and the environment to `local`.
 */
it('aborts on a safe environment paired with a dangerous config cache', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(
        cached: true,
        env: 'local',
        database: 'cotizaflow',
    ));
})->throws(RuntimeException::class, 'configuration cache is active');

it('aborts on a production-like config cache', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(
        cached: true,
        env: 'production',
        database: 'cotizaflow_production',
    ));
})->throws(RuntimeException::class, 'configuration cache is active');

it('refuses a dangerous effective config even with no cache present', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(env: 'local', database: 'cotizaflow'));
})->throws(RuntimeException::class, 'Effective app environment');

it('refuses the development database', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(database: 'cotizaflow'));
})->throws(RuntimeException::class, 'non-testing database');

it('refuses an empty database name', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(database: ''));
})->throws(RuntimeException::class, 'Effective database name is empty');

it('refuses a database outside the allow-list', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(database: 'some_other_db'));
})->throws(RuntimeException::class, 'not in the testing allow-list');

it('refuses a non-testing environment', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(env: 'local'));
})->throws(RuntimeException::class, 'Effective app environment');

it('refuses an unexpected connection', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(connection: 'sqlite'));
})->throws(RuntimeException::class, 'Effective database connection');

it('refuses an effective database URL that would override the name', function (): void {
    TestDatabaseGuard::assertSafe(effectiveConfig(url: 'mysql://user:secret@127.0.0.1/cotizaflow'));
})->throws(RuntimeException::class, 'effective database URL');

it('never prints credentials in its refusal message', function (): void {
    $message = '';

    try {
        TestDatabaseGuard::assertSafe(effectiveConfig(url: 'mysql://user:supersecret@127.0.0.1/cotizaflow'));
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toContain('supersecret')
        ->and($message)->not->toContain('user:')
        ->and($message)->toContain('No credentials are printed');
});

it('reads the effective config from the running application', function (): void {
    $effective = EffectiveDatabaseConfig::fromApplication(app());

    // This suite is itself the proof that the wired-up path is safe.
    expect($effective->configurationIsCached)->toBeFalse()
        ->and($effective->appEnv)->toBe('testing')
        ->and($effective->connection)->toBe('mysql')
        ->and($effective->database)->toBe('cotizaflow_testing');
});
