<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_root_redirects_to_a_supported_locale(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $this->assertMatchesRegularExpression('#/(ar|en)$#', $response->headers->get('Location'));
    }

    #[Test]
    public function an_unsupported_locale_is_not_found(): void
    {
        $this->get('/fr')->assertNotFound();
        $this->get('/de/categories')->assertNotFound();
        $this->get('/ar-SA')->assertNotFound();
    }

    #[Test]
    public function each_locale_renders_its_own_direction_and_language(): void
    {
        $arabic = $this->get('/ar');
        $arabic->assertOk();
        $arabic->assertSee('<html lang="ar" dir="rtl"', escape: false);

        $english = $this->get('/en');
        $english->assertOk();
        $english->assertSee('<html lang="en" dir="ltr"', escape: false);
    }

    #[Test]
    public function a_product_page_links_to_its_translation(): void
    {
        $product = Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $response = $this->get('/en/products/'.$product->slug_en);

        $response->assertOk();

        // Switching language must land on the same product, not the home page.
        // Arabic slugs are percent-encoded in a URL, so the expectation is
        // built with the same route helper the page uses.
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.alternates.ar', route('storefront.products.show', ['locale' => 'ar', 'slug' => $product->slug_ar]))
            ->where('seo.alternates.en', route('storefront.products.show', ['locale' => 'en', 'slug' => $product->slug_en]))
            ->where('seo.canonical', route('storefront.products.show', ['locale' => 'en', 'slug' => $product->slug_en]))
        );

        $response->assertSee('hreflang="ar"', escape: false);
        $response->assertSee('rel="canonical"', escape: false);
    }

    #[Test]
    public function each_locale_resolves_its_own_slug(): void
    {
        $category = Category::factory()->published()->create();

        $this->get('/ar/categories/'.$category->slug_ar)->assertOk();
        $this->get('/en/categories/'.$category->slug_en)->assertOk();

        // A slug belongs to one language only.
        $this->get('/en/categories/'.$category->slug_ar)->assertNotFound();
    }

    #[Test]
    public function a_missing_translation_falls_back_to_the_other_language(): void
    {
        $category = Category::factory()->published()->create([
            'name_ar' => 'فئة بلا ترجمة',
            'name_en' => 'Category without Arabic',
            'description_ar' => null,
            'description_en' => 'Only the English description exists.',
        ]);

        // The Arabic page has no Arabic description, so it shows the English
        // one rather than an empty block.
        $response = $this->get('/ar/categories/'.$category->slug_ar);

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('category.name', 'فئة بلا ترجمة')
            ->where('category.description', 'Only the English description exists.')
        );
    }

    #[Test]
    public function the_translated_interface_strings_are_shared_with_the_page(): void
    {
        // The page body is rendered in the browser, so the strings travel as
        // props rather than as text in the HTML.
        $this->get('/ar')->assertInertia(
            fn (AssertableInertia $page) => $page->where('translations.nav.categories', 'الفئات')
        );

        $this->get('/en')->assertInertia(
            fn (AssertableInertia $page) => $page->where('translations.nav.categories', 'Categories')
        );
    }

    #[Test]
    public function error_pages_are_localised_and_leak_nothing(): void
    {
        $arabic = $this->get('/ar/products/does-not-exist');
        $arabic->assertNotFound();
        $arabic->assertSee('dir="rtl"', escape: false);
        $arabic->assertDontSee('Exception', escape: false);
        $arabic->assertDontSee('vendor\\laravel', escape: false);

        $english = $this->get('/en/products/does-not-exist');
        $english->assertNotFound();
        $english->assertSee('dir="ltr"', escape: false);
    }
}
