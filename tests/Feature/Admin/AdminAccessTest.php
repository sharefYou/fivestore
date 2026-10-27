<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who may reach the admin panel, and what the server does when someone who
 * may not tries anyway.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirectContains('/admin/login');
        $this->get(CategoryResource::getUrl('index'))->assertRedirectContains('/admin/login');
    }

    #[Test]
    public function the_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function a_signed_in_user_without_admin_rights_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // canAccessPanel() returns false, so the panel refuses the request
        // rather than rendering anything.
        $this->get('/admin')->assertForbidden();
        $this->get(CategoryResource::getUrl('index'))->assertForbidden();
        $this->get(ProductResource::getUrl('index'))->assertForbidden();
    }

    #[Test]
    public function an_administrator_reaches_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
        $this->get(CategoryResource::getUrl('index'))->assertOk();
        $this->get(ProductResource::getUrl('index'))->assertOk();
        $this->get(ManageSiteSettings::getUrl())->assertOk();
    }

    #[Test]
    public function the_edit_pages_are_closed_to_non_administrators(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for_category($category)->create();

        $this->actingAs(User::factory()->create());

        $this->get(CategoryResource::getUrl('edit', ['record' => $category]))->assertForbidden();
        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertForbidden();
    }

    #[Test]
    public function the_admin_flag_cannot_be_set_through_mass_assignment(): void
    {
        $user = User::factory()->create();

        $this->assertNotContains('is_admin', $user->getFillable());

        // Strict mode (development and tests) rejects the unknown attribute
        // outright; production drops it silently. Either way the flag stays
        // false, which is the property that matters.
        try {
            $user->fill(['name' => 'Renamed', 'is_admin' => true])->save();
        } catch (MassAssignmentException) {
            // Expected while Model::shouldBeStrict() is on.
        }

        $this->assertFalse($user->fresh()->is_admin, 'is_admin must never be mass assignable.');
    }
}
