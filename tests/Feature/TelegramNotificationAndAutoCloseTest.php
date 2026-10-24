<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\NotificationLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TelegramNotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationAndAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_telegram_notification_service_records_to_outbox_log(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $service = new TelegramNotificationService();
        $result = $service->sendMessage(
            123456789,
            'Hello from CBVH IT Help Desk test alert!',
            null,
            null,
            'test_event',
            1
        );

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('notification_logs', [
            'telegram_chat_id' => 123456789,
            'event_type' => 'test_event',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_notification_handles_blocked_or_missing_chat_safely(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 403,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        $service = new TelegramNotificationService();
        $result = $service->sendMessage(
            999999999,
            'Alert that cannot be delivered',
            null,
            null,
            'test_fail',
            1
        );

        $this->assertFalse($result['success']);

        $this->assertDatabaseHas('notification_logs', [
            'telegram_chat_id' => 999999999,
            'status' => 'blocked_no_chat',
        ]);
    }

    public function test_auto_close_command_closes_expired_tickets_and_notifies_employee(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        // Expired ticket (deadline was yesterday)
        $expiredTicket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'pending_confirmation',
            'priority' => 'medium',
            'subject' => 'Printer power test',
            'description' => 'Done testing.',
            'resolved_at' => now()->subDays(4),
            'auto_close_deadline' => now()->subDay(),
        ]);

        // Unexpired ticket (deadline is tomorrow)
        $unexpiredTicket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'pending_confirmation',
            'priority' => 'medium',
            'subject' => 'Wi-Fi antenna replacement',
            'description' => 'Waiting for employee verification.',
            'resolved_at' => now()->subHours(12),
            'auto_close_deadline' => now()->addDays(2),
        ]);

        // Run scheduled auto-close command
        $exitCode = Artisan::call('helpdesk:auto-close');
        $this->assertEquals(0, $exitCode);

        // Verify expired ticket closed
        $expiredTicket->refresh();
        $this->assertEquals('resolved_auto_closed', $expiredTicket->status);
        $this->assertNotNull($expiredTicket->closed_at);

        // Verify unexpired ticket remained pending
        $unexpiredTicket->refresh();
        $this->assertEquals('pending_confirmation', $unexpiredTicket->status);

        // Verify audit log created
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $expiredTicket->id,
            'action' => 'RESOLVED_AUTO_CLOSED',
        ]);

        // Verify notification log created
        $this->assertDatabaseHas('notification_logs', [
            'ticket_id' => $expiredTicket->id,
            'event_type' => 'ticket_auto_closed',
            'status' => 'sent',
        ]);
    }
}
