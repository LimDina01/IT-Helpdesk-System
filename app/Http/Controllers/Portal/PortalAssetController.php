<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalAssetController extends Controller
{
    /**
     * Display the Asset Registry and Spare Parts Stock.
     */
    public function index(Request $request): View
    {
        $currentTab = $request->input('tab', 'assets');
        $search = trim($request->input('search', ''));
        $categoryId = $request->input('category_id');
        $branchId = $request->input('branch_id');
        $departmentId = $request->input('department_id');
        $ownershipType = $request->input('ownership_type');
        $status = $request->input('status');
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 10;
        }

        // 1. Assets Query
        $assetsQuery = Asset::with(['category', 'branch', 'department', 'assignedUser', 'components'])
            ->orderByDesc('id');

        if (! empty($search)) {
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

        if (! empty($categoryId)) {
            $assetsQuery->where('category_id', $categoryId);
        }

        if (! empty($branchId)) {
            $assetsQuery->where('branch_id', $branchId);
        }

        if (! empty($departmentId)) {
            $assetsQuery->where('department_id', $departmentId);
        }

        if (! empty($ownershipType) && in_array($ownershipType, ['personal', 'shared'])) {
            $assetsQuery->where('ownership_type', $ownershipType);
        }

        if (! empty($status)) {
            $assetsQuery->where('status', $status);
        }

        $assets = $assetsQuery->paginate($perPage, ['*'], 'assets_page')->withQueryString();

        // 2. Spare Components Stock Query
        $componentsQuery = AssetComponent::with(['asset', 'branch'])
            ->orderByDesc('id');

        $compSearch = trim($request->input('comp_search', ''));
        $compType = $request->input('comp_type');
        $compStatus = $request->input('comp_status');

        if (! empty($compSearch)) {
            $componentsQuery->where(function ($q) use ($compSearch) {
                $q->where('brand_model', 'like', "%{$compSearch}%")
                    ->orWhere('serial_number', 'like', "%{$compSearch}%")
                    ->orWhere('capacity_or_specs', 'like', "%{$compSearch}%")
                    ->orWhereHas('asset', fn ($aq) => $aq->where('asset_tag', 'like', "%{$compSearch}%")->orWhere('name', 'like', "%{$compSearch}%"));
            });
        }

        if (! empty($compType)) {
            $componentsQuery->where('component_type', $compType);
        }

        if (! empty($compStatus)) {
            $componentsQuery->where('status', $compStatus);
        }

        $components = $componentsQuery->paginate($perPage, ['*'], 'comp_page')->withQueryString();

        // Summary KPI Counts
        $totalAssetsCount = Asset::count();
        $inUseCount = Asset::where('status', 'in_use')->count();
        $spareAssetsCount = Asset::where('status', 'spare_stock')->count();
        $sharedLocationsCount = Asset::where('ownership_type', 'shared')->count();
        $spareComponentsCount = AssetComponent::where('status', 'in_stock')->count();

        // Lookups for Modals & Filters
        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        return view('portal.assets.index', compact(
            'assets',
            'components',
            'currentTab',
            'search',
            'categoryId',
            'branchId',
            'departmentId',
            'ownershipType',
            'status',
            'perPage',
            'compSearch',
            'compType',
            'compStatus',
            'totalAssetsCount',
            'inUseCount',
            'spareAssetsCount',
            'sharedLocationsCount',
            'spareComponentsCount',
            'categories',
            'branches',
            'departments',
            'users'
        ));
    }

    /**
     * Store a new hardware asset.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_tag' => 'nullable|string|max:50|unique:assets,asset_tag',
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:asset_categories,id',
            'ownership_type' => 'required|in:personal,shared',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'location_details' => 'nullable|string|max:150',
            'assigned_user_id' => 'nullable|exists:users,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'status' => 'required|in:in_use,spare_stock,under_repair,decommissioned',
            'specs_cpu' => 'nullable|string|max:100',
            'specs_ram' => 'nullable|string|max:100',
            'specs_storage' => 'nullable|string|max:100',
            'specs_os' => 'nullable|string|max:100',
            'specs_ip' => 'nullable|string|max:50',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'vendor_supplier' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        // Auto-generate asset tag if left blank
        if (empty($validated['asset_tag'])) {
            $category = AssetCategory::find($validated['category_id']);
            $catCode = $category ? $category->code : 'AST';
            $nextSeq = Asset::where('category_id', $validated['category_id'])->count() + 1;
            $validated['asset_tag'] = 'CBVH-'.$catCode.'-'.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        }

        // Structure specs
        $specs = array_filter([
            'cpu' => $validated['specs_cpu'] ?? null,
            'ram' => $validated['specs_ram'] ?? null,
            'storage' => $validated['specs_storage'] ?? null,
            'os' => $validated['specs_os'] ?? null,
            'ip' => $validated['specs_ip'] ?? null,
        ]);

        // If shared ownership, nullify assigned user
        if ($validated['ownership_type'] === 'shared') {
            $validated['assigned_user_id'] = null;
        }

        $asset = Asset::create([
            'asset_tag' => strtoupper($validated['asset_tag']),
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'ownership_type' => $validated['ownership_type'],
            'branch_id' => $validated['branch_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'location_details' => $validated['location_details'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
            'specs' => ! empty($specs) ? $specs : null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => $validated['purchase_cost'] ?? null,
            'warranty_expiry' => $validated['warranty_expiry'] ?? null,
            'vendor_supplier' => $validated['vendor_supplier'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('portal.assets.show', $asset->id)
            ->with('status', "Asset '{$asset->asset_tag}' registered successfully.");
    }

    /**
     * Show Asset Details & Full Service/Repair History.
     */
    public function show(Asset $asset): View
    {
        $asset->load([
            'category',
            'branch',
            'department',
            'assignedUser',
            'components',
            'tickets.technician',
            'tickets.category',
            'ticketParts.component',
            'ticketParts.recordedBy',
        ]);

        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        // Spare parts in stock for this branch or HQ to allow 1-click install
        $availableSpares = AssetComponent::where('status', 'in_stock')
            ->orderBy('component_type')
            ->orderBy('brand_model')
            ->get();

        return view('portal.assets.show', compact(
            'asset',
            'categories',
            'branches',
            'departments',
            'users',
            'availableSpares'
        ));
    }

    /**
     * Update hardware asset profile.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'asset_tag' => "required|string|max:50|unique:assets,asset_tag,{$asset->id}",
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:asset_categories,id',
            'ownership_type' => 'required|in:personal,shared',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'location_details' => 'nullable|string|max:150',
            'assigned_user_id' => 'nullable|exists:users,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'status' => 'required|in:in_use,spare_stock,under_repair,decommissioned',
            'specs_cpu' => 'nullable|string|max:100',
            'specs_ram' => 'nullable|string|max:100',
            'specs_storage' => 'nullable|string|max:100',
            'specs_os' => 'nullable|string|max:100',
            'specs_ip' => 'nullable|string|max:50',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'vendor_supplier' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $specs = array_filter([
            'cpu' => $validated['specs_cpu'] ?? null,
            'ram' => $validated['specs_ram'] ?? null,
            'storage' => $validated['specs_storage'] ?? null,
            'os' => $validated['specs_os'] ?? null,
            'ip' => $validated['specs_ip'] ?? null,
        ]);

        if ($validated['ownership_type'] === 'shared') {
            $validated['assigned_user_id'] = null;
        }

        $asset->update([
            'asset_tag' => strtoupper($validated['asset_tag']),
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'ownership_type' => $validated['ownership_type'],
            'branch_id' => $validated['branch_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'location_details' => $validated['location_details'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
            'specs' => ! empty($specs) ? $specs : null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => $validated['purchase_cost'] ?? null,
            'warranty_expiry' => $validated['warranty_expiry'] ?? null,
            'vendor_supplier' => $validated['vendor_supplier'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', "Asset '{$asset->asset_tag}' updated successfully.");
    }

    /**
     * Delete or retire an asset.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        $tag = $asset->asset_tag;
        $asset->delete();

        return redirect()->route('portal.assets.index')
            ->with('status', "Asset '{$tag}' has been removed.");
    }

    /**
     * Install a component (RAM, SSD, etc.) into an asset.
     */
    public function storeComponent(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'source_mode' => 'required|in:new,from_stock',
            'spare_component_id' => 'nullable|exists:asset_components,id',
            'component_type' => 'nullable|in:ram,ssd,hdd,psu,gpu,display,cable,other',
            'brand_model' => 'nullable|string|max:150',
            'capacity_or_specs' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validated['source_mode'] === 'from_stock' && ! empty($validated['spare_component_id'])) {
            $comp = AssetComponent::findOrFail($validated['spare_component_id']);
            $comp->update([
                'asset_id' => $asset->id,
                'status' => 'installed',
                'installed_at' => now(),
            ]);
            $msg = "Component '{$comp->brand_model}' assigned to asset.";
        } else {
            AssetComponent::create([
                'asset_id' => $asset->id,
                'branch_id' => $asset->branch_id,
                'component_type' => $validated['component_type'] ?? 'ram',
                'brand_model' => $validated['brand_model'] ?? 'Component',
                'capacity_or_specs' => $validated['capacity_or_specs'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'status' => 'installed',
                'installed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            $msg = 'New component installed into asset.';
        }

        return back()->with('status', $msg);
    }

    /**
     * Remove / Extract a component from an asset.
     */
    public function destroyComponent(Request $request, Asset $asset, AssetComponent $component): RedirectResponse
    {
        $action = $request->input('action', 'return_to_stock'); // return_to_stock, mark_faulty, dispose

        if ($action === 'return_to_stock') {
            $component->update([
                'asset_id' => null,
                'status' => 'in_stock',
                'installed_at' => null,
                'notes' => 'Extracted from '.$asset->asset_tag.' on '.now()->toDateString(),
            ]);
            $msg = 'Component returned to IT Spare Stock.';
        } elseif ($action === 'mark_faulty') {
            $component->update([
                'asset_id' => null,
                'status' => 'faulty',
                'installed_at' => null,
                'notes' => 'Marked faulty from '.$asset->asset_tag.' on '.now()->toDateString(),
            ]);
            $msg = 'Component marked as faulty.';
        } else {
            $component->delete();
            $msg = 'Component removed.';
        }

        return back()->with('status', $msg);
    }

    /**
     * Add a loose spare part directly into IT Spare Storage.
     */
    public function storeSpareComponent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'component_type' => 'required|in:ram,ssd,hdd,psu,gpu,display,cable,other',
            'brand_model' => 'required|string|max:150',
            'capacity_or_specs' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'quantity' => 'nullable|integer|min:1|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);
        for ($i = 0; $i < $quantity; $i++) {
            AssetComponent::create([
                'asset_id' => null, // in storage
                'branch_id' => $validated['branch_id'] ?? null,
                'component_type' => $validated['component_type'],
                'brand_model' => $validated['brand_model'],
                'capacity_or_specs' => $validated['capacity_or_specs'] ?? null,
                'serial_number' => ($quantity === 1) ? ($validated['serial_number'] ?? null) : null,
                'status' => 'in_stock',
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return redirect()->route('portal.assets.index', ['tab' => 'components'])
            ->with('status', "Added {$quantity}x spare component(s) to IT Storage.");
    }
}
