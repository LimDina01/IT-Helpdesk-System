<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketApprovalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReclassificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $helpdeskUser;
    protected User $techUser;
    protected User $requesterUser;
    protected Branch $branch;
    protected TicketCategory $incidentCat;
    protected TicketCategory $serviceCat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->helpdeskUser = User::where('email', 'helpdesk@cbvh.com.kh')->firstOrFail();
        $this->techUser = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $this->branch = Branch::firstOrFail();

        $this->incidentCat = TicketCategory::where('type', 'incident_issue')->whereNotNull('parent_id')->firstOrFail();
        $this->serviceCat = TicketCategory::where('type', 'service_request')->where('requires_approval', true)->firstOrFail();
    }

    public function test_helpdesk_can_convert_incident_to_service_request_initiating_approvals(): void
    {
        // 1. Create an incident ticket
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-INC-001',
            'ticket_type' => 'incident',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->incidentCat->id,
            'subject' => 'Need additional screen for desk',
            'description' => 'Screen is missing for workstation',
            'status' => 'submitted',
            'priority' => 'medium',
        ]);

        $this->assertEquals('incident', $ticket->ticket_type);
        $this->assertEquals('submitted', $ticket->status);
        $this->assertCount(0, $ticket->approvals);

        // 2. Helpdesk staff converts to Service Request
        $this->actingAs($this->helpdeskUser);

        $response = $this->post(route('portal.tickets.reclassify', $ticket->id), [
            'ticket_type' => 'service_request',
            'category_id' => $this->serviceCat->id,
            'reason' => 'User requested an additional monitor. Converting to Service Request for HOD approval.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticket->refresh();

        // 3. Verify ticket type changed and approvals initiated
        $this->assertEquals('service_request', $ticket->ticket_type);
        $this->assertEquals($this->serviceCat->id, $ticket->category_id);
        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertGreaterThan(0, $ticket->approvals()->count());

        // 4. Verify Audit Log
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'actor_id' => $this->helpdeskUser->id,
            'action' => 'TICKET_RECLASSIFIED',
        ]);

        // 5. Verify system message posted in chat
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'sender_id' => $this->helpdeskUser->id,
            'message_type' => 'status_change',
        ]);
    }

    public function test_helpdesk_can_convert_service_request_to_incident_cancelling_approvals(): void
    {
        // 1. Create a service request ticket with approval chain
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-SR-002',
            'ticket_type' => 'service_request',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->serviceCat->id,
            'subject' => 'PC power cord issue',
            'description' => 'Thought I needed a new cord, but current one is loose',
            'status' => 'submitted',
            'priority' => 'medium',
        ]);

        app(TicketApprovalService::class)->createApprovalChain($ticket);
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertGreaterThan(0, $ticket->approvals()->where('status', 'pending')->count());

        // 2. Helpdesk converts Service Request back to Incident
        $this->actingAs($this->techUser);

        $response = $this->post(route('portal.tickets.reclassify', $ticket->id), [
            'ticket_type' => 'incident',
            'category_id' => $this->incidentCat->id,
            'reason' => 'User just needs hardware inspection, no new purchase required.',
        ]);

        $response->assertRedirect();

        $ticket->refresh();

        // 3. Verify approvals cancelled and status returned to submitted
        $this->assertEquals('incident', $ticket->ticket_type);
        $this->assertEquals($this->incidentCat->id, $ticket->category_id);
        $this->assertEquals('submitted', $ticket->status);
        $this->assertEquals(0, $ticket->approvals()->where('status', 'pending')->count());
        $this->assertGreaterThan(0, $ticket->approvals()->where('status', 'cancelled')->count());
    }

    public function test_helpdesk_can_reassign_category_within_same_type(): void
    {
        $catA = $this->incidentCat;
        $catB = TicketCategory::where('type', 'incident_issue')
            ->where('id', '!=', $catA->id)
            ->whereNotNull('parent_id')
            ->firstOrFail();

        $ticket = Ticket::create([
            'ticket_code' => 'TICK-INC-003',
            'ticket_type' => 'incident',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $catA->id,
            'subject' => 'Printer jammed',
            'description' => 'Paper jammed in tray 2',
            'status' => 'submitted',
            'priority' => 'low',
        ]);

        $this->actingAs($this->helpdeskUser);

        $response = $this->post(route('portal.tickets.reclassify', $ticket->id), [
            'ticket_type' => 'incident',
            'category_id' => $catB->id,
            'reason' => 'Changed to more specific printer subcategory.',
        ]);

        $response->assertRedirect();
        $ticket->refresh();

        $this->assertEquals('incident', $ticket->ticket_type);
        $this->assertEquals($catB->id, $ticket->category_id);
    }

    public function test_non_staff_requester_cannot_reclassify_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-INC-004',
            'ticket_type' => 'incident',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->incidentCat->id,
            'subject' => 'Testing permission',
            'description' => 'Test',
            'status' => 'submitted',
            'priority' => 'low',
        ]);

        // Acting as regular requester without staff role
        $this->actingAs($this->requesterUser);

        $response = $this->post(route('portal.tickets.reclassify', $ticket->id), [
            'ticket_type' => 'service_request',
            'category_id' => $this->serviceCat->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_can_update_priority_via_quick_switcher(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-INC-005',
            'ticket_type' => 'incident',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->incidentCat->id,
            'subject' => 'Priority quick switch test',
            'description' => 'Test',
            'status' => 'submitted',
            'priority' => 'low',
        ]);

        $this->actingAs($this->helpdeskUser);

        $response = $this->post(route('portal.tickets.priority', $ticket->id), [
            'priority' => 'critical',
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertEquals('critical', $ticket->priority);

        // Check system message was created without DB column truncation error
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message_type' => 'status_change',
            'visibility' => 'system_event',
        ]);
    }

    public function test_staff_can_reclassify_and_change_priority(): void
    {
        $ticket = Ticket::create([
            'ticket_code' => 'TICK-INC-006',
            'ticket_type' => 'incident',
            'requester_id' => $this->requesterUser->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->incidentCat->id,
            'subject' => 'Reclassify with priority',
            'description' => 'Test',
            'status' => 'submitted',
            'priority' => 'low',
        ]);

        $this->actingAs($this->helpdeskUser);

        $response = $this->post(route('portal.tickets.reclassify', $ticket->id), [
            'ticket_type' => 'service_request',
            'category_id' => $this->serviceCat->id,
            'priority' => 'high',
            'reason' => 'Need higher priority signoff',
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertEquals('service_request', $ticket->ticket_type);
        $this->assertEquals($this->serviceCat->id, $ticket->category_id);
        $this->assertEquals('high', $ticket->priority);
        $this->assertEquals('pending_approval', $ticket->status);
    }
}

