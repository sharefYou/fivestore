<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogUrls;
use App\Support\Locales;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Published pages only, in every locale, with the alternates that tell a
     * crawler the pages are translations of one another.
     */
    public function __invoke(): Response
    {
        $entries = [];

        foreach (Locales::supported() as $locale) {
            $entries[] = [
                'url' => CatalogUrls::home($locale),
                'alternates' => CatalogUrls::alternates(fn (string $code) => CatalogUrls::home($code)),
                'lastmod' => null,
                'priority' => '1.0',
            ];

            $entries[] = [
                'url' => CatalogUrls::categories($locale),
                'alternates' => CatalogUrls::alternates(fn (string $code) => CatalogUrls::categories($code)),
                'lastmod' => null,
                'priority' => '0.8',
            ];
        }

        Category::query()->published()->ordered()->each(function (Category $category) use (&$entries): void {
            foreach (Locales::supported() as $locale) {
                $entries[] = [
                    'url' => CatalogUrls::category($category, $locale),
                    'alternates' => CatalogUrls::alternates(
                        fn (string $code) => CatalogUrls::category($category, $code)
                    ),
                    'lastmod' => $category->updated_at?->toAtomString(),
                    'priority' => '0.7',
                ];
            }
        });

        Product::query()->published()->ordered()->each(function (Product $product) use (&$entries): void {
            foreach (Locales::supported() as $locale) {
                $entries[] = [
                    'url' => CatalogUrls::product($product, $locale),
                    'alternates' => CatalogUrls::alternates(
                        fn (string $code) => CatalogUrls::product($product, $code)
                    ),
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'priority' => '0.6',
                ];
            }
        });

        return response()
            ->view('sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
