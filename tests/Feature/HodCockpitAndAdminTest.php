<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Employee;
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
        $response->assertSee(route('portal.dashboard', ['view' => 'sla_breached']));
        $response->assertSee(route('portal.dashboard', ['view' => 'blocked']));
        $response->assertSee(route('portal.dashboard', ['view' => 'pending_approvals']));
        $response->assertSee(route('portal.dashboard', ['view' => 'reopened']));
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

    public function test_admin_can_create_and_update_parent_category_with_custom_icon_and_description(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create parent group with custom icon upload and descriptions
        $fakeIcon = \Illuminate\Http\UploadedFile::fake()->image('pos_scanner.png', 100, 100);

        $response = $this->post(route('portal.admin.categories.store'), [
            'name' => 'Boutique Scales & Tag Scanners',
            'name_km' => 'ជញ្ជីងពេជ្រ & ម៉ាស៊ីនស្កេនស្លាក',
            'description' => 'Precision jewelry scales, gold tester, RFID tag scanner',
            'description_km' => 'ជញ្ជីងថ្លឹងពេជ្រ ម៉ាស៊ីនវាស់គុណភាពមាស និងស្កេន RFID',
            'type' => 'incident_issue',
            'icon' => 'pos',
            'icon_color' => 'amber',
            'icon_file' => $fakeIcon,
        ]);

        $response->assertSessionHasNoErrors();

        $category = TicketCategory::where('name', 'Boutique Scales & Tag Scanners')->firstOrFail();
        $this->assertEquals('ជញ្ជីងពេជ្រ & ម៉ាស៊ីនស្កេនស្លាក', $category->name_km);
        $this->assertEquals('Precision jewelry scales, gold tester, RFID tag scanner', $category->description);
        $this->assertEquals('amber', $category->icon_color);
        $this->assertNotNull($category->icon_url);
        $this->assertStringStartsWith('/uploads/categories/', $category->icon_url);

        // Clean up created file if on disk
        $localFilePath = public_path(ltrim($category->icon_url, '/'));
        if (file_exists($localFilePath)) {
            @unlink($localFilePath);
        }

        // 2. Update category to preset icon, custom hex brand color, and remove custom file
        $updateResponse = $this->put(route('portal.admin.categories.update', $category->id), [
            'name' => 'Boutique Scales & Tag Scanners',
            'name_km' => 'ជញ្ជីងពេជ្រ & ម៉ាស៊ីនស្កេនស្លាក',
            'description' => 'Updated precision jewelry scales and tools',
            'description_km' => 'ជញ្ជីងថ្លឹងពេជ្រដែលបានកែប្រែ',
            'type' => 'incident_issue',
            'icon' => 'other',
            'icon_color' => '#A16B54',
            'remove_icon_file' => 1,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $category->refresh();
        $this->assertEquals('Updated precision jewelry scales and tools', $category->description);
        $this->assertEquals('other', $category->icon);
        $this->assertEquals('#A16B54', $category->icon_color);
        $this->assertNull($category->icon_url);

        // 3. Verify TMA getFormData includes these fields
        $tmaUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $tmaResponse = $this->actingAs($tmaUser)->getJson(route('tma.form-data'));
        $tmaResponse->assertStatus(200);
        $tmaResponse->assertJsonFragment([
            'name' => 'Boutique Scales & Tag Scanners',
            'description' => 'Updated precision jewelry scales and tools',
            'icon' => 'other',
            'icon_color' => '#A16B54',
        ]);
    }

    public function test_category_priority_sla_assignment_and_ticket_deadline_calculation(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create subcategory with manual SLA hours for every priority tier
        $createResponse = $this->post(route('portal.admin.categories.store'), [
            'name' => 'Hardware Failure (Boutique Devices)',
            'name_km' => 'ការខូចខាតឧបករណ៍ផ្នែករឹង',
            'type' => 'incident_issue',
            'requires_approval' => 0,
            'default_priority' => 'high',
            'sla_critical_hours' => 2,
            'sla_high_hours' => 6,
            'sla_medium_hours' => 18,
            'sla_low_hours' => 36,
            'target_acknowledge_minutes' => 15,
        ]);

        $createResponse->assertSessionHasNoErrors();

        $category = TicketCategory::where('name', 'Hardware Failure (Boutique Devices)')->firstOrFail();
        $this->assertEquals(2, $category->sla_critical_hours);
        $this->assertEquals(6, $category->sla_high_hours);
        $this->assertEquals(18, $category->sla_medium_hours);
        $this->assertEquals(36, $category->sla_low_hours);
        $this->assertEquals('high', $category->default_priority);
        // Verify default priority SLA synchronized to target_resolve_hours
        $this->assertEquals(6, $category->target_resolve_hours);

        // 2. Test helper getResolveHoursForPriority
        $this->assertEquals(2, $category->getResolveHoursForPriority('critical'));
        $this->assertEquals(6, $category->getResolveHoursForPriority('high'));
        $this->assertEquals(18, $category->getResolveHoursForPriority('medium'));
        $this->assertEquals(36, $category->getResolveHoursForPriority('low'));

        // 3. Create tickets with different priorities under this category and verify target resolve hours
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $branch = \App\Models\Branch::firstOrFail();

        $ticketCrit = Ticket::create([
            'ticket_code' => 'IT-TEST-CRIT-' . time(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'priority' => 'critical',
            'status' => 'submitted',
            'subject' => 'Emergency POS crash',
            'description' => 'Cannot checkout customers',
        ]);
        $this->assertEquals(2, $ticketCrit->getTargetResolveHours());

        $ticketLow = Ticket::create([
            'ticket_code' => 'IT-TEST-LOW-' . time(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'priority' => 'low',
            'status' => 'submitted',
            'subject' => 'Cosmetic scratch on scanner stand',
            'description' => 'Minor cosmetic issue',
        ]);
        $this->assertEquals(36, $ticketLow->getTargetResolveHours());

        // 4. Update category: change default priority to critical and critical SLA to 1 hour
        $updateResponse = $this->put(route('portal.admin.categories.update', $category->id), [
            'name' => 'Hardware Failure (Boutique Devices)',
            'type' => 'incident_issue',
            'default_priority' => 'critical',
            'sla_critical_hours' => 1,
            'sla_high_hours' => 6,
            'sla_medium_hours' => 18,
            'sla_low_hours' => 36,
            'target_acknowledge_minutes' => 10,
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $category->refresh();
        $this->assertEquals(1, $category->sla_critical_hours);
        $this->assertEquals('critical', $category->default_priority);
        $this->assertEquals(1, $category->target_resolve_hours);
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

        // 5. Register new IT Staff User
        $newUserResponse = $this->post(route('portal.admin.users.store'), [
            'name' => 'Boran Sem Test',
            'email' => 'boran.sem.test@cbvh.com.kh',
            'telegram_username' => 'boran_sem_test',
            'roles' => [$techRole->id],
        ]);
        $newUserResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'boran.sem.test@cbvh.com.kh']);
        $createdUser = User::where('email', 'boran.sem.test@cbvh.com.kh')->firstOrFail();
        $this->assertTrue($createdUser->hasRole('technician'));

        // 6. Promoting an existing user with storeUser does not crash with email taken
        $promoteResponse = $this->post(route('portal.admin.users.store'), [
            'name' => 'Boran Sem Test Promoted',
            'email' => 'boran.sem.test@cbvh.com.kh',
            'telegram_username' => 'boran_sem_promoted',
            'roles' => [$techRole->id, $helpdeskRole->id],
        ]);
        $promoteResponse->assertSessionHasNoErrors();
        $createdUser->refresh();
        $this->assertEquals('Boran Sem Test Promoted', $createdUser->name);
        $this->assertTrue($createdUser->hasRole('helpdesk'));

        // 7. Register new TMA Requester Employee
        $dept = \App\Models\Department::firstOrFail();
        $branch = \App\Models\Branch::firstOrFail();

        $tmaRequesterResponse = $this->post(route('portal.admin.users.tma.store'), [
            'name' => 'Dina Requester Test',
            'email' => 'dina.requester@cbvh.com.kh',
            'employee_code' => 'EMP-9901',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'role' => 'requester',
            'password' => 'Password123!',
            'telegram_username' => 'dina_requester_test',
        ]);
        $tmaRequesterResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-9901',
            'full_name' => 'Dina Requester Test',
            'department_id' => $dept->id,
        ]);
        $tmaUser = User::where('email', 'dina.requester@cbvh.com.kh')->firstOrFail();
        $this->assertTrue($tmaUser->hasRole('requester'));
        $this->assertEquals($dept->id, $tmaUser->employee->department_id);

        // 8. Register new TMA Department HOD Employee
        $tmaHodResponse = $this->post(route('portal.admin.users.tma.store'), [
            'name' => 'Finance HOD Test',
            'email' => 'finance.hod.test@cbvh.com.kh',
            'employee_code' => 'EMP-9902',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'role' => 'hod',
            'password' => 'Password123!',
        ]);
        $tmaHodResponse->assertSessionHasNoErrors();
        $tmaHodUser = User::where('email', 'finance.hod.test@cbvh.com.kh')->firstOrFail();
        $this->assertTrue($tmaHodUser->hasRole('hod'));
        $this->assertTrue($tmaHodUser->hasRole('requester'));
    }

    public function test_admin_organization_departments_and_branches_crud(): void
    {
        $this->actingAs($this->adminUser);

        // 1. View organization overview
        $viewResponse = $this->get(route('portal.admin.organization'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Company Departments');
        $viewResponse->assertSee('Branches & Offices', false);

        // 2. Add Department
        $storeDeptResponse = $this->post(route('portal.admin.departments.store'), [
            'name' => 'Compliance & Risk Management',
            'code' => 'COMP',
        ]);
        $storeDeptResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', [
            'name' => 'Compliance & Risk Management',
            'code' => 'COMP',
            'is_active' => true,
        ]);
        $createdDept = \App\Models\Department::where('code', 'COMP')->firstOrFail();

        // 3. Update Department
        $updateDeptResponse = $this->put(route('portal.admin.departments.update', $createdDept->id), [
            'name' => 'Legal, Compliance & AML',
            'code' => 'LEGAL',
        ]);
        $updateDeptResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', [
            'id' => $createdDept->id,
            'name' => 'Legal, Compliance & AML',
            'code' => 'LEGAL',
        ]);

        // 4. Toggle Department Active
        $toggleDeptResponse = $this->post(route('portal.admin.departments.toggle', $createdDept->id));
        $toggleDeptResponse->assertSessionHasNoErrors();
        $createdDept->refresh();
        $this->assertFalse($createdDept->is_active);

        // 5. Add Branch
        $storeBranchResponse = $this->post(route('portal.admin.branches.store'), [
            'name' => 'Kompong Cham Provincial Branch',
            'code' => 'KC-01',
        ]);
        $storeBranchResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', [
            'name' => 'Kompong Cham Provincial Branch',
            'code' => 'KC-01',
            'is_active' => true,
        ]);
        $createdBranch = \App\Models\Branch::where('code', 'KC-01')->firstOrFail();

        // 6. Update Branch
        $updateBranchResponse = $this->put(route('portal.admin.branches.update', $createdBranch->id), [
            'name' => 'Kompong Cham Central Branch',
            'code' => 'KPC-01',
        ]);
        $updateBranchResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', [
            'id' => $createdBranch->id,
            'name' => 'Kompong Cham Central Branch',
            'code' => 'KPC-01',
        ]);

        // 7. Toggle Branch Active
        $toggleBranchResponse = $this->post(route('portal.admin.branches.toggle', $createdBranch->id));
        $toggleBranchResponse->assertSessionHasNoErrors();
        $createdBranch->refresh();
        $this->assertFalse($createdBranch->is_active);
    }

    public function test_admin_can_update_user_and_employee_details_and_promote_to_hod(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create a regular TMA employee user first
        $dept1 = Department::first();
        $dept2 = Department::where('code', '!=', 'IT')->skip(1)->first();
        $branch = Branch::first();
        $hodRole = Role::where('name', 'hod')->firstOrFail();
        $requesterRole = Role::where('name', 'requester')->firstOrFail();

        $createResponse = $this->post(route('portal.admin.users.tma.store'), [
            'name' => 'Dina Promoted',
            'email' => 'dina.promoted@cbvh.com.kh',
            'employee_code' => 'EMP-7788',
            'department_id' => $dept1->id,
            'branch_id' => $branch->id,
            'phone' => '+855 12 999 888',
            'role' => 'requester',
            'telegram_username' => 'dina_promoted',
        ]);
        $createResponse->assertSessionHasNoErrors();

        $user = User::where('email', 'dina.promoted@cbvh.com.kh')->firstOrFail();
        $this->assertEquals('EMP-7788', $user->employee->employee_code);
        $this->assertEquals($dept1->id, $user->employee->department_id);
        $this->assertFalse($user->hasRole('hod'));

        // 2. Admin edits user: changes department to dept2, updates phone, promotes to HOD
        $updateResponse = $this->put(route('portal.admin.users.update', $user->id), [
            'name' => 'Dina Promoted (HOD)',
            'email' => 'dina.hod@cbvh.com.kh',
            'employee_code' => 'EMP-7788',
            'department_id' => $dept2->id,
            'branch_id' => $branch->id,
            'phone' => '+855 98 111 222',
            'telegram_username' => 'dina_hod_official',
            'is_active' => 1,
            'roles' => [$requesterRole->id, $hodRole->id],
        ]);
        $updateResponse->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertEquals('Dina Promoted (HOD)', $user->name);
        $this->assertEquals('dina.hod@cbvh.com.kh', $user->email);
        $this->assertEquals('dina_hod_official', $user->telegram_username);
        $this->assertTrue($user->hasRole('hod'));
        $this->assertTrue($user->hasRole('requester'));

        $this->assertEquals('Dina Promoted (HOD)', $user->employee->full_name);
        $this->assertEquals($dept2->id, $user->employee->department_id);
        $this->assertEquals('+855 98 111 222', $user->employee->phone);

        // 3. Admin edits user again: unchecks HOD (demoting back to Requester)
        $demoteResponse = $this->put(route('portal.admin.users.update', $user->id), [
            'name' => 'Dina Demoted',
            'email' => 'dina.hod@cbvh.com.kh',
            'employee_code' => 'EMP-7788',
            'department_id' => $dept2->id,
            'branch_id' => $branch->id,
            'phone' => '+855 98 111 222',
            'is_active' => 1,
            'roles' => [$requesterRole->id], // Only requester checked!
        ]);
        $demoteResponse->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->hasRole('hod'));
        $this->assertTrue($user->hasRole('requester'));
        $this->assertFalse($user->can_access_portal);
    }

    public function test_demoting_seeded_hod_emp_1008_removes_hod_role(): void
    {
        $this->actingAs($this->adminUser);

        $user1008 = User::where('email', 'hod.ops@cbvh.com.kh')->firstOrFail();
        $this->assertTrue($user1008->hasRole('hod'));

        $requesterRole = Role::where('name', 'requester')->firstOrFail();

        $response = $this->put(route('portal.admin.users.update', $user1008->id), [
            'name' => $user1008->name,
            'email' => $user1008->email,
            'employee_code' => $user1008->employee->employee_code,
            'department_id' => $user1008->employee->department_id,
            'branch_id' => $user1008->employee->branch_id,
            'is_it_staff' => 0,
            'roles' => [$requesterRole->id], // HOD is unchecked!
        ]);

        $response->assertSessionHasNoErrors();
        $user1008->refresh();
        $this->assertFalse($user1008->hasRole('hod'));
        $this->assertTrue($user1008->hasRole('requester'));
    }

    public function test_technician_hold_request_requires_it_hod_approval_before_pausing_sla(): void
    {
        // 1. Create a ticket in progress assigned to technician
        $ticket = Ticket::create([
            'ticket_code' => 'IT-202610-9999',
            'ticket_type' => 'incident',
            'category_id' => TicketCategory::first()->id,
            'requester_id' => $this->requesterUser->id,
            'branch_id' => Branch::first()->id,
            'current_technician_id' => $this->techUser->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'subject' => 'Boutique Scale broken - replacement sensor required',
            'description' => 'Precision weight sensor faulted on scale #3.',
            'created_at' => now()->subHours(2),
        ]);

        $this->assertFalse($ticket->isSlaPaused());
        $this->assertFalse($ticket->isHoldPending());

        // 2. Technician requests a hold (Option A workflow)
        $this->actingAs($this->techUser);
        $holdResponse = $this->post(route('portal.tickets.block', $ticket->id), [
            'hold_category' => 'Waiting for Vendor / Hardware Delivery',
            'blocked_reason' => 'Vendor out of stock for load cell sensor, PO #8812 ordered from Singapore.',
        ]);
        $holdResponse->assertSessionHasNoErrors();

        $ticket->refresh();
        // Crucial: Status is STILL in_progress and SLA is NOT paused!
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertTrue($ticket->isHoldPending());
        $this->assertFalse($ticket->isSlaPaused());
        $this->assertEquals('Waiting for Vendor / Hardware Delivery', $ticket->hold_requested_category);
        $this->assertNotNull($ticket->hold_requested_at);
        $this->assertEquals($this->techUser->id, $ticket->hold_requested_by_id);

        // 3. Regular technician CANNOT self-approve hold
        $techApproveResponse = $this->post(route('portal.tickets.block.approve', $ticket->id));
        $techApproveResponse->assertSessionHasErrors(['error']);
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);

        // 4. IT HOD approves the hold
        $this->actingAs($this->adminUser); // adminUser has 'system_admin' and 'hod' roles
        $hodApproveResponse = $this->post(route('portal.tickets.block.approve', $ticket->id));
        $hodApproveResponse->assertSessionHasNoErrors();

        $ticket->refresh();
        // Now it is officially blocked, SLA clock paused!
        $this->assertEquals('blocked', $ticket->status);
        $this->assertTrue($ticket->isSlaPaused());
        $this->assertFalse($ticket->isHoldPending());
        $this->assertNotNull($ticket->blocked_at);
        $this->assertStringContainsString('Singapore', $ticket->blocked_reason);

        // 5. Work can resume anytime
        $this->actingAs($this->techUser);
        $resumeResponse = $this->post(route('portal.tickets.accept', $ticket->id));
        $resumeResponse->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertFalse($ticket->isSlaPaused());
        $this->assertNull($ticket->blocked_at);
    }
}
