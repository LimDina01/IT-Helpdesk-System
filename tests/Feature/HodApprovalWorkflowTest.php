<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HodApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;     // EMP-1001: Sokha Chan (Operations Dept)
    protected User $opsHod;        // EMP-1008: Chantha Som (Operations HOD - Process 1 Approver)
    protected User $itHod;         // EMP-1007: Vannak Ouk (IT HOD / Admin - Process 2 Approver)
    protected User $technician;    // EMP-1005: Dara Sam (IT Technician)
    protected Branch $branch;
    protected TicketCategory $serviceCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->requester = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $this->opsHod = User::where('email', 'hod.ops@cbvh.com.kh')->firstOrFail();
        $this->itHod = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->technician = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
        $this->branch = Branch::firstOrFail();
        $this->serviceCategory = TicketCategory::where('name', 'Laptop / Desktop Procurement Request')->firstOrFail();
    }

    public function test_complete_two_stage_approval_separation_and_hod_tracking_visibility(): void
    {
        // -------------------------------------------------------------
        // STAGE 1: IT HOD (EMP-1007) initially has 0 pending approvals
        // -------------------------------------------------------------
        $this->actingAs($this->itHod, 'tma');

        $authRes = $this->postJson('/tma/api/auth');
        $authRes->assertStatus(200);
        $this->assertTrue($authRes->json('user.is_hod'));
        $this->assertTrue($authRes->json('user.is_it_hod'));

        $formDataRes = $this->getJson('/tma/api/form-data');
        $formDataRes->assertStatus(200);
        $this->assertTrue($formDataRes->json('user.is_hod'));
        $this->assertEquals(0, $formDataRes->json('user.pending_approvals_count'));

        $ticketsRes = $this->getJson('/tma/api/tickets');
        $ticketsRes->assertStatus(200);
        $this->assertTrue($ticketsRes->json('is_hod'));
        $this->assertEquals(0, $ticketsRes->json('pending_approvals_count'));
        $this->assertCount(0, $ticketsRes->json('approvals'));

        // -------------------------------------------------------------
        // STAGE 2: Requester in Operations submits a service request
        // -------------------------------------------------------------
        $this->actingAs($this->requester, 'tma');

        $createRes = $this->postJson('/tma/api/tickets', [
            'ticket_type' => 'service_request',
            'category_id' => $this->serviceCategory->id,
            'branch_id' => $this->branch->id,
            'subject' => 'New Laptop for Operations Team',
            'description' => 'Dell Latitude laptop required for logistics monitoring.',
            'priority' => 'high',
        ]);
        $createRes->assertStatus(201);
        $ticketId = $createRes->json('ticket_id');

        $ticket = Ticket::with('approvals')->findOrFail($ticketId);
        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertCount(2, $ticket->approvals);

        $step1 = $ticket->approvals->where('step_number', 1)->first();
        $step2 = $ticket->approvals->where('step_number', 2)->first();

        // -------------------------------------------------------------
        // STAGE 3: IT HOD (EMP-1007) CANNOT approve Step 1 (Operations HOD must)
        // IT HOD still sees 0 pending approvals
        // -------------------------------------------------------------
        $this->actingAs($this->itHod, 'tma');

        $itHodTicketsRes = $this->getJson('/tma/api/tickets');
        $this->assertEquals(0, $itHodTicketsRes->json('pending_approvals_count'), 'IT HOD should have 0 pending approvals while Step 1 is awaiting Operations HOD');
        $this->assertCount(0, $itHodTicketsRes->json('approvals'));

        // Attempting to approve Step 1 as IT HOD fails with 403
        $unauthorizedApproveRes = $this->postJson("/tma/api/approvals/{$step1->id}/approve", [
            'comments' => 'IT HOD trying to approve Dept HOD step',
        ]);
        $unauthorizedApproveRes->assertStatus(403);

        // Attempting to approve Step 2 as IT HOD before Step 1 fails with 403
        $earlyStep2Res = $this->postJson("/tma/api/approvals/{$step2->id}/approve", [
            'comments' => 'IT HOD trying to approve Step 2 early',
        ]);
        $earlyStep2Res->assertStatus(403);

        // -------------------------------------------------------------
        // STAGE 4: Operations HOD (EMP-1008) sees 1 pending approval and approves Step 1
        // -------------------------------------------------------------
        $this->actingAs($this->opsHod, 'tma');

        $opsTicketsRes = $this->getJson('/tma/api/tickets');
        $this->assertEquals(1, $opsTicketsRes->json('pending_approvals_count'));
        $this->assertCount(1, $opsTicketsRes->json('approvals'));
        $this->assertEquals('Department HOD Approval', $opsTicketsRes->json('approvals.0.current_approval_step.step_name'));
        $this->assertTrue($opsTicketsRes->json('approvals.0.can_approve'));

        $opsApproveRes = $this->postJson("/tma/api/approvals/{$step1->id}/approve", [
            'comments' => 'Budget approved by Operations HOD Chantha Som.',
        ]);
        $opsApproveRes->assertStatus(200);

        $step1->refresh();
        $this->assertEquals('approved', $step1->status);
        $this->assertEquals($this->opsHod->id, $step1->approver_id);

        // -------------------------------------------------------------
        // STAGE 5: Operations HOD can now track this approved request in flight!
        // -------------------------------------------------------------
        $opsAfterApprovalRes = $this->getJson('/tma/api/tickets');
        $this->assertEquals(0, $opsAfterApprovalRes->json('pending_approvals_count'), 'Operations HOD now has 0 pending');
        $this->assertCount(1, $opsAfterApprovalRes->json('approved_by_me'), 'Operations HOD sees ticket in approved_by_me');
        $this->assertTrue($opsAfterApprovalRes->json('approved_by_me.0.approved_by_me'));
        $this->assertEquals('pending_approval', $opsAfterApprovalRes->json('approved_by_me.0.status'));

        // -------------------------------------------------------------
        // STAGE 6: Now IT HOD (EMP-1007) sees 1 pending approval (Step 2 is active)
        // -------------------------------------------------------------
        $this->actingAs($this->itHod, 'tma');

        $itHodReadyRes = $this->getJson('/tma/api/tickets');
        $this->assertEquals(1, $itHodReadyRes->json('pending_approvals_count'), 'IT HOD now has 1 pending approval for Step 2');
        $this->assertCount(1, $itHodReadyRes->json('approvals'));
        $this->assertEquals('IT Manager Approval', $itHodReadyRes->json('approvals.0.current_approval_step.step_name'));
        $this->assertTrue($itHodReadyRes->json('approvals.0.can_approve'));

        // IT HOD approves Step 2
        $itApproveRes = $this->postJson("/tma/api/approvals/{$step2->id}/approve", [
            'comments' => 'IT hardware in stock, approved by Vannak Ouk.',
        ]);
        $itApproveRes->assertStatus(200);

        $step2->refresh();
        $this->assertEquals('approved', $step2->status);

        // All steps approved -> Ticket moves to 'submitted' for IT fulfillment
        $ticket->refresh();
        $this->assertEquals('submitted', $ticket->status);

        // -------------------------------------------------------------
        // STAGE 7: Both HODs can track live progress (In Progress & Resolved)
        // -------------------------------------------------------------
        // 7a. Technician assigns and works on ticket -> in_progress
        $ticket->update([
            'status' => 'in_progress',
            'current_technician_id' => $this->technician->id,
        ]);

        // Operations HOD checks TMA phone
        $this->actingAs($this->opsHod, 'tma');
        $opsTrackingRes = $this->getJson('/tma/api/tickets');
        $this->assertCount(1, $opsTrackingRes->json('approved_by_me'));
        $this->assertEquals('in_progress', $opsTrackingRes->json('approved_by_me.0.status'));
        $this->assertEquals($this->technician->name, $opsTrackingRes->json('approved_by_me.0.technician_name'));
        // HOD did not personally request this ticket, so personal active_count is 0
        $this->assertEquals(0, $opsTrackingRes->json('active_count'));

        // IT HOD checks TMA phone
        $this->actingAs($this->itHod, 'tma');
        $itTrackingRes = $this->getJson('/tma/api/tickets');
        $this->assertCount(1, $itTrackingRes->json('approved_by_me'));
        $this->assertEquals('in_progress', $itTrackingRes->json('approved_by_me.0.status'));
        $this->assertEquals($this->technician->name, $itTrackingRes->json('approved_by_me.0.technician_name'));
        $this->assertEquals(0, $itTrackingRes->json('active_count'));

        // 7b. Technician completes resolution -> resolved_confirmed
        $ticket->update([
            'status' => 'resolved_confirmed',
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        // Both HODs see ticket in approved_by_me and history_count reflects resolved actioned ticket
        $this->actingAs($this->opsHod, 'tma');
        $opsResolvedRes = $this->getJson('/tma/api/tickets');
        $this->assertCount(1, $opsResolvedRes->json('approved_by_me'));
        $this->assertEquals('resolved_confirmed', $opsResolvedRes->json('approved_by_me.0.status'));
        $this->assertEquals(1, $opsResolvedRes->json('history_count'));
    }

    public function test_hold_requests_are_visible_in_cockpit_urgent_queue_and_dashboard_approvals_view(): void
    {
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();
        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $this->requester->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->serviceCategory->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'subject' => 'Need spare fan for server',
            'description' => 'Waiting for vendor replacement fan',
            'hold_requested_category' => 'hardware_procurement',
            'hold_requested_reason' => 'Vendor out of stock until next Tuesday',
            'hold_requested_at' => now(),
            'hold_requested_by_id' => $admin->id,
        ]);

        $this->assertTrue($ticket->isHoldPending());

        // 1. Cockpit view contains ticket in urgentTickets and has pendingHoldCount
        $cockpitRes = $this->actingAs($admin)->get('/portal/cockpit');
        $cockpitRes->assertStatus(200);
        $cockpitRes->assertViewHas('pendingHoldCount', 1);
        $urgentTickets = $cockpitRes->viewData('urgentTickets');
        $this->assertTrue($urgentTickets->contains('id', $ticket->id));

        // 2. Dashboard Approvals tab lists the hold request
        $dashboardRes = $this->actingAs($admin)->get('/portal?view=pending_approvals');
        $dashboardRes->assertStatus(200);
        $dashboardRes->assertViewHas('pendingHoldCount', 1);
        $dashboardTickets = $dashboardRes->viewData('tickets');
        $this->assertTrue($dashboardTickets->contains('id', $ticket->id));
    }
}
