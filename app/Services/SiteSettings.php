<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to the single settings row.
 *
 * Every storefront page renders the header and footer, so the row is cached
 * and invalidated from SiteSettingObserver whenever an administrator saves.
 */
class SiteSettings
{
    public const CACHE_KEY = 'site-settings';

    protected ?SiteSetting $resolved = null;

    public function get(): SiteSetting
    {
        if ($this->resolved instanceof SiteSetting) {
            return $this->resolved;
        }

        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => SiteSetting::current()->attributesToArray(),
        );

        return $this->resolved = (new SiteSetting)->newFromBuilder($attributes);
    }

    public function forget(): void
    {
        $this->resolved = null;

        Cache::forget(self::CACHE_KEY);
    }
}
