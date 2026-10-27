<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catches the quiet failure mode of a bilingual site: a string that exists in
 * one language and not the other, which only shows up when someone reads the
 * page in the language nobody checked.
 */
class TranslationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function keysOf(string $locale, string $file): array
    {
        $path = lang_path("{$locale}/{$file}.php");

        $this->assertFileExists($path, "Missing translation file: {$locale}/{$file}.php");

        $keys = array_keys(Arr::dot(require $path));
        sort($keys);

        return $keys;
    }

    #[Test]
    public function the_storefront_strings_exist_in_both_languages(): void
    {
        $arabic = $this->keysOf('ar', 'storefront');
        $english = $this->keysOf('en', 'storefront');

        $this->assertSame(
            [],
            array_diff($english, $arabic),
            'These storefront strings have no Arabic translation.',
        );

        $this->assertSame(
            [],
            array_diff($arabic, $english),
            'These storefront strings have no English translation.',
        );
    }

    #[Test]
    public function the_admin_strings_exist_in_both_languages(): void
    {
        $arabic = $this->keysOf('ar', 'admin');
        $english = $this->keysOf('en', 'admin');

        $this->assertSame([], array_diff($english, $arabic), 'Admin strings missing from Arabic.');
        $this->assertSame([], array_diff($arabic, $english), 'Admin strings missing from English.');
    }

    #[Test]
    public function no_storefront_string_is_left_empty(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (Arr::dot(require lang_path("{$locale}/storefront.php")) as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "Empty translation: {$locale}.{$key}");
            }
        }
    }

    /**
     * Filament decides the panel's text direction from its own translations,
     * so the panel follows APP_LOCALE into RTL.
     */
    #[Test]
    public function the_admin_panel_direction_follows_the_application_locale(): void
    {
        app()->setLocale('ar');
        $this->assertSame('rtl', __('filament-panels::layout.direction'));

        app()->setLocale('en');
        $this->assertSame('ltr', __('filament-panels::layout.direction'));
    }

    #[Test]
    public function a_product_without_an_arabic_description_falls_back_to_english(): void
    {
        $product = Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create([
                'name_ar' => 'منتج بلا وصف عربي',
                'description_ar' => null,
                'description_en' => 'Only the English description exists.',
            ]);

        $this->get('/ar/products/'.$product->slug_ar)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.name', 'منتج بلا وصف عربي')
                ->where('product.description', 'Only the English description exists.')
            );
    }

    #[Test]
    public function a_product_without_an_english_name_falls_back_to_arabic(): void
    {
        $product = Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create(['name_en' => '', 'name_ar' => 'اسم عربي فقط']);

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.name', 'اسم عربي فقط')
            );
    }

    /**
     * Arabic distinguishes one, two, a few and many, so the plural string must
     * carry explicit ranges rather than a single "other" form.
     */
    #[Test]
    public function the_arabic_product_count_covers_the_plural_forms(): void
    {
        $line = __('storefront.categories.product_count', [], 'ar');

        $this->assertStringContainsString('{0}', $line);
        $this->assertStringContainsString('{1}', $line);
        $this->assertStringContainsString('{2}', $line);
        $this->assertStringContainsString('[3,10]', $line);
        $this->assertStringContainsString('[11,*]', $line);
    }

    #[Test]
    public function validation_messages_are_available_in_arabic(): void
    {
        app()->setLocale('ar');

        $message = __('validation.required', ['attribute' => 'الاسم']);

        $this->assertNotSame('validation.required', $message, 'Arabic validation file is missing.');
        $this->assertStringNotContainsString('field is required', $message);
    }
}
