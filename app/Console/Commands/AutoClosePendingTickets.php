<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\TicketMessage;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoClosePendingTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'helpdesk:auto-close';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close tickets in pending_confirmation state whose business days deadline has expired';

    /**
     * Execute the console command.
     */
    public function handle(TelegramNotificationService $telegram): int
    {
        $now = Carbon::now();

        $expiredTickets = Ticket::where('status', 'pending_confirmation')
            ->whereNotNull('auto_close_deadline')
            ->where('auto_close_deadline', '<=', $now)
            ->with('requester')
            ->get();

        if ($expiredTickets->isEmpty()) {
            $this->info('No expired tickets awaiting auto-closure.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiredTickets->count()} ticket(s) eligible for auto-closure.");

        foreach ($expiredTickets as $ticket) {
            $ticket->update([
                'status' => 'resolved_auto_closed',
                'closed_at' => $now,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $ticket->current_technician_id ?? $ticket->requester_id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => 'Auto-closed after standard business days inactivity window without employee rejection.',
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => null, // Automated system worker
                'action' => 'RESOLVED_AUTO_CLOSED',
                'old_value' => 'pending_confirmation',
                'new_value' => 'resolved_auto_closed',
                'metadata' => [
                    'closed_at' => $now->toIso8601String(),
                    'reason' => 'Auto-closed after business days deadline expired without employee rejection.',
                ],
                'created_at' => $now,
            ]);

            // Notify employee via Telegram Bot
            if ($ticket->requester) {
                $telegram->sendAutoClosedAlert($ticket->requester, $ticket);
            }

            $this->line("✔ Closed ticket: {$ticket->ticket_code}");
        }

        $this->info('Auto-close execution completed successfully.');

        return self::SUCCESS;
    }
}
