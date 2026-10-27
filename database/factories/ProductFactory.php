<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slugs = app(SlugGenerator::class);

        $nameEn = fake()->unique()->words(3, true);
        $nameAr = 'منتج '.fake()->unique()->numerify('####');

        return [
            'category_id' => Category::factory(),
            'name_ar' => $nameAr,
            'name_en' => ucwords($nameEn),
            'description_ar' => fake()->optional()->paragraph(),
            'description_en' => fake()->optional()->paragraph(),
            'slug_ar' => $slugs->unique(Product::class, 'slug_ar', $nameAr, 'ar'),
            'slug_en' => $slugs->unique(Product::class, 'slug_en', $nameEn, 'en'),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'is_active' => true,
            'is_featured' => false,
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

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function for_category(Category $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }
}
