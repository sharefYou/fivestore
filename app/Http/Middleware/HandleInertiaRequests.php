<?php

namespace App\Http\Middleware;

use App\Services\SiteSettings;
use App\Support\CatalogPresenter;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(protected SiteSettings $settings) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Every locale-dependent value below is wrapped in a closure, because
        // this method runs before the route middleware that reads the locale
        // out of the URL. Closures are resolved when the response is built,
        // by which time SetLocale has run.
        return [
            ...parent::share($request),

            'locale' => fn (): array => [
                'current' => app()->getLocale(),
                'direction' => Locales::direction(app()->getLocale()),
                'html_lang' => Locales::htmlLang(app()->getLocale()),
                'available' => array_map(fn (string $code) => [
                    'code' => $code,
                    'name' => Locales::name($code),
                    'direction' => Locales::direction($code),
                ], Locales::supported()),
            ],

            // The same translation files the server and the admin panel use,
            // so a string is never written twice.
            'translations' => fn (): array => Lang::get('storefront', [], app()->getLocale()),

            'site' => fn (): array => $this->site(app()->getLocale()),

            'flash' => fn (): array => [
                'status' => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function site(string $locale): array
    {
        $settings = $this->settings->get();

        return [
            'name' => $settings->translate('site_name', $locale) ?? config('app.name'),
            'logo' => CatalogPresenter::imageUrl($settings->logo_path),
            'footer_text' => $settings->translate('footer_text', $locale),
            'meta_description' => $settings->translate('meta_description', $locale),
            'contact' => array_filter([
                'email' => $settings->contact_email,
                'phone' => $settings->contact_phone,
                'address' => $settings->translate('contact_address', $locale),
            ]),
            'social' => array_filter([
                'facebook' => $settings->social_facebook,
                'instagram' => $settings->social_instagram,
                'whatsapp' => $settings->social_whatsapp,
            ]),
        ];
    }
}
