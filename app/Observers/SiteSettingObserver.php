<?php

namespace App\Observers;

use App\Models\SiteSetting;
use App\Services\ImageStorage;
use App\Services\SiteSettings;

class SiteSettingObserver
{
    public function __construct(
        protected SiteSettings $settings,
        protected ImageStorage $images,
    ) {}

    public function saved(SiteSetting $setting): void
    {
        if ($setting->wasChanged('logo_path')) {
            $this->images->delete($setting->getOriginal('logo_path'));
        }

        // Without this the storefront would keep rendering the old header.
        $this->settings->forget();
    }
}
