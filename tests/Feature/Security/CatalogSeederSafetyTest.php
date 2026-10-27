<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RealisticCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The catalog seeder rebuilds the catalog by deleting it first. These tests
 * pin down where it is allowed to do that and what it must never touch.
 */
class CatalogSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    protected function runSeeder(): void
    {
        $seeder = $this->app->make(RealisticCatalogSeeder::class);

        $this->app->call([$seeder, 'run']);
    }

    #[Test]
    public function it_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();

        try {
            // Called directly: `db:seed` has its own production confirmation
            // that would abort first, hiding the guard under test.
            $this->runSeeder();
            $this->fail('The seeder should refuse to run in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('will not run in production', $e->getMessage());
        }

        // The refusal must happen before anything is deleted.
        $this->assertModelExists($category);
        $this->assertModelExists($product);
    }

    #[Test]
    public function the_production_refusal_names_the_override_and_the_backup(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->runSeeder();
            $this->fail('Expected a refusal.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('backup', $e->getMessage());
            $this->assertStringContainsString('CATALOG_SEEDER_ALLOW_DESTRUCTIVE', $e->getMessage());
        }
    }

    #[Test]
    public function it_deletes_the_whole_catalog_including_rows_it_did_not_create(): void
    {
        // Stated as a test rather than left implicit: the seeder cannot tell a
        // hand-made product from a seeded one, so it removes both. That is why
        // the production guard above exists.
        $category = Category::factory()->create(['name_en' => 'Made by hand']);
        $product = Product::factory()->for_category($category)->create();
        $product->delete();

        $seeder = $this->app->make(RealisticCatalogSeeder::class);
        $forget = new \ReflectionMethod($seeder, 'forgetPreviousCatalog');
        $forget->invoke($seeder);

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        // Soft-deleted rows go too, so no slug stays reserved by a ghost.
        $this->assertSame(0, Product::withTrashed()->count());
        $this->assertSame(0, Category::withTrashed()->count());
    }

    #[Test]
    public function wiping_the_catalog_leaves_users_and_site_settings_alone(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = SiteSetting::current();
        $settings->update(['contact_email' => 'owner@example.test']);

        $category = Category::factory()->create();
        Product::factory()->for_category($category)->create();

        $seeder = $this->app->make(RealisticCatalogSeeder::class);
        (new \ReflectionMethod($seeder, 'forgetPreviousCatalog'))->invoke($seeder);

        // The shop owner's own data is not this seeder's to remove.
        $this->assertModelExists($admin);
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertSame('owner@example.test', SiteSetting::current()->contact_email);
        $this->assertDatabaseCount('site_settings', 1);
    }

    #[Test]
    public function wiping_the_catalog_removes_the_image_files_with_the_rows(): void
    {
        $disk = Storage::disk(config('catalog.images.disk'));

        $categoryImage = 'categories/'.Str::uuid().'.jpg';
        $productImage = 'products/'.Str::uuid().'.jpg';
        $disk->put($categoryImage, 'x');
        $disk->put($productImage, 'x');

        $category = Category::factory()->create(['image_path' => $categoryImage]);
        $product = Product::factory()->for_category($category)->create();
        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => $productImage,
        ]);

        $seeder = $this->app->make(RealisticCatalogSeeder::class);
        (new \ReflectionMethod($seeder, 'forgetPreviousCatalog'))->invoke($seeder);

        // Deleting through Eloquent is what lets the observers clean up; a
        // mass delete would leave both files orphaned on disk.
        $disk->assertMissing($categoryImage);
        $disk->assertMissing($productImage);
        $this->assertDatabaseCount('product_images', 0);
    }
}
