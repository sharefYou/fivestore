<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every public listing paginates, and a page that does not exist says so.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->published()->create();
    }

    protected function seedProducts(int $count, array $state = []): void
    {
        Product::factory()
            ->count($count)
            ->published()
            ->for_category($this->category)
            ->create($state);
    }

    #[Test]
    public function a_category_page_shows_twelve_products_per_page(): void
    {
        $perPage = config('catalog.per_page.category_products');
        $this->assertSame(12, $perPage);

        $this->seedProducts($perPage + 5);

        $this->get('/en/categories/'.$this->category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', $perPage)
                ->where('products.meta.last_page', 2)
            );

        $this->get('/en/categories/'.$this->category->slug_en.'?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 5)
                ->where('products.meta.current_page', 2)
            );
    }

    #[Test]
    public function search_results_paginate_at_twelve(): void
    {
        $this->seedProducts(14);

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 12));
    }

    #[Test]
    public function the_home_sections_paginate_independently(): void
    {
        $this->seedProducts(15, ['is_featured' => true]);

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('featured.data', 12)
                ->has('latest.data', 12)
                ->where('featured.meta.last_page', 2)
            );

        // Moving one section must not move the other.
        $this->get('/en?featured=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('featured.data', 3)
                ->where('featured.meta.current_page', 2)
                ->where('latest.meta.current_page', 1)
            );
    }

    #[Test]
    public function page_links_keep_the_search_term_and_filter(): void
    {
        $this->seedProducts(14, ['name_en' => 'Lamp Model']);

        $response = $this->get('/en/products?q=Lamp&category='.$this->category->slug_en);

        $response->assertOk()->assertInertia(function (AssertableInertia $page) {
            $next = $page->toArray()['props']['products']['meta']['next_url'];

            $this->assertNotNull($next);
            $this->assertStringContainsString('q=Lamp', $next);
            $this->assertStringContainsString('category=', $next);
            $this->assertStringContainsString('page=2', $next);
        });
    }

    #[Test]
    public function a_page_beyond_the_last_one_is_not_found(): void
    {
        $this->seedProducts(14);

        $this->get('/en/categories/'.$this->category->slug_en.'?page=99')->assertNotFound();
        $this->get('/en/products?page=99')->assertNotFound();
        $this->get('/en?featured=99')->assertNotFound();
    }

    #[Test]
    public function a_malformed_page_is_not_found_rather_than_an_error(): void
    {
        $this->seedProducts(14);

        foreach (['abc', '-1', '0', '1.5', '9999999999', ''] as $value) {
            $response = $this->get('/en/products?page='.$value);

            $this->assertContains(
                $response->getStatusCode(),
                [200, 404],
                "page={$value} produced {$response->getStatusCode()}",
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }

        $this->get('/en/products?page=abc')->assertNotFound();
        $this->get('/en/products?page=-1')->assertNotFound();
        $this->get('/en/products?page=0')->assertNotFound();
    }

    #[Test]
    public function an_array_page_parameter_does_not_break_the_listing(): void
    {
        $this->seedProducts(3);

        $this->get('/en/products?page[]=1')->assertNotFound();
    }

    #[Test]
    public function page_one_of_an_empty_listing_still_renders(): void
    {
        // Nothing published: an empty listing is a real page, not a 404.
        $this->get('/en/categories/'.$this->category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));

        $this->get('/en/products?page=1')->assertOk();
    }

    #[Test]
    public function the_listing_exposes_only_pagination_figures(): void
    {
        $this->seedProducts(14);

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.meta', fn (AssertableInertia $meta) => $meta
                    ->hasAll(['current_page', 'last_page', 'from', 'to', 'total', 'prev_url', 'next_url'])
                    ->etc()
                )
                // Administrative columns never travel with a listing.
                ->missing('products.data.0.is_active')
                ->missing('products.data.0.sort_order')
            );
    }
}
