<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->published()->create();
    }

    #[Test]
    public function it_finds_a_product_by_its_english_name(): void
    {
        $match = Product::factory()->published()->for_category($this->category)
            ->create(['name_en' => 'Brass Table Lamp']);

        Product::factory()->published()->for_category($this->category)
            ->create(['name_en' => 'Wooden Chair']);

        $this->get('/en/products?q=Brass')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $match->id)
                ->where('filters.q', 'Brass')
            );
    }

    #[Test]
    public function it_finds_a_product_by_its_arabic_name(): void
    {
        $match = Product::factory()->published()->for_category($this->category)
            ->create(['name_ar' => 'مصباح طاولة نحاسي']);

        Product::factory()->published()->for_category($this->category)
            ->create(['name_ar' => 'كرسي خشبي']);

        $this->get('/ar/products?q='.urlencode('مصباح'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $match->id)
            );
    }

    #[Test]
    public function it_finds_a_product_by_sku(): void
    {
        $match = Product::factory()->published()->for_category($this->category)
            ->create(['sku' => 'SKU-12345-AB']);

        $this->get('/en/products?q=12345')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $match->id)
            );
    }

    #[Test]
    public function an_unpublished_product_never_appears_in_results(): void
    {
        Product::factory()->unpublished()->for_category($this->category)
            ->create(['name_en' => 'Secret Prototype']);

        $this->get('/en/products?q=Prototype')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
    }

    #[Test]
    public function results_can_be_narrowed_to_one_category(): void
    {
        $other = Category::factory()->published()->create();

        $inCategory = Product::factory()->published()->for_category($this->category)
            ->create(['name_en' => 'Lamp One']);

        Product::factory()->published()->for_category($other)
            ->create(['name_en' => 'Lamp Two']);

        $this->get('/en/products?q=Lamp&category='.$this->category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $inCategory->id)
            );
    }

    #[Test]
    public function an_over_long_search_term_is_rejected(): void
    {
        $this->get('/en/products?q='.str_repeat('a', 300))
            ->assertSessionHasErrors('q');
    }

    #[Test]
    public function sql_control_characters_in_the_term_are_treated_as_text(): void
    {
        Product::factory()->published()->for_category($this->category)
            ->create(['name_en' => 'Safe Product']);

        // If this were concatenated into SQL the table would be gone; the
        // bound parameter means it is only ever a string to look for.
        $this->get('/en/products?q='.urlencode("' OR 1=1; DROP TABLE products;--"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));

        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function like_wildcards_typed_by_a_visitor_are_not_treated_as_wildcards(): void
    {
        Product::factory()->published()->for_category($this->category)
            ->create(['name_en' => 'Plain Product']);

        $this->get('/en/products?q='.urlencode('%'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
    }

    #[Test]
    public function long_listings_are_paginated(): void
    {
        $perPage = config('catalog.per_page.search_results');

        Product::factory()
            ->count($perPage + 3)
            ->published()
            ->for_category($this->category)
            ->create();

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', $perPage)
                ->where('products.meta.total', $perPage + 3)
                ->where('products.meta.last_page', 2)
                ->whereNot('products.meta.next_url', null)
            );

        $this->get('/en/products?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 3)
                ->where('products.meta.current_page', 2)
            );
    }
}
