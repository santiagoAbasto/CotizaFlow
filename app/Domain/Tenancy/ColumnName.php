<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

/**
 * Single place where a column identifier is reduced to a comparable form.
 *
 * Column names reach the query builder in several shapes, and MySQL treats them
 * all as the same column:
 *
 *   company_id · COMPANY_ID · customers.company_id · CUSTOMERS.COMPANY_ID
 *   `customers`.`company_id`
 *
 * Comparing with `===` against one spelling is how the uppercase and qualified
 * bypasses got through. Every tenant check normalises through here; the parsing
 * is not duplicated anywhere else.
 */
final class ColumnName
{
    public const TENANT = 'company_id';

    /**
     * Lower-cases, strips quoting and drops any table/alias qualifier.
     */
    public static function normalize(string $column): string
    {
        $clean = str_replace(['`', '"', '[', ']'], '', trim($column));

        if (str_contains($clean, '.')) {
            $clean = substr($clean, (int) strrpos($clean, '.') + 1);
        }

        return mb_strtolower(trim($clean));
    }

    public static function isTenantColumn(string $column): bool
    {
        return self::normalize($column) === self::TENANT;
    }

    /**
     * Returns the key under which a row carries the tenant column, in whatever
     * spelling the caller used, or null when the row has none.
     *
     * @param  array<string, mixed>  $row
     */
    public static function findTenantKey(array $row): ?string
    {
        foreach (array_keys($row) as $key) {
            if (self::isTenantColumn((string) $key)) {
                return (string) $key;
            }
        }

        return null;
    }
}
