<?php

declare(strict_types=1);

namespace App\Testing;

use RuntimeException;

/**
 * Fail-closed guard that runs before any test touches the database.
 *
 * RefreshDatabase issues `migrate:fresh`, which drops every table on the active
 * connection. The guard therefore validates THE CONFIGURATION LARAVEL WILL
 * ACTUALLY USE, not the process environment: a config cache can hold
 * `cotizaflow` while phpunit.xml has forced `cotizaflow_testing` into $_SERVER,
 * and an env-only check would happily approve dropping the development
 * database.
 *
 * A config cache present during a test run is rejected outright rather than
 * interpreted. Reasoning about a cache built from an unknown .env is strictly
 * worse than refusing to run.
 */
final class TestDatabaseGuard
{
    /**
     * The only databases this suite may ever destroy.
     *
     * @var list<string>
     */
    public const ALLOWED_DATABASES = ['cotizaflow_testing'];

    /**
     * Databases that must never be reachable from a test run.
     *
     * @var list<string>
     */
    public const FORBIDDEN_DATABASES = ['cotizaflow'];

    public const EXPECTED_ENV = 'testing';

    public const EXPECTED_CONNECTION = 'mysql';

    public static function assertSafe(EffectiveDatabaseConfig $config): void
    {
        // Checked first: with a cache active every other value below is
        // untrustworthy, so no further inspection is meaningful.
        if ($config->configurationIsCached) {
            self::abort('Laravel configuration cache is active during tests.', [
                'Run `php artisan config:clear` before running the suite.',
                'A cached config silently overrides the values phpunit.xml forces,',
                'so the suite cannot prove which database it would drop.',
            ]);
        }

        if ($config->appEnv !== self::EXPECTED_ENV) {
            self::abort(sprintf(
                'Effective app environment is "%s" but the test suite requires "%s".',
                $config->appEnv === '' ? '(empty)' : $config->appEnv,
                self::EXPECTED_ENV,
            ));
        }

        if ($config->connection !== self::EXPECTED_CONNECTION) {
            self::abort(sprintf(
                'Effective database connection is "%s" but the test suite requires "%s".',
                $config->connection === '' ? '(empty)' : $config->connection,
                self::EXPECTED_CONNECTION,
            ));
        }

        if ($config->database === '') {
            self::abort('Effective database name is empty. Refusing to run against an undefined database.');
        }

        // Before the allow-list, so a mistaken allow-list entry can never
        // authorise the development database.
        if (in_array($config->database, self::FORBIDDEN_DATABASES, true)) {
            self::abort(sprintf(
                'Effective database is "%s", which is a non-testing database.',
                $config->database,
            ));
        }

        if (! in_array($config->database, self::ALLOWED_DATABASES, true)) {
            self::abort(sprintf(
                'Effective database is "%s", which is not in the testing allow-list [%s].',
                $config->database,
                implode(', ', self::ALLOWED_DATABASES),
            ));
        }

        // A DSN silently overrides the discrete credentials, so it must be empty.
        // The value itself is never echoed: it carries a password.
        if ($config->url !== '') {
            self::abort('An effective database URL is configured and would override the database name.');
        }
    }

    /**
     * @param  list<string>  $detail
     *
     * @throws RuntimeException
     */
    private static function abort(string $reason, array $detail = []): never
    {
        throw new RuntimeException(implode(PHP_EOL, array_filter([
            '',
            'TEST DATABASE SAFETY ABORTED:',
            $reason,
            '',
            ...$detail,
            $detail === [] ? null : '',
            'The suite runs migrate:fresh, which DROPS EVERY TABLE on the active',
            'connection. It will only ever do so against: '.implode(', ', self::ALLOWED_DATABASES).'.',
            '',
            'No credentials are printed by this guard.',
            '',
        ], static fn ($line) => $line !== null)));
    }
}
