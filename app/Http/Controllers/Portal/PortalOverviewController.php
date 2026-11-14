<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalOverviewController extends Controller
{
    /**
     * Display the GLPI-inspired Central IT Operations and Asset Overview.
     */
    public function index(Request $request): View
    {
        $branchFilter = $request->input('branch_id');
        $selectedBranch = $branchFilter ? Branch::find($branchFilter) : null;

        // Base Queries scoped optionally by Branch
        $assetQuery = Asset::query();
        $ticketQuery = Ticket::query();

        if ($branchFilter) {
            $assetQuery->where('branch_id', $branchFilter);
            $ticketQuery->where('branch_id', $branchFilter);
        }

        // 1. Asset Category Breakdown (GLPI Colorful Tiles)
        $categories = AssetCategory::withCount(['assets' => function ($q) use ($branchFilter) {
            if ($branchFilter) {
                $q->where('branch_id', $branchFilter);
            }
        }])->get();

        $totalAssets = (clone $assetQuery)->count();
        $inUseAssets = (clone $assetQuery)->where('status', 'in_use')->count();
        $spareStockAssets = (clone $assetQuery)->where('status', 'spare_stock')->count();
        $underRepairAssets = (clone $assetQuery)->where('status', 'under_repair')->count();
        $decommissionedAssets = (clone $assetQuery)->where('status', 'decommissioned')->count();

        // Spare Components
        $sparesInStock = AssetComponent::whereNull('asset_id')->where('status', 'in_stock')->count();
        $attachedComponents = AssetComponent::whereNotNull('asset_id')->count();

        // 2. Hardware Lifecycle & Capital Valuation
        $todayDate = Carbon::now()->toDateString();
        $totalValuation = (float) (clone $assetQuery)->sum('purchase_cost');

        $activeWarrantyCount = (clone $assetQuery)->where('warranty_expiry', '>=', $todayDate)->count();
        $activeWarrantyValuation = (float) (clone $assetQuery)->where('warranty_expiry', '>=', $todayDate)->sum('purchase_cost');

        $outOfWarrantyCount = (clone $assetQuery)->where('warranty_expiry', '<', $todayDate)->count();
        $outOfWarrantyValuation = (float) (clone $assetQuery)->where('warranty_expiry', '<', $todayDate)->sum('purchase_cost');

        $pastEodCount = (clone $assetQuery)->where('eod_date', '<', $todayDate)->count();
        $pastEodValuation = (float) (clone $assetQuery)->where('eod_date', '<', $todayDate)->sum('purchase_cost');

        $pastEolCount = (clone $assetQuery)->where('eol_date', '<', $todayDate)->count();
        $pastEolValuation = (float) (clone $assetQuery)->where('eol_date', '<', $todayDate)->sum('purchase_cost');

        // 3. Helpdesk Operations Pulse (GLPI Assistance / Tickets)
        $activeStatuses = ['pending_approval', 'submitted', 'clarifying', 'assigned', 'in_progress', 'blocked', 'pending_confirmation', 'reopened'];

        $allActiveTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->count();
        $triageTickets = (clone $ticketQuery)->whereIn('status', ['submitted', 'reopened'])->count();
        $inProgressTickets = (clone $ticketQuery)->whereIn('status', ['assigned', 'in_progress'])->count();
        $blockedTickets = (clone $ticketQuery)->where('status', 'blocked')->orWhereNotNull('hold_requested_at')->count();
        $pendingApprovals = (clone $ticketQuery)->where('status', 'pending_approval')->count();
        $pendingConfirmation = (clone $ticketQuery)->where('status', 'pending_confirmation')->count();
        $solvedTickets = (clone $ticketQuery)->whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'closed'])->count();
        $solvedToday = (clone $ticketQuery)->whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'closed'])
            ->whereDate('resolved_at', Carbon::today())
            ->count();

        // Overdue 30-min SLA acknowledgement breaches
        $slaBreaches = (clone $ticketQuery)->where('status', 'assigned')
            ->whereNull('acknowledged_at')
            ->where('assigned_at', '<', Carbon::now()->subMinutes(30))
            ->count();

        // Ticket Priorities
        $criticalTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('priority', 'critical')->count();
        $highTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('priority', 'high')->count();
        $mediumTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('priority', 'medium')->count();
        $lowTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('priority', 'low')->count();

        // Divisions
        $oprTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('it_division', 'opr')->count();
        $prdTickets = (clone $ticketQuery)->whereIn('status', $activeStatuses)->where('it_division', 'prd')->count();

        // 4. Branch Cross-Matrix (GLPI Multi-Entity View)
        $branches = Branch::where('is_active', true)->get()->map(function (Branch $branch) use ($todayDate, $activeStatuses) {
            $branchAssets = Asset::where('branch_id', $branch->id);
            $branchTickets = Ticket::where('branch_id', $branch->id);

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'location' => $branch->location,
                'manager' => $branch->manager?->name ?? 'Unassigned',
                'assets_count' => (clone $branchAssets)->count(),
                'assets_valuation' => (float) (clone $branchAssets)->sum('purchase_cost'),
                'active_tickets_count' => (clone $branchTickets)->whereIn('status', $activeStatuses)->count(),
                'sla_breaches_count' => (clone $branchTickets)->where('status', 'assigned')
                    ->whereNull('acknowledged_at')
                    ->where('assigned_at', '<', Carbon::now()->subMinutes(30))
                    ->count(),
                'eod_risk_count' => (clone $branchAssets)->where('eod_date', '<', $todayDate)->count(),
            ];
        });

        // 5. Technician Workload Roster
        $technicians = User::whereHas('roles', fn ($q) => $q->where('name', 'technician'))
            ->where('is_active', true)
            ->withCount(['assignedTickets as active_tasks_count' => function ($q) {
                $q->whereIn('status', ['assigned', 'in_progress', 'blocked', 'reopened']);
            }])
            ->get();

        // 6. Recent Operational Activity Feed
        $recentTickets = (clone $ticketQuery)->with(['requester', 'branch', 'category'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $recentAssets = (clone $assetQuery)->with(['category', 'branch', 'assignedUser'])
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('portal.overview', compact(
            'branchFilter',
            'selectedBranch',
            'categories',
            'totalAssets',
            'inUseAssets',
            'spareStockAssets',
            'underRepairAssets',
            'decommissionedAssets',
            'sparesInStock',
            'attachedComponents',
            'totalValuation',
            'activeWarrantyCount',
            'activeWarrantyValuation',
            'outOfWarrantyCount',
            'outOfWarrantyValuation',
            'pastEodCount',
            'pastEodValuation',
            'pastEolCount',
            'pastEolValuation',
            'allActiveTickets',
            'triageTickets',
            'inProgressTickets',
            'blockedTickets',
            'pendingApprovals',
            'pendingConfirmation',
            'solvedTickets',
            'solvedToday',
            'slaBreaches',
            'criticalTickets',
            'highTickets',
            'mediumTickets',
            'lowTickets',
            'oprTickets',
            'prdTickets',
            'branches',
            'technicians',
            'recentTickets',
            'recentAssets'
        ));
    }
}
