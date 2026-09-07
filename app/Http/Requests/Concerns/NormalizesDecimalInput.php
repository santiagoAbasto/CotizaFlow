<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesDecimalInput
{
    protected function normalizeDecimal(string $field): void
    {
        $value = $this->input($field);
        if (! is_string($value)) {
            return;
        }

        $value = trim($value);
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }
        $this->merge([$field => $value]);
    }

    /** @return array<int, string> */
    protected function decimalRules(bool $nullable = false): array
    {
        return array_values(array_filter([
            $nullable ? 'nullable' : 'required',
            'string',
            'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?$/',
        ]));
    }
}
