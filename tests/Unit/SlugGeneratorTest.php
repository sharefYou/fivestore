<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\SlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected SlugGenerator $slugs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slugs = app(SlugGenerator::class);
    }

    #[Test]
    public function an_arabic_title_keeps_its_letters(): void
    {
        $slug = $this->slugs->slugify('مصباح طاولة نحاسي', 'ar');

        // Str::slug() would transliterate this to an empty string.
        $this->assertSame('مصباح-طاولة-نحاسي', $slug);
    }

    #[Test]
    public function an_english_title_becomes_a_lowercase_ascii_slug(): void
    {
        $this->assertSame('brass-table-lamp', $this->slugs->slugify('Brass Table Lamp', 'en'));
        $this->assertSame('cafe-au-lait', $this->slugs->slugify('Café au lait', 'en'));
    }

    #[Test]
    public function punctuation_and_repeated_separators_are_collapsed(): void
    {
        $this->assertSame('لوح-تقطيع', $this->slugs->slugify('  لوح //// تقطيع!!  ', 'ar'));
        $this->assertSame('wall-light', $this->slugs->slugify('Wall -- Light?', 'en'));
    }

    #[Test]
    public function a_title_with_no_usable_characters_still_produces_a_slug(): void
    {
        $slug = $this->slugs->slugify('!!! ???', 'en');

        $this->assertNotSame('', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $slug);
    }

    #[Test]
    public function a_taken_slug_gets_a_numeric_suffix(): void
    {
        Category::factory()->create(['slug_en' => 'lighting']);

        $this->assertSame(
            'lighting-2',
            $this->slugs->unique(Category::class, 'slug_en', 'Lighting', 'en'),
        );
    }

    #[Test]
    public function suffixes_keep_climbing_while_slugs_are_taken(): void
    {
        Category::factory()->create(['slug_en' => 'lighting']);
        Category::factory()->create(['slug_en' => 'lighting-2']);

        $this->assertSame(
            'lighting-3',
            $this->slugs->unique(Category::class, 'slug_en', 'Lighting', 'en'),
        );
    }

    #[Test]
    public function a_record_does_not_collide_with_its_own_slug(): void
    {
        $category = Category::factory()->create(['slug_en' => 'lighting']);

        $this->assertSame(
            'lighting',
            $this->slugs->unique(Category::class, 'slug_en', 'Lighting', 'en', $category->id),
        );
    }

    #[Test]
    public function a_soft_deleted_record_keeps_its_slug_reserved(): void
    {
        $category = Category::factory()->create(['slug_en' => 'lighting']);
        $category->delete();

        // The unique index still covers the trashed row, and restoring it must
        // not produce two categories sharing one URL.
        $this->assertSame(
            'lighting-2',
            $this->slugs->unique(Category::class, 'slug_en', 'Lighting', 'en'),
        );
    }
}
