<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\ProductRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What a price may be, who may set one, and how it reaches a visitor.
 */
class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
        $this->category = Category::factory()->published()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name_ar' => 'مصباح طاولة',
            'name_en' => 'Table Lamp',
            'slug_ar' => 'مصباح-طاولة',
            'slug_en' => 'table-lamp',
            'sku' => 'LGT-9001',
            'price' => 250.00,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validate(mixed $price): array
    {
        return Validator::make(
            ['price' => $price],
            ['price' => ProductRules::price()],
        )->errors()->toArray();
    }

    #[Test]
    public function a_valid_price_is_accepted(): void
    {
        $this->assertSame([], $this->validate(1250.00));
        $this->assertSame([], $this->validate('99.99'));
        $this->assertSame([], $this->validate(0));
    }

    #[Test]
    public function a_missing_price_is_allowed(): void
    {
        $this->assertSame([], $this->validate(null));
    }

    #[Test]
    public function a_negative_price_is_rejected(): void
    {
        $this->assertArrayHasKey('price', $this->validate(-1));
        $this->assertArrayHasKey('price', $this->validate(-0.01));
    }

    #[Test]
    public function a_non_numeric_price_is_rejected(): void
    {
        $this->assertArrayHasKey('price', $this->validate('free'));
        $this->assertArrayHasKey('price', $this->validate('12abc'));
        $this->assertArrayHasKey('price', $this->validate([250]));
    }

    #[Test]
    public function a_price_beyond_the_column_range_is_rejected(): void
    {
        $this->assertArrayHasKey('price', $this->validate(1000000));
    }

    #[Test]
    public function a_price_with_too_many_decimals_is_rejected(): void
    {
        // decimal(10,2) would silently round this, changing what was typed.
        $this->assertArrayHasKey('price', $this->validate('10.555'));
    }

    #[Test]
    public function an_administrator_saves_a_price_through_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm($this->payload())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('250.00', Product::query()->where('sku', 'LGT-9001')->value('price'));
    }

    #[Test]
    public function the_panel_refuses_a_negative_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm($this->payload(['price' => -50]))
            ->call('create')
            ->assertHasFormErrors(['price']);

        $this->assertDatabaseCount('products', 0);
    }

    #[Test]
    public function the_panel_refuses_a_non_numeric_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm($this->payload(['price' => 'free']))
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    #[Test]
    public function a_product_may_be_saved_without_a_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm($this->payload(['price' => null]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Product::query()->where('sku', 'LGT-9001')->value('price'));
    }

    #[Test]
    public function a_non_administrator_cannot_set_a_price(): void
    {
        $product = Product::factory()->for_category($this->category)->create(['price' => 100]);

        $this->actingAs(User::factory()->create());

        // The policy refuses before any price rule is even considered.
        $this->assertFalse(auth()->user()->can('update', $product));

        $request = ProductRequest::create('/', 'POST', ['price' => 5]);
        $request->setUserResolver(fn () => auth()->user());
        $request->setRouteResolver(fn () => new class($product)
        {
            public function __construct(protected Product $product) {}

            public function parameter(string $name)
            {
                return $name === 'product' ? $this->product : null;
            }
        });

        $this->assertFalse($request->authorize());
    }

    #[Test]
    public function an_editor_can_clear_a_price_back_to_none(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->for_category($this->category)->create(['price' => 500]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($product->fresh()->price);
    }

    #[Test]
    public function the_price_reaches_the_storefront_formatted_in_riyal(): void
    {
        $product = Product::factory()->published()->for_category($this->category)
            ->create(['price' => 1250.00]);

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.price', 'SAR 1,250.00')
            );

        $this->get('/ar/products/'.$product->slug_ar)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.price', '1,250.00 ر.س')
            );
    }

    #[Test]
    public function an_unpriced_product_sends_no_price_rather_than_zero(): void
    {
        $product = Product::factory()->published()->for_category($this->category)
            ->create(['price' => null]);

        $this->get('/en/products/'.$product->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('product.price', null));
    }

    #[Test]
    public function a_zero_price_is_shown_as_a_real_amount_not_as_missing(): void
    {
        // 0.00 is a deliberate "free" price; it must not read as "no price".
        $this->assertSame('SAR 0.00', Money::format(0, 'en'));
        $this->assertNull(Money::format(null, 'en'));
    }

    #[Test]
    public function prices_appear_in_listings_as_well_as_on_the_product_page(): void
    {
        Product::factory()->published()->for_category($this->category)
            ->create(['price' => 99.50]);

        $this->get('/en/categories/'.$this->category->slug_en)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.data.0.price', 'SAR 99.50')
            );

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.data.0.price', 'SAR 99.50')
            );
    }
}
