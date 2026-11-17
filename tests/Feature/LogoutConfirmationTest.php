<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected User $portalUser;

    protected User $tmaUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->portalUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->tmaUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
    }

    public function test_portal_renders_sign_out_button_and_confirmation_modal(): void
    {
        $response = $this->actingAs($this->portalUser, 'web')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Sign Out');
        $response->assertSee('Sign Out Confirmation');
        $response->assertSee('Are you sure you want to end your current session?');
        $response->assertSee('showLogoutModal');
        $response->assertSee('Cancel');
        $response->assertSee(route('portal.logout'));
    }

    public function test_portal_logout_clears_session_and_redirects_to_login(): void
    {
        $this->actingAs($this->portalUser, 'web');
        $this->assertAuthenticated('web');

        $response = $this->post(route('portal.logout'));

        $response->assertRedirect(route('portal.login'));
        $this->assertGuest('web');
    }

    public function test_tma_view_contains_logout_confirmation_modal_and_translations(): void
    {
        $response = $this->get(route('tma.index'));

        $response->assertStatus(200);
        $response->assertSee('showLogoutModal');
        $response->assertSee('logout_confirm_title');
        $response->assertSee('logout_confirm_msg');
        $response->assertSee('executeLogout');
    }

    public function test_tma_logout_api_invalidates_session(): void
    {
        $this->actingAs($this->tmaUser, 'tma');
        $this->assertAuthenticated('tma');

        $response = $this->postJson(route('tma.logout'));

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'csrf_token'])
            ->assertJson(['status' => 'logged_out']);

        $this->assertGuest('tma');
    }
}
