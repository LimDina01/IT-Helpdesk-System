<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketApprovalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiLevelDepartmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected TicketApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->approvalService = app(TicketApprovalService::class);
    }

    public function test_admin_can_add_and_remove_department_approver_cards(): void
    {
        $this->actingAs($this->adminUser);

        $dept = Department::where('code', 'OPS')->firstOrFail();

        $teamLeader = User::factory()->create([
            'name' => 'Operations Team Leader 1',
            'email' => 'ops.tl1@cbvh.com.kh',
            'is_active' => true,
        ]);

        // 1. Add Level 1 Team Leader card
        $response = $this->post(route('portal.admin.departments.approvers.store', $dept->id), [
            'user_id' => $teamLeader->id,
            'level' => 1,
            'title' => 'Team Leader - Cash Logistics',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('department_approvers', [
            'department_id' => $dept->id,
            'user_id' => $teamLeader->id,
            'level' => 1,
            'title' => 'Team Leader - Cash Logistics',
        ]);

        $approverCard = DepartmentApprover::where('department_id', $dept->id)
            ->where('user_id', $teamLeader->id)
            ->firstOrFail();

        // 2. Remove approver card
        $deleteResponse = $this->delete(route('portal.admin.departments.approvers.destroy', [$dept->id, $approverCard->id]));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('department_approvers', ['id' => $approverCard->id]);
    }

    public function test_admin_can_edit_department_approver_card_and_level(): void
    {
        $this->actingAs($this->adminUser);

        $dept = Department::where('code', 'SALES')->firstOrFail();

        $originalUser = User::factory()->create([
            'name' => 'Jewelry Store Sales Lead',
            'email' => 'sales.lead@cbvh.com.kh',
            'is_active' => true,
        ]);

        $approver = DepartmentApprover::create([
            'department_id' => $dept->id,
            'user_id' => $originalUser->id,
            'level' => 1,
            'title' => 'Sales Team Leader',
            'is_active' => true,
        ]);

        $promotedUser = User::factory()->create([
            'name' => 'Regional Jewelry Retail Manager',
            'email' => 'retail.mgr@cbvh.com.kh',
            'is_active' => true,
        ]);

        // Edit level from 1 to 4 and change title and assigned user
        $response = $this->put(route('portal.admin.departments.approvers.update', [$dept->id, $approver->id]), [
            'user_id' => $promotedUser->id,
            'level' => 4,
            'title' => 'Retail General Manager',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $approver->refresh();
        $this->assertEquals(4, $approver->level);
        $this->assertEquals('Retail General Manager', $approver->title);
        $this->assertEquals($promotedUser->id, $approver->user_id);
    }

    public function test_option_b_requester_routes_step_1_directly_to_designated_line_manager(): void
    {
        $dept = Department::where('code', 'FIN')->firstOrFail();
        $branch = Branch::where('code', 'HQ-PP')->firstOrFail();

        // Create Team Leader 1 (Level 1)
        $teamLeader1 = User::factory()->create([
            'name' => 'Accounting Team Leader A',
            'email' => 'acct.tl.a@cbvh.com.kh',
            'is_active' => true,
        ]);
        $teamLeader1->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'user_id' => $teamLeader1->id,
            'level' => 1,
            'title' => 'Team Leader - Payables',
            'is_active' => true,
        ]);

        // Create Team Leader 2 (Peer Level 1)
        $teamLeader2 = User::factory()->create([
            'name' => 'Accounting Team Leader B',
            'email' => 'acct.tl.b@cbvh.com.kh',
            'is_active' => true,
        ]);
        $teamLeader2->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        // Create Employee mapped directly to Team Leader 1 (Option B)
        $emp = Employee::create([
            'employee_code' => 'FIN-999',
            'full_name' => 'Account Clerk Sok',
            'email' => 'clerk.sok@cbvh.com.kh',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'line_manager_user_id' => $teamLeader1->id, // Option B
            'is_active' => true,
        ]);

        $requester = User::factory()->create([
            'employee_id' => $emp->id,
            'name' => 'Account Clerk Sok',
            'email' => 'clerk.sok@cbvh.com.kh',
            'is_active' => true,
        ]);
        $requester->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        // Submit Service Request
        $category = TicketCategory::where('requires_approval', true)->firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'SR-FIN-2026-001',
            'subject' => 'Need additional ledger accounting monitor',
            'description' => 'Ledger work requires second display',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'priority' => 'medium',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();
        $this->assertEquals('pending', $step1->status);
        $this->assertEquals($teamLeader1->id, $step1->approver_id); // Assigned to Option B Line Manager!

        // Team Leader 1 CAN approve
        $this->assertTrue($this->approvalService->canUserApprove($teamLeader1, $step1));

        // Requester CANNOT approve their own ticket
        $this->assertFalse($this->approvalService->canUserApprove($requester, $step1));
    }

    public function test_team_leader_can_escalate_ticket_to_supervisor_level_2(): void
    {
        $dept = Department::where('code', 'FIN')->firstOrFail();
        $branch = Branch::where('code', 'HQ-PP')->firstOrFail();

        // Level 1: Team Leader
        $teamLeader = User::factory()->create([
            'name' => 'Team Leader Chea',
            'email' => 'tl.chea@cbvh.com.kh',
            'is_active' => true,
        ]);
        $teamLeader->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'user_id' => $teamLeader->id,
            'level' => 1,
            'title' => 'Team Leader',
            'is_active' => true,
        ]);

        // Level 2: Supervisor
        $supervisor = User::factory()->create([
            'name' => 'Supervisor Ouk',
            'email' => 'sup.ouk@cbvh.com.kh',
            'is_active' => true,
        ]);
        $supervisor->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'user_id' => $supervisor->id,
            'level' => 2,
            'title' => 'Finance Supervisor',
            'is_active' => true,
        ]);

        // Employee
        $emp = Employee::create([
            'employee_code' => 'FIN-888',
            'full_name' => 'Accountant Dara',
            'email' => 'dara.fin@cbvh.com.kh',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'line_manager_user_id' => $teamLeader->id,
            'is_active' => true,
        ]);

        $requester = User::factory()->create([
            'employee_id' => $emp->id,
            'name' => 'Accountant Dara',
            'email' => 'dara.fin@cbvh.com.kh',
            'is_active' => true,
        ]);
        $requester->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        // Create Ticket
        $category = TicketCategory::where('requires_approval', true)->firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'SR-FIN-2026-002',
            'subject' => 'Request advanced financial analytics tool',
            'description' => 'High cost specialized software module',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'priority' => 'high',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();

        // 1. Team Leader decides this request exceeds their threshold -> ESCALATES
        $escalateSuccess = $this->approvalService->escalate(
            $step1,
            $teamLeader,
            'Endorsed by Team Leader: High budget request, escalating to Supervisor Ouk for sign-off.',
            $supervisor->id
        );

        $this->assertTrue($escalateSuccess);
        $step1->refresh();
        $this->assertEquals('approved', $step1->status);
        $this->assertStringContainsString('Endorsed & Escalated', $step1->comments);

        // 2. An escalated step (Step 2) was inserted for the Supervisor!
        $ticket->refresh();
        $escalatedStep = $ticket->approvals()->where('step_number', 2)->firstOrFail();
        $this->assertEquals('pending', $escalatedStep->status);
        $this->assertEquals($supervisor->id, $escalatedStep->approver_id);
        $this->assertStringContainsString('Escalated', $escalatedStep->step_name);

        // 3. Supervisor can approve the escalated step
        $this->assertTrue($this->approvalService->canUserApprove($supervisor, $escalatedStep));

        $supervisorApproved = $this->approvalService->approve($escalatedStep, $supervisor, 'Supervisor approved budget.');
        $this->assertTrue($supervisorApproved);

        // 4. Now IT Manager step (shifted to Step 3) is pending
        $itStep = $ticket->approvals()->where('step_number', 3)->firstOrFail();
        $this->assertEquals('it_manager', $itStep->role_name);
        $this->assertEquals('pending', $itStep->status);

        // IT Admin approves final step
        $this->assertTrue($this->approvalService->approve($itStep, $this->adminUser, 'IT approved.'));
        $ticket->refresh();
        $this->assertEquals('submitted', $ticket->status); // Ready for IT fulfillment!
    }

    public function test_team_leader_can_forward_step_to_peer(): void
    {
        $dept = Department::where('code', 'OPS')->firstOrFail();
        $branch = Branch::where('code', 'HQ-PP')->firstOrFail();

        $tl1 = User::factory()->create([
            'name' => 'Team Leader 1',
            'email' => 'tl1@cbvh.com.kh',
            'is_active' => true,
        ]);
        $tl1->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        $tl2 = User::factory()->create([
            'name' => 'Team Leader 2 (Peer)',
            'email' => 'tl2@cbvh.com.kh',
            'is_active' => true,
        ]);
        $tl2->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        $emp = Employee::create([
            'employee_code' => 'OPS-777',
            'full_name' => 'Ops Staff Meng',
            'email' => 'meng.ops@cbvh.com.kh',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'line_manager_user_id' => $tl1->id,
            'is_active' => true,
        ]);

        $requester = User::factory()->create([
            'employee_id' => $emp->id,
            'name' => 'Ops Staff Meng',
            'email' => 'meng.ops@cbvh.com.kh',
            'is_active' => true,
        ]);
        $requester->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        $category = TicketCategory::where('requires_approval', true)->firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'SR-OPS-2026-003',
            'subject' => 'Request scanner replacement',
            'description' => 'Broken document scanner',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'priority' => 'low',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();

        // TL1 forwards to peer TL2
        $forwardSuccess = $this->approvalService->forward($step1, $tl1, $tl2, 'Forwarding to TL2 while out of office');
        $this->assertTrue($forwardSuccess);

        $step1->refresh();
        $this->assertEquals($tl2->id, $step1->approver_id);
        $this->assertTrue($this->approvalService->canUserApprove($tl2, $step1));
    }

    public function test_branch_department_approval_matrix_routes_requests_to_specific_branch_leader(): void
    {
        $this->actingAs($this->adminUser);

        $salesDept = Department::where('code', 'SALES')->firstOrFail();
        $aeonBranch = Branch::firstOrCreate(
            ['code' => 'AEON-MALL'],
            ['name' => 'Aeon Mall Store', 'is_active' => true]
        );
        $centralBranch = Branch::firstOrCreate(
            ['code' => 'CENTRAL-MKT'],
            ['name' => 'Central Market Store', 'is_active' => true]
        );

        // Aeon Mall Store Sales Team Leader
        $aeonLeader = User::factory()->create([
            'name' => 'Aeon Sales Lead Sokha',
            'email' => 'sokha.aeon@cbvh.com.kh',
            'is_active' => true,
        ]);
        $aeonLeader->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        // Central Market Store Sales Team Leader
        $centralLeader = User::factory()->create([
            'name' => 'Central Market Sales Lead Vicheka',
            'email' => 'vicheka.central@cbvh.com.kh',
            'is_active' => true,
        ]);
        $centralLeader->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        // 1. Admin configures Aeon Approver via department endpoint with branch_id
        $resp1 = $this->post(route('portal.admin.departments.approvers.store', $salesDept->id), [
            'branch_id' => $aeonBranch->id,
            'user_id' => $aeonLeader->id,
            'level' => 1,
            'title' => 'Aeon Store Sales Lead',
        ]);
        $resp1->assertRedirect();

        // 2. Admin configures Central Market Approver via branch matrix endpoint
        $resp2 = $this->post(route('portal.admin.branches.approvers.store', $centralBranch->id), [
            'department_id' => $salesDept->id,
            'user_id' => $centralLeader->id,
            'level' => 1,
            'title' => 'Central Store Sales Lead',
        ]);
        $resp2->assertRedirect();

        $this->assertDatabaseHas('department_approvers', [
            'department_id' => $salesDept->id,
            'branch_id' => $aeonBranch->id,
            'user_id' => $aeonLeader->id,
            'level' => 1,
        ]);

        $this->assertDatabaseHas('department_approvers', [
            'department_id' => $salesDept->id,
            'branch_id' => $centralBranch->id,
            'user_id' => $centralLeader->id,
            'level' => 1,
        ]);

        // 3. Aeon Mall Sales Requester submits request
        $aeonStaff = Employee::create([
            'employee_code' => 'AEON-SLS-01',
            'full_name' => 'Aeon Jewelry Sales Rep Bunna',
            'email' => 'bunna.aeon@cbvh.com.kh',
            'department_id' => $salesDept->id,
            'branch_id' => $aeonBranch->id,
            'is_active' => true,
        ]);

        $aeonRequester = User::factory()->create([
            'employee_id' => $aeonStaff->id,
            'name' => 'Aeon Jewelry Sales Rep Bunna',
            'email' => 'bunna.aeon@cbvh.com.kh',
            'is_active' => true,
        ]);
        $aeonRequester->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        $category = TicketCategory::where('requires_approval', true)->firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'SR-AEON-001',
            'subject' => 'Need POS barcode scanner replacement for showcase #3',
            'description' => 'Scanner laser failed during diamond ring sale',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'requester_id' => $aeonRequester->id,
            'branch_id' => $aeonBranch->id,
            'priority' => 'high',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();

        // Must route to Aeon Leader
        $this->assertEquals($aeonLeader->id, $step1->approver_id);

        // Aeon Leader CAN approve
        $this->assertTrue($this->approvalService->canUserApprove($aeonLeader, $step1));

        // Central Market Leader CANNOT approve Aeon's ticket
        $this->assertFalse($this->approvalService->canUserApprove($centralLeader, $step1));
    }

    public function test_admin_can_manage_approvers_on_dedicated_approvers_tab(): void
    {
        $this->actingAs($this->adminUser);

        $dept = Department::where('code', 'SALES')->firstOrFail();
        $branch = Branch::where('code', 'HQ-PP')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Jewelry Floor Supervisor Sreypov',
            'email' => 'sreypov@cbvh.com.kh',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        // 1. Dedicated tab loads successfully
        $response = $this->get(route('portal.admin.approvers'));
        $response->assertOk();
        $response->assertSee('Approval Hierarchy &amp; Levels', false);

        // 2. Store via dedicated endpoint
        $storeResp = $this->post(route('portal.admin.approvers.store'), [
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'level' => 2,
            'title' => 'Jewelry Floor Supervisor',
        ]);
        $storeResp->assertRedirect();
        $storeResp->assertSessionHas('status');

        $card = DepartmentApprover::where('department_id', $dept->id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals(2, $card->level);
        $this->assertEquals('Jewelry Floor Supervisor', $card->title);

        // 3. Update via dedicated endpoint
        $updateResp = $this->put(route('portal.admin.approvers.update', $card->id), [
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'level' => 3,
            'title' => 'Jewelry Floor Operations Manager',
        ]);
        $updateResp->assertRedirect();
        $updateResp->assertSessionHas('status');

        $card->refresh();
        $this->assertEquals(3, $card->level);
        $this->assertEquals('Jewelry Floor Operations Manager', $card->title);

        // 4. Delete via dedicated endpoint
        $deleteResp = $this->delete(route('portal.admin.approvers.destroy', $card->id));
        $deleteResp->assertRedirect();
        $deleteResp->assertSessionHas('status');

        $this->assertDatabaseMissing('department_approvers', ['id' => $card->id]);
    }

    public function test_non_admin_cannot_access_dedicated_approvers_tab(): void
    {
        $requester = User::factory()->create(['is_active' => true]);
        $requester->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        $this->actingAs($requester);

        $response = $this->get(route('portal.admin.approvers'));
        $response->assertForbidden();
    }
}

