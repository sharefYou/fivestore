<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The rule that decides what a visitor may see.
 *
 * Assertions read the page props rather than the HTML: the storefront renders
 * in the browser, so the props are what the visitor actually receives.
 */
class PublishingRulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_published_categories(): void
    {
        $published = Category::factory()->published()->create();
        Category::factory()->unpublished()->create();

        $this->get('/en/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Categories/Index')
                ->has('categories', 1)
                ->where('categories.0.id', $published->id)
            );
    }

    #[Test]
    public function an_unpublished_category_is_not_reachable_by_its_slug(): void
    {
        $category = Category::factory()->unpublished()->create();

        $this->get('/en/categories/'.$category->slug_en)->assertNotFound();
        $this->get('/ar/categories/'.$category->slug_ar)->assertNotFound();
    }

    #[Test]
    public function an_unpublished_product_is_not_reachable_by_its_slug(): void
    {
        $product = Product::factory()
            ->unpublished()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $this->get('/en/products/'.$product->slug_en)->assertNotFound();
        $this->get('/ar/products/'.$product->slug_ar)->assertNotFound();
    }

    #[Test]
    public function a_published_product_inside_an_unpublished_category_stays_hidden(): void
    {
        $category = Category::factory()->unpublished()->create();
        $product = Product::factory()->published()->for_category($category)->create();

        // The product itself is published, but its category is not.
        $this->get('/en/products/'.$product->slug_en)->assertNotFound();

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
    }

    #[Test]
    public function the_category_page_lists_only_its_published_products(): void
    {
        $category = Category::factory()->published()->create();
        $visible = Product::factory()->published()->for_category($category)->create();
        Product::factory()->unpublished()->for_category($category)->create();

        $this->get('/en/categories/'.$category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Categories/Show')
                ->has('products.data', 1)
                ->where('products.data.0.id', $visible->id)
                ->where('products.meta.total', 1)
            );
    }

    #[Test]
    public function the_home_page_shows_only_published_featured_products(): void
    {
        $category = Category::factory()->published()->create();
        $featured = Product::factory()->published()->featured()->for_category($category)->create();
        Product::factory()->unpublished()->featured()->for_category($category)->create();

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->has('featured', 1)
                ->where('featured.0.id', $featured->id)
            );
    }

    #[Test]
    public function the_category_product_count_ignores_unpublished_products(): void
    {
        $category = Category::factory()->published()->create();
        Product::factory()->published()->for_category($category)->create();
        Product::factory()->unpublished()->for_category($category)->create();

        $this->get('/en/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('categories.0.products_count', 1)
            );
    }

    #[Test]
    public function administrative_fields_are_not_sent_to_visitors(): void
    {
        $product = Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('product.is_active')
                ->missing('product.sort_order')
                ->missing('product.deleted_at')
                ->missing('product.category_id')
            );
    }

    #[Test]
    public function the_sitemap_contains_published_pages_only(): void
    {
        $visible = Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $hidden = Product::factory()
            ->unpublished()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee($visible->slug_en, escape: false);
        $response->assertDontSee($hidden->slug_en, escape: false);
    }
}
