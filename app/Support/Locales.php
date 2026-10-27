<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Single source of truth for the locales the storefront serves.
 */
final class Locales
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return array_keys(Config::array('catalog.locales'));
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    public static function fallback(): string
    {
        return self::supported()[0];
    }

    public static function direction(string $locale): string
    {
        return Config::string("catalog.locales.{$locale}.direction", 'ltr');
    }

    public static function htmlLang(string $locale): string
    {
        return Config::string("catalog.locales.{$locale}.html_lang", $locale);
    }

    public static function name(string $locale): string
    {
        return Config::string("catalog.locales.{$locale}.name", $locale);
    }

    /**
     * The route pattern used to reject unknown locale segments before a
     * controller ever runs.
     */
    public static function routePattern(): string
    {
        return implode('|', self::supported());
    }
}
