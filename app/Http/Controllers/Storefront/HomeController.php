<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\SiteSettings;
use App\Support\CatalogPresenter;
use App\Support\CatalogUrls;
use App\Support\Pagination;
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

        // Two listings share one page, so each carries its own page number.
        // A single `page` would move both at once.
        $featured = Pagination::guardWithinRange(
            Product::query()
                ->published()
                ->where('is_featured', true)
                ->with(['primaryImage', 'category'])
                ->ordered()
                ->paginate(
                    config('catalog.per_page.home_featured'),
                    pageName: 'featured',
                    page: Pagination::pageNumber(request(), 'featured'),
                )
                ->withQueryString()
        );

        $latest = Pagination::guardWithinRange(
            Product::query()
                ->published()
                ->with(['primaryImage', 'category'])
                ->latest('created_at')
                ->paginate(
                    config('catalog.per_page.home_latest'),
                    pageName: 'latest',
                    page: Pagination::pageNumber(request(), 'latest'),
                )
                ->withQueryString()
        );

        $siteName = $site->translate('site_name', $locale) ?? config('app.name');

        return inertia('Home', [
            'hero' => [
                'title' => $site->translate('hero_title', $locale) ?? __('storefront.home.hero_title'),
                'subtitle' => $site->translate('hero_subtitle', $locale) ?? __('storefront.home.hero_subtitle'),
            ],
            'categories' => $categories
                ->map(fn (Category $category) => CatalogPresenter::categoryCard($category, $locale))
                ->all(),
            'featured' => [
                'data' => collect($featured->items())
                    ->map(fn (Product $product) => CatalogPresenter::productCard($product, $locale))
                    ->all(),
                'meta' => Pagination::meta($featured),
            ],
            'latest' => [
                'data' => collect($latest->items())
                    ->map(fn (Product $product) => CatalogPresenter::productCard($product, $locale))
                    ->all(),
                'meta' => Pagination::meta($latest),
            ],
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
