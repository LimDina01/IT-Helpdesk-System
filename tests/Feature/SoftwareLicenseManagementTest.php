<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Role;
use App\Models\SoftwareLicense;
use App\Models\SoftwareLicenseAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ItAssetSampleSeeder;
use Database\Seeders\SoftwareLicenseSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftwareLicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $techUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(ItAssetSampleSeeder::class);
        $this->seed(SoftwareLicenseSampleSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();

        $this->techUser = User::factory()->create([
            'name' => 'Dara Sam (Tech)',
            'email' => 'tech.software@cbvh.com.kh',
            'is_active' => true,
            'can_access_portal' => true,
        ]);
        $this->techUser->roles()->attach(Role::where('name', 'technician')->firstOrFail());

        $this->regularUser = User::factory()->create([
            'name' => 'General Employee',
            'email' => 'employee.software@cbvh.com.kh',
            'is_active' => true,
            'can_access_portal' => false,
        ]);
        $this->regularUser->roles()->attach(Role::where('name', 'requester')->firstOrFail());
    }

    public function test_it_staff_can_view_software_licenses_tab_with_financials_masked(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->get(route('portal.assets.index', ['tab' => 'licenses']));
        $response->assertOk();
        $response->assertSee('Digital Software &amp; SaaS Subscriptions', false);
        $response->assertSee('Microsoft 365 Business Standard');
        $response->assertSee('Adobe Creative Cloud');
        $response->assertSee('Kaspersky Endpoint Security');
        $response->assertSee('[Management Confidential]');
    }

    public function test_admin_can_view_full_software_budget(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('portal.assets.index', ['tab' => 'licenses']));
        $response->assertOk();
        $response->assertSee('7,500.00');
        $response->assertDontSee('[Management Confidential]');
    }

    public function test_it_staff_can_register_new_software_license(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->post(route('portal.assets.licenses.store'), [
            'name' => 'Zoom Workplace Pro',
            'vendor_publisher' => 'Zoom Video Communications',
            'license_type' => 'subscription',
            'billing_cycle' => 'annual',
            'seats_total' => 20,
            'license_key' => 'https://zoom.us/account',
            'status' => 'active',
            'notes' => 'Corporate video conference rooms',
        ]);

        $response->assertRedirect(route('portal.assets.index', ['tab' => 'licenses']));
        $this->assertDatabaseHas('software_licenses', [
            'name' => 'Zoom Workplace Pro',
            'vendor_publisher' => 'Zoom Video Communications',
            'seats_total' => 20,
        ]);
    }

    public function test_it_staff_can_allocate_and_revoke_license_seat(): void
    {
        $this->actingAs($this->techUser);

        $license = SoftwareLicense::where('name', 'Microsoft 365 Business Standard')->firstOrFail();
        $asset = Asset::firstOrFail();

        // 1. Allocate seat to staff member & asset
        $assignResponse = $this->post(route('portal.assets.licenses.assign', $license->id), [
            'user_id' => $this->regularUser->id,
            'asset_id' => $asset->id,
            'notes' => 'Allocated for customer support desk',
        ]);

        $assignResponse->assertRedirect(route('portal.assets.index', ['tab' => 'licenses']));
        $this->assertDatabaseHas('software_license_assignments', [
            'software_license_id' => $license->id,
            'user_id' => $this->regularUser->id,
            'asset_id' => $asset->id,
        ]);

        $assignment = SoftwareLicenseAssignment::where('software_license_id', $license->id)
            ->where('user_id', $this->regularUser->id)
            ->firstOrFail();

        // 2. Revoke seat
        $revokeResponse = $this->delete(route('portal.assets.licenses.unassign', $assignment->id));
        $revokeResponse->assertRedirect(route('portal.assets.index', ['tab' => 'licenses']));
        $this->assertDatabaseMissing('software_license_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_overview_displays_real_software_tile_with_seats(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->get(route('portal.overview'));
        $response->assertOk();
        $response->assertSee('Software &amp; Licenses', false);
        $response->assertSee('seats');
    }
}
