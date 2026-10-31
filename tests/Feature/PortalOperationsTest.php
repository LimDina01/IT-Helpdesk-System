<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\TelegramLinkRequest;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_redirected_to_portal_login(): void
    {
        $response = $this->get('/portal');
        $response->assertRedirect('/login');
    }

    public function test_non_staff_user_cannot_access_portal(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $this->assertFalse($requester->isStaff());

        $response = $this->actingAs($requester)->get('/portal');
        $response->assertStatus(403);
    }

    public function test_staff_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/portal/login', [
            'email' => 'helpdesk@cbvh.com.kh',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticated();
    }

    public function test_staff_can_login_with_username_prefix(): void
    {
        $response = $this->post('/portal/login', [
            'email' => 'tech1',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticated();
        $this->assertEquals('tech1@cbvh.com.kh', auth()->user()->email);
    }

    public function test_staff_can_login_with_employee_code(): void
    {
        $employee = \App\Models\Employee::create([
            'employee_code' => 'EMP-9001',
            'full_name' => 'Kosal Meng',
            'email' => 'helpdesk@cbvh.com.kh',
            'branch_id' => \App\Models\Branch::first()->id,
            'department_id' => \App\Models\Department::first()->id,
            'is_active' => true,
        ]);
        $staff = User::where('email', 'helpdesk@cbvh.com.kh')->first();
        $staff->update(['employee_id' => $employee->id]);

        $response = $this->post('/portal/login', [
            'email' => 'EMP-9001',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticated();
        $this->assertEquals('helpdesk@cbvh.com.kh', auth()->user()->email);
    }

    public function test_ad_user_without_portal_access_is_denied_login(): void
    {
        $adUser = User::create([
            'name' => 'New AD Employee',
            'email' => 'new.ad.emp@cbvh.com.kh',
            'password' => \Illuminate\Support\Facades\Hash::make('Temporary123!'),
            'can_access_portal' => false,
            'is_active' => true,
        ]);

        $mockLdap = $this->createMock(\App\Services\LdapAuthService::class);
        $mockLdap->method('isEnabled')->willReturn(true);
        $mockLdap->expects($this->once())
            ->method('authenticate')
            ->willReturn($adUser);

        $this->app->instance(\App\Services\LdapAuthService::class, $mockLdap);

        $response = $this->post('/portal/login', [
            'email' => 'new.ad.emp@cbvh.com.kh',
            'password' => 'DomainPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse($adUser->refresh()->can_access_portal);
        $this->assertFalse($adUser->hasRole('technician'));
    }

    public function test_ad_user_with_portal_access_logs_in_without_altering_roles(): void
    {
        $adUser = User::create([
            'name' => 'Permitted AD Helpdesk',
            'email' => 'permitted.ad@cbvh.com.kh',
            'password' => \Illuminate\Support\Facades\Hash::make('Temporary123!'),
            'can_access_portal' => true,
            'is_active' => true,
        ]);
        $helpdeskRole = \App\Models\Role::where('name', 'helpdesk')->first();
        $adUser->roles()->attach($helpdeskRole->id);

        $mockLdap = $this->createMock(\App\Services\LdapAuthService::class);
        $mockLdap->method('isEnabled')->willReturn(true);
        $mockLdap->expects($this->once())
            ->method('authenticate')
            ->willReturn($adUser);

        $this->app->instance(\App\Services\LdapAuthService::class, $mockLdap);

        $response = $this->post('/portal/login', [
            'email' => 'permitted.ad@cbvh.com.kh',
            'password' => 'DomainPassword123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticatedAs($adUser);
        $this->assertTrue($adUser->refresh()->hasRole('helpdesk'));
        $this->assertFalse($adUser->refresh()->hasRole('technician'));
    }

    public function test_ad_ldap_login_assigns_technician_role_and_portal_access_without_requester(): void
    {
        $techRole = \App\Models\Role::firstOrCreate(['name' => 'technician'], ['label' => 'IT Technician']);
        $requesterRole = \App\Models\Role::firstOrCreate(['name' => 'requester'], ['label' => 'Requester']);

        $adUser = User::create([
            'name' => 'New AD Tech',
            'email' => 'new.tech@cbvh66.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Pass123!'),
            'can_access_portal' => true,
            'is_active' => true,
        ]);
        $adUser->roles()->sync([$techRole->id]);

        $this->assertTrue($adUser->hasRole('technician'));
        $this->assertFalse($adUser->hasRole('requester'));
        $this->assertTrue($adUser->can_access_portal);
    }

    public function test_tma_requester_is_strictly_denied_login_to_web_portal(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $this->assertFalse($requester->isStaff());

        $response = $this->post('/portal/login', [
            'email' => 'sokha.chan@cbvh.com.kh',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Even if can_access_portal is true, lack of staff role strictly blocks portal access
        $requester->update(['can_access_portal' => true]);
        $response2 = $this->post('/portal/login', [
            'email' => 'sokha.chan@cbvh.com.kh',
            'password' => 'Password123!',
        ]);
        $response2->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_helpdesk_can_assign_technician_and_record_assignment_history(): void
    {
        $helpdesk = User::where('email', 'helpdesk@cbvh.com.kh')->first();
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'submitted',
            'priority' => 'medium',
            'subject' => 'Printer jammed in accounting',
            'description' => 'Roll is stuck.',
        ]);

        $response = $this->actingAs($helpdesk)->post("/portal/tickets/{$ticket->id}/assign", [
            'technician_id' => $tech->id,
            'priority' => 'high',
            'assignment_note' => 'Please bring replacement roller.',
        ]);

        $response->assertSessionHas('status');
        $ticket->refresh();

        $this->assertEquals('assigned', $ticket->status);
        $this->assertEquals($tech->id, $ticket->current_technician_id);
        $this->assertEquals($helpdesk->id, $ticket->current_helpdesk_id);
        $this->assertEquals('high', $ticket->priority);
        $this->assertNotNull($ticket->assigned_at);

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_by_id' => $helpdesk->id,
            'technician_id' => $tech->id,
            'reassignment_reason' => 'Please bring replacement roller.',
        ]);

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'actor_id' => $helpdesk->id,
            'action' => 'ASSIGNED',
            'new_value' => (string) $tech->id,
        ]);
    }

    public function test_assigned_technician_can_accept_and_start_work(): void
    {
        $helpdesk = User::where('email', 'helpdesk@cbvh.com.kh')->first();
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_helpdesk_id' => $helpdesk->id,
            'current_technician_id' => $tech->id,
            'status' => 'assigned',
            'priority' => 'high',
            'subject' => 'Core switch port inactive',
            'description' => 'Port 14 is dead.',
            'assigned_at' => now(),
        ]);

        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'assigned_by_id' => $helpdesk->id,
            'technician_id' => $tech->id,
            'assigned_at' => now(),
        ]);

        // Technician clicks Accept & Start
        $response = $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/accept");
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNotNull($ticket->acknowledged_at);

        $assignment = TicketAssignment::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($assignment->acknowledged_at);
    }

    public function test_staff_can_post_confidential_internal_note(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'subject' => 'Laptop won\'t turn on',
            'description' => 'No power light.',
        ]);

        $response = $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/messages", [
            'body' => 'CONFIDENTIAL: User liquid damaged the motherboard with coffee.',
            'visibility' => 'internal_staff_only',
        ]);

        $response->assertSessionHas('status');

        $message = TicketMessage::where('ticket_id', $ticket->id)->where('visibility', 'internal_staff_only')->first();
        $this->assertNotNull($message);
        $this->assertTrue($message->isInternalNote());
        $this->assertFalse($message->isRequesterVisible());

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'actor_id' => $tech->id,
            'action' => 'INTERNAL_NOTE_ADDED',
        ]);
    }

    public function test_helpdesk_can_approve_telegram_link_request_option_b(): void
    {
        $helpdesk = User::where('email', 'helpdesk@cbvh.com.kh')->first();
        $employee = Employee::where('employee_code', 'EMP-1002')->first(); // Bopha Keo

        // Create pending request
        $linkRequest = TelegramLinkRequest::create([
            'telegram_id' => 888777666,
            'telegram_username' => 'bophakeo_demo',
            'first_name' => 'Bopha',
            'last_name' => 'Keo',
            'employee_code' => $employee->employee_code,
            'status' => 'pending',
        ]);

        // Help Desk approves
        $response = $this->actingAs($helpdesk)->post("/portal/link-requests/{$linkRequest->id}/approve");
        $response->assertSessionHas('status');

        $linkRequest->refresh();
        $this->assertEquals('approved', $linkRequest->status);
        $this->assertEquals($helpdesk->id, $linkRequest->reviewed_by_id);
        $this->assertNotNull($linkRequest->reviewed_at);

        // Verify user account created and linked
        $user = User::where('telegram_id', 888777666)->first();
        $this->assertNotNull($user);
        $this->assertEquals($employee->id, $user->employee_id);
        $this->assertTrue($user->hasRole('requester'));
    }

    public function test_technician_can_mark_ticket_as_done(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_technician_id' => $tech->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'subject' => 'Printer jammed',
            'description' => 'Fixing roller.',
        ]);

        $response = $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/done");
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertEquals('pending_confirmation', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->auto_close_deadline);
    }

    private function createSampleTicket(User $requester, User $tech): Ticket
    {
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        return Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_technician_id' => $tech->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'subject' => 'Printer jammed',
            'description' => 'Fixing roller.',
        ]);
    }

    public function test_staff_reply_suppresses_telegram_alert_when_requester_is_active_in_chat(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $ticket = $this->createSampleTicket($requester, $tech);

        // Simulate requester is actively looking at the ticket chat (heartbeat in cache)
        \Illuminate\Support\Facades\Cache::put("ticket_presence_{$ticket->id}_{$requester->id}", now()->timestamp, 45);

        $initialLogsCount = \App\Models\NotificationLog::where('ticket_id', $ticket->id)->count();

        // Staff posts a public message
        $response = $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/messages", [
            'body' => 'I can see you in chat right now!',
            'visibility' => 'requester_visible',
        ]);
        $response->assertSessionHas('status', 'Reply sent to requester.');

        // Verify Telegram notification was suppressed (no new notification logged)
        $newLogsCount = \App\Models\NotificationLog::where('ticket_id', $ticket->id)->count();
        $this->assertEquals($initialLogsCount, $newLogsCount);
    }

    public function test_staff_reply_sends_telegram_alert_when_requester_is_away(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $ticket = $this->createSampleTicket($requester, $tech);

        // Ensure requester is NOT active in chat (no cache presence)
        \Illuminate\Support\Facades\Cache::forget("ticket_presence_{$ticket->id}_{$requester->id}");

        $initialLogsCount = \App\Models\NotificationLog::where('ticket_id', $ticket->id)->count();

        // Staff posts a public message
        $response = $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/messages", [
            'body' => 'You are away so you will get a Telegram push notification.',
            'visibility' => 'requester_visible',
        ]);
        $response->assertSessionHas('status', 'Reply sent to requester.');

        // Verify Telegram notification was dispatched
        $newLogsCount = \App\Models\NotificationLog::where('ticket_id', $ticket->id)->count();
        $this->assertEquals($initialLogsCount + 1, $newLogsCount);
    }

    public function test_ticket_live_poll_endpoint_returns_presence_and_messages(): void
    {
        $staff = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $ticket = $this->createSampleTicket($requester, $staff);

        // Set presence in cache
        \Illuminate\Support\Facades\Cache::put("ticket_presence_{$ticket->id}_{$requester->id}", now()->timestamp, 45);

        $response = $this->actingAs($staff)->getJson("/portal/tickets/{$ticket->id}/poll");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => $ticket->status,
            'is_requester_active' => true,
        ]);
        $response->assertJsonStructure(['messages_count', 'messages']);
    }

    public function test_staff_can_send_reply_via_ajax_and_receive_json_response(): void
    {
        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $ticket = $this->createSampleTicket($requester, $tech);

        $response = $this->actingAs($tech)->postJson("/portal/tickets/{$ticket->id}/messages", [
            'body' => 'Hello via AJAX',
            'visibility' => 'requester_visible',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Reply sent to requester.',
        ]);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Hello via AJAX',
            'visibility' => 'requester_visible',
        ]);
    }

    public function test_sla_pause_tracking_pauses_clock_and_accumulates_duration_on_resume(): void
    {
        // Fix test time to Monday 08:00 AM so 4 hours falls squarely in the 08:00 - 12:00 morning shift
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-26 08:00:00'));

        $tech = User::where('email', 'tech1@cbvh.com.kh')->first();
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $ticket = $this->createSampleTicket($requester, $tech);

        // 1. Tech requests hold, and IT HOD approves hold
        $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/block", [
            'blocked_reason' => 'Waiting for replacement power supply from vendor',
        ]);
        $this->actingAs($admin)->post("/portal/tickets/{$ticket->id}/block-approve")
            ->assertSessionHas('status', 'Hold request approved. Ticket is now on hold and SLA clock is paused.');

        $ticket->refresh();
        $this->assertEquals('blocked', $ticket->status);
        $this->assertNotNull($ticket->blocked_at);
        $this->assertTrue($ticket->isSlaPaused());
        $this->assertFalse($ticket->isSlaOverdue());

        // Verify dashboard displays SLA Paused
        $dashboardResponse = $this->actingAs($tech)->get(route('portal.dashboard', ['view' => 'blocked']));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('SLA Paused');
        $dashboardResponse->assertDontSee('Overdue');

        // 2. Simulate 4 hours passing while on hold
        \Carbon\Carbon::setTestNow(now()->addHours(4));

        // 3. Resume work
        $this->actingAs($tech)->post("/portal/tickets/{$ticket->id}/accept")
            ->assertSessionHas('status', 'Ticket resumed from hold. SLA clock unpaused and active.');

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->blocked_at);
        $this->assertEquals(240, $ticket->paused_minutes);
        $this->assertEquals(4, $ticket->getPausedHours());
        $this->assertFalse($ticket->isSlaPaused());

        // Restore real time
        \Carbon\Carbon::setTestNow();
    }
}

