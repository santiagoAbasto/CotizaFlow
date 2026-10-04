<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Quotes\ExpireDueQuotes;
use Illuminate\Console\Command;

final class ExpireDueQuotesCommand extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Mark sent quotes past their expiration date as expired.';

    public function handle(ExpireDueQuotes $expireDueQuotes): int
    {
        $count = $expireDueQuotes();
        $this->info("{$count} cotizaciones vencidas.");

        return self::SUCCESS;
    }
}
