<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\TelegramLinkRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TelegramAuthService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TmaRequesterPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('attachments');
    }

    public function test_tma_page_loads_successfully(): void
    {
        $response = $this->get('/tma');
        $response->assertStatus(200);
        $response->assertSee('CBVH Help Desk');
    }

    public function test_telegram_auth_service_validates_authentic_init_data(): void
    {
        $service = new TelegramAuthService();
        $initData = $service->generateTestInitData([
            'id' => 123456789,
            'first_name' => 'Sokha',
            'last_name' => 'Chan',
            'username' => 'sokhachan_demo',
        ]);

        $response = $this->postJson('/tma/api/auth', [
            'init_data' => $initData,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'authenticated',
            'user' => [
                'name' => 'Sokha Chan',
                'telegram_id' => 123456789,
                'employee_code' => 'EMP-1001',
            ],
        ]);
        $this->assertAuthenticated();
    }

    public function test_telegram_auth_rejects_tampered_signature(): void
    {
        $service = new TelegramAuthService();
        $initData = $service->generateTestInitData(['id' => 123456789]);
        $tamperedData = $initData . '&extra_param=hacked';

        $response = $this->postJson('/tma/api/auth', [
            'init_data' => $tamperedData,
        ]);

        $response->assertStatus(401);
    }

    public function test_unlinked_telegram_user_can_submit_link_request_option_b(): void
    {
        $response = $this->postJson('/tma/api/link-request', [
            'telegram_id' => 999888777,
            'telegram_username' => 'new_staff_user',
            'first_name' => 'Bopha',
            'last_name' => 'Keo',
            'employee_code' => 'EMP-1002',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'submitted',
            'employee_code' => 'EMP-1002',
        ]);

        $this->assertDatabaseHas('telegram_link_requests', [
            'telegram_id' => 999888777,
            'employee_code' => 'EMP-1002',
            'status' => 'pending',
        ]);
    }

    public function test_requester_can_submit_ticket_with_attachment(): void
    {
        $user = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $file = UploadedFile::fake()->create('printer_error.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/tma/api/tickets', [
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'subject' => 'Cashier receipt printer jammed',
            'description' => 'The paper roller is stuck and red light is flashing.',
            'attachments' => [$file],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['ticket_id', 'ticket_code']);

        $ticket = Ticket::where('requester_id', $user->id)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('submitted', $ticket->status);
        $this->assertCount(1, $ticket->attachments);
    }

    public function test_internal_notes_are_strictly_hidden_from_requester_in_tma(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $staff = User::where('email', 'tech1@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        // 1. Create ticket
        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'subject' => 'Monitor flickers',
            'description' => 'Screen goes dark randomly.',
        ]);

        // 2. Add public message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $staff->id,
            'visibility' => 'requester_visible',
            'message_type' => 'text',
            'body' => 'Hello, please try switching HDMI cables.',
        ]);

        // 3. Add internal note (Confidential)
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $staff->id,
            'visibility' => 'internal_staff_only',
            'message_type' => 'text',
            'body' => 'CONFIDENTIAL: User was running pirated software on work laptop.',
        ]);

        // 4. Requester fetches ticket via TMA endpoint
        $response = $this->actingAs($requester)->getJson("/tma/api/tickets/{$ticket->id}");

        $response->assertStatus(200);
        $messages = $response->json('messages');

        // Verify only public message is returned
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello, please try switching HDMI cables.', $messages[0]['body']);
        $this->assertStringNotContainsString('CONFIDENTIAL', json_encode($messages));
    }

    public function test_requester_resolution_confirmation_flow(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'pending_confirmation',
            'priority' => 'medium',
            'subject' => 'Wi-Fi slow',
            'description' => 'Cannot load intranet.',
        ]);

        // Requester confirms [Yes, Solved]
        $response = $this->actingAs($requester)->postJson("/tma/api/tickets/{$ticket->id}/confirm");
        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals('resolved_confirmed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_requester_reopen_ticket_flow(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'pending_confirmation',
            'priority' => 'medium',
            'subject' => 'Wi-Fi slow',
            'description' => 'Cannot load intranet.',
        ]);

        // Requester rejects [No, Still Broken]
        $response = $this->actingAs($requester)->postJson("/tma/api/tickets/{$ticket->id}/reopen", [
            'reason' => 'Disconnected again after 5 minutes.',
        ]);
        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals('reopened', $ticket->status);
    }

    public function test_auth_returns_web_login_required_when_accessed_directly_without_init_data(): void
    {
        $response = $this->postJson('/tma/api/auth', []);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'web_login_required',
        ]);
    }

    public function test_web_login_authenticates_with_employee_code_and_password(): void
    {
        $response = $this->postJson('/tma/api/login', [
            'login' => 'EMP-1001',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'authenticated',
            'user' => [
                'employee_code' => 'EMP-1001',
                'name' => 'Sokha Chan',
            ],
        ]);
        $this->assertAuthenticated();
    }

    public function test_web_login_authenticates_with_telegram_username(): void
    {
        $response = $this->postJson('/tma/api/login', [
            'login' => '@sokhachan_demo',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'authenticated',
            'user' => [
                'name' => 'Sokha Chan',
            ],
        ]);
        $this->assertAuthenticated();
    }

    public function test_ticket_image_attachment_preview_and_metadata(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        // Create ticket with image attachment
        $imageFile = UploadedFile::fake()->create('error_screenshot.png', 640, 'image/png');
        $response = $this->actingAs($requester)->postJson('/tma/api/tickets', [
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'subject' => 'Printer error display',
            'description' => 'Screen display is red with error code #902',
            'attachments' => [$imageFile],
        ]);
        $response->assertStatus(201);
        $ticketId = $response->json('ticket_id');

        // Fetch ticket details via API
        $detailResponse = $this->actingAs($requester)->getJson("/tma/api/tickets/{$ticketId}");
        $detailResponse->assertStatus(200);

        $messages = $detailResponse->json('messages');
        $this->assertNotEmpty($messages);
        $attachments = $messages[0]['attachments'];
        $this->assertNotEmpty($attachments);
        $this->assertTrue($attachments[0]['is_image']);
        $this->assertStringContainsString('preview=1', $attachments[0]['preview_url']);

        // Test streaming preview response
        $previewUrl = $attachments[0]['preview_url'];
        $streamResponse = $this->actingAs($requester)->get($previewUrl);
        $streamResponse->assertStatus(200);
        $streamResponse->assertHeader('Content-Type', 'image/png');
    }

    public function test_tma_ticket_index_includes_latest_message_and_staff_reply_indicator(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $staff = User::where('email', 'tech1@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'subject' => 'Monitor flickers',
            'description' => 'Screen turns off every few minutes.',
        ]);

        \App\Models\TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $staff->id,
            'visibility' => 'requester_visible',
            'message_type' => 'text',
            'body' => 'I will bring a replacement HDMI cable shortly.',
        ]);

        $response = $this->actingAs($requester)->getJson('/tma/api/tickets');
        $response->assertStatus(200);

        $tickets = $response->json('tickets');
        $this->assertNotEmpty($tickets);
        $found = collect($tickets)->firstWhere('id', $ticket->id);
        $this->assertNotNull($found);
        $this->assertNotNull($found['latest_message']);
        $this->assertEquals('I will bring a replacement HDMI cable shortly.', $found['latest_message']['body']);
        $this->assertTrue($found['latest_message']['is_staff']);
        $this->assertTrue($found['has_new_reply']);
    }

    public function test_tma_leave_ticket_immediately_clears_presence_cache(): void
    {
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
            'subject' => 'Mouse scroll broken',
            'description' => 'Wheel does not scroll.',
        ]);

        // When requester opens ticket, presence is set
        $this->actingAs($requester)->getJson("/tma/api/tickets/{$ticket->id}");
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("ticket_presence_{$ticket->id}_{$requester->id}"));

        // When requester closes modal, leave endpoint is called
        $leaveResponse = $this->actingAs($requester)->postJson("/tma/api/tickets/{$ticket->id}/leave");
        $leaveResponse->assertStatus(200);
        $leaveResponse->assertJson(['status' => 'left']);

        // Presence must be gone immediately
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("ticket_presence_{$ticket->id}_{$requester->id}"));
    }
}

