<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ItAssetSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $techUser;

    protected User $regularUser;

    protected Branch $hqBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(ItAssetSampleSeeder::class);

        $this->techUser = User::factory()->create([
            'name' => 'Dara Sam (Tech)',
            'email' => 'tech.overview@cbvh.com.kh',
            'is_active' => true,
            'can_access_portal' => true,
        ]);
        $this->techUser->roles()->attach(Role::where('name', 'technician')->firstOrFail());

        $this->regularUser = User::factory()->create([
            'name' => 'General Employee',
            'email' => 'employee.overview@cbvh.com.kh',
            'is_active' => true,
            'can_access_portal' => false,
        ]);
        $this->regularUser->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        $this->hqBranch = Branch::where('code', 'HQ-PP')->firstOrFail();
    }

    public function test_guest_is_redirected_to_portal_login(): void
    {
        $response = $this->get(route('portal.overview'));
        $response->assertRedirect('/login');
    }

    public function test_non_staff_user_is_forbidden(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->get(route('portal.overview'));
        $response->assertForbidden();
    }

    public function test_it_staff_can_view_central_operations_overview(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->get(route('portal.overview'));
        $response->assertOk();
        $response->assertSee('Central IT Operations Overview');
        $response->assertSee('GLPI Cockpit Mode');
        $response->assertSee('Computers');
        $response->assertSee('Displays');
        $response->assertSee('Network');
        $response->assertSee('Printers');
        $response->assertSee('Spares');
        $response->assertSee('Active Tickets');
        $response->assertSee('Total Hardware Fleet Valuation');
        $response->assertSee('Branch Entities Matrix');
        $response->assertSee('Technician Load Roster');
    }

    public function test_it_staff_can_filter_overview_by_branch(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->get(route('portal.overview', ['branch_id' => $this->hqBranch->id]));
        $response->assertOk();
        $response->assertSee('Filtering by:');
        $response->assertSee($this->hqBranch->name);
        $response->assertSee('Reset to Global View');
    }
}
