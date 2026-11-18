<?php

namespace App\Livewire\Portal;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\SoftwareLicense;
use App\Models\SoftwareLicenseAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AssetInventoryBoard extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $currentTab = 'assets';

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'category_id')]
    public string $categoryId = '';

    #[Url(as: 'branch_id')]
    public string $branchId = '';

    #[Url(as: 'department_id')]
    public string $departmentId = '';

    #[Url(as: 'ownership_type')]
    public string $ownershipType = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'lifecycle')]
    public string $lifecycle = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    // Components & Spares Filters
    #[Url(as: 'comp_search')]
    public string $compSearch = '';

    #[Url(as: 'comp_type')]
    public string $compType = '';

    #[Url(as: 'comp_status')]
    public string $compStatus = '';

    // Software Licenses Filters
    #[Url(as: 'license_search')]
    public string $licenseSearch = '';

    #[Url(as: 'license_type')]
    public string $licenseType = '';

    #[Url(as: 'license_status')]
    public string $licenseStatus = '';

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(?string $tab = null, ?string $currentTab = null): void
    {
        if ($currentTab) {
            $this->currentTab = $currentTab;
        } elseif ($tab) {
            $this->currentTab = $tab;
        }

        if ($this->currentTab === 'spares') {
            $this->currentTab = 'components';
        }

        if (! in_array($this->currentTab, ['assets', 'components', 'report', 'licenses'])) {
            $this->currentTab = 'assets';
        }

        if (! in_array($this->perPage, [10, 25, 50, 100])) {
            $this->perPage = 10;
        }
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'spares') {
            $tab = 'components';
        }

        if (in_array($tab, ['assets', 'components', 'report', 'licenses'])) {
            $this->currentTab = $tab;
            $this->resetPage('assets_page');
            $this->resetPage('comp_page');
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryId = '';
        $this->branchId = '';
        $this->departmentId = '';
        $this->ownershipType = '';
        $this->status = '';
        $this->lifecycle = '';
        $this->resetPage('assets_page');
    }

    public function clearCompFilters(): void
    {
        $this->compSearch = '';
        $this->compType = '';
        $this->compStatus = '';
        $this->resetPage('comp_page');
    }

    public function clearLicenseFilters(): void
    {
        $this->licenseSearch = '';
        $this->licenseType = '';
        $this->licenseStatus = '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedBranchId(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedDepartmentId(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedOwnershipType(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedLifecycle(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage('assets_page');
    }

    public function updatedCompSearch(): void
    {
        $this->resetPage('comp_page');
    }

    public function updatedCompType(): void
    {
        $this->resetPage('comp_page');
    }

    public function updatedCompStatus(): void
    {
        $this->resetPage('comp_page');
    }

    public function updatedLicenseSearch(): void
    {
        // No pagination on licenses (full list filtered)
    }

    public function updatedLicenseType(): void
    {
        // No pagination on licenses
    }

    public function updatedLicenseStatus(): void
    {
        // No pagination on licenses
    }

    public function dismissFeedback(): void
    {
        $this->feedbackMessage = null;
    }

    public function render(): View
    {
        // Lookups
        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $users = User::with(['employee.department', 'employee.branch', 'assignedAssets'])->where('is_active', true)->orderBy('name')->get();

        $usersJson = $users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'dept' => $u->employee?->department?->name ?? 'General Staff',
            'branch' => $u->employee?->branch?->name ?? 'HQ',
            'primary_asset_id' => $u->assignedAssets->first()?->id ?? null,
        ])->values();

        $allAssetsJson = $this->currentTab === 'licenses'
            ? Asset::with('branch')->orderBy('asset_tag')->get()->map(fn ($a) => [
                'id' => $a->id,
                'tag' => $a->asset_tag,
                'name' => $a->name,
                'model' => trim(($a->brand ?? '').' '.($a->model ?? '')),
                'branch' => $a->branch?->name ?? 'HQ',
                'assigned_user_id' => $a->assigned_user_id,
            ])->values()
            : collect();

        $canViewFinancials = Auth::check() && Auth::user()->hasRole(['hod', 'system_admin']);

        // Summary KPI Counts
        $totalAssetsCount = Asset::count();
        $inUseCount = Asset::where('status', 'in_use')->count();
        $spareAssetsCount = Asset::where('status', 'spare_stock')->count();
        $sharedLocationsCount = Asset::where('ownership_type', 'shared')->count();
        $spareComponentsCount = AssetComponent::where('status', 'in_stock')->count();
        $pastEodCount = Asset::whereNotNull('eod_date')->where('eod_date', '<=', now()->toDateString())->where('status', '!=', 'decommissioned')->count();
        $pastEolCount = Asset::whereNotNull('eol_date')->where('eol_date', '<=', now()->toDateString())->where('status', '!=', 'decommissioned')->count();
        $expiringWarrantyCount = Asset::whereNotNull('warranty_expiry')->whereBetween('warranty_expiry', [now()->toDateString(), now()->addDays(60)->toDateString()])->where('status', '!=', 'decommissioned')->count();

        // Financial & Breakdown Metrics
        $totalValuation = (float) Asset::where('status', '!=', 'decommissioned')->sum('purchase_cost');
        $underRepairCount = Asset::where('status', 'under_repair')->count();
        $decommissionedCount = Asset::where('status', 'decommissioned')->count();

        $activeWarrantyCount = Asset::whereNotNull('warranty_expiry')->where('warranty_expiry', '>=', now()->toDateString())->where('status', '!=', 'decommissioned')->count();
        $activeWarrantyValuation = (float) Asset::whereNotNull('warranty_expiry')->where('warranty_expiry', '>=', now()->toDateString())->where('status', '!=', 'decommissioned')->sum('purchase_cost');

        $outOfWarrantyCount = Asset::whereNotNull('warranty_expiry')->where('warranty_expiry', '<', now()->toDateString())->where('status', '!=', 'decommissioned')->count();
        $outOfWarrantyValuation = (float) Asset::whereNotNull('warranty_expiry')->where('warranty_expiry', '<', now()->toDateString())->where('status', '!=', 'decommissioned')->sum('purchase_cost');

        $pastEodValuation = (float) Asset::whereNotNull('eod_date')->where('eod_date', '<=', now()->toDateString())->where('status', '!=', 'decommissioned')->sum('purchase_cost');
        $pastEolValuation = (float) Asset::whereNotNull('eol_date')->where('eol_date', '<=', now()->toDateString())->where('status', '!=', 'decommissioned')->sum('purchase_cost');

        $deptBreakdown = Department::withCount(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')])
            ->withSum(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')], 'purchase_cost')
            ->orderByDesc('assets_count')
            ->get();

        $branchBreakdown = Branch::withCount(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')])
            ->withSum(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')], 'purchase_cost')
            ->orderByDesc('assets_count')
            ->get();

        $categoryBreakdown = AssetCategory::withCount(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')])
            ->withSum(['assets' => fn ($q) => $q->where('status', '!=', 'decommissioned')], 'purchase_cost')
            ->orderByDesc('assets_count')
            ->get();

        // 1. Assets Query
        $assetsQuery = Asset::with(['category', 'branch', 'department', 'assignedUser', 'components'])
            ->orderByDesc('id');

        if (! empty($this->search)) {
            $search = trim($this->search);
            $assetsQuery->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('location_details', 'like', "%{$search}%")
                    ->orWhereHas('assignedUser', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($this->categoryId)) {
            $assetsQuery->where('category_id', $this->categoryId);
        }

        if (! empty($this->branchId)) {
            $assetsQuery->where('branch_id', $this->branchId);
        }

        if (! empty($this->departmentId)) {
            $assetsQuery->where('department_id', $this->departmentId);
        }

        if (! empty($this->ownershipType) && in_array($this->ownershipType, ['personal', 'shared'])) {
            $assetsQuery->where('ownership_type', $this->ownershipType);
        }

        if (! empty($this->status)) {
            $assetsQuery->where('status', $this->status);
        }

        if (! empty($this->lifecycle)) {
            if ($this->lifecycle === 'past_eod') {
                $assetsQuery->whereNotNull('eod_date')->where('eod_date', '<=', now()->toDateString());
            } elseif ($this->lifecycle === 'past_eol') {
                $assetsQuery->whereNotNull('eol_date')->where('eol_date', '<=', now()->toDateString());
            } elseif ($this->lifecycle === 'expiring_warranty') {
                $assetsQuery->whereNotNull('warranty_expiry')->whereBetween('warranty_expiry', [now()->toDateString(), now()->addDays(60)->toDateString()]);
            } elseif ($this->lifecycle === 'out_of_warranty') {
                $assetsQuery->whereNotNull('warranty_expiry')->where('warranty_expiry', '<', now()->toDateString());
            } elseif ($this->lifecycle === 'under_warranty') {
                $assetsQuery->whereNotNull('warranty_expiry')->where('warranty_expiry', '>=', now()->toDateString());
            }
        }

        $assets = $assetsQuery->paginate($this->perPage, ['*'], 'assets_page');

        // 2. Spare Components Stock Query
        $componentsQuery = AssetComponent::with(['asset', 'branch'])
            ->orderByDesc('id');

        if (! empty($this->compSearch)) {
            $compSearch = trim($this->compSearch);
            $componentsQuery->where(function ($q) use ($compSearch) {
                $q->where('brand_model', 'like', "%{$compSearch}%")
                    ->orWhere('serial_number', 'like', "%{$compSearch}%")
                    ->orWhere('capacity_or_specs', 'like', "%{$compSearch}%")
                    ->orWhereHas('asset', fn ($aq) => $aq->where('asset_tag', 'like', "%{$compSearch}%")->orWhere('name', 'like', "%{$compSearch}%"));
            });
        }

        if (! empty($this->compType)) {
            $componentsQuery->where('component_type', $this->compType);
        }

        if (! empty($this->compStatus)) {
            $componentsQuery->where('status', $this->compStatus);
        }

        $components = $componentsQuery->paginate(10, ['*'], 'comp_page');

        // 3. Software & Digital Licenses
        $licensesQuery = SoftwareLicense::with(['assignments.user', 'assignments.asset'])
            ->orderBy('name');

        if (! empty($this->licenseSearch)) {
            $licenseSearch = trim($this->licenseSearch);
            $licensesQuery->where(function ($q) use ($licenseSearch) {
                $q->where('name', 'like', "%{$licenseSearch}%")
                    ->orWhere('vendor_publisher', 'like', "%{$licenseSearch}%")
                    ->orWhere('license_key', 'like', "%{$licenseSearch}%")
                    ->orWhere('notes', 'like', "%{$licenseSearch}%")
                    ->orWhereHas('assignments.user', fn ($uq) => $uq->where('name', 'like', "%{$licenseSearch}%"))
                    ->orWhereHas('assignments.asset', fn ($aq) => $aq->where('asset_tag', 'like', "%{$licenseSearch}%"));
            });
        }

        if (! empty($this->licenseType)) {
            $licensesQuery->where('license_type', $this->licenseType);
        }

        if (! empty($this->licenseStatus)) {
            if ($this->licenseStatus === 'expiring_soon') {
                $licensesQuery->where('status', '!=', 'cancelled')
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            } elseif ($this->licenseStatus === 'expired') {
                $licensesQuery->where(function ($q) {
                    $q->where('status', 'expired')
                        ->orWhere(function ($sq) {
                            $sq->whereNotNull('expiry_date')
                                ->where('expiry_date', '<', now()->toDateString())
                                ->where('status', '!=', 'cancelled');
                        });
                });
            } elseif ($this->licenseStatus === 'active') {
                $licensesQuery->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')
                            ->orWhere('expiry_date', '>=', now()->toDateString());
                    });
            } else {
                $licensesQuery->where('status', $this->licenseStatus);
            }
        }

        $licenses = $licensesQuery->get();

        $totalLicensesCount = SoftwareLicense::count();
        $totalSeatsCount = (int) SoftwareLicense::sum('seats_total');
        $assignedSeatsCount = SoftwareLicenseAssignment::count();
        $expiringLicensesCount = SoftwareLicense::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();
        $totalSoftwareValuation = (float) SoftwareLicense::sum('purchase_cost');

        return view('livewire.portal.asset-inventory-board', compact(
            'canViewFinancials',
            'allAssetsJson',
            'assets',
            'components',
            'licenses',
            'totalLicensesCount',
            'totalSeatsCount',
            'assignedSeatsCount',
            'expiringLicensesCount',
            'totalSoftwareValuation',
            'totalAssetsCount',
            'inUseCount',
            'spareAssetsCount',
            'underRepairCount',
            'decommissionedCount',
            'sharedLocationsCount',
            'spareComponentsCount',
            'pastEodCount',
            'pastEolCount',
            'expiringWarrantyCount',
            'totalValuation',
            'activeWarrantyCount',
            'activeWarrantyValuation',
            'outOfWarrantyCount',
            'outOfWarrantyValuation',
            'pastEodValuation',
            'pastEolValuation',
            'deptBreakdown',
            'branchBreakdown',
            'categoryBreakdown',
            'categories',
            'branches',
            'departments',
            'users',
            'usersJson'
        ));
    }
}
