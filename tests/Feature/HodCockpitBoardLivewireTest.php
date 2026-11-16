<?php

namespace Tests\Feature;

use App\Livewire\Portal\HodCockpitBoard;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HodCockpitBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $technicianUser;

    protected User $requesterUser;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();
        $this->technicianUser = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();

        $branch = Branch::firstOrFail();
        $category = TicketCategory::firstOrFail();

        $this->ticket = Ticket::create([
            'ticket_code' => 'TICK-COCKPIT-001',
            'ticket_type' => 'incident',
            'subject' => 'Core Switch Port Flapping',
            'description' => 'Switch in server room dropping packets intermittently.',
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'requester_id' => $this->requesterUser->id,
            'current_technician_id' => $this->technicianUser->id,
            'status' => 'assigned',
            'priority' => 'low',
        ]);
    }

    public function test_cockpit_board_renders_technicians_and_bottlenecks(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->assertStatus(200)
            ->assertSee('Live Technician Workload')
            ->assertSee('High-Attention Incident')
            ->assertSee('System Audit Trail')
            ->assertSee($this->technicianUser->name);
    }

    public function test_cockpit_page_mounts_livewire_component(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('portal.cockpit'))
            ->assertStatus(200)
            ->assertSeeLivewire(HodCockpitBoard::class);
    }

    public function test_non_hod_cannot_access_cockpit_board(): void
    {
        Livewire::actingAs($this->requesterUser)
            ->test(HodCockpitBoard::class)
            ->assertForbidden();
    }

    public function test_hod_can_override_priority_reactively(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->call('openPriorityModal', $this->ticket->id)
            ->assertSet('priorityModal', true)
            ->assertSet('targetTicketId', $this->ticket->id)
            ->assertSet('targetTicketCode', 'TICK-COCKPIT-001')
            ->set('newPriority', 'critical')
            ->set('overrideReason', 'Executive escalation due to branch impact')
            ->call('overridePriority')
            ->assertSet('priorityModal', false)
            ->assertSet('feedbackType', 'success')
            ->assertSee('Priority for TICK-COCKPIT-001 updated to CRITICAL');

        $this->ticket->refresh();
        $this->assertEquals('critical', $this->ticket->priority);

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $this->ticket->id,
            'action' => 'PRIORITY_OVERRIDDEN',
            'old_value' => 'low',
            'new_value' => 'critical',
        ]);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $this->ticket->id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
        ]);
    }

    public function test_priority_override_validates_inputs(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->call('openPriorityModal', $this->ticket->id)
            ->set('overrideReason', '') // Missing reason
            ->call('overridePriority')
            ->assertHasErrors(['overrideReason' => 'required']);
    }

    public function test_hod_can_filter_urgent_queue(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->assertSet('urgentFilter', 'all')
            ->call('setUrgentFilter', 'hold')
            ->assertSet('urgentFilter', 'hold')
            ->call('setUrgentFilter', 'critical')
            ->assertSet('urgentFilter', 'critical');
    }

    public function test_hod_can_review_and_approve_hold_request(): void
    {
        // Put ticket in hold pending state
        $this->ticket->update([
            'status' => 'in_progress',
            'hold_requested_at' => now(),
            'hold_requested_category' => 'Waiting for Vendor / Hardware Delivery',
            'hold_requested_reason' => 'Awaiting motherboard replacement from Dell supplier.',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->call('openHoldModal', $this->ticket->id)
            ->assertSet('holdModal', true)
            ->assertSet('holdTicketId', $this->ticket->id)
            ->assertSet('holdTicketCode', 'TICK-COCKPIT-001')
            ->call('approveHold')
            ->assertSet('holdModal', false)
            ->assertSet('feedbackType', 'success');

        $this->ticket->refresh();
        $this->assertEquals('blocked', $this->ticket->status);
        $this->assertNull($this->ticket->hold_requested_at);

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $this->ticket->id,
            'action' => 'HOLD_APPROVED',
            'old_value' => 'in_progress',
            'new_value' => 'blocked',
        ]);
    }

    public function test_hod_can_review_and_reject_hold_request(): void
    {
        // Put ticket in hold pending state
        $this->ticket->update([
            'status' => 'in_progress',
            'hold_requested_at' => now(),
            'hold_requested_category' => 'Waiting for User Feedback',
            'hold_requested_reason' => 'User did not reply within 10 minutes.',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(HodCockpitBoard::class)
            ->call('openHoldModal', $this->ticket->id)
            ->set('holdRejectionReason', '10 minutes is insufficient; follow standard 4-hour grace period.')
            ->call('rejectHold')
            ->assertSet('holdModal', false)
            ->assertSet('feedbackType', 'warning');

        $this->ticket->refresh();
        $this->assertEquals('in_progress', $this->ticket->status);
        $this->assertNull($this->ticket->hold_requested_at);

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $this->ticket->id,
            'action' => 'HOLD_REJECTED',
            'old_value' => 'in_progress',
            'new_value' => 'in_progress',
        ]);
    }
}
