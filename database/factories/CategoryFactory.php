<?php

namespace Database\Factories;

use App\Models\Category;
use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slugs = app(SlugGenerator::class);

        $nameEn = fake()->unique()->words(2, true);
        $nameAr = 'فئة '.fake()->unique()->numerify('###');

        return [
            'name_ar' => $nameAr,
            'name_en' => ucwords($nameEn),
            'description_ar' => fake()->optional()->sentence(),
            'description_en' => fake()->optional()->sentence(),
            'slug_ar' => $slugs->unique(Category::class, 'slug_ar', $nameAr, 'ar'),
            'slug_en' => $slugs->unique(Category::class, 'slug_en', $nameEn, 'en'),
            'image_path' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
