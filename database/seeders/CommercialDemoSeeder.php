<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\FollowUps\Actions\CreateFollowUp;
use App\Domain\Money\Currency;
use App\Domain\Pricing\Actions\SetProductPrice;
use App\Domain\Products\ProductUnit;
use App\Domain\Quotes\Actions\CreateQuote;
use App\Domain\Quotes\Actions\SendQuote;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Synthetic commercial data only. Invoke through demo:commercial, which
 * deliberately refuses production and non-demo databases.
 */
final class CommercialDemoSeeder extends Seeder
{
    public const COMPANY_SLUG = 'distribuidora-industrial-andina-demo';

    public const USER_EMAIL = 'demo@cotizaflow.test';

    public const USER_PASSWORD = 'CotizaFlowDemo2026!';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->resetExistingDemoCompany();

            $actor = User::query()->updateOrCreate(
                ['email' => self::USER_EMAIL],
                ['name' => 'Demo CotizaFlow', 'password' => self::USER_PASSWORD],
            );
            $company = Company::query()->create([
                'name' => 'Distribuidora Industrial Andina',
                'slug' => self::COMPANY_SLUG,
            ]);
            Membership::factory()->create([
                'user_id' => $actor->id,
                'company_id' => $company->id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
            $actor->refresh();

            app(TenantContext::class)->runAs($company, function () use ($actor): void {
                $customers = $this->customers();
                $products = $this->products();
                $priceLists = $this->priceLists();
                $this->setListPrices($actor, $priceLists, $products);
                $this->quotes($actor, $customers, $products, $priceLists);
            });
        });
    }

    private function resetExistingDemoCompany(): void
    {
        $company = Company::query()->where('slug', self::COMPANY_SLUG)->first();
        if ($company === null) {
            return;
        }

        // This is an intentional base-query boundary: the command already
        // whitelists the database, and every delete is keyed to this synthetic
        // company's exact id before the tenant root is removed.
        foreach (['follow_ups', 'quote_items', 'product_prices', 'quotes', 'quote_sequences', 'products', 'price_lists', 'customers'] as $table) {
            DB::table($table)->where('company_id', $company->id)->delete();
        }

        $company->delete();
    }

    /** @return array<string, Customer> */
    private function customers(): array
    {
        return [
            'taller' => Customer::query()->create(['name' => 'Taller Mecánico San Martín', 'commercial_condition' => 'Pago a 30 días']),
            'industrias' => Customer::query()->create(['name' => 'Industrias Rodríguez', 'commercial_condition' => 'Cuenta corriente']),
            'mineros' => Customer::query()->create(['name' => 'Servicios Mineros del Valle', 'commercial_condition' => 'Transferencia anticipada']),
        ];
    }

    /** @return array<string, Product> */
    private function products(): array
    {
        $definitions = [
            'skf6204' => ['sku' => 'SKF-6204-2RS', 'name' => 'Rodamiento SKF 6204-2RS', 'base_cost' => '9.600000', 'base_price' => '16.800000'],
            'skf6205' => ['sku' => 'SKF-6205-2RS', 'name' => 'Rodamiento SKF 6205-2RS', 'base_cost' => '11.600000', 'base_price' => '20.400000'],
            'weg3' => ['sku' => 'WEG-MTR-3HP', 'name' => 'Motor WEG 3HP', 'base_cost' => '106.000000', 'base_price' => '149.000000'],
            'weg5' => ['sku' => 'WEG-MTR-5HP', 'name' => 'Motor WEG 5HP', 'base_cost' => '164.000000', 'base_price' => '230.000000'],
            'schneider' => ['sku' => 'SCH-LC1D18', 'name' => 'Contactor Schneider LC1D18', 'base_cost' => '23.000000', 'base_price' => '34.500000'],
        ];

        return collect($definitions)->mapWithKeys(fn (array $product, string $key) => [$key => Product::query()->create($product + [
            'description' => 'Datos demostrativos para una cotización industrial.',
            'category' => 'Industrial',
            'unit' => ProductUnit::Unit,
            'currency' => Currency::USD,
        ])])->all();
    }

    /** @return array<string, PriceList> */
    private function priceLists(): array
    {
        return [
            'mayorista' => PriceList::query()->create(['name' => 'Mayorista', 'currency' => Currency::USD]),
            'distribuidor' => PriceList::query()->create(['name' => 'Distribuidor', 'currency' => Currency::USD]),
        ];
    }

    /** @param array<string, PriceList> $priceLists @param array<string, Product> $products */
    private function setListPrices(User $actor, array $priceLists, array $products): void
    {
        $setPrice = app(SetProductPrice::class);
        foreach ($products as $key => $product) {
            $setPrice($actor, $priceLists['mayorista'], $product, match ($key) {
                'skf6204' => '15.900000', 'skf6205' => '19.300000', 'weg3' => '142.000000', 'weg5' => '219.000000', default => '32.000000',
            });
            $setPrice($actor, $priceLists['distribuidor'], $product, match ($key) {
                'skf6204' => '15.100000', 'skf6205' => '18.500000', 'weg3' => '135.000000', 'weg5' => '210.000000', default => '30.000000',
            });
        }
    }

    /** @param array<string, Customer> $customers @param array<string, Product> $products @param array<string, PriceList> $priceLists */
    private function quotes(User $actor, array $customers, array $products, array $priceLists): void
    {
        $createQuote = app(CreateQuote::class);
        $sendQuote = app(SendQuote::class);
        $createFollowUp = app(CreateFollowUp::class);
        $today = Carbon::today();

        $overdue = $createQuote($actor, $this->quoteData($customers['industrias'], $priceLists['mayorista'], [
            $this->item($products['skf6204'], '15'), $this->item($products['weg3'], '2'),
        ], $today));
        $overdue = $sendQuote($actor, $overdue);
        $createFollowUp($actor, $overdue, ['scheduled_at' => $today->copy()->subDay()->setTime(10, 0)->toDateTimeString(), 'note' => 'Consultar decisión de compra.']);

        $todayQuote = $createQuote($actor, $this->quoteData($customers['mineros'], $priceLists['distribuidor'], [
            $this->item($products['weg5'], '2'), $this->item($products['schneider'], '10'),
        ], $today));
        $todayQuote = $sendQuote($actor, $todayQuote);
        $createFollowUp($actor, $todayQuote, ['scheduled_at' => $today->copy()->setTime(15, 0)->toDateTimeString(), 'note' => 'Confirmar vigencia y condiciones.']);

        $withoutFollowUp = $createQuote($actor, $this->quoteData($customers['taller'], $priceLists['mayorista'], [
            $this->item($products['skf6205'], '20'), $this->item($products['schneider'], '4'),
        ], $today));
        $sendQuote($actor, $withoutFollowUp);
    }

    /** @param list<array{product_id: int, quantity: string, discount_percent: string, tax_percent: string}> $items @return array<string, mixed> */
    private function quoteData(Customer $customer, PriceList $priceList, array $items, Carbon $today): array
    {
        return [
            'customer_id' => $customer->id,
            'price_list_id' => $priceList->id,
            'currency' => Currency::USD->value,
            'issued_at' => $today->copy()->subDays(2)->toDateString(),
            'expires_at' => $today->copy()->addDays(14)->toDateString(),
            'notes' => 'Documento demostrativo. Valores no comerciales.',
            'terms' => 'Validez 15 días. Entrega a coordinar.',
            'items' => $items,
        ];
    }

    /** @return array{product_id: int, quantity: string, discount_percent: string, tax_percent: string} */
    private function item(Product $product, string $quantity): array
    {
        return ['product_id' => $product->id, 'quantity' => $quantity, 'discount_percent' => '0', 'tax_percent' => '0'];
    }
}
