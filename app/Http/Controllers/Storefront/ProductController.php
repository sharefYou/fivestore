<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductSearchRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogPresenter;
use App\Support\CatalogUrls;
use App\Support\Seo;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(ProductSearchRequest $request, string $locale): Response
    {
        $term = $request->term();
        $categorySlug = $request->categorySlug();

        $category = $categorySlug === null
            ? null
            : Category::query()->published()->slug($locale, $categorySlug)->first();

        $products = Product::query()
            ->published()
            ->when($category !== null, fn ($query) => $query->where('category_id', $category->id))
            ->search($term)
            ->with(['primaryImage', 'category'])
            ->ordered()
            ->paginate(config('catalog.per_page.search_results'))
            ->withQueryString();

        $categories = Category::query()->published()->ordered()->get();

        return inertia('Products/Index', [
            'filters' => [
                'q' => $term,
                'category' => $category?->slugFor($locale),
            ],
            'categories' => $categories
                ->map(fn (Category $item) => [
                    'slug' => $item->slugFor($locale),
                    'name' => $item->translate('name', $locale),
                ])
                ->all(),
            'products' => [
                'data' => collect($products->items())
                    ->map(fn (Product $product) => CatalogPresenter::productCard($product, $locale))
                    ->all(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                    'prev_url' => $products->previousPageUrl(),
                    'next_url' => $products->nextPageUrl(),
                ],
            ],
            'links' => [
                'self' => CatalogUrls::products($locale),
            ],
            'seo' => Seo::make(
                title: $term !== null
                    ? __('storefront.products.results_for', ['term' => $term])
                    : __('storefront.products.title'),
                description: __('storefront.products.subtitle'),
                canonical: CatalogUrls::products($locale),
                alternates: CatalogUrls::alternates(fn (string $code) => CatalogUrls::products($code)),
            ),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        // The publishing rule lives in the query: an unpublished product, or a
        // published product inside an unpublished category, is simply not found.
        $product = Product::query()
            ->published()
            ->slug($locale, $slug)
            ->with(['images', 'category'])
            ->firstOrFail();

        $related = Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->with(['primaryImage'])
            ->ordered()
            ->limit(4)
            ->get();

        $detail = CatalogPresenter::productDetail($product, $locale);

        return inertia('Products/Show', [
            'product' => $detail,
            'related' => $related
                ->map(fn (Product $item) => CatalogPresenter::productCard($item, $locale))
                ->all(),
            'links' => [
                'category' => CatalogUrls::category($product->category, $locale),
            ],
            'seo' => Seo::make(
                title: $detail['name'] ?? '',
                description: $detail['description'],
                canonical: CatalogUrls::product($product, $locale),
                alternates: CatalogUrls::alternates(
                    fn (string $code) => CatalogUrls::product($product, $code)
                ),
                // Link previews are rendered off-site, so this one is absolute.
                image: CatalogPresenter::absoluteImageUrl($product->images->first()?->path),
                schema: $this->schema($product, $detail, $locale),
            ),
        ]);
    }

    /**
     * Structured data describing what the page actually shows: a product, its
     * images and its category. No price or availability is claimed, because
     * this catalog does not sell.
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    protected function schema(Product $product, array $detail, string $locale): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $detail['name'],
            'description' => Seo::trim($detail['description'], 300),
            'sku' => $product->sku,
            'category' => $product->category->translate('name', $locale),
            'image' => $product->images
                ->map(fn ($image) => CatalogPresenter::absoluteImageUrl($image->path))
                ->filter()
                ->values()
                ->all() ?: null,
            'url' => CatalogUrls::product($product, $locale),
        ], fn ($value) => $value !== null && $value !== []);
    }
}
