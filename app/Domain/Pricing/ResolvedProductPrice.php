<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Currency;

final readonly class ResolvedProductPrice
{
    public function __construct(
        public string $price,
        public Currency $currency,
        public PriceSource $source,
        public ?int $priceListId = null,
        public ?string $priceListName = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'display_price' => $this->currency->format($this->price),
            'currency' => $this->currency->value,
            'source' => $this->source->value,
            'price_list_id' => $this->priceListId,
            'price_list_name' => $this->priceListName,
        ];
    }
}
