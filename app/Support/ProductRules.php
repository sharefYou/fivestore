<?php

namespace App\Support;

/**
 * One definition of what a valid product price is, shared by the admin panel
 * and by request validation, so the two can never drift apart.
 *
 * Mirrors the ImageRules pattern used for uploads.
 */
final class ProductRules
{
    public const PRICE_MAX = 999999.99;

    /**
     * A price is optional — a product may legitimately have none — but when
     * given it must be a non-negative number within the column's range.
     *
     * @return array<int, mixed>
     */
    public static function price(): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            'max:'.self::PRICE_MAX,
            // decimal(10,2): more than two decimals would be silently rounded
            // by the database, changing what the administrator typed.
            'decimal:0,2',
        ];
    }
}
