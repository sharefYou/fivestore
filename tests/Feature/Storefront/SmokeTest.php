<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every public page answers, in both languages, with the data it promises.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_root_sends_the_visitor_to_a_language(): void
    {
        $this->get('/')->assertRedirect();
    }

    #[Test]
    public function the_health_check_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function every_public_page_renders_in_both_languages(): void
    {
        $category = Category::factory()->published()->create();
        $product = Product::factory()->published()->for_category($category)->create();
        ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        foreach (['ar', 'en'] as $locale) {
            $slugSuffix = $locale === 'ar' ? $category->slug_ar : $category->slug_en;
            $productSlug = $locale === 'ar' ? $product->slug_ar : $product->slug_en;

            $this->get("/{$locale}")->assertOk();
            $this->get("/{$locale}/categories")->assertOk();
            $this->get("/{$locale}/categories/".rawurlencode($slugSuffix))->assertOk();
            $this->get("/{$locale}/products")->assertOk();
            $this->get("/{$locale}/products/".rawurlencode($productSlug))->assertOk();
        }
    }

    #[Test]
    public function a_product_page_carries_its_gallery_and_structured_data(): void
    {
        $category = Category::factory()->published()->create();
        $product = Product::factory()->published()->for_category($category)
            ->create(['sku' => 'SKU-00042']);

        ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 10]);

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Products/Show')
                ->has('product.images', 2)
                ->where('product.images.0.is_primary', true)
                ->where('product.sku', 'SKU-00042')
                ->where('seo.schema.@type', 'Product')
            );
    }

    #[Test]
    public function related_products_come_from_the_same_category_and_exclude_the_current_one(): void
    {
        $category = Category::factory()->published()->create();
        $product = Product::factory()->published()->for_category($category)->create();
        $sibling = Product::factory()->published()->for_category($category)->create();

        Product::factory()->published()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('related', 1)
                ->where('related.0.id', $sibling->id)
            );
    }

    #[Test]
    public function empty_listings_still_render(): void
    {
        $category = Category::factory()->published()->create();

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('featured.data', 0)->has('latest.data', 0));

        $this->get('/en/categories/'.$category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
    }
}
