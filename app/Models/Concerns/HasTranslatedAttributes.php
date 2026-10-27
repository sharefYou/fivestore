<?php

namespace App\Models\Concerns;

use App\Support\Locales;

/**
 * Reads a per-locale attribute pair such as `name_ar` / `name_en`.
 *
 * One fallback policy for the whole application: ask for the active locale,
 * and when that translation is missing fall back to the other supported
 * locales in order rather than rendering an empty string.
 */
trait HasTranslatedAttributes
{
    public function translate(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $candidates = array_values(array_unique([$locale, ...Locales::supported()]));

        foreach ($candidates as $candidate) {
            $value = $this->getAttribute("{$attribute}_{$candidate}");

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * True when the value shown came from a different locale than requested.
     */
    public function isFallingBack(string $attribute, ?string $locale = null): bool
    {
        $locale ??= app()->getLocale();

        return blank($this->getAttribute("{$attribute}_{$locale}"))
            && filled($this->translate($attribute, $locale));
    }

    public function slugFor(string $locale): ?string
    {
        return $this->getAttribute("slug_{$locale}");
    }
}
