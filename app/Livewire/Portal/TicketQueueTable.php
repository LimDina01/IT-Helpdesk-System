<?php

namespace App\Livewire\Portal;

use App\Models\Branch;
use App\Models\TelegramLinkRequest;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAuditLog;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TicketQueueTable extends Component
{
    use WithPagination;

    #[Url(as: 'view')]
    public string $viewTab = 'triage';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'branch_id')]
    public string $branchFilter = '';

    #[Url(as: 'priority')]
    public string $priorityFilter = '';

    #[Url(as: 'division')]
    public string $divisionFilter = '';

    #[Url(as: 'per_page')]
    public int $perPage = 15;

    public ?string $toastMsg = null;

    public bool $isPaused = false;

    public function setViewTab(string $tab): void
    {
        $this->viewTab = $tab;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->branchFilter = '';
        $this->priorityFilter = '';
        $this->divisionFilter = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDivisionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function togglePause(): void
    {
        $this->isPaused = ! $this->isPaused;
        $this->toastMsg = $this->isPaused ? '⏸️ Auto-refresh paused' : '▶️ Auto-refresh resumed';
    }

    public function clearToast(): void
    {
        $this->toastMsg = null;
    }

    public function acceptTicket(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $user = Auth::user();

        if ($ticket->current_technician_id !== $user->id && ! $user->hasRole(['system_admin', 'hod'])) {
            $this->toastMsg = '⚠️ Only the assigned technician can accept or resume this ticket.';

            return;
        }

        $oldStatus = $ticket->status;

        DB::transaction(function () use ($ticket, $user, $oldStatus) {
            $now = now();
            $ticket->update([
                'status' => 'in_progress',
                'acknowledged_at' => $ticket->acknowledged_at ?? $now,
                'started_at' => $ticket->started_at ?? $now,
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'TICKET_ACCEPTED',
                'old_value' => $oldStatus,
                'new_value' => 'in_progress',
                'metadata' => [
                    'acknowledged_at' => $ticket->acknowledged_at?->toIso8601String(),
                    'started_at' => $ticket->started_at?->toIso8601String(),
                ],
                'created_at' => $now,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => $oldStatus === 'reopened'
                    ? "{$user->name} resumed work on this reopened ticket."
                    : "{$user->name} accepted and started working on this ticket.",
            ]);
        });

        $this->toastMsg = "Ticket {$ticket->ticket_code} accepted and in progress.";
    }

    public function assignToMe(int $ticketId, TelegramNotificationService $telegram): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $user = Auth::user();

        if (! $user->hasAnyRole(['technician', 'helpdesk', 'system_admin', 'hod'])) {
            $this->toastMsg = '⚠️ Only IT staff can assign tickets.';

            return;
        }

        DB::transaction(function () use ($ticket, $user) {
            $oldTechId = $ticket->current_technician_id;

            $ticket->update([
                'current_technician_id' => $user->id,
                'current_helpdesk_id' => $ticket->current_helpdesk_id ?? $user->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'acknowledged_at' => null,
            ]);

            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'assigned_by_id' => $user->id,
                'technician_id' => $user->id,
                'assigned_at' => now(),
                'note' => 'Self-assigned from Livewire Dashboard',
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'TICKET_ASSIGNED',
                'old_value' => $oldTechId ? (string) $oldTechId : null,
                'new_value' => (string) $user->id,
                'metadata' => ['self_assigned' => true],
                'created_at' => now(),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'reassignment',
                'body' => "Ticket self-assigned to {$user->name}.",
            ]);
        });

        $this->toastMsg = "Ticket {$ticket->ticket_code} assigned to you.";
    }

    public function render(): View
    {
        $user = Auth::user();

        // 1. KPI Counts
        $reopenedCount = Ticket::where('status', 'reopened')->count();
        $triageCount = Ticket::whereIn('status', ['submitted', 'reopened'])->count();
        $myTasksCount = Ticket::where('current_technician_id', $user?->id)
            ->whereIn('status', ['assigned', 'in_progress', 'blocked', 'reopened'])
            ->count();

        $slaBreachCount = Ticket::where('status', 'assigned')
            ->whereNull('acknowledged_at')
            ->where('assigned_at', '<', Carbon::now()->subMinutes(30))
            ->count();

        $blockedCount = Ticket::where('status', 'blocked')->count();
        $pendingConfirmCount = Ticket::where('status', 'pending_confirmation')->count();
        $pendingHoldCount = Ticket::whereNotNull('hold_requested_at')->count();
        $pendingApprovalsCount = Ticket::where('status', 'pending_approval')->count() + $pendingHoldCount;
        $pendingLinkRequestsCount = TelegramLinkRequest::where('status', 'pending')->count();
        $allActiveCount = Ticket::whereIn('status', [
            'pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened',
        ])->count();
        $oprCount = Ticket::whereIn('status', [
            'pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened',
        ])->where('it_division', 'opr')->count();
        $prdCount = Ticket::whereIn('status', [
            'pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened',
        ])->where('it_division', 'prd')->count();
        $solvedCount = Ticket::whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'closed'])->count();

        // 2. Queue Query
        $query = Ticket::with(['requester', 'branch', 'category.parent', 'technician', 'helpdesk', 'approvals']);

        if ($this->divisionFilter && in_array($this->divisionFilter, ['opr', 'prd'], true)) {
            $query->where('it_division', $this->divisionFilter);
        }

        if ($this->branchFilter) {
            $query->where('branch_id', $this->branchFilter);
        }

        if ($this->priorityFilter) {
            if (in_array($this->priorityFilter, ['critical_high', 'high_critical'], true)) {
                $query->whereIn('priority', ['critical', 'high']);
            } else {
                $query->where('priority', $this->priorityFilter);
            }
        }

        if (! empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('ticket_code', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('branch', fn ($bq) => $bq->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            });
        }

        switch ($this->viewTab) {
            case 'pending_approvals':
                $query->where(function ($q) {
                    $q->where('status', 'pending_approval')
                        ->orWhereNotNull('hold_requested_at');
                })
                    ->orderByRaw('CASE WHEN hold_requested_at IS NOT NULL THEN 0 ELSE 1 END')
                    ->orderByDesc('created_at');
                break;

            case 'my_tasks':
                $query->where('current_technician_id', $user?->id)
                    ->whereIn('status', ['assigned', 'in_progress', 'blocked', 'reopened'])
                    ->orderByRaw("CASE status WHEN 'reopened' THEN 1 ELSE 2 END")
                    ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('created_at');
                break;

            case 'all_active':
                $query->whereIn('status', ['pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened'])
                    ->orderByRaw("CASE WHEN hold_requested_at IS NOT NULL THEN 0 WHEN status = 'reopened' THEN 1 WHEN status = 'pending_approval' THEN 2 WHEN status = 'submitted' THEN 3 WHEN status = 'assigned' THEN 4 WHEN status = 'in_progress' THEN 5 WHEN status = 'blocked' THEN 6 WHEN status = 'clarifying' THEN 7 WHEN status = 'pending_confirmation' THEN 8 ELSE 9 END")
                    ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderByDesc('created_at');
                break;

            case 'blocked':
                $query->where(function ($q) {
                    $q->where('status', 'blocked')
                        ->orWhereNotNull('hold_requested_at');
                })
                    ->orderByRaw('CASE WHEN hold_requested_at IS NOT NULL THEN 0 ELSE 1 END')
                    ->orderByDesc('updated_at');
                break;

            case 'pending_confirmation':
                $query->where('status', 'pending_confirmation')
                    ->orderByDesc('resolved_at')
                    ->orderByDesc('updated_at');
                break;

            case 'solved':
            case 'closed':
                $query->whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'closed'])
                    ->orderByDesc('resolved_at')
                    ->orderByDesc('updated_at');
                break;

            case 'sla_breached':
                $query->where('status', 'assigned')
                    ->whereNull('acknowledged_at')
                    ->where('assigned_at', '<', Carbon::now()->subMinutes(30))
                    ->orderBy('assigned_at');
                break;

            case 'triage':
            default:
                $this->viewTab = 'triage';
                $query->whereIn('status', ['submitted', 'reopened'])
                    ->orderByRaw("CASE status WHEN 'reopened' THEN 1 ELSE 2 END")
                    ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('created_at');
                break;
        }

        $perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $tickets = $query->paginate($perPage);

        $branches = Branch::where('is_active', true)->get();
        $categories = TicketCategory::whereNull('parent_id')->get();

        return view('livewire.portal.ticket-queue-table', [
            'tickets' => $tickets,
            'allActiveCount' => $allActiveCount,
            'triageCount' => $triageCount,
            'reopenedCount' => $reopenedCount,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'pendingHoldCount' => $pendingHoldCount,
            'myTasksCount' => $myTasksCount,
            'blockedCount' => $blockedCount,
            'pendingConfirmCount' => $pendingConfirmCount,
            'solvedCount' => $solvedCount,
            'slaBreachCount' => $slaBreachCount,
            'branches' => $branches,
            'categories' => $categories,
        ]);
    }
}
