<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Counts read from the database on every render. Nothing here is a constant.
 */
class CatalogStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $categories = Category::query()->count();
        $publishedCategories = Category::query()->where('is_active', true)->count();

        $products = Product::query()->count();
        $publishedProducts = Product::query()->where('is_active', true)->count();
        $featured = Product::query()->where('is_featured', true)->count();

        return [
            Stat::make(__('admin.dashboard.categories_total'), (string) $categories)
                ->description(__('admin.dashboard.published_of_total', [
                    'published' => $publishedCategories,
                    'total' => $categories,
                ]))
                ->color($publishedCategories > 0 ? 'success' : 'gray'),

            Stat::make(__('admin.dashboard.products_total'), (string) $products)
                ->description(__('admin.dashboard.published_of_total', [
                    'published' => $publishedProducts,
                    'total' => $products,
                ]))
                ->color($publishedProducts > 0 ? 'success' : 'gray'),

            Stat::make(__('admin.dashboard.products_featured'), (string) $featured)
                ->description(__('admin.dashboard.unpublished_count', [
                    'count' => $products - $publishedProducts,
                ]))
                ->color('info'),

            Stat::make(__('admin.dashboard.images_total'), (string) ProductImage::query()->count())
                ->color('gray'),
        ];
    }
}
