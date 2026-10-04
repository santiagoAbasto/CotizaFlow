<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Quotes\Actions\ExpireQuote;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Quote;

/** Runs one tenant at a time so every query remains behind the tenant scope. */
final class ExpireDueQuotes
{
    public function __construct(
        private TenantContext $tenantContext,
        private ExpireQuote $expireQuote,
    ) {}

    public function __invoke(): int
    {
        $expired = 0;

        Company::query()->orderBy('id')->each(function (Company $company) use (&$expired): void {
            $expired += $this->tenantContext->runAs($company, function (): int {
                $count = 0;
                Quote::query()
                    ->where('status', QuoteStatus::Sent->value)
                    ->whereDate('expires_at', '<', today())
                    ->orderBy('id')
                    ->each(function (Quote $quote) use (&$count): void {
                        ($this->expireQuote)($quote);
                        $count++;
                    });

                return $count;
            });
        });

        return $expired;
    }
}
