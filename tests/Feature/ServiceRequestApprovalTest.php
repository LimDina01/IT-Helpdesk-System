<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TelegramAuthService;
use App\Services\TicketApprovalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ServiceRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;
    protected User $hodUser;
    protected User $opsHodUser;
    protected User $itHodUser;
    protected User $adminUser;
    protected User $techUser;
    protected Branch $branch;
    protected TicketCategory $procurementCat;
    protected TicketCategory $incidentCat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->requester = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $this->opsHodUser = User::where('email', 'hod.ops@cbvh.com.kh')->firstOrFail();
        $this->hodUser = $this->opsHodUser;
        $this->itHodUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->techUser = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
        $this->branch = Branch::firstOrFail();

        $this->procurementCat = TicketCategory::where('name', 'Laptop / Desktop Procurement Request')->firstOrFail();
        $this->incidentCat = TicketCategory::where('name', 'Hardware Failure')->firstOrFail();
    }

    public function test_submitting_incident_skips_approval_and_enters_triage(): void
    {
        $this->actingAs($this->requester);

        $response = $this->postJson('/tma/api/tickets', [
            'ticket_type' => 'incident',
            'category_id' => $this->incidentCat->id,
            'branch_id' => $this->branch->id,
            'subject' => 'Monitor Screen Cracked and Flickering',
            'description' => 'The monitor on desk 4 has flickering colors and crack lines.',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $ticketId = $response->json('ticket_id');

        $ticket = Ticket::with('approvals')->findOrFail($ticketId);
        $this->assertEquals('incident', $ticket->ticket_type);
        $this->assertEquals('submitted', $ticket->status);
        $this->assertCount(0, $ticket->approvals);
    }

    public function test_submitting_service_request_creates_2_step_approval_chain(): void
    {
        $this->actingAs($this->requester);

        $response = $this->postJson('/tma/api/tickets', [
            'ticket_type' => 'service_request',
            'category_id' => $this->procurementCat->id,
            'branch_id' => $this->branch->id,
            'subject' => 'New Laptop Request for Senior Analyst',
            'description' => 'Requesting ThinkPad T14 Gen 4 for data analysis.',
            'priority' => 'medium',
        ]);

        $response->assertStatus(201);
        $ticketId = $response->json('ticket_id');

        $ticket = Ticket::with('approvals')->findOrFail($ticketId);
        $this->assertEquals('service_request', $ticket->ticket_type);
        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertCount(2, $ticket->approvals);

        $step1 = $ticket->approvals->where('step_number', 1)->first();
        $this->assertEquals('Department HOD Approval', $step1->step_name);
        $this->assertEquals('department_hod', $step1->role_name);
        $this->assertEquals('pending', $step1->status);

        $step2 = $ticket->approvals->where('step_number', 2)->first();
        $this->assertEquals('IT Manager Approval', $step2->step_name);
        $this->assertEquals('it_manager', $step2->role_name);
        $this->assertEquals('pending', $step2->status);

        // System message created
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'visibility' => 'system_event',
        ]);
    }

    public function test_hod_approves_step_1_via_web_portal(): void
    {
        // 1. Create ticket
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-001',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'high',
            'status' => 'submitted',
            'subject' => 'Office Move to 5th Floor',
            'description' => 'Desk and phone relocation',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $step1 = $ticket->approvals()->where('step_number', 1)->first();

        // 2. Act as HOD and approve Step 1
        $this->actingAs($this->hodUser);

        $response = $this->post(route('portal.tickets.approvals.approve', [$ticket->id, $step1->id]), [
            'comments' => 'Approved from department budget.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $step1->refresh();
        $this->assertEquals('approved', $step1->status);
        $this->assertEquals($this->hodUser->id, $step1->approver_id);
        $this->assertEquals('Approved from department budget.', $step1->comments);
        $this->assertNotNull($step1->actioned_at);

        // Ticket remains pending_approval waiting for Step 2
        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);
    }

    public function test_it_manager_approves_step_2_moving_ticket_to_triage(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-002',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'high',
            'status' => 'submitted',
            'subject' => 'Procurement Request',
            'description' => 'Need second screen for monitoring',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $step1 = $ticket->approvals()->where('step_number', 1)->first();
        $step2 = $ticket->approvals()->where('step_number', 2)->first();

        // Step 1 approved
        $approvalService->approve($step1, $this->hodUser, 'Dept HOD approved');

        // Step 2 approved by Admin/IT Manager
        $this->actingAs($this->adminUser);
        $response = $this->post(route('portal.tickets.approvals.approve', [$ticket->id, $step2->id]), [
            'comments' => 'IT stock available, proceed to assign.',
        ]);

        $response->assertSessionHasNoErrors();

        $step2->refresh();
        $this->assertEquals('approved', $step2->status);

        // Ticket is now submitted and ready for Help Desk assignment!
        $ticket->refresh();
        $this->assertEquals('submitted', $ticket->status);
    }

    public function test_rejection_halts_approval_workflow(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-003',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'low',
            'status' => 'submitted',
            'subject' => 'Personal Gaming Mouse Request',
            'description' => 'Requesting high-DPI gaming mouse',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $step1 = $ticket->approvals()->where('step_number', 1)->first();

        // Act as HOD and reject
        $this->actingAs($this->hodUser);
        $response = $this->post(route('portal.tickets.approvals.reject', [$ticket->id, $step1->id]), [
            'reason' => 'Non-standard hardware request denied.',
        ]);

        $response->assertSessionHasNoErrors();

        $step1->refresh();
        $this->assertEquals('rejected', $step1->status);
        $this->assertEquals('Non-standard hardware request denied.', $step1->comments);

        $ticket->refresh();
        $this->assertEquals('rejected', $ticket->status);
        $this->assertTrue($ticket->isRejected());
    }

    public function test_tma_api_returns_approval_steps_and_full_timestamps(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-004',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'medium',
            'status' => 'submitted',
            'subject' => 'Software License for JetBrains',
            'description' => 'IDE license request',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);

        // Add a message
        $ticket->messages()->create([
            'sender_id' => $this->requester->id,
            'visibility' => 'requester_visible',
            'message_type' => 'text',
            'body' => 'I need this by Monday please.',
        ]);

        $this->actingAs($this->requester);
        $response = $this->getJson("/tma/api/tickets/{$ticket->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Check approvals array in JSON
        $this->assertArrayHasKey('approvals', $data['ticket']);
        $this->assertCount(2, $data['ticket']['approvals']);

        // Check full datetime timestamp on messages
        $this->assertNotEmpty($data['messages']);
        $this->assertArrayHasKey('full_timestamp', $data['messages'][0]);
        $this->assertArrayHasKey('created_at_human', $data['messages'][0]);
    }

    public function test_portal_views_render_approval_stepper_and_badges(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-005',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'medium',
            'status' => 'submitted',
            'subject' => 'RAM Upgrade Request',
            'description' => 'Need 32GB RAM upgrade for Docker',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);

        $this->actingAs($this->hodUser);

        // Test dashboard view shows Approvals tab and Pending Approval badge
        $dashboardResponse = $this->get(route('portal.dashboard', ['view' => 'pending_approvals']));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Approvals');
        $dashboardResponse->assertSee('TICK-TEST-005');
        $dashboardResponse->assertSee('Service Request');

        // Test ticket detail view shows multi-step workflow and action buttons
        $detailResponse = $this->get(route('portal.tickets.show', $ticket->id));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Multi-Step Service Approval Workflow');
        $detailResponse->assertSee('Department HOD Approval');
        $detailResponse->assertSee('IT Manager Approval');
        $detailResponse->assertSee('Action Required: Department HOD Approval');
        $detailResponse->assertSee('Approve Step');
        $detailResponse->assertSee('Reject Step');
    }

    public function test_two_step_separation_it_hod_cannot_approve_step_1_for_ops_and_cannot_approve_step_2_early(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-SEP',
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->procurementCat->id,
            'ticket_type' => 'service_request',
            'priority' => 'high',
            'status' => 'submitted',
            'subject' => 'Separation Test',
            'description' => 'Test department HOD vs IT HOD',
        ]);

        $approvalService = app(TicketApprovalService::class);
        $approvalService->createApprovalChain($ticket);
        $ticket->refresh();

        $step1 = $ticket->approvals()->where('step_number', 1)->first();
        $step2 = $ticket->approvals()->where('step_number', 2)->first();

        // 1. IT HOD cannot approve Step 1 for Operations request
        $this->assertFalse($approvalService->canUserApprove($this->itHodUser, $step1));
        $this->assertTrue($approvalService->canUserApprove($this->opsHodUser, $step1));

        // 2. IT HOD cannot approve Step 2 while Step 1 is still pending
        $this->assertFalse($approvalService->canUserApprove($this->itHodUser, $step2));

        // 3. Step 1 approved by Operations HOD
        $this->assertTrue($approvalService->approve($step1, $this->opsHodUser, 'Ops approved'));
        $step1->refresh();
        $this->assertEquals('approved', $step1->status);

        // 4. Now Step 2 is ready: IT HOD can approve Step 2, but Ops HOD cannot
        $this->assertTrue($approvalService->canUserApprove($this->itHodUser, $step2));
        $this->assertFalse($approvalService->canUserApprove($this->opsHodUser, $step2));

        // 5. IT HOD approves Step 2
        $this->assertTrue($approvalService->approve($step2, $this->itHodUser, 'IT approved'));
        $step2->refresh();
        $this->assertEquals('approved', $step2->status);

        $ticket->refresh();
        $this->assertEquals('submitted', $ticket->status);
    }
}
