<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\SiteSettings;
use App\Support\CatalogPresenter;
use App\Support\CatalogUrls;
use App\Support\Seo;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(SiteSettings $settings): Response
    {
        $locale = app()->getLocale();
        $site = $settings->get();

        $categories = Category::query()
            ->published()
            ->ordered()
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->get();

        $featured = Product::query()
            ->published()
            ->where('is_featured', true)
            ->with(['primaryImage', 'category'])
            ->ordered()
            ->limit(config('catalog.home.featured_limit'))
            ->get();

        $latest = Product::query()
            ->published()
            ->with(['primaryImage', 'category'])
            ->latest('created_at')
            ->limit(config('catalog.home.latest_limit'))
            ->get();

        $siteName = $site->translate('site_name', $locale) ?? config('app.name');

        return inertia('Home', [
            'hero' => [
                'title' => $site->translate('hero_title', $locale) ?? __('storefront.home.hero_title'),
                'subtitle' => $site->translate('hero_subtitle', $locale) ?? __('storefront.home.hero_subtitle'),
            ],
            'categories' => $categories
                ->map(fn (Category $category) => CatalogPresenter::categoryCard($category, $locale))
                ->all(),
            'featured' => $featured
                ->map(fn (Product $product) => CatalogPresenter::productCard($product, $locale))
                ->all(),
            'latest' => $latest
                ->map(fn (Product $product) => CatalogPresenter::productCard($product, $locale))
                ->all(),
            'links' => [
                'categories' => CatalogUrls::categories($locale),
                'products' => CatalogUrls::products($locale),
            ],
            'seo' => Seo::make(
                title: $siteName,
                description: $site->translate('meta_description', $locale)
                    ?? $site->translate('hero_subtitle', $locale)
                    ?? __('storefront.home.hero_subtitle'),
                canonical: CatalogUrls::home($locale),
                alternates: CatalogUrls::alternates(fn (string $code) => CatalogUrls::home($code)),
            ),
        ]);
    }
}
