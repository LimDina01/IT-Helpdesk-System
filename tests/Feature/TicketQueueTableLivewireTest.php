<?php

namespace Tests\Feature;

use App\Livewire\Portal\TicketQueueTable;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketQueueTableLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;

    protected User $requesterUser;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->staffUser = User::where('email', 'tech1@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $branch = Branch::firstOrFail();
        $category = TicketCategory::firstOrFail();

        $this->ticket = Ticket::create([
            'ticket_code' => 'TICK-DASH-101',
            'ticket_type' => 'incident',
            'subject' => 'Main Router DHCP Failure',
            'description' => 'Clients cannot receive IP addresses.',
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'requester_id' => $this->requesterUser->id,
            'status' => 'submitted',
            'priority' => 'high',
        ]);
    }

    public function test_dashboard_renders_successfully(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketQueueTable::class)
            ->assertStatus(200)
            ->assertSee('Triage Queue')
            ->assertSee('All Active')
            ->assertSee('My Tasks')
            ->assertSee('TICK-DASH-101')
            ->assertSee('Main Router DHCP Failure');
    }

    public function test_can_switch_queue_tabs(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketQueueTable::class)
            ->call('setViewTab', 'all_active')
            ->assertSet('viewTab', 'all_active')
            ->assertSee('TICK-DASH-101')
            ->call('setViewTab', 'my_tasks')
            ->assertSet('viewTab', 'my_tasks')
            ->assertDontSee('TICK-DASH-101');
    }

    public function test_search_filters_tickets(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketQueueTable::class)
            ->set('search', 'DHCP')
            ->assertSee('TICK-DASH-101')
            ->set('search', 'NONEXISTENT_KEYWORD_XYZ')
            ->assertDontSee('TICK-DASH-101')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSee('TICK-DASH-101');
    }

    public function test_staff_can_claim_ticket(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketQueueTable::class)
            ->call('assignToMe', $this->ticket->id)
            ->assertSet('toastMsg', "Ticket {$this->ticket->ticket_code} assigned to you.");

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'current_technician_id' => $this->staffUser->id,
            'status' => 'assigned',
        ]);
    }

    public function test_assigned_technician_can_accept_ticket(): void
    {
        $this->ticket->update([
            'current_technician_id' => $this->staffUser->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($this->staffUser)
            ->test(TicketQueueTable::class)
            ->call('acceptTicket', $this->ticket->id)
            ->assertSet('toastMsg', "Ticket {$this->ticket->ticket_code} accepted and in progress.");

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'status' => 'in_progress',
        ]);
    }
}
