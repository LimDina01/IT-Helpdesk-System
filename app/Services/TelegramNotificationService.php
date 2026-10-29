<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected string $botToken;
    protected string $appUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->appUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
    }

    /**
     * Send message via Telegram Bot API and record to notification_logs.
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $inlineKeyboard = null,
        ?int $ticketId = null,
        string $eventType = 'general_alert',
        ?int $recipientUserId = null
    ): array {
        if (empty($this->botToken) || empty($chatId)) {
            return [
                'success' => false,
                'error' => 'Bot token or Telegram Chat ID is not configured.',
            ];
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = [
                'inline_keyboard' => $inlineKeyboard,
            ];
        }

        // Record queued log
        $log = NotificationLog::create([
            'ticket_id' => $ticketId,
            'recipient_user_id' => $recipientUserId ?? 1,
            'channel' => 'telegram',
            'event_type' => $eventType,
            'telegram_chat_id' => is_numeric($chatId) ? (int)$chatId : null,
            'message_payload' => json_encode($payload),
            'status' => 'queued',
        ]);

        try {
            $client = Http::timeout(10);
            if (app()->environment('local') || !config('services.telegram.verify_ssl', true)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);

            if ($response->successful()) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                return [
                    'success' => true,
                    'result' => $response->json(),
                ];
            } else {
                $errorData = $response->json();
                $errorDesc = $errorData['description'] ?? 'Telegram API error: ' . $response->body();
                
                $status = str_contains(strtolower($errorDesc), 'chat not found') || str_contains(strtolower($errorDesc), 'bot was blocked') 
                    ? 'blocked_no_chat' 
                    : 'failed';

                $log->update([
                    'status' => $status,
                    'error_message' => $errorDesc,
                    'retry_count' => $log->retry_count + 1,
                ]);

                Log::warning("Telegram notification failed for chat {$chatId}: {$errorDesc}");

                return [
                    'success' => false,
                    'error' => $errorDesc,
                ];
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $log->retry_count + 1,
            ]);

            Log::error("Exception sending Telegram notification: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Alert employee when Help Desk requests clarification.
     */
    public function sendClarificationAlert(User $requester, Ticket $ticket, string $question): array
    {
        if (!$requester->telegram_id) {
            return ['success' => false, 'error' => 'Requester has no linked Telegram ID'];
        }

        $text = "❓ *Information Needed on Ticket: {$ticket->ticket_code}*\n\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "*Question from IT Support:*\n_{$question}_\n\n"
              . "Please open the Mini App to reply directly.";

        $keyboard = [
            [
                ['text' => '📱 Open Ticket in Mini App', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ]
        ];

        return $this->sendMessage(
            $requester->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'clarification_requested',
            $requester->id
        );
    }

    /**
     * Alert employee when Help Desk or Technician sends a public reply.
     */
    public function sendStaffReplyAlert(User $requester, Ticket $ticket, string $messageBody, string $staffName): array
    {
        if (!$requester->telegram_id) {
            return ['success' => false, 'error' => 'Requester has no linked Telegram ID'];
        }

        $snippet = mb_strlen($messageBody) > 200 ? mb_substr($messageBody, 0, 197) . '...' : $messageBody;

        $text = "💬 *New Message from IT Support ({$staffName})*\n"
              . "*Ticket:* `{$ticket->ticket_code}`\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "_{$snippet}_\n\n"
              . "Please open the Mini App to view or reply.";

        $keyboard = [
            [
                ['text' => '📱 Open Ticket in Mini App', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ]
        ];

        return $this->sendMessage(
            $requester->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'staff_reply_sent',
            $requester->id
        );
    }

    /**
     * Alert technician when a new ticket is assigned to them.
     */
    public function sendAssignmentAlert(User $technician, Ticket $ticket): array
    {
        if (!$technician->telegram_id) {
            return ['success' => false, 'error' => 'Technician has no linked Telegram ID'];
        }

        $priorityEmoji = match($ticket->priority) {
            'critical' => '🚨 CRITICAL',
            'high' => '🔥 HIGH',
            'medium' => '⚡ MEDIUM',
            default => 'ℹ️ LOW'
        };

        $text = "📋 *New Task Assigned: {$ticket->ticket_code}*\n\n"
              . "*Priority:* {$priorityEmoji}\n"
              . "*Requester:* {$ticket->requester->name}\n"
              . "*Branch:* {$ticket->branch->name} ({$ticket->branch->code})\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "⚠️ *SLA Target:* Please accept and acknowledge within *30 minutes*.";

        $keyboard = [
            [
                ['text' => '🖥 Open Operations Portal', 'url' => "{$this->appUrl}/portal/tickets/{$ticket->id}"]
            ]
        ];

        return $this->sendMessage(
            $technician->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'ticket_assigned',
            $technician->id
        );
    }

    /**
     * Alert employee when technician marks ticket resolved (requesting confirmation).
     */
    public function sendResolutionAlert(User $requester, Ticket $ticket): array
    {
        if (!$requester->telegram_id) {
            return ['success' => false, 'error' => 'Requester has no linked Telegram ID'];
        }

        $deadlineStr = $ticket->auto_close_deadline ? $ticket->auto_close_deadline->format('l, M d') : '3 business days';

        $text = "✨ *Issue Resolved: {$ticket->ticket_code}*\n\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "The IT technician has marked your ticket as resolved.\n"
              . "Please verify if your problem is solved:\n\n"
              . "• *[Yes, Solved]* to complete\n"
              . "• *[No, Still Broken]* to return to technician\n\n"
              . "⏳ Ticket will auto-close on *{$deadlineStr}* if no response.";

        $keyboard = [
            [
                ['text' => '✅ Verify Resolution in Mini App', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ]
        ];

        return $this->sendMessage(
            $requester->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'resolution_confirmation_requested',
            $requester->id
        );
    }

    /**
     * Alert technician when employee rejects resolution and reopens ticket.
     */
    public function sendReopenAlert(User $technician, Ticket $ticket, string $reason): array
    {
        if (!$technician->telegram_id) {
            return ['success' => false, 'error' => 'Technician has no linked Telegram ID'];
        }

        $text = "⚠️ *Ticket Reopened: {$ticket->ticket_code}*\n\n"
              . "*Subject:* {$ticket->subject}\n"
              . "*Requester:* {$ticket->requester->name}\n\n"
              . "*Employee Reason:*\n_{$reason}_\n\n"
              . "Please re-investigate this issue.";

        $keyboard = [
            [
                ['text' => '🖥 View Reopened Ticket', 'url' => "{$this->appUrl}/portal/tickets/{$ticket->id}"]
            ]
        ];

        return $this->sendMessage(
            $technician->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'ticket_reopened',
            $technician->id
        );
    }

    /**
     * Alert employee when ticket is auto-closed after business days inactivity.
     */
    public function sendAutoClosedAlert(User $requester, Ticket $ticket): array
    {
        if (!$requester->telegram_id) {
            return ['success' => false, 'error' => 'Requester has no linked Telegram ID'];
        }

        $text = "📦 *Ticket Auto-Closed: {$ticket->ticket_code}*\n\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "This ticket has been automatically closed following the standard business-day resolution window. Thank you!";

        $keyboard = [
            [
                ['text' => '📱 View Ticket History', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ]
        ];

        return $this->sendMessage(
            $requester->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'ticket_auto_closed'
        );
    }

    /**
     * Alert approver (HOD / IT Manager) when a service request is awaiting their approval.
     */
    public function sendApprovalRequestAlert(User $approver, Ticket $ticket, \App\Models\TicketApproval $approval): array
    {
        if (!$approver->telegram_id) {
            return ['success' => false, 'error' => 'Approver has no linked Telegram ID'];
        }

        $text = "🔔 *Action Required: Service Request Approval*\n\n"
              . "*Step:* {$approval->step_name}\n"
              . "*Ticket:* `{$ticket->ticket_code}`\n"
              . "*Requester:* {$ticket->requester->name}\n"
              . "*Subject:* {$ticket->subject}\n\n"
              . "Please review this request and approve or reject it in the Mini App or Web Portal.";

        $keyboard = [
            [
                ['text' => '📱 Review & Approve in Mini App', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ],
            [
                ['text' => '🖥 Open in Web Portal', 'url' => "{$this->appUrl}/portal/tickets/{$ticket->id}"]
            ]
        ];

        return $this->sendMessage(
            $approver->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'approval_requested',
            $approver->id
        );
    }

    /**
     * Alert requester when their request is approved, progressed, or rejected.
     */
    public function sendApprovalStatusUpdate(User $requester, Ticket $ticket, string $statusText, ?string $reason = null): array
    {
        if (!$requester->telegram_id) {
            return ['success' => false, 'error' => 'Requester has no linked Telegram ID'];
        }

        $text = "📋 *Service Request Update: {$ticket->ticket_code}*\n\n"
              . "*Subject:* {$ticket->subject}\n"
              . "*Status:* {$statusText}\n";

        if ($reason) {
            $text .= "\n*Comments / Reason:*\n_{$reason}_\n";
        }

        $keyboard = [
            [
                ['text' => '📱 Track Request in Mini App', 'web_app' => ['url' => "{$this->appUrl}/tma"]]
            ]
        ];

        return $this->sendMessage(
            $requester->telegram_id,
            $text,
            $keyboard,
            $ticket->id,
            'approval_status_updated',
            $requester->id
        );
    }

    /**
     * Alert IT HOD / Admin when a technician requests to pause/hold a ticket.
     */
    public function sendHoldRequestAlertToItHod(Ticket $ticket, string $category, string $reason, string $techName): void
    {
        $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
            ->whereNotNull('telegram_id')
            ->where('is_active', true)
            ->get();

        $text = "⏸️ *Ticket Hold Approval Requested*\n\n"
              . "*Ticket:* `{$ticket->ticket_code}`\n"
              . "*Subject:* {$ticket->subject}\n"
              . "*Technician:* {$techName}\n"
              . "*Category:* {$category}\n"
              . "*Reason:* _{$reason}_\n\n"
              . "⚠️ *SLA countdown remains active until approved by IT HOD.*";

        $keyboard = [
            [
                ['text' => '🖥️ Review in Portal', 'url' => "{$this->appUrl}/portal/tickets/{$ticket->id}"]
            ]
        ];

        foreach ($approvers as $approver) {
            $this->sendMessage(
                $approver->telegram_id,
                $text,
                $keyboard,
                $ticket->id,
                'hold_approval_requested',
                $approver->id
            );
        }
    }
}
