<?php

declare(strict_types=1);
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use App\Testing\EffectiveDatabaseConfig;
use App\Testing\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::assertSafe(EffectiveDatabaseConfig::fromApplication($app));
Artisan::call('migrate', ['--force' => true]);

// Browser-only fixtures: the guard above refuses every non-testing database.
$user = User::firstOrCreate(['email' => 'customer-e2e@example.test'], [
    'name' => 'Vendedor de prueba',
    'password' => 'E2E-only-not-a-real-account!',
]);
foreach (['Empresa de prueba A', 'Empresa de prueba B'] as $name) {
    $company = Company::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
    if (! $user->membershipFor($company)) {
        $membership = new Membership;
        $membership->user_id = $user->id;
        $membership->company_id = $company->id;
        $membership->role = MembershipRole::Sales;
        $membership->status = MembershipStatus::Active;
        $membership->save();
    }
}
