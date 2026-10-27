<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use App\Support\ImageRules;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * What may be uploaded, under what name it is stored, and what happens to the
 * file when the record goes away.
 */
class ImageHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    /**
     * @return array<string, string|null>
     */
    protected function validate(UploadedFile $file): array
    {
        return Validator::make(
            ['image' => $file],
            ['image' => ImageRules::rules()],
        )->errors()->toArray();
    }

    #[Test]
    public function a_normal_image_passes_validation(): void
    {
        $errors = $this->validate(UploadedFile::fake()->image('photo.jpg', 800, 600));

        $this->assertSame([], $errors);
    }

    #[Test]
    public function a_script_disguised_as_an_image_is_rejected(): void
    {
        // Correct extension, but the bytes are not an image.
        $file = UploadedFile::fake()->createWithContent(
            'payload.jpg',
            '<?php echo shell_exec($_GET["c"]); ?>',
        );

        $this->assertArrayHasKey('image', $this->validate($file));
    }

    #[Test]
    public function an_svg_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->assertArrayHasKey('image', $this->validate($svg));
    }

    #[Test]
    public function an_executable_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('tool.exe', 64, 'application/octet-stream');

        $this->assertArrayHasKey('image', $this->validate($file));
    }

    #[Test]
    public function an_oversized_image_is_rejected(): void
    {
        $file = UploadedFile::fake()->image('huge.jpg', 1200, 1200)
            ->size(ImageRules::maxKilobytes() + 512);

        $this->assertArrayHasKey('image', $this->validate($file));
    }

    #[Test]
    public function an_image_smaller_than_the_minimum_is_rejected(): void
    {
        $tiny = ImageRules::minDimension() - 20;

        $this->assertArrayHasKey('image', $this->validate(
            UploadedFile::fake()->image('tiny.jpg', $tiny, $tiny)
        ));
    }

    #[Test]
    public function an_image_beyond_the_maximum_dimension_is_rejected(): void
    {
        $huge = ImageRules::maxDimension() + 100;

        $this->assertArrayHasKey('image', $this->validate(
            UploadedFile::fake()->image('wide.jpg', $huge, 400)
        ));
    }

    #[Test]
    public function stored_files_get_a_server_generated_name(): void
    {
        $storage = app(ImageStorage::class);

        $path = $storage->store(
            UploadedFile::fake()->image('../../evil name.jpg', 600, 400),
            'products',
        );

        $this->assertStringStartsWith('products/', $path);
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertMatchesRegularExpression(
            '#^products/[0-9a-f-]{36}\.(jpg|png|webp)$#',
            $path,
        );
        Storage::disk(config('catalog.images.disk'))->assertExists($path);
    }

    #[Test]
    public function the_storage_service_refuses_content_that_is_not_an_image(): void
    {
        $this->expectException(RuntimeException::class);

        app(ImageStorage::class)->store(
            UploadedFile::fake()->createWithContent('payload.jpg', 'not an image at all'),
            'products',
        );
    }

    #[Test]
    public function deleting_an_image_row_removes_its_file(): void
    {
        $disk = Storage::disk(config('catalog.images.disk'));
        $path = app(ImageStorage::class)->store(
            UploadedFile::fake()->image('photo.jpg', 600, 400),
            'products',
        );

        $image = ProductImage::factory()->create([
            'product_id' => Product::factory()->create()->id,
            'path' => $path,
        ]);

        $disk->assertExists($path);

        $image->delete();

        $disk->assertMissing($path);
    }

    #[Test]
    public function replacing_a_category_image_removes_the_previous_file(): void
    {
        $disk = Storage::disk(config('catalog.images.disk'));
        $storage = app(ImageStorage::class);

        $first = $storage->store(UploadedFile::fake()->image('one.jpg', 600, 400), 'categories');
        $second = $storage->store(UploadedFile::fake()->image('two.jpg', 600, 400), 'categories');

        $category = Category::factory()->create(['image_path' => $first]);

        $category->update(['image_path' => $second]);

        $disk->assertMissing($first);
        $disk->assertExists($second);
    }

    #[Test]
    public function force_deleting_a_product_removes_its_image_files(): void
    {
        $disk = Storage::disk(config('catalog.images.disk'));
        $path = app(ImageStorage::class)->store(
            UploadedFile::fake()->image('photo.jpg', 600, 400),
            'products',
        );

        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => $path]);

        $product->forceDelete();

        $disk->assertMissing($path);
        $this->assertDatabaseCount('product_images', 0);
    }

    #[Test]
    public function a_product_cannot_hold_two_primary_images(): void
    {
        $product = Product::factory()->create();

        ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        // The unique index over the generated primary flag is the real
        // guarantee, independent of any form validation.
        $this->expectException(QueryException::class);

        ProductImage::factory()->primary()->create(['product_id' => $product->id]);
    }

    #[Test]
    public function two_products_may_each_have_their_own_primary_image(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        ProductImage::factory()->primary()->create(['product_id' => $first->id]);
        ProductImage::factory()->primary()->create(['product_id' => $second->id]);

        $this->assertDatabaseCount('product_images', 2);
    }
}
