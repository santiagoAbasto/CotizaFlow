<?php

declare(strict_types=1);

namespace App\Testing;

use Illuminate\Contracts\Foundation\Application;

/**
 * A snapshot of the configuration Laravel will ACTUALLY use, as opposed to the
 * variables sitting in the process environment.
 *
 * The two diverge whenever a config cache is present: phpunit.xml can force
 * DB_DATABASE=cotizaflow_testing into $_SERVER while bootstrap/cache/config.php
 * still resolves the connection to the development database. Reading env alone
 * therefore proves nothing about what migrate:fresh is going to drop.
 *
 * Kept as a plain value object so every dangerous combination can be asserted
 * in tests without ever building a real config cache or touching a real
 * database.
 */
final readonly class EffectiveDatabaseConfig
{
    public function __construct(
        public bool $configurationIsCached,
        public string $appEnv,
        public string $connection,
        public string $database,
        public string $url,
    ) {}

    public static function fromApplication(Application $app): self
    {
        $config = $app->make('config');
        $connection = (string) ($config->get('database.default') ?? '');

        return new self(
            configurationIsCached: $app->configurationIsCached(),
            appEnv: (string) ($config->get('app.env') ?? ''),
            connection: $connection,
            database: (string) ($config->get("database.connections.{$connection}.database") ?? ''),
            url: (string) ($config->get("database.connections.{$connection}.url") ?? ''),
        );
    }
}
