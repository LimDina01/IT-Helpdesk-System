@extends('portal.layout')

@section('title', 'IT Assets & Inventory Management')

@section('content')
<div x-data="{ 
    createAssetModal: false, 
    createSpareModal: false,
    assetOwnership: 'personal',
    currentTab: '{{ $currentTab }}'
}" class="space-y-6">

    <!-- Top Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-blue-600 font-extrabold uppercase tracking-wider flex items-center space-x-1.5">
                <span>🖥️</span>
                <span>IT Operations &bull; Asset &amp; Inventory Management</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">IT Assets, Equipment &amp; Spare Stock</h1>
            <p class="text-xs text-slate-500 mt-0.5">Track workstations, meeting room projectors, shared printers, retail POS, and spare components (RAM, SSDs, PSUs).</p>
        </div>

        <div class="flex items-center space-x-2.5">
            <button type="button" @click="createSpareModal = true" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <span>⚙️</span>
                <span>+ Add Spare Stock</span>
            </button>
            <button type="button" @click="createAssetModal = true" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <span>🖥️</span>
                <span>+ Register New Asset</span>
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Hardware</div>
            <div class="text-xl font-black text-slate-900 mt-1">{{ $totalAssetsCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Registered machines</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">In Active Use</div>
            <div class="text-xl font-black text-emerald-700 mt-1">{{ $inUseCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Deployed to staff/floors</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-purple-600 uppercase tracking-wider">Shared / Rooms</div>
            <div class="text-xl font-black text-purple-700 mt-1">{{ $sharedLocationsCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Meeting rooms &amp; floors</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Spare Hardware</div>
            <div class="text-xl font-black text-blue-700 mt-1">{{ $spareAssetsCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Ready for deployment</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Past EOD (Review)</div>
            <div class="text-xl font-black text-amber-700 mt-1">{{ $pastEodCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Due for overhaul/budget</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="text-[10px] font-bold text-slate-600 uppercase tracking-wider">Loose Spare Stock</div>
            <div class="text-xl font-black text-slate-800 mt-1">{{ $spareComponentsCount }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">RAM, SSDs, PSUs</div>
        </div>
    </div>

    <!-- Segmented Navigation Tabs -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <a href="{{ route('portal.assets.index', ['tab' => 'assets']) }}" 
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 {{ $currentTab === 'assets' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <span>🖥️</span>
                <span>Hardware Assets</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-black {{ $currentTab === 'assets' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">
                    {{ $totalAssetsCount }}
                </span>
            </a>

            <a href="{{ route('portal.assets.index', ['tab' => 'components']) }}" 
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 {{ $currentTab === 'components' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <span>⚙️</span>
                <span>Spare Parts &amp; Components</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-black {{ $currentTab === 'components' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">
                    {{ $spareComponentsCount }} in stock
                </span>
            </a>
        </div>
    </div>

    @if($currentTab === 'assets')
        <!-- TAB 1: HARDWARE ASSETS LIST -->
        <div class="space-y-4">
            <!-- Filter & Search Toolbar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <form method="GET" action="{{ route('portal.assets.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-2.5">
                    <input type="hidden" name="tab" value="assets">
                    @if(isset($perPage) && $perPage != 10)
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                    @endif

                    <!-- Search Input -->
                    <div class="lg:col-span-2 relative">
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search tag, name, serial, model... (Enter)"
                               class="w-full text-xs pl-8 pr-7 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs">
                        <span class="absolute left-2.5 top-2.5 text-slate-400 text-xs">🔍</span>
                        @if(!empty($search) || !empty($lifecycle))
                            <a href="{{ route('portal.assets.index', array_filter(['tab' => 'assets', 'category_id' => $categoryId, 'branch_id' => $branchId, 'department_id' => $departmentId, 'ownership_type' => $ownershipType, 'status' => $status])) }}"
                               class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600 font-bold" title="Clear">✕</a>
                        @endif
                    </div>

                    <!-- Category Filter -->
                    <select name="category_id" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Categories ({{ $categories->count() }})</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <!-- Branch Filter -->
                    <select name="branch_id" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Branches ({{ $branches->count() }})</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>📍 {{ $b->name }}</option>
                        @endforeach
                    </select>

                    <!-- Ownership Type (Personal vs Shared) -->
                    <select name="ownership_type" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Types (Personal &amp; Shared)</option>
                        <option value="personal" {{ $ownershipType === 'personal' ? 'selected' : '' }}>👤 Personal (Staff)</option>
                        <option value="shared" {{ $ownershipType === 'shared' ? 'selected' : '' }}>🏢 Shared (Area/Room)</option>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Statuses</option>
                        <option value="in_use" {{ $status === 'in_use' ? 'selected' : '' }}>🟢 In Use</option>
                        <option value="spare_stock" {{ $status === 'spare_stock' ? 'selected' : '' }}>🔵 Spare Stock</option>
                        <option value="under_repair" {{ $status === 'under_repair' ? 'selected' : '' }}>🟡 Under Repair</option>
                        <option value="decommissioned" {{ $status === 'decommissioned' ? 'selected' : '' }}>⚪ Decommissioned</option>
                    </select>

                    <!-- Lifecycle Filter (EOD / EOL / Warranty) -->
                    <select name="lifecycle" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Lifecycles</option>
                        <option value="past_eod" {{ $lifecycle === 'past_eod' ? 'selected' : '' }}>⚠️ Past EOD (Due for Review)</option>
                        <option value="past_eol" {{ $lifecycle === 'past_eol' ? 'selected' : '' }}>🛑 End of Life (EOL)</option>
                        <option value="expiring_warranty" {{ $lifecycle === 'expiring_warranty' ? 'selected' : '' }}>🛡️ Warranty Expiring (&lt;60d)</option>
                        <option value="under_warranty" {{ $lifecycle === 'under_warranty' ? 'selected' : '' }}>🟢 Under Active Warranty</option>
                        <option value="out_of_warranty" {{ $lifecycle === 'out_of_warranty' ? 'selected' : '' }}>❌ Out of Warranty</option>
                    </select>
                </form>
            </div>

            <!-- Assets Table Card -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-5">Asset Tag &amp; Name</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Location &amp; Custody</th>
                                <th class="py-3 px-4">Hardware Specs</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Repair History</th>
                                <th class="py-3 px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($assets as $asset)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-700 border border-blue-200/60 flex items-center justify-center text-sm font-black shrink-0">
                                                {{ $asset->category?->icon ?? '🖥️' }}
                                            </div>
                                            <div>
                                                <a href="{{ route('portal.assets.show', $asset->id) }}" class="font-black text-slate-900 hover:text-blue-600 transition">
                                                    {{ $asset->asset_tag }}
                                                </a>
                                                <div class="text-[11px] text-slate-500 truncate max-w-xs font-medium">{{ $asset->name }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 font-semibold text-slate-800">
                                        {{ $asset->category?->name ?? 'General' }}
                                    </td>

                                    <td class="py-3.5 px-4">
                                        @if($asset->status === 'spare_stock')
                                            <div class="flex items-center space-x-1.5 text-blue-700 font-bold text-[11px]">
                                                <span>📦</span>
                                                <span>IT Spare Inventory</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 font-medium">
                                                📍 {{ $asset->branch?->name ?? 'HQ Phnom Penh' }}
                                                @if($asset->location_details)
                                                    &bull; <span class="italic text-slate-400">{{ $asset->location_details }}</span>
                                                @endif
                                            </div>
                                        @elseif($asset->status === 'decommissioned')
                                            <div class="flex items-center space-x-1.5 text-slate-500 font-bold text-[11px]">
                                                <span>⚪</span>
                                                <span>Retired / Scrapped</span>
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                📍 {{ $asset->branch?->name ?? 'HQ' }}
                                            </div>
                                        @elseif($asset->ownership_type === 'shared' || empty($asset->assigned_user_id))
                                            <div class="flex items-center space-x-1.5 text-purple-700 font-bold text-[11px]">
                                                <span>🏢</span>
                                                <span>Shared / Room</span>
                                            </div>
                                            <div class="text-[11px] text-slate-500 font-medium">
                                                {{ $asset->location_details ?: ($asset->branch?->name ?? 'HQ / Common Area') }}
                                            </div>
                                        @else
                                            <div class="flex items-center space-x-1.5 text-blue-700 font-bold text-[11px]">
                                                <span>👤</span>
                                                <span>{{ $asset->assignedUser?->name ?? 'Assigned Employee' }}</span>
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                {{ $asset->branch?->name ?? 'HQ' }} &bull; {{ $asset->department?->name ?? 'General' }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <div class="text-[11px] font-semibold text-slate-800">
                                            {{ $asset->brand }} {{ $asset->model }}
                                        </div>
                                        @if(!empty($asset->specs))
                                            <div class="text-[10px] text-slate-500 font-mono">
                                                {{ implode(' • ', array_filter([$asset->specs['cpu'] ?? null, $asset->specs['ram'] ?? null, $asset->specs['storage'] ?? null])) }}
                                            </div>
                                        @elseif(!empty($asset->serial_number))
                                            <div class="text-[10px] text-slate-400 font-mono">SN: {{ $asset->serial_number }}</div>
                                        @endif
                                    </td>

                                    <td class="py-3.5 px-4">
                                        @php
                                            $badge = $asset->status_badge;
                                            $life = $asset->lifecycle_status;
                                        @endphp
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border {{ $badge['class'] }}">
                                                {{ $badge['label'] }}
                                            </span>
                                            <div>
                                                <span class="inline-flex items-center space-x-1 text-[9px] font-bold px-2 py-0.5 rounded-md border {{ $life['class'] }}" title="{{ $life['description'] }}">
                                                    <span>{{ $life['icon'] }}</span>
                                                    <span>{{ $life['label'] }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        @php $ticketCount = $asset->tickets->count(); @endphp
                                        @if($ticketCount > 0)
                                            <a href="{{ route('portal.assets.show', $asset->id) }}" class="inline-flex items-center space-x-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                                <span>🎫</span>
                                                <span>{{ $ticketCount }} {{ Str::plural('ticket', $ticketCount) }}</span>
                                            </a>
                                        @else
                                            <span class="text-[11px] text-slate-400">No issues</span>
                                        @endif
                                    </td>

                                    <td class="py-3.5 px-5 text-right font-semibold">
                                        <a href="{{ route('portal.assets.show', $asset->id) }}" class="text-blue-600 hover:text-blue-800 font-bold bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-xl transition inline-flex items-center space-x-1">
                                            <span>Details &amp; History &rarr;</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <div class="text-3xl mb-2">🖥️</div>
                                        <div class="font-bold text-slate-700">No hardware assets found</div>
                                        <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or click "+ Register New Asset" above.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="text-xs text-slate-500 font-medium">
                        Showing <span class="font-bold text-slate-700">{{ $assets->firstItem() ?? 0 }}</span> to <span class="font-bold text-slate-700">{{ $assets->lastItem() ?? 0 }}</span> of <span class="font-bold text-slate-700">{{ $assets->total() }}</span> assets
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-1.5 text-xs text-slate-500">
                            <span class="text-[11px] text-slate-400 font-semibold">Per page:</span>
                            @foreach([10, 25, 50, 100] as $size)
                                <a href="{{ request()->fullUrlWithQuery(['per_page' => $size, 'assets_page' => 1]) }}"
                                   class="px-2 py-0.5 rounded-md text-[11px] font-bold transition {{ ($perPage ?? 10) == $size ? 'bg-blue-600 text-white' : 'bg-slate-200/70 hover:bg-slate-200 text-slate-600' }}">
                                    {{ $size }}
                                </a>
                            @endforeach
                        </div>

                        @if($assets->hasPages())
                            <div class="shrink-0">
                                {{ $assets->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @else
        <!-- TAB 2: SPARE PARTS & COMPONENTS INVENTORY -->
        <div class="space-y-4">
            <!-- Filter & Search Toolbar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <form method="GET" action="{{ route('portal.assets.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <input type="hidden" name="tab" value="components">
                    @if(isset($perPage) && $perPage != 10)
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                    @endif

                    <div class="relative">
                        <input type="text"
                               name="comp_search"
                               value="{{ $compSearch }}"
                               placeholder="Search component, brand, specs... (Enter)"
                               class="w-full text-xs pl-8 pr-7 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 shadow-2xs">
                        <span class="absolute left-2.5 top-2.5 text-slate-400 text-xs">🔍</span>
                        @if(!empty($compSearch))
                            <a href="{{ route('portal.assets.index', ['tab' => 'components']) }}" class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600 font-bold">✕</a>
                        @endif
                    </div>

                    <select name="comp_type" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Component Types</option>
                        <option value="ram" {{ $compType === 'ram' ? 'selected' : '' }}>🧠 RAM Memory</option>
                        <option value="ssd" {{ $compType === 'ssd' ? 'selected' : '' }}>💾 SSD Storage</option>
                        <option value="hdd" {{ $compType === 'hdd' ? 'selected' : '' }}>💽 Hard Disk (HDD)</option>
                        <option value="psu" {{ $compType === 'psu' ? 'selected' : '' }}>⚡ Power Supply (PSU)</option>
                        <option value="gpu" {{ $compType === 'gpu' ? 'selected' : '' }}>🎮 Graphics Card (GPU)</option>
                        <option value="cable" {{ $compType === 'cable' ? 'selected' : '' }}>🔌 Cable / Adapter</option>
                        <option value="other" {{ $compType === 'other' ? 'selected' : '' }}>⚙️ Other Parts</option>
                    </select>

                    <select name="comp_status" onchange="this.form.submit()" class="text-xs font-semibold px-2.5 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white text-slate-700">
                        <option value="">All Statuses</option>
                        <option value="in_stock" {{ $compStatus === 'in_stock' ? 'selected' : '' }}>🔵 In IT Spare Stock</option>
                        <option value="installed" {{ $compStatus === 'installed' ? 'selected' : '' }}>🟢 Installed in PC</option>
                        <option value="faulty" {{ $compStatus === 'faulty' ? 'selected' : '' }}>🔴 Faulty / Defective</option>
                    </select>
                </form>
            </div>

            <!-- Components Table Card -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-5">Component Type &amp; Model</th>
                                <th class="py-3 px-4">Capacity / Specs</th>
                                <th class="py-3 px-4">Serial Number</th>
                                <th class="py-3 px-4">Current Location / Assigned PC</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Stock Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($components as $comp)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-5 font-bold text-slate-900">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm">
                                                {{ match($comp->component_type) { 'ram' => '🧠', 'ssd' => '💾', 'hdd' => '💽', 'psu' => '⚡', 'gpu' => '🎮', 'cable' => '🔌', default => '⚙️' } }}
                                            </span>
                                            <div>
                                                <div>{{ $comp->brand_model }}</div>
                                                <div class="text-[10px] text-slate-400 font-normal">{{ $comp->type_label }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 font-mono font-semibold text-slate-800">
                                        {{ $comp->capacity_or_specs ?: '—' }}
                                    </td>

                                    <td class="py-3.5 px-4 font-mono text-slate-500 text-[11px]">
                                        {{ $comp->serial_number ?: '—' }}
                                    </td>

                                    <td class="py-3.5 px-4">
                                        @if($comp->asset)
                                            <a href="{{ route('portal.assets.show', $comp->asset_id) }}" class="inline-flex items-center space-x-1.5 font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                                <span>🖥️</span>
                                                <span>{{ $comp->asset->asset_tag }} ({{ $comp->asset->name }})</span>
                                            </a>
                                        @else
                                            <div class="flex items-center space-x-1.5 text-blue-700 font-bold text-[11px]">
                                                <span>📦</span>
                                                <span>In IT Storage ({{ $comp->branch?->name ?? 'HQ IT Storage' }})</span>
                                            </div>
                                        @endif
                                    </td>

                                    <td class="py-3.5 px-4">
                                        @php $badge = $comp->status_badge; @endphp
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border {{ $badge['class'] }}">
                                            {{ $badge['label'] }}
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                                        {{ $comp->notes ?: '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        <div class="text-3xl mb-2">⚙️</div>
                                        <div class="font-bold text-slate-700">No spare components recorded</div>
                                        <p class="text-xs text-slate-400 mt-1">Click "+ Add Spare Stock" to record loose RAM, SSDs, or power supplies.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="text-xs text-slate-500 font-medium">
                        Showing <span class="font-bold text-slate-700">{{ $components->firstItem() ?? 0 }}</span> to <span class="font-bold text-slate-700">{{ $components->lastItem() ?? 0 }}</span> of <span class="font-bold text-slate-700">{{ $components->total() }}</span> components
                    </div>

                    @if($components->hasPages())
                        <div class="shrink-0">
                            {{ $components->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- ======================================================== -->
    <!-- MODAL 1: REGISTER NEW HARDWARE ASSET                      -->
    <!-- ======================================================== -->
    <div x-show="createAssetModal" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-4" 
         x-cloak>
        <div @click.away="createAssetModal = false" class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
            <!-- Fixed Pinned Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80 shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-bold shadow-2xs">
                        🖥️
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Register New Hardware Asset</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Record a workstation, meeting room projector, printer, or retail POS.</p>
                    </div>
                </div>
                <button type="button" @click="createAssetModal = false" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm font-bold" title="Close">&times;</button>
            </div>

            <!-- Scrollable Body with Form -->
            <form method="POST" action="{{ route('portal.assets.store') }}" class="flex flex-col flex-1 overflow-hidden">
                @csrf

                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4 text-xs">
                    <!-- Ownership Choice (Personal vs Shared Location) -->
                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase">Who or Where Does This Equipment Belong?</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center space-x-2 p-2.5 bg-white rounded-xl border border-slate-200 hover:border-blue-400 cursor-pointer transition shadow-2xs">
                                <input type="radio" name="ownership_type" value="personal" x-model="assetOwnership" class="rounded text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">👤 Personal Custody</div>
                                    <div class="text-[10px] text-slate-400">Assigned to a specific employee</div>
                                </div>
                            </label>
                            <label class="flex items-center space-x-2 p-2.5 bg-white rounded-xl border border-slate-200 hover:border-blue-400 cursor-pointer transition shadow-2xs">
                                <input type="radio" name="ownership_type" value="shared" x-model="assetOwnership" class="rounded text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">🏢 Shared Room / Floor</div>
                                    <div class="text-[10px] text-slate-400">Meeting room, printing floor, POS counter</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Asset Tag / Code</label>
                            <input type="text" name="asset_tag" placeholder="e.g. CBVH-PC-0042 (Leave blank to auto-generate)" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Category <span class="text-rose-500">*</span></label>
                            <select name="category_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->icon }} {{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Asset Name / Description <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required placeholder="e.g. HQ Floor 3 - Meeting Room A Slide Projector or Accounting PC" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                    </div>

                    <!-- Conditional Ownership Fields -->
                    <div x-show="assetOwnership === 'personal'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-blue-50/60 rounded-xl border border-blue-100">
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-blue-900 uppercase mb-1">Assigned Employee (Custodian)</label>
                            <select name="assigned_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">-- Choose Employee --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Branch Location</label>
                            <select name="branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">-- Select Branch --</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">📍 {{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Department</label>
                            <select name="department_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">-- Select Department --</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Specific Room / Floor / Desk</label>
                            <input type="text" name="location_details" placeholder="e.g. Floor 3, Meeting Room 2" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Hardware Specifications -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Brand</label>
                            <input type="text" name="brand" placeholder="e.g. Dell, Epson, ViewSonic" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Model</label>
                            <input type="text" name="model" placeholder="e.g. OptiPlex 7090" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Serial Number</label>
                            <input type="text" name="serial_number" placeholder="e.g. SN-897362" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">CPU Processor</label>
                            <input type="text" name="specs_cpu" placeholder="e.g. i7-11700" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">RAM Capacity</label>
                            <input type="text" name="specs_ram" placeholder="e.g. 16GB DDR4" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Storage / SSD</label>
                            <input type="text" name="specs_storage" placeholder="e.g. 512GB NVMe SSD" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Operating System</label>
                            <input type="text" name="specs_os" placeholder="e.g. Windows 11 Pro" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                            <select name="status" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                                <option value="in_use">🟢 In Use / Active</option>
                                <option value="spare_stock">🔵 Spare in Stock</option>
                                <option value="under_repair">🟡 Under Repair / RMA</option>
                                <option value="decommissioned">⚪ Decommissioned</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Purchase Date</label>
                            <input type="date" name="purchase_date" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Purchase Cost (USD)</label>
                            <input type="number" step="0.01" min="0" name="purchase_cost" placeholder="e.g. 850.00" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Lifecycle & Warranty Dates -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-amber-50/50 rounded-2xl border border-amber-200/70">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-800 uppercase mb-0.5">Warranty Expiry (EOW)</label>
                            <span class="text-[10px] text-slate-500 block mb-1">Free supplier repair end</span>
                            <input type="date" name="warranty_expiry" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-amber-900 uppercase mb-0.5">EOD Date (Depreciation)</label>
                            <span class="text-[10px] text-amber-700 block mb-1">Recommended overhaul / $0 book</span>
                            <input type="date" name="eod_date" class="w-full text-xs px-3 py-2 rounded-xl border border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-rose-900 uppercase mb-0.5">EOL Date (End of Life)</label>
                            <span class="text-[10px] text-rose-700 block mb-1">Obsolescence / retirement target</span>
                            <input type="date" name="eol_date" class="w-full text-xs px-3 py-2 rounded-xl border border-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-500 bg-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Supplier / Vendor</label>
                        <input type="text" name="vendor_supplier" placeholder="e.g. Anana Computer, Epson Cambodia" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Technical Notes</label>
                        <textarea name="notes" rows="2" placeholder="e.g. Connected to network port #12 in floor switch rack" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>

                <!-- Fixed Pinned Footer -->
                <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-200/80 bg-slate-50/95 shrink-0">
                    <div class="text-[11px] text-slate-500 hidden sm:flex items-center space-x-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>Asset tag will be auto-generated if left empty.</span>
                    </div>
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button type="button" @click="createAssetModal = false" class="px-4 py-2.5 text-xs text-slate-600 font-bold hover:bg-slate-200/70 rounded-xl transition cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer flex items-center space-x-1.5">
                            <span>✨</span>
                            <span>Save &amp; Register Asset</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: ADD SPARE PARTS / COMPONENTS TO STORAGE          -->
    <!-- ======================================================== -->
    <div x-show="createSpareModal" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:leave="transition ease-in duration-150" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto" 
         x-cloak>
        <div @click.away="createSpareModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                        ⚙️
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Add Spare Parts to IT Storage</h3>
                        <p class="text-[11px] text-slate-500">Record loose RAM sticks, SSDs, power supplies, or cables in stock.</p>
                    </div>
                </div>
                <button type="button" @click="createSpareModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('portal.assets.spares.store') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Component Type <span class="text-rose-500">*</span></label>
                        <select name="component_type" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white font-medium">
                            <option value="ram">🧠 RAM Memory</option>
                            <option value="ssd">💾 SSD Storage</option>
                            <option value="hdd">💽 Hard Disk Drive (HDD)</option>
                            <option value="psu">⚡ Power Supply (PSU)</option>
                            <option value="gpu">🎮 Graphics Card (GPU)</option>
                            <option value="cable">🔌 Cable / Adapter (HDMI, LAN)</option>
                            <option value="other">⚙️ Other Spare Part</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Storage Location Branch</label>
                        <select name="branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white font-medium">
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">📍 {{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Brand &amp; Model <span class="text-rose-500">*</span></label>
                    <input type="text" name="brand_model" required placeholder="e.g. Kingston Fury Beast DDR4 or Samsung 980 NVMe" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Capacity / Specs</label>
                        <input type="text" name="capacity_or_specs" placeholder="e.g. 8GB 3200MHz, 500GB, 550W" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Quantity in Stock</label>
                        <input type="number" name="quantity" min="1" max="50" value="1" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 font-bold">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Serial Number (If single item)</label>
                    <input type="text" name="serial_number" placeholder="Optional for batch items" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Storage Notes</label>
                    <input type="text" name="notes" placeholder="e.g. Shelf B, IT Server Room" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createSpareModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Add to IT Stock</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

