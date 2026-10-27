<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogPresenter;
use App\Support\CatalogUrls;
use App\Support\Seo;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $locale = app()->getLocale();

        $categories = Category::query()
            ->published()
            ->ordered()
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->get();

        return inertia('Categories/Index', [
            'categories' => $categories
                ->map(fn (Category $category) => CatalogPresenter::categoryCard($category, $locale))
                ->all(),
            'seo' => Seo::make(
                title: __('storefront.categories.title'),
                description: __('storefront.categories.subtitle'),
                canonical: CatalogUrls::categories($locale),
                alternates: CatalogUrls::alternates(fn (string $code) => CatalogUrls::categories($code)),
            ),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        // published() is part of the lookup, not a filter applied afterwards:
        // knowing the slug of an unpublished category is not access to it.
        $category = Category::query()
            ->published()
            ->slug($locale, $slug)
            ->firstOrFail();

        $products = Product::query()
            ->published()
            ->where('category_id', $category->id)
            ->with(['primaryImage'])
            ->ordered()
            ->paginate(config('catalog.per_page.category_products'))
            ->withQueryString();

        $name = $category->translate('name', $locale);

        return inertia('Categories/Show', [
            'category' => [
                ...CatalogPresenter::categoryCard($category, $locale),
                'products_count' => $products->total(),
            ],
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
                'categories' => CatalogUrls::categories($locale),
            ],
            'seo' => Seo::make(
                title: $name ?? '',
                description: $category->translate('description', $locale),
                canonical: CatalogUrls::category($category, $locale),
                alternates: CatalogUrls::alternates(
                    fn (string $code) => CatalogUrls::category($category, $code)
                ),
                image: CatalogPresenter::absoluteImageUrl($category->image_path),
            ),
        ]);
    }
}
