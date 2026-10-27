<?php

namespace Tests\Feature\Security;

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Authorization checked at every layer that can be reached from outside, not
 * only on the pages a browser renders.
 */
class AuthorizationDepthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function panelUrls(): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();

        return [
            '/admin',
            CategoryResource::getUrl('index'),
            CategoryResource::getUrl('create'),
            CategoryResource::getUrl('edit', ['record' => $category]),
            ProductResource::getUrl('index'),
            ProductResource::getUrl('create'),
            ProductResource::getUrl('edit', ['record' => $product]),
            ManageSiteSettings::getUrl(),
        ];
    }

    #[Test]
    public function every_panel_url_turns_a_guest_away(): void
    {
        foreach ($this->panelUrls() as $url) {
            $this->get($url)->assertRedirectContains('/admin/login');
        }
    }

    #[Test]
    public function every_panel_url_refuses_a_signed_in_non_administrator(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->panelUrls() as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    /**
     * Livewire's follow-up requests do not go through the panel's routes, so
     * Filament re-applies the authentication middleware to them. Without this
     * the admin components would be reachable after the first page load.
     */
    #[Test]
    public function livewire_follow_up_requests_still_run_the_panel_authentication(): void
    {
        $this->assertContains(
            Authenticate::class,
            Livewire::getPersistentMiddleware(),
            'Filament authentication must persist across Livewire requests.',
        );
    }

    #[Test]
    public function a_non_administrator_is_denied_every_catalog_ability(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse($user->can($ability, Category::class), "Category::{$ability}");
            $this->assertFalse($user->can($ability, Product::class), "Product::{$ability}");
        }

        foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse($user->can($ability, $category), "Category::{$ability}");
            $this->assertFalse($user->can($ability, $product), "Product::{$ability}");
        }

        $this->assertFalse($user->can('update', SiteSetting::current()));
        $this->assertFalse($user->can('viewAny', SiteSetting::class));
    }

    #[Test]
    public function an_administrator_is_allowed_the_abilities_the_panel_needs(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();

        $this->assertTrue($admin->can('viewAny', Category::class));
        $this->assertTrue($admin->can('update', $category));
        $this->assertTrue($admin->can('update', $product));
        $this->assertTrue($admin->can('update', SiteSetting::current()));
    }

    /**
     * A user model can arrive without every column, for example from a query
     * that selects a few fields. That must deny access, not grant it and not
     * raise an error.
     */
    #[Test]
    public function a_user_loaded_without_the_admin_column_is_refused(): void
    {
        User::factory()->admin()->create(['email' => 'partial@example.test']);

        $partial = User::query()
            ->select(['id', 'email'])
            ->where('email', 'partial@example.test')
            ->firstOrFail();

        $panel = filament()->getPanel('admin');

        $this->assertFalse($partial->canAccessPanel($panel));
    }

    #[Test]
    public function the_settings_page_authorises_on_mount_and_on_save(): void
    {
        $this->actingAs(User::factory()->create());

        // canAccess() hides it from the sidebar; mount() is what stops a
        // visitor who types the URL.
        $this->assertFalse(ManageSiteSettings::canAccess());

        $this->get(ManageSiteSettings::getUrl())->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_save_settings_and_the_storefront_follows(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'site_name_ar' => 'اسم جديد',
                'site_name_en' => 'A New Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('site_settings', ['site_name_en' => 'A New Name']);

        // The cached settings row must not keep serving the old name.
        $this->get('/en')->assertInertia(
            fn (AssertableInertia $page) => $page->where('site.name', 'A New Name')
        );
    }

    #[Test]
    public function an_unpublished_record_is_not_exposed_by_any_public_route(): void
    {
        $hiddenCategory = Category::factory()->unpublished()->create();
        $hiddenProduct = Product::factory()->unpublished()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $urls = [
            '/en/categories/'.$hiddenCategory->slug_en,
            '/ar/categories/'.$hiddenCategory->slug_ar,
            '/en/products/'.$hiddenProduct->slug_en,
            '/ar/products/'.$hiddenProduct->slug_ar,
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertNotFound();
        }

        // Nor named anywhere a crawler would look.
        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertDontSee($hiddenCategory->slug_en, escape: false);
        $sitemap->assertDontSee($hiddenProduct->slug_en, escape: false);
    }
}
