<?php

namespace App\Support;

/**
 * Formats catalog prices in Saudi Riyal.
 *
 * Formatted on the server so both languages agree on grouping, decimals and
 * where the currency sits, and so the browser never has to guess a locale.
 */
final class Money
{
    public const CURRENCY = 'SAR';

    /**
     * "1,250.00 ر.س" in Arabic, "SAR 1,250.00" in English.
     *
     * Latin digits in both: the catalog's SKUs and numbers are Latin
     * throughout, and mixing digit systems inside one page reads badly.
     *
     * Returns null when there is no price, so a caller renders nothing rather
     * than a zero that would read as "free".
     */
    public static function format(int|float|string|null $amount, ?string $locale = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $locale ??= app()->getLocale();
        $number = number_format((float) $amount, 2, '.', ',');

        return $locale === 'ar'
            ? $number.' ر.س'
            : self::CURRENCY.' '.$number;
    }
}
