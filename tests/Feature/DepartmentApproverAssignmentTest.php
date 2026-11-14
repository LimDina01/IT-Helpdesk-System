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

class DepartmentApproverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $requesterUser;

    protected TicketApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $this->approvalService = app(TicketApprovalService::class);
    }

    public function test_organization_page_displays_designated_approvers_and_it_split(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('portal.admin.organization'));
        $response->assertStatus(200);
        $response->assertSee('Organization: Departments, Branches & Approvers', false);
        $response->assertSee('IT Operations');
        $response->assertSee('IT Production');
        $response->assertSee('IT-OPR');
        $response->assertSee('IT-PRD');
        $response->assertSee('Company Departments');
        $response->assertSee('Branch Manager');
    }

    public function test_admin_can_update_department_designated_approver(): void
    {
        $this->actingAs($this->adminUser);

        $dept = Department::where('code', 'HR')->firstOrFail();
        $newHead = User::factory()->create([
            'name' => 'HR Director User',
            'email' => 'hr.director@cbvh.com.kh',
            'is_active' => true,
        ]);

        // 1. Quick approver assignment route
        $response = $this->post(route('portal.admin.departments.approver', $dept->id), [
            'head_user_id' => $newHead->id,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals($newHead->id, $dept->fresh()->head_user_id);

        // 2. Clear approver assignment (set null)
        $response = $this->post(route('portal.admin.departments.approver', $dept->id), [
            'head_user_id' => null,
        ]);
        $response->assertRedirect();
        $this->assertNull($dept->fresh()->head_user_id);
    }

    public function test_admin_can_update_branch_manager(): void
    {
        $this->actingAs($this->adminUser);

        $branch = Branch::where('code', 'BTB-01')->firstOrFail();
        $manager = User::factory()->create([
            'name' => 'Battambang Regional Manager',
            'email' => 'btb.manager@cbvh.com.kh',
            'is_active' => true,
        ]);

        $response = $this->post(route('portal.admin.branches.manager', $branch->id), [
            'manager_user_id' => $manager->id,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals($manager->id, $branch->fresh()->manager_user_id);
    }

    public function test_slot_1_routes_to_explicitly_designated_department_head(): void
    {
        // 1. Create a business department and assign an explicit designated head
        $dept = Department::create([
            'name' => 'Legal & Compliance',
            'code' => 'LEGAL',
            'is_active' => true,
        ]);

        $legalHead = User::factory()->create([
            'name' => 'Attorney General User',
            'email' => 'general.counsel@cbvh.com.kh',
            'is_active' => true,
        ]);
        $dept->update(['head_user_id' => $legalHead->id]);

        // 2. Create employee in Legal and user requester
        $branch = Branch::firstOrFail();
        $emp = Employee::create([
            'employee_code' => 'LEG-001',
            'full_name' => 'Legal Clerk',
            'email' => 'clerk@cbvh.com.kh',
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $clerkUser = User::factory()->create([
            'employee_id' => $emp->id,
            'name' => 'Legal Clerk',
            'email' => 'clerk@cbvh.com.kh',
            'is_active' => true,
        ]);
        $clerkUser->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        // Random other user with 'hod' role from another department
        $randomHod = User::factory()->create([
            'name' => 'Unrelated HOD',
            'email' => 'random.hod@cbvh.com.kh',
            'is_active' => true,
        ]);
        $randomHod->roles()->attach(Role::where('name', 'hod')->firstOrFail());

        // 3. Create service request ticket
        $category = TicketCategory::where('requires_approval', true)->firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'IT-202611-9991',
            'subject' => 'Request special software license for legal review',
            'description' => 'Need contract analysis software',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'requester_id' => $clerkUser->id,
            'branch_id' => $branch->id,
            'priority' => 'medium',
            'status' => 'submitted',
        ]);

        // Create approval chain
        $this->approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();

        // 4. Verification:
        // - Designated legal head CAN approve Step 1
        $this->assertTrue($this->approvalService->canUserApprove($legalHead, $step1));

        // - IT HOD / Admin CANNOT approve Step 1 for business department
        $this->assertFalse($this->approvalService->canUserApprove($this->adminUser, $step1));

        // - Requester CANNOT approve their own ticket
        $this->assertFalse($this->approvalService->canUserApprove($clerkUser, $step1));

        // - Random HOD from another department CANNOT approve
        $this->assertFalse($this->approvalService->canUserApprove($randomHod, $step1));

        // 5. Legal head approves Step 1
        $success = $this->approvalService->approve($step1, $legalHead, 'Approved by Legal Head');
        $this->assertTrue($success);
        $this->assertEquals('approved', $step1->fresh()->status);
        $this->assertEquals($legalHead->id, $step1->fresh()->approver_id);
    }

    public function test_it_operations_head_approves_opr_tickets(): void
    {
        $oprDept = Department::where('code', 'IT-OPR')->firstOrFail();
        $oprHead = User::factory()->create([
            'name' => 'Mr. OPR Chief',
            'email' => 'opr.chief@cbvh.com.kh',
            'is_active' => true,
        ]);
        $oprHead->roles()->attach(Role::where('name', 'hod')->firstOrFail());
        $oprDept->update(['head_user_id' => $oprHead->id]);

        $prdDept = Department::where('code', 'IT-PRD')->firstOrFail();
        $prdHead = User::factory()->create([
            'name' => 'Mr. PRD Chief',
            'email' => 'prd.chief@cbvh.com.kh',
            'is_active' => true,
        ]);
        $prdHead->roles()->attach(Role::where('name', 'hod')->firstOrFail());
        $prdDept->update(['head_user_id' => $prdHead->id]);

        // Create OPR ticket
        $oprCategory = TicketCategory::where('it_division', 'opr')->first()
            ?? TicketCategory::first();
        $oprCategory->update(['it_division' => 'opr', 'requires_approval' => true]);

        $ticket = Ticket::create([
            'ticket_code' => 'IT-202611-9992',
            'subject' => 'Need additional RAM for office desktop',
            'description' => 'Office desktop upgrade',
            'ticket_type' => 'service_request',
            'category_id' => $oprCategory->id,
            'it_division' => 'opr',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => Branch::firstOrFail()->id,
            'priority' => 'low',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $step2 = $ticket->approvals()->where('step_number', 2)->firstOrFail();

        // If Step 1 is pending, approve Step 1 first so Step 2 becomes actionable
        $step1 = $ticket->approvals()->where('step_number', 1)->first();
        if ($step1 && $step1->status === 'pending') {
            $step1->update(['status' => 'approved', 'actioned_at' => now()]);
        }

        // Verification for Slot 3 / IT Manager Approval:
        // Designated OPR Head can approve OPR ticket
        $this->assertTrue($this->approvalService->canUserApprove($oprHead, $step2));

        // System Admin can always approve
        $this->assertTrue($this->approvalService->canUserApprove($this->adminUser, $step2));
    }

    public function test_dynamic_it_sub_unit_creation_and_approval_routing(): void
    {
        $itParent = Department::where('code', 'IT')->firstOrFail();

        // 1. Create a dynamic new IT office / sub-unit (e.g. IT Solutions & Custom Apps)
        $itSol = Department::create([
            'name' => 'IT Solutions Office',
            'code' => 'IT-SOL-NEW',
            'parent_id' => $itParent->id,
            'is_active' => true,
        ]);

        $solHead = User::factory()->create([
            'name' => 'Solutions Office Head',
            'email' => 'sol.head@cbvh.com.kh',
            'is_active' => true,
        ]);
        $solHead->roles()->attach(Role::where('name', 'hod')->firstOrFail());
        $itSol->update(['head_user_id' => $solHead->id]);

        // 2. An OPR head for another office
        $oprHead = User::factory()->create([
            'name' => 'Operations Office Head',
            'email' => 'operations.chief@cbvh.com.kh',
            'is_active' => true,
        ]);
        $oprHead->roles()->attach(Role::where('name', 'hod')->firstOrFail());
        Department::where('code', 'IT-OPR')->update(['head_user_id' => $oprHead->id]);

        // 3. Category dynamically linked to this new sub-unit
        $category = TicketCategory::create([
            'name' => 'Custom Core Banking Modules',
            'code' => 'SOL-099',
            'it_division' => 'prd',
            'it_department_id' => $itSol->id,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'ticket_code' => 'IT-202611-9994',
            'subject' => 'Deploy custom loan schedule workflow',
            'description' => 'Requirement for banking loan schedules',
            'ticket_type' => 'service_request',
            'category_id' => $category->id,
            'it_division' => 'prd',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => Branch::firstOrFail()->id,
            'priority' => 'high',
            'status' => 'submitted',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $step2 = $ticket->approvals()->where('step_number', 2)->firstOrFail();

        // Step 1 approved
        $step1 = $ticket->approvals()->where('step_number', 1)->first();
        if ($step1 && $step1->status === 'pending') {
            $step1->update(['status' => 'approved', 'actioned_at' => now()]);
        }

        // Verification:
        // - Newly designated IT Solutions Head CAN approve this ticket
        $this->assertTrue($this->approvalService->canUserApprove($solHead, $step2));

        // - Parent IT Head / Admin CAN approve
        $this->assertTrue($this->approvalService->canUserApprove($this->adminUser, $step2));

        // - Another sub-unit head (Operations) CANNOT approve IT Solutions ticket
        $this->assertFalse($this->approvalService->canUserApprove($oprHead, $step2));

        // 4. Verify Admin Organization screen displays the nested hierarchy
        $this->actingAs($this->adminUser);
        $response = $this->get(route('portal.admin.organization'));
        $response->assertStatus(200);
        $response->assertSee('IT Solutions Office');
        $response->assertSee('Part of Information Technology');
    }

    public function test_approval_chain_assigns_lowest_level_card_and_pauses_sla(): void
    {
        $requester = $this->requesterUser; // Sokha Chan (dept OPS, branch HQ-PP)
        $dept = $requester->employee->department;
        $branch = $requester->employee->branch;

        // Create a dedicated Supervisor (Level 2) card only - no Level 1 card exists
        $supervisorUser = User::factory()->create([
            'name' => 'Operations Supervisor User',
            'email' => 'ops.supervisor@cbvh.com.kh',
            'is_active' => true,
        ]);

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'user_id' => $supervisorUser->id,
            'level' => 2,
            'title' => 'Shift Supervisor',
            'is_active' => true,
        ]);

        $category = TicketCategory::where('requires_approval', true)->firstOrFail();

        $ticket = Ticket::create([
            'ticket_code' => 'TICK-SR-LEVEL-FALLBACK',
            'ticket_type' => 'service_request',
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'subject' => 'Need additional logistics trolley',
            'description' => 'Replacing broken trolley',
            'status' => 'submitted',
            'priority' => 'high',
        ]);

        $this->approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        // 1. Verify Step 1 is assigned to the Level 2 supervisor rather than being left null
        $step1 = $ticket->approvals()->where('step_number', 1)->firstOrFail();
        $this->assertEquals($supervisorUser->id, $step1->approver_id);
        $this->assertEquals('Shift Supervisor', $step1->step_name);

        // 2. Verify SLA is paused during pending_approval
        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertTrue($ticket->isSlaPaused());
        $this->assertFalse($ticket->isSlaOverdue());

        // 3. Supervisor approves step 1
        $this->approvalService->approve($step1, $supervisorUser, 'Supervisor approved');
        $step2 = $ticket->approvals()->where('step_number', 2)->firstOrFail();
        $this->assertEquals('pending', $step2->status);

        // 4. IT manager approves step 2 (final approval)
        $this->approvalService->approve($step2, $this->adminUser, 'IT signoff complete');
        $ticket->refresh();

        // 5. Verify ticket moves to submitted and SLA clock is unpaused
        $this->assertEquals('submitted', $ticket->status);
        $this->assertFalse($ticket->isSlaPaused());
    }
}
