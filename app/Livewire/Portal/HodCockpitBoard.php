<?php

namespace App\Livewire\Portal;

use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class HodCockpitBoard extends Component
{
    /**
     * Filter filter for high-attention queue: 'all', 'hold', 'sla', 'blocked', 'approvals', 'critical'
     */
    public string $urgentFilter = 'all';

    /**
     * Priority override modal state
     */
    public bool $priorityModal = false;

    public ?int $targetTicketId = null;

    public string $targetTicketCode = '';

    public string $newPriority = 'critical';

    public string $overrideReason = '';

    /**
     * Hold decision modal state
     */
    public bool $holdModal = false;

    public ?int $holdTicketId = null;

    public string $holdTicketCode = '';

    public string $holdCategory = '';

    public string $holdRequestedReason = '';

    public string $holdRejectionReason = '';

    /**
     * Flash feedback notification
     */
    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    /**
     * Authorize user access.
     */
    public function boot(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole(['hod', 'system_admin']), 403, 'HOD or System Administrator privileges required.');
    }

    /**
     * Set urgent queue filter.
     */
    public function setUrgentFilter(string $filter): void
    {
        $this->urgentFilter = $filter;
    }

    /**
     * Open Priority Override modal for a ticket.
     */
    public function openPriorityModal(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $this->targetTicketId = $ticket->id;
        $this->targetTicketCode = $ticket->ticket_code;
        $this->newPriority = $ticket->priority;
        $this->overrideReason = '';
        $this->priorityModal = true;
    }

    /**
     * Close Priority Override modal.
     */
    public function closePriorityModal(): void
    {
        $this->priorityModal = false;
        $this->targetTicketId = null;
        $this->targetTicketCode = '';
        $this->overrideReason = '';
    }

    /**
     * Submit Executive Priority Override.
     */
    public function overridePriority(): void
    {
        $this->validate([
            'targetTicketId' => 'required|exists:tickets,id',
            'newPriority' => 'required|in:low,medium,high,critical',
            'overrideReason' => 'required|string|min:3|max:1000',
        ]);

        $ticket = Ticket::findOrFail($this->targetTicketId);
        $oldPriority = $ticket->priority;
        $user = Auth::user();

        DB::transaction(function () use ($ticket, $oldPriority, $user) {
            $ticket->update(['priority' => $this->newPriority]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'PRIORITY_OVERRIDDEN',
                'old_value' => $oldPriority,
                'new_value' => $this->newPriority,
                'metadata' => [
                    'reason' => $this->overrideReason,
                    'executive' => $user->name,
                ],
                'created_at' => now(),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => '⚡ Executive Override: Priority changed from '.strtoupper($oldPriority).' to '.strtoupper($this->newPriority).'. Reason: '.$this->overrideReason,
            ]);
        });

        $this->feedbackMessage = "Priority for {$ticket->ticket_code} updated to ".strtoupper($this->newPriority);
        $this->feedbackType = 'success';
        $this->closePriorityModal();
    }

    /**
     * Open Hold decision modal.
     */
    public function openHoldModal(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $this->holdTicketId = $ticket->id;
        $this->holdTicketCode = $ticket->ticket_code;
        $this->holdCategory = $ticket->hold_requested_category ?? 'Waiting for Vendor / Hardware Delivery';
        $this->holdRequestedReason = $ticket->hold_requested_reason ?? 'No detailed reason provided.';
        $this->holdRejectionReason = '';
        $this->holdModal = true;
    }

    /**
     * Close Hold decision modal.
     */
    public function closeHoldModal(): void
    {
        $this->holdModal = false;
        $this->holdTicketId = null;
        $this->holdTicketCode = '';
        $this->holdRejectionReason = '';
    }

    /**
     * Approve hold request.
     */
    public function approveHold(?int $ticketId = null): void
    {
        $id = $ticketId ?? $this->holdTicketId;
        if (! $id) {
            return;
        }

        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();
        $category = $ticket->hold_requested_category ?: 'General Hold';
        $reason = $ticket->hold_requested_reason ?: 'Approved by IT HOD';

        DB::transaction(function () use ($ticket, $user, $category, $reason) {
            $ticket->update([
                'status' => 'blocked',
                'blocked_reason' => "[{$category}] {$reason}",
                'blocked_at' => now(),
                'hold_requested_at' => null,
                'hold_requested_reason' => null,
                'hold_requested_category' => null,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => "⏸ IT HOD {$user->name} APPROVED Hold Request: [{$category}] {$reason}. Ticket is now on hold and SLA clock paused.",
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'HOLD_APPROVED',
                'old_value' => 'in_progress',
                'new_value' => 'blocked',
                'metadata' => [
                    'category' => $category,
                    'reason' => $reason,
                    'approved_by' => $user->name,
                ],
                'created_at' => now(),
            ]);
        });

        try {
            app(TelegramNotificationService::class)->notifyStatusChange($ticket, 'in_progress', 'blocked', "Hold approved by IT HOD: [{$category}] {$reason}");
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed on hold approval', ['error' => $e->getMessage()]);
        }

        $this->feedbackMessage = "Hold request for {$ticket->ticket_code} approved. SLA clock paused.";
        $this->feedbackType = 'success';
        $this->closeHoldModal();
    }

    /**
     * Reject hold request.
     */
    public function rejectHold(): void
    {
        $this->validate([
            'holdTicketId' => 'required|exists:tickets,id',
            'holdRejectionReason' => 'required|string|min:3|max:1000',
        ]);

        $ticket = Ticket::findOrFail($this->holdTicketId);
        $user = Auth::user();
        $reason = trim($this->holdRejectionReason);

        DB::transaction(function () use ($ticket, $user, $reason) {
            $ticket->update([
                'hold_requested_at' => null,
                'hold_requested_reason' => null,
                'hold_requested_category' => null,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => "❌ IT HOD {$user->name} REJECTED Hold Request: {$reason}. Work continues and SLA timer remains active.",
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'HOLD_REJECTED',
                'old_value' => 'in_progress',
                'new_value' => 'in_progress',
                'metadata' => [
                    'rejection_reason' => $reason,
                    'rejected_by' => $user->name,
                ],
                'created_at' => now(),
            ]);
        });

        try {
            app(TelegramNotificationService::class)->notifyStatusChange($ticket, 'in_progress', 'in_progress', "Hold request rejected by IT HOD: {$reason}");
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed on hold rejection', ['error' => $e->getMessage()]);
        }

        $this->feedbackMessage = "Hold request for {$ticket->ticket_code} rejected. Ticket remains active.";
        $this->feedbackType = 'warning';
        $this->closeHoldModal();
    }

    /**
     * Dismiss feedback banner.
     */
    public function dismissFeedback(): void
    {
        $this->feedbackMessage = null;
    }

    /**
     * Render the reactive component.
     */
    public function render(): View
    {
        // 1. Executive KPIs
        $escalatedCount = Ticket::whereIn('priority', ['critical', 'high'])
            ->whereIn('status', ['assigned', 'in_progress', 'blocked'])
            ->count();

        $blockedCount = Ticket::where('status', 'blocked')->count();

        $slaBreachCount = Ticket::where('status', 'assigned')
            ->whereNull('acknowledged_at')
            ->where('assigned_at', '<', Carbon::now()->subMinutes(30))
            ->count();

        $reopenedCount = Ticket::where('status', 'reopened')->count();
        $pendingApprovalsCount = Ticket::where('status', 'pending_approval')->count();
        $pendingHoldCount = Ticket::whereNotNull('hold_requested_at')->count();
        $unassignedTriageCount = Ticket::where('status', 'submitted')->count();
        $totalFulfillmentCount = Ticket::whereIn('status', ['assigned', 'in_progress', 'blocked'])->count();

        // 2. Live Technician Workload Distribution Board
        $technicians = User::whereHas('roles', fn ($q) => $q->where('name', 'technician'))
            ->where('is_active', true)
            ->withCount([
                'assignedTickets as active_tasks_count' => fn ($q) => $q->whereIn('status', ['assigned', 'in_progress', 'blocked']),
                'assignedTickets as resolved_today_count' => fn ($q) => $q->where('resolved_at', '>=', Carbon::today()),
                'assignedTickets as overdue_sla_count' => fn ($q) => $q->where('status', 'assigned')
                    ->whereNull('acknowledged_at')
                    ->where('assigned_at', '<', Carbon::now()->subMinutes(30)),
            ])
            ->get()
            ->map(function ($tech) {
                $active = $tech->active_tasks_count;
                if ($active === 0) {
                    $tech->load_status = 'Available';
                    $tech->load_badge = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                    $tech->load_indicator = 'bg-emerald-500';
                } elseif ($active <= 3) {
                    $tech->load_status = 'Normal';
                    $tech->load_badge = 'bg-blue-100 text-blue-800 border-blue-200';
                    $tech->load_indicator = 'bg-blue-500';
                } elseif ($active <= 5) {
                    $tech->load_status = 'Busy';
                    $tech->load_badge = 'bg-amber-100 text-amber-900 border-amber-200';
                    $tech->load_indicator = 'bg-amber-500';
                } else {
                    $tech->load_status = 'Overloaded';
                    $tech->load_badge = 'bg-rose-100 text-rose-900 border-rose-200';
                    $tech->load_indicator = 'bg-rose-500 animate-pulse';
                }

                return $tech;
            });

        // 3. Urgent Intervention Queue Query
        $urgentQuery = Ticket::with(['requester', 'branch', 'category', 'technician', 'approvals']);

        match ($this->urgentFilter) {
            'hold' => $urgentQuery->whereNotNull('hold_requested_at'),
            'sla' => $urgentQuery->where('status', 'assigned')
                ->whereNull('acknowledged_at')
                ->where('assigned_at', '<', Carbon::now()->subMinutes(30)),
            'blocked' => $urgentQuery->where('status', 'blocked'),
            'approvals' => $urgentQuery->where('status', 'pending_approval'),
            'critical' => $urgentQuery->where('priority', 'critical'),
            default => $urgentQuery->where(function ($q) {
                $q->where('status', 'blocked')
                    ->orWhere('status', 'reopened')
                    ->orWhere('priority', 'critical')
                    ->orWhere('status', 'pending_approval')
                    ->orWhereNotNull('hold_requested_at')
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'assigned')
                            ->whereNull('acknowledged_at')
                            ->where('assigned_at', '<', Carbon::now()->subMinutes(30));
                    });
            }),
        };

        $urgentTickets = $urgentQuery
            ->orderByRaw("CASE WHEN hold_requested_at IS NOT NULL THEN 0 WHEN priority = 'critical' THEN 1 WHEN priority = 'high' THEN 2 WHEN priority = 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // 4. Audit Trail Stream
        $auditLogs = TicketAuditLog::with(['actor', 'ticket'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('livewire.portal.hod-cockpit-board', compact(
            'escalatedCount',
            'blockedCount',
            'slaBreachCount',
            'reopenedCount',
            'pendingApprovalsCount',
            'pendingHoldCount',
            'unassignedTriageCount',
            'totalFulfillmentCount',
            'technicians',
            'urgentTickets',
            'auditLogs'
        ));
    }
}
