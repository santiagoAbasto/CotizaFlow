<?php

declare(strict_types=1);
use App\Domain\Customers\CustomerStatus;
use App\Domain\Money\Currency;
use App\Domain\Pricing\Actions\SetProductPrice;
use App\Domain\Pricing\PriceListStatus;
use App\Domain\Products\ProductStatus;
use App\Domain\Products\ProductUnit;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\PriceList;
use App\Models\Product;
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

$company = Company::where('name', 'Empresa de prueba A')->firstOrFail();
app(TenantContext::class)->runAs($company, function () use ($user): void {
    $customer = Customer::firstOrNew(['name' => 'Taller Andino E2E']);
    $customer->legal_name = 'Taller Andino SRL';
    $customer->tax_id = 'E2E-100';
    $customer->status = CustomerStatus::Active;
    $customer->save();

    $bearing = Product::firstOrNew(['sku' => 'SKF-E2E-6204']);
    $bearing->name = 'Rulemán SKF 6204 E2E';
    $bearing->unit = ProductUnit::Piece;
    $bearing->base_price = '18.400000';
    $bearing->currency = Currency::USD;
    $bearing->status = ProductStatus::Active;
    $bearing->save();

    $motor = Product::firstOrNew(['sku' => 'WEG-E2E-3HP']);
    $motor->name = 'Motor WEG 3HP E2E';
    $motor->unit = ProductUnit::Piece;
    $motor->base_price = '149.000000';
    $motor->currency = Currency::USD;
    $motor->status = ProductStatus::Active;
    $motor->save();

    $list = PriceList::firstOrNew(['name' => 'Mayorista E2E']);
    $list->currency = Currency::USD;
    $list->status = PriceListStatus::Active;
    $list->save();
    app(SetProductPrice::class)($user, $list, $bearing, '16.800000');
});
