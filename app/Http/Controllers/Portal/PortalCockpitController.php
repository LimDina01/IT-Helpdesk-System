<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalCockpitController extends Controller
{
    /**
     * Display the HOD Executive Operations Cockpit.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user->hasRole(['hod', 'system_admin']), 403, 'HOD or System Administrator privileges required.');

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
        $unassignedTriageCount = Ticket::where('status', 'submitted')->count();

        // Total active in fulfillment
        $totalFulfillmentCount = Ticket::whereIn('status', ['assigned', 'in_progress', 'blocked'])->count();

        // 2. Live Technician Workload Distribution Board
        $technicians = User::whereHas('roles', fn($q) => $q->where('name', 'technician'))
            ->where('is_active', true)
            ->withCount([
                'assignedTickets as active_tasks_count' => fn($q) => $q->whereIn('status', ['assigned', 'in_progress', 'blocked']),
                'assignedTickets as resolved_today_count' => fn($q) => $q->where('resolved_at', '>=', Carbon::today()),
                'assignedTickets as overdue_sla_count' => fn($q) => $q->where('status', 'assigned')
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

        // 3. Urgent Intervention Queue (Blocked, SLA Breaches, Reopened, Criticals, Approvals)
        $urgentQuery = Ticket::with(['requester', 'branch', 'category', 'technician', 'approvals'])
            ->where(function ($q) {
                $q->where('status', 'blocked')
                  ->orWhere('status', 'reopened')
                  ->orWhere('priority', 'critical')
                  ->orWhere('status', 'pending_approval')
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'assigned')
                          ->whereNull('acknowledged_at')
                          ->where('assigned_at', '<', Carbon::now()->subMinutes(30));
                  });
            })
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');

        $urgentTickets = $urgentQuery->limit(20)->get();

        // 4. Audit Trail Stream
        $auditLogs = TicketAuditLog::with(['actor', 'ticket'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('portal.cockpit', compact(
            'escalatedCount',
            'blockedCount',
            'slaBreachCount',
            'reopenedCount',
            'pendingApprovalsCount',
            'unassignedTriageCount',
            'totalFulfillmentCount',
            'technicians',
            'urgentTickets',
            'auditLogs'
        ));
    }

    /**
     * Executive override: Adjust ticket priority instantly.
     */
    public function overridePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['hod', 'system_admin']), 403);

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
            'reason' => 'required|string|min:3',
        ]);

        $oldPriority = $ticket->priority;

        DB::transaction(function () use ($ticket, $validated, $oldPriority) {
            $ticket->update(['priority' => $validated['priority']]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => Auth::id(),
                'action' => 'PRIORITY_OVERRIDDEN',
                'old_value' => $oldPriority,
                'new_value' => $validated['priority'],
                'metadata' => [
                    'reason' => $validated['reason'],
                    'executive' => Auth::user()->name,
                ],
                'created_at' => now(),
            ]);

            // Post system event to conversation
            $ticket->messages()->create([
                'sender_id' => Auth::id(),
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => "⚡ Executive Override: Priority changed from " . strtoupper($oldPriority) . " to " . strtoupper($validated['priority']) . ". Reason: " . $validated['reason'],
            ]);
        });

        return back()->with('status', "Priority for {$ticket->ticket_code} updated to " . strtoupper($validated['priority']));
    }
}
