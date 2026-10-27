<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;

/**
 * Builds the public URLs of the catalog.
 *
 * Keeping them here means the language switcher, the canonical tag, the
 * alternate links and the sitemap all agree on what a page's address is.
 */
final class CatalogUrls
{
    public static function home(string $locale): string
    {
        return route('storefront.home', ['locale' => $locale]);
    }

    public static function categories(string $locale): string
    {
        return route('storefront.categories.index', ['locale' => $locale]);
    }

    public static function category(Category $category, string $locale): string
    {
        return route('storefront.categories.show', [
            'locale' => $locale,
            'slug' => $category->slugFor($locale),
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function products(string $locale, array $query = []): string
    {
        return route('storefront.products.index', ['locale' => $locale, ...$query]);
    }

    public static function product(Product $product, string $locale): string
    {
        return route('storefront.products.show', [
            'locale' => $locale,
            'slug' => $product->slugFor($locale),
        ]);
    }

    /**
     * The same page in every supported locale.
     *
     * Switching language keeps the visitor on the record they were reading,
     * because each locale has its own slug for it.
     *
     * @param  callable(string): string  $builder
     * @return array<string, string>
     */
    public static function alternates(callable $builder): array
    {
        $alternates = [];

        foreach (Locales::supported() as $locale) {
            $alternates[$locale] = $builder($locale);
        }

        return $alternates;
    }
}
