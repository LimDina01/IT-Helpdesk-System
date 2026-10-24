<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HodCockpitAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $requesterUser;
    protected User $techUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $this->techUser = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
    }

    public function test_non_hod_cannot_access_cockpit_or_admin(): void
    {
        // Unauthenticated
        $this->get(route('portal.cockpit'))->assertRedirect(route('login'));
        $this->get(route('portal.admin.index'))->assertRedirect(route('login'));

        // Requester (403 forbidden)
        $this->actingAs($this->requesterUser);
        $this->get(route('portal.cockpit'))->assertStatus(403);
        $this->get(route('portal.admin.index'))->assertStatus(403);
    }

    public function test_hod_accesses_cockpit_and_views_live_workload_board(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('portal.cockpit'));
        $response->assertStatus(200);
        $response->assertSee('Live Technician Workload');
        $response->assertSee('High-Attention Incident');
        $response->assertSee('System Audit Trail');
        $response->assertSee('Dara Sam'); // seeded technician
    }

    public function test_executive_priority_override_action(): void
    {
        $branch = Branch::firstOrFail();
        $category = TicketCategory::where('name', 'Thermal Receipt Printer Offline')->firstOrFail();

        $ticket = Ticket::create([
            'ticket_code' => 'TICK-EXEC-001',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'priority' => 'low',
            'status' => 'submitted',
            'subject' => 'VIP Boardroom Display Glitch',
            'description' => 'Display flickering before AGM meeting',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->post(route('portal.cockpit.override-priority', $ticket->id), [
            'priority' => 'critical',
            'reason' => 'Annual General Meeting happening in 15 mins.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('critical', $ticket->priority);

        // Verify audit log
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'PRIORITY_OVERRIDDEN',
            'old_value' => 'low',
            'new_value' => 'critical',
        ]);

        // Verify system message
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'visibility' => 'system_event',
        ]);
    }

    public function test_admin_category_management_crud(): void
    {
        $this->actingAs($this->adminUser);

        // 1. View categories
        $viewResponse = $this->get(route('portal.admin.index'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Categories, Workflows');

        // 2. Create new category
        $createResponse = $this->post(route('portal.admin.categories.store'), [
            'name' => 'Data Center UPS Battery Alarm',
            'type' => 'incident_issue',
            'requires_approval' => 0,
            'default_priority' => 'critical',
            'target_acknowledge_minutes' => 10,
            'target_resolve_hours' => 2,
        ]);

        $createResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_categories', [
            'name' => 'Data Center UPS Battery Alarm',
            'default_priority' => 'critical',
            'target_acknowledge_minutes' => 10,
        ]);

        $newCategory = TicketCategory::where('name', 'Data Center UPS Battery Alarm')->firstOrFail();

        // 3. Update category
        $updateResponse = $this->put(route('portal.admin.categories.update', $newCategory->id), [
            'name' => 'Data Center UPS Battery Alarm (Updated)',
            'type' => 'incident_issue',
            'requires_approval' => 0,
            'default_priority' => 'critical',
            'target_acknowledge_minutes' => 5,
            'target_resolve_hours' => 1,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_categories', [
            'id' => $newCategory->id,
            'target_acknowledge_minutes' => 5,
        ]);

        // 4. Toggle category active status
        $toggleResponse = $this->post(route('portal.admin.categories.toggle', $newCategory->id));
        $toggleResponse->assertSessionHasNoErrors();
        $newCategory->refresh();
        $this->assertFalse($newCategory->is_active);
    }

    public function test_admin_holidays_management(): void
    {
        $this->actingAs($this->adminUser);

        // 1. View holidays
        $viewResponse = $this->get(route('portal.admin.holidays'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Official Company Holidays Calendar');

        // 2. Add holiday
        $addResponse = $this->post(route('portal.admin.holidays.store'), [
            'name' => 'National Peace Day',
            'holiday_date' => '2026-12-29',
            'is_recurring' => 1,
            'notes' => 'Celebration of peace',
        ]);

        $addResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('company_holidays', [
            'name' => 'National Peace Day',
            'holiday_date' => '2026-12-29 00:00:00',
        ]);

        $holiday = CompanyHoliday::where('name', 'National Peace Day')->firstOrFail();

        // 3. Delete holiday
        $deleteResponse = $this->delete(route('portal.admin.holidays.destroy', $holiday->id));
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('company_holidays', ['id' => $holiday->id]);
    }

    public function test_admin_user_roles_management(): void
    {
        $this->actingAs($this->adminUser);

        // 1. View users
        $viewResponse = $this->get(route('portal.admin.users'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Staff Roster');

        // 2. Update roles for tech user (grant helpdesk role in addition to tech)
        $helpdeskRole = Role::where('name', 'helpdesk')->firstOrFail();
        $techRole = Role::where('name', 'technician')->firstOrFail();

        $roleUpdateResponse = $this->post(route('portal.admin.users.roles', $this->techUser->id), [
            'roles' => [$helpdeskRole->id, $techRole->id],
        ]);

        $roleUpdateResponse->assertSessionHasNoErrors();
        $this->techUser->refresh();
        $this->assertTrue($this->techUser->hasRole('helpdesk'));
        $this->assertTrue($this->techUser->hasRole('technician'));

        // 3. Toggle user active status
        $toggleResponse = $this->post(route('portal.admin.users.toggle', $this->techUser->id));
        $toggleResponse->assertSessionHasNoErrors();
        $this->techUser->refresh();
        $this->assertFalse($this->techUser->is_active);

        // 4. Verify cannot deactivate oneself
        $selfToggleResponse = $this->post(route('portal.admin.users.toggle', $this->adminUser->id));
        $selfToggleResponse->assertSessionHasErrors(['error']);
    }
}
