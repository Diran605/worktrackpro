<?php

namespace Tests\Feature;

use App\Support\Branding;
use Filament\Support\Colors\Color;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The organisation's colours (Organisation › Branding) reach the admin panel and the staff portal.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_uses_the_organisation_primary_colour(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser('super_admin');
        $admin->organisation->update(['primary_color' => '#dc2626']);

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString(Color::hex('#dc2626')[600], $html);
        $this->assertStringNotContainsString(Color::Teal[600], $html);
    }

    public function test_staff_portal_page_carries_every_shade(): void
    {
        $this->seedRoles();
        $user = $this->makeUser('worker');
        $user->organisation->update(['primary_color' => '#dc2626', 'secondary_color' => '#7c3aed']);

        $html = $this->actingAs($user)->get('/staff')->assertOk()->getContent();

        $this->assertStringContainsString('--color-teal-600: #dc2626;', $html);
        $this->assertStringContainsString('--color-teal-50: color-mix(in oklab, #dc2626 8%, white);', $html);
        $this->assertStringContainsString('--color-indigo-700: color-mix(in oklab, #7c3aed 84%, black);', $html);
    }

    public function test_invalid_colours_fall_back_to_the_defaults(): void
    {
        $this->assertNull(Branding::valid('red; } body { display:none'));
        $this->assertStringContainsString('--color-teal-600: #0d9488;', Branding::css(null));
    }
}
