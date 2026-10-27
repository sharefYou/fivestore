<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));

        $this->actingAs(User::factory()->admin()->create());
        $this->category = Category::factory()->published()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name_ar' => 'مصباح طاولة',
            'name_en' => 'Table Lamp',
            'slug_ar' => 'مصباح-طاولة',
            'slug_en' => 'table-lamp',
            'description_ar' => 'وصف المنتج',
            'description_en' => 'Product description',
            'sku' => 'SKU-00100',
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    #[Test]
    public function an_administrator_creates_a_product(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'name_en' => 'Table Lamp',
            'slug_en' => 'table-lamp',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function a_created_product_is_immediately_visible_on_the_storefront(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/en/products/table-lamp')->assertOk();
    }

    #[Test]
    public function a_product_must_belong_to_a_category(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload(['category_id' => null]))
            ->call('create')
            ->assertHasFormErrors(['category_id' => 'required']);
    }

    #[Test]
    public function a_product_cannot_be_attached_to_a_category_that_does_not_exist(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload(['category_id' => 999999]))
            ->call('create')
            ->assertHasFormErrors(['category_id']);

        $this->assertDatabaseCount('products', 0);
    }

    #[Test]
    public function a_duplicate_sku_is_rejected(): void
    {
        Product::factory()->for_category($this->category)->create(['sku' => 'SKU-00100']);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload())
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);
    }

    #[Test]
    public function an_empty_sku_is_allowed_on_more_than_one_product(): void
    {
        Product::factory()->for_category($this->category)->create(['sku' => null]);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload(['sku' => null]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Product::query()->whereNull('sku')->count());
    }

    #[Test]
    public function a_duplicate_slug_is_rejected(): void
    {
        Product::factory()->for_category($this->category)->create(['slug_en' => 'table-lamp']);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload())
            ->call('create')
            ->assertHasFormErrors(['slug_en' => 'unique']);
    }

    #[Test]
    public function marking_two_images_as_primary_is_refused_by_the_form(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload([
                'images' => [
                    ['path' => ['file-1' => 'products/one.jpg'], 'is_primary' => true, 'sort_order' => 0],
                    ['path' => ['file-2' => 'products/two.jpg'], 'is_primary' => true, 'sort_order' => 1],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['images']);

        $this->assertDatabaseCount('products', 0);
    }

    #[Test]
    public function images_without_a_primary_one_are_refused(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload([
                'images' => [
                    ['path' => ['file-1' => 'products/one.jpg'], 'is_primary' => false, 'sort_order' => 0],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['images']);
    }

    #[Test]
    public function a_product_is_saved_with_its_images(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validPayload([
                'images' => [
                    ['path' => ['file-1' => 'products/one.jpg'], 'is_primary' => true, 'sort_order' => 0, 'alt_en' => 'Front'],
                    ['path' => ['file-2' => 'products/two.jpg'], 'is_primary' => false, 'sort_order' => 1, 'alt_en' => 'Side'],
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug_en', 'table-lamp')->firstOrFail();

        $this->assertCount(2, $product->images);
        $this->assertSame('products/one.jpg', $product->primaryImage?->path);
    }

    #[Test]
    public function unpublishing_a_product_removes_it_from_the_storefront(): void
    {
        $product = Product::factory()->published()->for_category($this->category)->create();

        $this->get('/en/products/'.$product->slug_en)->assertOk();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/en/products/'.$product->slug_en)->assertNotFound();
    }

    #[Test]
    public function featuring_a_product_puts_it_on_the_home_page(): void
    {
        $product = Product::factory()->published()->for_category($this->category)->create();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/en')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('featured.data', 1)
                ->where('featured.data.0.id', $product->id)
        );
    }

    #[Test]
    public function deleting_a_product_removes_it_from_the_storefront_but_keeps_the_record(): void
    {
        $product = Product::factory()->published()->for_category($this->category)->create();
        ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->get('/en/products/'.$product->slug_en)->assertNotFound();
        // Soft delete keeps the images so a restore brings back a whole product.
        $this->assertDatabaseCount('product_images', 1);
    }
}
