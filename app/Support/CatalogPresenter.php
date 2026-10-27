<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Shapes catalog records for the storefront.
 *
 * Only the fields a visitor may see are listed here, so administrative
 * columns cannot leak into a page's props by accident.
 */
final class CatalogPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function categoryCard(Category $category, string $locale): array
    {
        return [
            'id' => $category->id,
            'name' => $category->translate('name', $locale),
            'description' => $category->translate('description', $locale),
            'url' => CatalogUrls::category($category, $locale),
            'image' => self::imageUrl($category->image_path),
            'products_count' => $category->products_count ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productCard(Product $product, string $locale): array
    {
        $image = $product->relationLoaded('images')
            ? $product->images->first()
            : $product->primaryImage;

        return [
            'id' => $product->id,
            'name' => $product->translate('name', $locale),
            'url' => CatalogUrls::product($product, $locale),
            'is_featured' => $product->is_featured,
            'image' => self::image($image, $product, $locale),
            'category' => $product->relationLoaded('category') && $product->category !== null
                ? [
                    'name' => $product->category->translate('name', $locale),
                    'url' => CatalogUrls::category($product->category, $locale),
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productDetail(Product $product, string $locale): array
    {
        return [
            ...self::productCard($product, $locale),
            'description' => $product->translate('description', $locale),
            'sku' => $product->sku,
            'images' => $product->images
                ->map(fn (ProductImage $image) => self::image($image, $product, $locale))
                ->values()
                ->all(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function image(?ProductImage $image, Product $product, string $locale): ?array
    {
        if ($image === null) {
            return null;
        }

        return [
            'url' => self::imageUrl($image->path),
            // A product image without its own alt text still describes the
            // product, so the product name is a better fallback than "".
            'alt' => $image->translate('alt', $locale) ?? $product->translate('name', $locale),
            'is_primary' => $image->is_primary,
        ];
    }

    /**
     * A root-relative URL such as /storage/products/....
     *
     * Images are served from the same origin as the page, so a relative URL is
     * correct whichever hostname the site answers on. An absolute one built
     * from APP_URL breaks the moment the two disagree, and is then blocked by
     * the same-origin image policy as well.
     */
    public static function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $url = Storage::disk(config('catalog.images.disk'))->url($path);

        return (string) (parse_url($url, PHP_URL_PATH) ?? $url);
    }

    /**
     * The absolute form, for the places that require one: og:image tags and
     * structured data are read off-site.
     */
    public static function absoluteImageUrl(?string $path): ?string
    {
        $relative = self::imageUrl($path);

        return $relative === null ? null : URL::to($relative);
    }
}
