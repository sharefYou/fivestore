<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Which files end up where, and what is reachable from the web.
 */
class FileStorageExposureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Catalog images are public, so the default disk is `public`. Livewire
     * would otherwise inherit that for its temporary uploads and publish
     * every in-progress file under /storage before validation accepted it.
     */
    #[Test]
    public function temporary_uploads_are_not_written_to_a_public_disk(): void
    {
        $disk = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');

        $this->assertNotSame('public', $disk, 'Livewire temporary uploads must not use the public disk.');

        $root = config("filesystems.disks.{$disk}.root");

        $this->assertStringNotContainsString(
            'app'.DIRECTORY_SEPARATOR.'public',
            str_replace('/', DIRECTORY_SEPARATOR, (string) $root),
            'The temporary upload disk must not resolve inside the published storage directory.',
        );
    }

    #[Test]
    public function catalog_images_are_stored_inside_their_own_buckets(): void
    {
        Storage::fake(config('catalog.images.disk'));

        $storage = app(ImageStorage::class);

        $category = $storage->store(UploadedFile::fake()->image('c.jpg', 600, 400), 'categories');
        $product = $storage->store(UploadedFile::fake()->image('p.jpg', 600, 400), 'products');

        $this->assertStringStartsWith('categories/', $category);
        $this->assertStringStartsWith('products/', $product);
    }

    #[Test]
    public function an_unknown_bucket_is_refused(): void
    {
        Storage::fake(config('catalog.images.disk'));

        $this->expectException(\RuntimeException::class);

        app(ImageStorage::class)->store(
            UploadedFile::fake()->image('x.jpg', 600, 400),
            '../../etc',
        );
    }

    /**
     * Removing an image through the panel deletes the row through Eloquent,
     * which is what lets the observer remove the file as well. A mass delete
     * would leave the file behind.
     */
    #[Test]
    public function removing_images_from_a_product_deletes_their_files(): void
    {
        Storage::fake(config('catalog.images.disk'));

        $disk = Storage::disk(config('catalog.images.disk'));
        $storage = app(ImageStorage::class);

        $product = Product::factory()
            ->for_category(Category::factory()->create())
            ->create();

        $keptPath = $storage->store(UploadedFile::fake()->image('keep.jpg', 600, 400), 'products');
        $goingPath = $storage->store(UploadedFile::fake()->image('drop.jpg', 600, 400), 'products');

        ProductImage::factory()->primary()->create(['product_id' => $product->id, 'path' => $keptPath]);
        $removed = ProductImage::factory()->create(['product_id' => $product->id, 'path' => $goingPath]);

        $removed->delete();

        $disk->assertMissing($goingPath);
        $disk->assertExists($keptPath);
        $this->assertDatabaseCount('product_images', 1);
    }

    #[Test]
    public function no_orphan_files_remain_after_a_product_is_purged(): void
    {
        Storage::fake(config('catalog.images.disk'));

        $disk = Storage::disk(config('catalog.images.disk'));
        $storage = app(ImageStorage::class);

        $product = Product::factory()->for_category(Category::factory()->create())->create();

        $paths = collect(['a', 'b', 'c'])->map(function (string $name) use ($storage) {
            return $storage->store(UploadedFile::fake()->image("{$name}.jpg", 600, 400), 'products');
        });

        $paths->each(fn (string $path, int $index) => ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => $index === 0,
        ]));

        $product->forceDelete();

        $paths->each(fn (string $path) => $disk->assertMissing($path));
        $this->assertDatabaseCount('product_images', 0);
    }

    #[Test]
    public function a_soft_deleted_record_keeps_its_file_so_a_restore_is_complete(): void
    {
        Storage::fake(config('catalog.images.disk'));

        $disk = Storage::disk(config('catalog.images.disk'));
        $path = app(ImageStorage::class)->store(
            UploadedFile::fake()->image('c.jpg', 600, 400),
            'categories',
        );

        $category = Category::factory()->create(['image_path' => $path]);
        $category->delete();

        $disk->assertExists($path);

        $category->restore();

        $this->assertNull($category->fresh()->deleted_at);
        $disk->assertExists($path);
    }
}
