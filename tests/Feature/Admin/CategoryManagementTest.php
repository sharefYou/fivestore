<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    #[Test]
    public function an_administrator_creates_a_category_in_both_languages(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_ar' => 'إضاءة',
                'name_en' => 'Lighting',
                'slug_ar' => 'اضاءة',
                'slug_en' => 'lighting',
                'description_ar' => 'وصف عربي',
                'description_en' => 'English description',
                'is_active' => true,
                'sort_order' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'name_ar' => 'إضاءة',
            'name_en' => 'Lighting',
            'slug_en' => 'lighting',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function required_fields_are_enforced_on_the_server(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_ar' => '',
                'name_en' => '',
                'slug_ar' => '',
                'slug_en' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name_ar' => 'required',
                'name_en' => 'required',
                'slug_ar' => 'required',
                'slug_en' => 'required',
            ]);

        $this->assertDatabaseCount('categories', 0);
    }

    #[Test]
    public function a_duplicate_slug_is_rejected(): void
    {
        Category::factory()->create(['slug_en' => 'lighting']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_ar' => 'إضاءة',
                'name_en' => 'Lighting',
                'slug_ar' => 'اضاءة-2',
                'slug_en' => 'lighting',
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug_en' => 'unique']);
    }

    #[Test]
    public function a_malformed_slug_is_rejected(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_ar' => 'إضاءة',
                'name_en' => 'Lighting',
                'slug_ar' => 'اضاءة',
                'slug_en' => 'not a slug!',
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug_en']);
    }

    #[Test]
    public function editing_a_category_updates_the_storefront(): void
    {
        $category = Category::factory()->published()->create(['name_en' => 'Old name']);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name_en' => 'New name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/en/categories')->assertSee('New name', escape: false);
    }

    #[Test]
    public function unpublishing_a_category_hides_it_and_its_products(): void
    {
        $category = Category::factory()->published()->create();
        $product = Product::factory()->published()->for_category($category)->create();

        $this->get('/en/products/'.$product->slug_en)->assertOk();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/en/categories/'.$category->slug_en)->assertNotFound();
        $this->get('/en/products/'.$product->slug_en)->assertNotFound();
    }

    #[Test]
    public function a_category_holding_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for_category($category)->create();

        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('delete', $category));

        $category->refresh();
        $this->assertNotNull($category->id);
    }

    #[Test]
    public function an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('delete', $category));
    }

    #[Test]
    public function a_category_whose_products_are_only_soft_deleted_still_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();
        $product->delete();

        $admin = User::factory()->admin()->create();

        // The rows still exist, so the foreign key would refuse the delete.
        $this->assertSoftDeleted($product);
        $this->assertFalse($admin->can('delete', $category));
    }
}
