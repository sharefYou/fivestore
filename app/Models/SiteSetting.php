<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedAttributes;
use App\Observers\SiteSettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * The storefront's editable chrome: a single row with known columns, rather
 * than a generic CMS. Read through SiteSettings so it stays cached.
 */
#[ObservedBy(SiteSettingObserver::class)]
class SiteSetting extends Model
{
    use HasTranslatedAttributes;

    protected $fillable = [
        'site_name_ar',
        'site_name_en',
        'hero_title_ar',
        'hero_title_en',
        'hero_subtitle_ar',
        'hero_subtitle_en',
        'logo_path',
        'contact_email',
        'contact_phone',
        'contact_address_ar',
        'contact_address_en',
        'social_facebook',
        'social_instagram',
        'social_whatsapp',
        'footer_text_ar',
        'footer_text_en',
        'meta_description_ar',
        'meta_description_en',
    ];

    /**
     * The one settings row, created with the application name on first access.
     */
    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([], [
            'site_name_ar' => config('app.name'),
            'site_name_en' => config('app.name'),
        ]);

        // A model that was just inserted only holds the attributes that were
        // written, so the columns left at their default are missing from it.
        // Reading one of those would then fail under strict attribute access.
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }
}
