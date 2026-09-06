<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantWriteRejected;

/** Resolves the supported Query Builder root-table forms to one safe qualifier. */
final readonly class TenantTableReference
{
    private function __construct(public string $qualifier) {}

    /** @param class-string $model */
    public static function resolve(mixed $from, string $expectedTable, string $model): self
    {
        if (! is_string($from) || trim($from) === '') {
            throw TenantWriteRejected::unsupported($model, 'query against an unresolved table');
        }

        $segments = preg_split('/\s+/', trim(str_replace(['`', '"'], '', $from))) ?: [];

        if (count($segments) === 1) {
            [$source, $qualifier] = [$segments[0], $segments[0]];
        } elseif (count($segments) === 2) {
            [$source, $qualifier] = $segments;
        } elseif (count($segments) === 3 && mb_strtolower($segments[1]) === 'as') {
            [$source, , $qualifier] = $segments;
        } else {
            throw TenantWriteRejected::unsupported($model, 'query with an unsupported root table expression');
        }

        if ($source !== $expectedTable || $qualifier === '') {
            throw TenantWriteRejected::unsupported($model, 'query after replacing the model table');
        }

        return new self($qualifier);
    }

    public function tenantColumn(): string
    {
        return $this->qualifier.'.'.ColumnName::TENANT;
    }
}
