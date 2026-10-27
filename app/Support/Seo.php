<?php

namespace App\Support;

/**
 * The metadata block every storefront page passes to the layout.
 */
final class Seo
{
    /**
     * @param  array<string, string>  $alternates
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    public static function make(
        string $title,
        ?string $description,
        string $canonical,
        array $alternates,
        ?string $image = null,
        ?array $schema = null,
    ): array {
        return [
            'title' => $title,
            'description' => self::trim($description),
            'canonical' => $canonical,
            'alternates' => $alternates,
            'image' => $image,
            'schema' => $schema,
        ];
    }

    /**
     * Meta descriptions are cut to a length search engines actually show.
     */
    public static function trim(?string $value, int $length = 160): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_strlen($value) > $length
            ? rtrim(mb_substr($value, 0, $length - 1)).'…'
            : $value;
    }
}
