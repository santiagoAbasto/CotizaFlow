<?php

declare(strict_types=1);

use App\Domain\Quotes\QuoteStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quote;
use Database\Seeders\CommercialDemoSeeder;

it('prepares a reproducible synthetic commercial demo only in the approved test database', function (): void {
    $this->artisan('demo:commercial --force')->assertSuccessful();

    $company = Company::query()->where('slug', CommercialDemoSeeder::COMPANY_SLUG)->sole();

    app(TenantContext::class)->runAs($company, function (): void {
        expect(Product::query()->count())->toBe(5)
            ->and(Quote::query()->where('status', QuoteStatus::Sent->value)->count())->toBe(3)
            ->and(Quote::query()->whereDoesntHave('followUps')->count())->toBe(1);
    });

    $this->artisan('demo:commercial --force')->assertSuccessful();
    $company = Company::query()->where('slug', CommercialDemoSeeder::COMPANY_SLUG)->sole();
    app(TenantContext::class)->runAs($company, function (): void {
        expect(Product::query()->count())->toBe(5)
            ->and(Quote::query()->where('status', QuoteStatus::Sent->value)->count())->toBe(3);
    });
});

it('refuses to reset a non-demo database', function (): void {
    config()->set('database.connections.mysql.database', 'cotizaflow');

    $this->artisan('demo:commercial --force')
        ->expectsOutputToContain('Refusing to seed commercial demo data')
        ->assertFailed();
});

it('refuses a connection URL even when the database name is allowed', function (): void {
    config()->set('database.connections.mysql.url', 'mysql://example.invalid/cotizaflow_testing');

    $this->artisan('demo:commercial --force')
        ->expectsOutputToContain('Refusing to seed commercial demo data')
        ->assertFailed();
});
