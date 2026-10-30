<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TelegramLinkRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    /**
     * Display the operations dashboard and ticket queues.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $viewTab = $request->input('view', 'triage'); // 'triage', 'my_tasks', 'all_active', 'blocked'
        $branchFilter = $request->input('branch_id');
        $priorityFilter = $request->input('priority');
        $statusFilter = $request->input('status');
        $searchQuery = $request->input('q');

        // 1. KPI Counts
        $reopenedCount = Ticket::where('status', 'reopened')->count();
        $triageCount = Ticket::whereIn('status', ['submitted', 'reopened'])->count();
        $myTasksCount = Ticket::where('current_technician_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'blocked', 'reopened'])
            ->count();
        
        // 30-minute acknowledgement SLA breach count
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
            'pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened'
        ])->count();
        $solvedCount = Ticket::whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'closed'])->count();

        // 2. Queue Query
        $query = Ticket::with(['requester', 'branch', 'category.parent', 'technician', 'helpdesk', 'approvals']);

        if ($branchFilter) {
            $query->where('branch_id', $branchFilter);
        }
        if ($priorityFilter) {
            if (in_array($priorityFilter, ['critical_high', 'high_critical'], true)) {
                $query->whereIn('priority', ['critical', 'high']);
            } else {
                $query->where('priority', $priorityFilter);
            }
        }
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('ticket_code', 'like', "%{$searchQuery}%")
                  ->orWhere('subject', 'like', "%{$searchQuery}%")
                  ->orWhere('description', 'like', "%{$searchQuery}%")
                  ->orWhereHas('requester', fn($rq) => $rq->where('name', 'like', "%{$searchQuery}%"))
                  ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$searchQuery}%")->orWhere('code', 'like', "%{$searchQuery}%"));
            });
        }

        switch ($viewTab) {
            case 'pending_approvals':
                $query->where(function ($q) {
                    $q->where('status', 'pending_approval')
                      ->orWhereNotNull('hold_requested_at');
                })
                ->orderByRaw("CASE WHEN hold_requested_at IS NOT NULL THEN 0 ELSE 1 END")
                ->orderByDesc('created_at');
                break;

            case 'my_tasks':
                $query->where('current_technician_id', $user->id)
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
                ->orderByRaw("CASE WHEN hold_requested_at IS NOT NULL THEN 0 ELSE 1 END")
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

            case 'reopened':
                $viewTab = 'triage';
                $query->where('status', 'reopened')
                    ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('created_at');
                break;

            case 'triage':
            default:
                $viewTab = 'triage';
                $query->whereIn('status', ['submitted', 'reopened'])
                    ->orderByRaw("CASE status WHEN 'reopened' THEN 1 ELSE 2 END")
                    ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('created_at');
                break;
        }

        $tickets = $query->paginate(15)->withQueryString();

        // 3. Technicians available for assignment
        $technicians = User::whereHas('roles', fn($q) => $q->where('name', 'technician'))
            ->where('is_active', true)
            ->withCount(['assignedTickets as active_tasks_count' => function ($q) {
                $q->whereIn('status', ['assigned', 'in_progress', 'blocked', 'reopened']);
            }])
            ->get();

        $branches = Branch::where('is_active', true)->get();
        $categories = TicketCategory::whereNull('parent_id')->get();

        return view('portal.dashboard', compact(
            'tickets',
            'viewTab',
            'triageCount',
            'reopenedCount',
            'myTasksCount',
            'allActiveCount',
            'solvedCount',
            'slaBreachCount',
            'blockedCount',
            'pendingConfirmCount',
            'pendingApprovalsCount',
            'pendingHoldCount',
            'pendingLinkRequestsCount',
            'technicians',
            'branches',
            'categories',
            'branchFilter',
            'priorityFilter',
            'searchQuery'
        ));
    }
}
