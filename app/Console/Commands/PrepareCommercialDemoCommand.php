<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\CommercialDemoSeeder;
use Illuminate\Console\Command;

final class PrepareCommercialDemoCommand extends Command
{
    private const ALLOWED_DATABASES = ['cotizaflow_demo', 'cotizaflow_testing'];

    protected $signature = 'demo:commercial {--force : Reset the synthetic commercial demo tenant}';

    protected $description = 'Prepare the isolated synthetic data used for CotizaFlow commercial demos.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to reset demo data without --force.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $url = config("database.connections.{$connection}.url");

        if (
            ! app()->environment(['local', 'testing'])
            || filled($url)
            || ! in_array($database, self::ALLOWED_DATABASES, true)
        ) {
            $this->error('Refusing to seed commercial demo data outside an approved local/testing demo database.');

            return self::FAILURE;
        }

        $status = $this->call('db:seed', [
            '--class' => CommercialDemoSeeder::class,
            '--force' => true,
        ]);
        if ($status !== self::SUCCESS) {
            return $status;
        }

        $this->info('Synthetic commercial demo data is ready.');

        return self::SUCCESS;
    }
}
