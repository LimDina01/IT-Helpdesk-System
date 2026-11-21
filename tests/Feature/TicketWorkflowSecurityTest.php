<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createSampleTicket(array $attributes = []): Ticket
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        return Ticket::create(array_merge([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'subject' => 'Test Ticket Security',
            'description' => 'Security test ticket description',
        ], $attributes));
    }

    public function test_cannot_assign_ticket_awaiting_approval(): void
    {
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'pending_approval',
            'current_technician_id' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('portal.tickets.assign', $ticket->id), [
            'technician_id' => $tech->id,
            'priority' => 'medium',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals('pending_approval', $ticket->fresh()->status);
    }

    public function test_cannot_self_assign_ticket_awaiting_approval(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'pending_approval',
            'current_technician_id' => null,
        ]);

        $response = $this->actingAs($tech)->post(route('portal.tickets.assign-me', $ticket->id));

        $response->assertSessionHasErrors('error');
        $this->assertEquals('pending_approval', $ticket->fresh()->status);
    }

    public function test_cannot_accept_ticket_awaiting_approval(): void
    {
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'pending_approval',
            'current_technician_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('portal.tickets.accept', $ticket->id));

        $response->assertSessionHasErrors('error');
        $this->assertEquals('pending_approval', $ticket->fresh()->status);
    }

    public function test_cannot_mark_done_ticket_awaiting_approval(): void
    {
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'pending_approval',
            'current_technician_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('portal.tickets.done', $ticket->id));

        $response->assertSessionHasErrors('error');
        $this->assertEquals('pending_approval', $ticket->fresh()->status);
    }

    public function test_non_assigned_technician_cannot_mark_done_someone_elses_ticket(): void
    {
        $tech1 = User::where('email', 'tech1@cbvh.com.kh')->first();
        $tech2 = User::where('email', 'tech2@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'in_progress',
            'current_technician_id' => $tech1->id,
        ]);

        $response = $this->actingAs($tech2)->post(route('portal.tickets.done', $ticket->id));

        $response->assertSessionHasErrors('error');
        $this->assertEquals('in_progress', $ticket->fresh()->status);
    }

    public function test_assigned_technician_can_mark_done_in_progress_ticket(): void
    {
        $tech1 = User::where('email', 'tech1@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'in_progress',
            'current_technician_id' => $tech1->id,
        ]);

        $response = $this->actingAs($tech1)->post(route('portal.tickets.done', $ticket->id));

        $response->assertSessionHas('status');
        $this->assertEquals('pending_confirmation', $ticket->fresh()->status);
    }

    public function test_admin_can_mark_done_in_progress_ticket(): void
    {
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();
        $tech1 = User::where('email', 'tech1@cbvh.com.kh')->first();

        $ticket = $this->createSampleTicket([
            'status' => 'in_progress',
            'current_technician_id' => $tech1->id,
        ]);

        $response = $this->actingAs($admin)->post(route('portal.tickets.done', $ticket->id));

        $response->assertSessionHas('status');
        $this->assertEquals('pending_confirmation', $ticket->fresh()->status);
    }

    public function test_demo_password_bypass_disabled_in_production(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $admin = User::where('email', 'admin@cbvh.com.kh')->first();
        $admin->update(['password' => Hash::make('ActualSecret123!')]);

        // Mock production environment
        $this->app['env'] = 'production';

        $response = $this->from(route('portal.login'))->post(route('portal.login.submit'), [
            'email' => 'admin@cbvh.com.kh',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Valid password works in production
        $response = $this->from(route('portal.login'))->post(route('portal.login.submit'), [
            'email' => 'admin@cbvh.com.kh',
            'password' => 'ActualSecret123!',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }
}
