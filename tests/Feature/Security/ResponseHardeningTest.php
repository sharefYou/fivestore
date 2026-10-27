<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponseHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storefront_responses_carry_the_security_headers(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    /**
     * The panel is excluded from the Content-Security-Policy only, not from
     * the rest: a missing nosniff or anti-framing header is just as wrong on
     * an admin page as on a public one.
     */
    #[Test]
    public function admin_panel_responses_carry_the_plain_hardening_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));

        // Livewire and Filament need inline scripts, so no policy is claimed
        // here rather than one that would have to allow them anyway.
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function a_not_found_page_shows_no_internals(): void
    {
        $response = $this->get('/en/products/nothing-here');

        $response->assertNotFound();
        $response->assertDontSee('Illuminate\\', escape: false);
        $response->assertDontSee('ModelNotFoundException', escape: false);
        $response->assertDontSee('D:\\Laravel', escape: false);
    }

    #[Test]
    public function the_robots_file_keeps_crawlers_off_the_admin_panel(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    #[Test]
    public function the_sitemap_never_advertises_the_admin_panel(): void
    {
        Product::factory()
            ->published()
            ->for_category(Category::factory()->published()->create())
            ->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/admin', escape: false);
    }

    #[Test]
    public function the_session_cookie_is_not_readable_by_scripts(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    #[Test]
    public function passwords_are_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'a-real-password']);

        $this->assertNotSame('a-real-password', $user->password);
        $this->assertTrue(Hash::check('a-real-password', $user->password));
    }
}
