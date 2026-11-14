@extends('portal.layout')

@section('title', "Asset {$asset->asset_tag} - {$asset->name}")

@section('content')
<div x-data="{ 
    editAssetModal: false, 
    installCompModal: false,
    assetOwnership: '{{ $asset->ownership_type }}',
    assetStatus: '{{ $asset->status }}',
    compSourceMode: 'from_stock'
}" class="space-y-6">

    <!-- Top Breadcrumb & Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-blue-600 font-extrabold uppercase tracking-wider flex items-center space-x-1.5">
                <a href="{{ route('portal.assets.index') }}" class="hover:underline flex items-center space-x-1">
                    <span>🖥️</span>
                    <span>IT Assets &amp; Inventory</span>
                </a>
                <span>&rsaquo;</span>
                <span class="font-mono text-slate-800">{{ $asset->asset_tag }}</span>
            </div>
            <div class="flex items-center space-x-3 mt-1.5">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">{{ $asset->name }}</h1>
                @php $badge = $asset->status_badge; @endphp
                <span class="text-xs font-extrabold px-3 py-1 rounded-full border {{ $badge['class'] }}">
                    {{ $badge['label'] }}
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                <span class="flex items-center space-x-1">
                    <span>{{ $asset->category?->icon ?? '🖥️' }}</span>
                    <span class="font-semibold text-slate-700">{{ $asset->category?->name ?? 'Hardware' }}</span>
                </span>
                <span>&bull;</span>
                <span class="font-mono text-slate-600">Tag: {{ $asset->asset_tag }}</span>
                @if($asset->serial_number)
                    <span>&bull;</span>
                    <span class="font-mono text-slate-500">Serial: {{ $asset->serial_number }}</span>
                @endif
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <button type="button" @click="editAssetModal = true" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <span>✏️</span>
                <span>Edit Asset</span>
            </button>
            <form method="POST" action="{{ route('portal.assets.destroy', $asset->id) }}" onsubmit="return confirm('Are you sure you want to delete asset {{ $asset->asset_tag }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 font-bold text-xs px-3.5 py-2.5 rounded-xl transition flex items-center space-x-1 cursor-pointer" title="Delete Asset">
                    <span>🗑️</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Lemon Detection Alert Banner (If 3+ repair tickets recently) -->
    @php
        $recentTicketsCount = $asset->tickets->where('created_at', '>=', now()->subDays(90))->count();
    @endphp
    @if($recentTicketsCount >= 3)
        <div class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 text-xs text-amber-900 flex items-start space-x-3 shadow-2xs">
            <span class="text-xl">⚠️</span>
            <div>
                <div class="font-bold">Frequent Breakdown Alert ("Lemon Detection")</div>
                <div class="text-amber-700 mt-0.5">
                    This equipment has had <strong>{{ $recentTicketsCount }} repair tickets</strong> in the last 90 days. Frequent component failures indicate it may be reaching end-of-life or requires comprehensive inspection.
                </div>
            </div>
        </div>
    @endif

    <!-- Lifecycle Degradation & EOL Alert Banners -->
    @if($asset->status !== 'decommissioned')
        @if($asset->isPastEol())
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 text-xs text-rose-900 flex items-start space-x-3 shadow-2xs">
                <span class="text-xl">🛑</span>
                <div>
                    <div class="font-bold">Hardware End of Life (EOL) Reached</div>
                    <div class="text-rose-700 mt-0.5">
                        This equipment reached its designated End of Life on <strong>{{ $asset->eol_date->format('d-M-Y') }}</strong>. It is now considered obsolete, carries high risk of unrepairable component failure, and should be scheduled for formal decommissioning or replacement.
                    </div>
                </div>
            </div>
        @elseif($asset->isPastEod())
            <div class="bg-amber-50 border border-amber-300/80 rounded-2xl p-4 text-xs text-amber-950 flex items-start space-x-3 shadow-2xs">
                <span class="text-xl">⚠️</span>
                <div>
                    <div class="font-bold text-amber-900">End of Depreciation (EOD) / Recommended Useful Duty Life Reached</div>
                    <div class="text-amber-800 mt-0.5">
                        This machine passed its End of Depreciation threshold on <strong>{{ $asset->eod_date->format('d-M-Y') }}</strong> (financial book value is $0). While the device may still operate (e.g. projector bulb, older PC), performance degradation is expected. Recommend scheduling maintenance, bulb/part overhaul, or planning budget replacement.
                    </div>
                </div>
            </div>
        @elseif($asset->warranty_expiry && $asset->warranty_expiry->isFuture() && now()->diffInDays($asset->warranty_expiry) <= 60)
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-xs text-blue-900 flex items-start space-x-3 shadow-2xs">
                <span class="text-xl">🛡️</span>
                <div>
                    <div class="font-bold">Supplier Warranty Expiring Soon</div>
                    <div class="text-blue-700 mt-0.5">
                        Free vendor repair warranty ends in <strong>{{ now()->diffInDays($asset->warranty_expiry) }} days</strong> ({{ $asset->warranty_expiry->format('d-M-Y') }}). Inspect machine for any hardware flaws to claim manufacturer warranty repair before expiration.
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- 3-Column Asset Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 1. Hardware Specifications -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center space-x-1.5">
                    <span>⚙️</span>
                    <span>Hardware Specifications</span>
                </div>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Brand / Model</span>
                    <span class="font-bold text-slate-800">{{ $asset->brand ?: '—' }} {{ $asset->model }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Serial Number</span>
                    <span class="font-mono font-bold text-slate-800">{{ $asset->serial_number ?: '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">CPU Processor</span>
                    <span class="font-semibold text-slate-800">{{ $asset->specs['cpu'] ?? '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">RAM Memory</span>
                    <span class="font-semibold text-slate-800">{{ $asset->specs['ram'] ?? '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Primary Storage</span>
                    <span class="font-semibold text-slate-800">{{ $asset->specs['storage'] ?? '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Operating System</span>
                    <span class="font-semibold text-slate-800">{{ $asset->specs['os'] ?? '—' }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">IP Address</span>
                    <span class="font-mono text-slate-600">{{ $asset->specs['ip'] ?? 'DHCP / Dynamic' }}</span>
                </div>
            </div>
        </div>

        <!-- 2. Location & Custody -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center space-x-1.5">
                    <span>📍</span>
                    <span>Location &amp; Custody</span>
                </div>
                @if($asset->status === 'spare_stock')
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200">📦 Spare Stock</span>
                @elseif($asset->status === 'under_repair')
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200">🟡 Under Repair</span>
                @elseif($asset->status === 'decommissioned')
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-300">⚪ Decommissioned</span>
                @elseif($asset->ownership_type === 'shared' || empty($asset->assigned_user_id))
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200">Shared / Room</span>
                @else
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">Personal Device</span>
                @endif
            </div>

            <div class="space-y-2 text-xs">
                @if($asset->status === 'spare_stock')
                    <div class="p-3 bg-blue-50/70 rounded-xl border border-blue-100">
                        <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Storage &amp; Backup Status</div>
                        <div class="text-xs font-bold text-slate-900 mt-0.5">📦 IT Shelf / Spare Stock</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Unassigned backup machine ready for deployment or hot-swap replacement.</div>
                    </div>
                @elseif($asset->ownership_type === 'shared' || empty($asset->assigned_user_id))
                    <div class="p-3 bg-purple-50/70 rounded-xl border border-purple-100">
                        <div class="text-[10px] font-bold text-purple-700 uppercase tracking-wider">Shared Location / Area</div>
                        <div class="text-xs font-bold text-slate-900 mt-0.5">{{ $asset->location_details ?: 'Meeting Room / Common Floor' }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Used by anyone in this meeting room or floor.</div>
                    </div>
                @else
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Custodian (User)</span>
                        <span class="font-bold text-blue-700">{{ $asset->assignedUser?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Corporate Email</span>
                        <span class="text-slate-600">{{ $asset->assignedUser?->email ?? '—' }}</span>
                    </div>
                @endif

                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Branch Location</span>
                    <span class="font-bold text-slate-800">📍 {{ $asset->branch?->name ?? 'HQ Phnom Penh' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Department</span>
                    <span class="font-semibold text-slate-800">{{ $asset->department?->name ?? 'Company-Wide' }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Room / Shelf Details</span>
                    <span class="text-slate-700 font-medium">{{ $asset->location_details ?: '—' }}</span>
                </div>
            </div>
        </div>

        <!-- 3. Purchase & Lifecycle Information -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center space-x-1.5">
                    <span>🧾</span>
                    <span>Purchase &amp; Lifecycle</span>
                </div>
                @php $life = $asset->lifecycle_status; @endphp
                <span class="inline-flex items-center space-x-1 text-[10px] font-extrabold px-2 py-0.5 rounded-md border {{ $life['class'] }}">
                    <span>{{ $life['icon'] }}</span>
                    <span>{{ $life['label'] }}</span>
                </span>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Purchase Date</span>
                    <span class="font-semibold text-slate-800">{{ $asset->purchase_date ? $asset->purchase_date->format('d-M-Y') : '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Purchase Cost</span>
                    <span class="font-semibold text-slate-800">{{ $asset->purchase_cost ? '$' . number_format($asset->purchase_cost, 2) : '—' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Warranty Expiry (EOW)</span>
                    <span class="font-semibold text-slate-800">
                        @if($asset->warranty_expiry)
                            {{ $asset->warranty_expiry->format('d-M-Y') }}
                            @if($asset->warranty_expiry->isPast())
                                <span class="text-[10px] text-rose-600 font-bold ml-1">(Expired)</span>
                            @else
                                <span class="text-[10px] text-emerald-600 font-bold ml-1">({{ now()->diffInDays($asset->warranty_expiry) }}d left)</span>
                            @endif
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400" title="End of Depreciation / Recommended Useful Duty Life">EOD (Depreciation)</span>
                    <span class="font-semibold text-slate-800">
                        @if($asset->eod_date)
                            {{ $asset->eod_date->format('d-M-Y') }}
                            @if($asset->isPastEod())
                                <span class="text-[10px] text-amber-700 font-bold ml-1">(Past EOD • Fully Depreciated)</span>
                            @else
                                <span class="text-[10px] text-slate-500 font-medium ml-1">({{ now()->diffInDays($asset->eod_date) }}d left)</span>
                            @endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400" title="End of Life / Obsolescence Cutoff">EOL (End of Life)</span>
                    <span class="font-semibold text-slate-800">
                        @if($asset->eol_date)
                            {{ $asset->eol_date->format('d-M-Y') }}
                            @if($asset->isPastEol())
                                <span class="text-[10px] text-rose-600 font-bold ml-1">(Obsolete / EOL)</span>
                            @else
                                <span class="text-[10px] text-slate-500 font-medium ml-1">({{ now()->diffInDays($asset->eol_date) }}d left)</span>
                            @endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Supplier / Vendor</span>
                    <span class="text-slate-700">{{ $asset->vendor_supplier ?: '—' }}</span>
                </div>
                @if($asset->notes)
                    <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-500 italic">
                        "{{ $asset->notes }}"
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- INSTALLED COMPONENTS & HARDWARE MODULES (RAM, SSDs, etc) -->
    <!-- ======================================================== -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold">
                    🧠
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Installed Internal Components</h2>
                    <p class="text-[11px] text-slate-500">Track RAM sticks, SSDs, and power units currently inside this machine.</p>
                </div>
            </div>

            <button type="button" @click="installCompModal = true" class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs px-3.5 py-2 rounded-xl transition flex items-center space-x-1.5 cursor-pointer">
                <span>➕</span>
                <span>Install Component / RAM</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-5">Type</th>
                        <th class="py-3 px-4">Component Brand &amp; Model</th>
                        <th class="py-3 px-4">Capacity / Specs</th>
                        <th class="py-3 px-4">Serial Number</th>
                        <th class="py-3 px-4">Installed Date</th>
                        <th class="py-3 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($asset->components as $comp)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-5 font-bold text-slate-900">
                                <span class="mr-1.5">{{ match($comp->component_type) { 'ram' => '🧠', 'ssd' => '💾', 'hdd' => '💽', 'psu' => '⚡', 'gpu' => '🎮', 'cable' => '🔌', default => '⚙️' } }}</span>
                                <span>{{ $comp->type_label }}</span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-800">
                                {{ $comp->brand_model }}
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-800">
                                {{ $comp->capacity_or_specs ?: '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-500">
                                {{ $comp->serial_number ?: '—' }}
                            </td>
                            <td class="py-3 px-4 text-slate-500">
                                {{ $comp->installed_at ? $comp->installed_at->format('d-M-Y') : 'Original Component' }}
                            </td>
                            <td class="py-3 px-5 text-right font-semibold">
                                <form method="POST" action="{{ route('portal.assets.components.destroy', [$asset->id, $comp->id]) }}" class="inline-flex space-x-1.5" onsubmit="return confirm('Extract this component from {{ $asset->asset_tag }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" name="action" value="return_to_stock" class="text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-2 py-1 rounded-lg transition" title="Return to IT Spare Storage">
                                        Return to Stock
                                    </button>
                                    <button type="submit" name="action" value="mark_faulty" class="text-xs bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 px-2 py-1 rounded-lg transition" title="Mark component as broken/faulty">
                                        Mark Faulty
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400">
                                <span class="text-lg">⚙️</span>
                                <div class="text-xs mt-1">No modular components individually tracked for this machine yet.</div>
                                <div class="text-[11px] text-slate-400">Click "Install Component / RAM" above to record loose RAM, SSDs, or upgrades.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- PERMANENT SERVICE & REPAIR HISTORY (THE BOSS'S DREAM)    -->
    <!-- ======================================================== -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-bold">
                    📋
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h2 class="text-sm font-bold text-slate-900">Service &amp; Repair History</h2>
                        <span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                            {{ $asset->tickets->count() }} Recorded {{ Str::plural('Ticket', $asset->tickets->count()) }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500">Chronological history of all helpdesk incidents and service requests logged against this machine.</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-5">Ticket Code</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Subject &amp; Issue Summary</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Assigned Tech</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-5 text-right">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($asset->tickets as $ticket)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-5 font-mono font-bold text-blue-600">
                                <a href="{{ route('portal.tickets.show', $ticket->id) }}" class="hover:underline">
                                    {{ $ticket->ticket_code }}
                                </a>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                {{ $ticket->created_at->format('d-M-Y') }}
                            </td>
                            <td class="py-3.5 px-4 max-w-sm">
                                <div class="font-bold text-slate-900 truncate">{{ $ticket->subject }}</div>
                                <div class="text-[11px] text-slate-500 truncate">{{ $ticket->description }}</div>
                                
                                <!-- Part replacements recorded for this ticket -->
                                @php
                                    $parts = $asset->ticketParts->where('ticket_id', $ticket->id);
                                @endphp
                                @if($parts->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($parts as $part)
                                            <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                                                <span>⚙️ {{ $part->description }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">
                                {{ $ticket->category?->name ?? 'General' }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($ticket->technician)
                                    <span class="font-bold text-slate-800">👤 {{ $ticket->technician->name }}</span>
                                @else
                                    <span class="text-slate-400">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center text-[10px] font-bold px-2.5 py-0.5 rounded-full {{ match($ticket->status) { 'closed', 'resolved' => 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'in_progress' => 'bg-blue-50 text-blue-700 border border-blue-200', 'assigned' => 'bg-purple-50 text-purple-700 border border-purple-200', default => 'bg-slate-100 text-slate-600 border border-slate-200' } }}">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-right font-semibold">
                                <a href="{{ route('portal.tickets.show', $ticket->id) }}" class="text-blue-600 hover:text-blue-800 font-bold text-xs hover:underline">
                                    Open Ticket &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <div class="text-2xl mb-1">✨</div>
                                <div class="font-bold text-slate-700">No service tickets recorded yet</div>
                                <p class="text-xs text-slate-400 mt-0.5">This hardware has run cleanly with zero reported issues.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: EDIT ASSET DETAILS                                -->
    <!-- ======================================================== -->
    <div x-show="editAssetModal" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-4" 
         x-cloak>
        <div @click.away="editAssetModal = false" class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
            <!-- Fixed Pinned Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80 shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-bold shadow-2xs">
                        ✏️
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="text-sm font-extrabold text-slate-900">Edit Asset Profile</h3>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200">{{ $asset->asset_tag }}</span>
                            @php $badge = $asset->status_badge; @endphp
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full border {{ $badge['class'] }}">
                                {{ $badge['label'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">Update custody, operational status, lifecycle dates, and hardware specs.</p>
                    </div>
                </div>
                <button type="button" @click="editAssetModal = false" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm font-bold" title="Close">&times;</button>
            </div>

            <!-- Scrollable Body with Form -->
            <form id="editAssetForm" method="POST" action="{{ route('portal.assets.update', $asset->id) }}" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                @method('PUT')

                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4 text-xs">
                    <!-- Section 1: Operational Status & Financials -->
                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-slate-800 uppercase tracking-wider">Operational Status &amp; Financials</label>
                            <span class="text-[10px] text-slate-400">Controls deployment &amp; availability</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                                <select name="status" x-model="assetStatus" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-bold text-slate-800">
                                    <option value="in_use">🟢 In Use / Active</option>
                                    <option value="spare_stock">🔵 Spare in Stock</option>
                                    <option value="under_repair">🟡 Under Repair / RMA</option>
                                    <option value="decommissioned">⚪ Decommissioned</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Purchase Date</label>
                                <input type="date" name="purchase_date" value="{{ $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : '' }}" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Purchase Cost (USD)</label>
                                <input type="number" step="0.01" min="0" name="purchase_cost" value="{{ $asset->purchase_cost }}" placeholder="e.g. 850.00" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                        </div>

                        <!-- Dynamic Status Helper Banner -->
                        <div x-show="assetStatus === 'spare_stock'" class="p-2.5 bg-blue-50 rounded-xl border border-blue-200/80 text-[11px] text-blue-800 flex items-start space-x-2">
                            <span class="text-sm">💡</span>
                            <div>
                                <strong class="font-bold">Spare in Stock:</strong> This asset is in the IT storage room / shelf ready for hot-swap or assignment to staff. If previously assigned to a person, consider clearing Custodian below.
                            </div>
                        </div>
                        <div x-show="assetStatus === 'under_repair'" class="p-2.5 bg-amber-50 rounded-xl border border-amber-200/80 text-[11px] text-amber-900 flex items-start space-x-2" x-cloak>
                            <span class="text-sm">⚠️</span>
                            <div>
                                <strong class="font-bold">Under Repair / RMA:</strong> Asset is flagged as defective or sent to supplier. Will be highlighted in technical triage.
                            </div>
                        </div>
                        <div x-show="assetStatus === 'decommissioned'" class="p-2.5 bg-rose-50 rounded-xl border border-rose-200/80 text-[11px] text-rose-900 flex items-start space-x-2" x-cloak>
                            <span class="text-sm">🛑</span>
                            <div>
                                <strong class="font-bold">Decommissioned:</strong> Asset is retired from active service. Will be excluded from regular operational counts.
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Ownership & Location -->
                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
                        <label class="block text-[11px] font-bold text-slate-800 uppercase tracking-wider">Equipment Ownership &amp; Location</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center space-x-2 p-2.5 bg-white rounded-xl border border-slate-200 hover:border-blue-400 cursor-pointer transition shadow-2xs">
                                <input type="radio" name="ownership_type" value="personal" x-model="assetOwnership" class="rounded text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">👤 Personal Custody</div>
                                    <div class="text-[10px] text-slate-400">Assigned to specific staff</div>
                                </div>
                            </label>
                            <label class="flex items-center space-x-2 p-2.5 bg-white rounded-xl border border-slate-200 hover:border-blue-400 cursor-pointer transition shadow-2xs">
                                <input type="radio" name="ownership_type" value="shared" x-model="assetOwnership" class="rounded text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">🏢 Shared Room / Floor</div>
                                    <div class="text-[10px] text-slate-400">Meeting room, printing area</div>
                                </div>
                            </label>
                        </div>

                        <!-- Conditional Ownership User field -->
                        <div x-show="assetOwnership === 'personal'" class="p-3 bg-blue-50/70 rounded-xl border border-blue-200/70 space-y-1">
                            <label class="block text-[11px] font-bold text-blue-900 uppercase">Assigned Employee (Custodian)</label>
                            <select name="assigned_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">-- Unassigned (or Spare in IT Stock) --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $asset->assigned_user_id == $u->id ? 'selected' : '' }}>👤 {{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Branch</label>
                                <select name="branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="">-- Select Branch --</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" {{ $asset->branch_id == $b->id ? 'selected' : '' }}>📍 {{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Department</label>
                                <select name="department_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="">-- Select Department --</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}" {{ $asset->department_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Specific Room / Shelf / Desk</label>
                                <input type="text" name="location_details" value="{{ $asset->location_details }}" placeholder="e.g. IT Stock Shelf B or Meeting Room B" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Asset Identification -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Asset Tag</label>
                            <input type="text" name="asset_tag" value="{{ $asset->asset_tag }}" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Category</label>
                            <select name="category_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $asset->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Asset Name</label>
                            <input type="text" name="name" value="{{ $asset->name }}" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                        </div>
                    </div>

                    <!-- Section 4: Hardware Lifecycle Dates (EOW / EOD / EOL) -->
                    <div class="p-3.5 bg-amber-50/50 rounded-2xl border border-amber-200/70 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-amber-950 uppercase tracking-wider">Hardware Lifecycle &amp; Depreciation</label>
                            <span class="text-[10px] text-amber-700 font-semibold">Warranty vs EOD vs EOL</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-800 uppercase mb-0.5">Warranty Expiry (EOW)</label>
                                <span class="text-[10px] text-slate-500 block mb-1">Free supplier repair end</span>
                                <input type="date" name="warranty_expiry" value="{{ $asset->warranty_expiry ? $asset->warranty_expiry->format('Y-m-d') : '' }}" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-amber-900 uppercase mb-0.5">EOD Date (Depreciation)</label>
                                <span class="text-[10px] text-amber-700 block mb-1">Recommended overhaul / $0 book</span>
                                <input type="date" name="eod_date" value="{{ $asset->eod_date ? $asset->eod_date->format('Y-m-d') : '' }}" class="w-full text-xs px-3 py-2 rounded-xl border border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-rose-900 uppercase mb-0.5">EOL Date (End of Life)</label>
                                <span class="text-[10px] text-rose-700 block mb-1">Obsolescence / retirement target</span>
                                <input type="date" name="eol_date" value="{{ $asset->eol_date ? $asset->eol_date->format('Y-m-d') : '' }}" class="w-full text-xs px-3 py-2 rounded-xl border border-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-500 bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- Section 5: Hardware Specifications -->
                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
                        <label class="block text-[11px] font-bold text-slate-800 uppercase tracking-wider">Hardware Specifications</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Brand</label>
                                <input type="text" name="brand" value="{{ $asset->brand }}" placeholder="e.g. Dell, LG, Epson" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Model</label>
                                <input type="text" name="model" value="{{ $asset->model }}" placeholder="e.g. OptiPlex 7010, 75UR8050" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Serial Number</label>
                                <input type="text" name="serial_number" value="{{ $asset->serial_number }}" placeholder="e.g. SN-498120" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono bg-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">CPU</label>
                                <input type="text" name="specs_cpu" value="{{ $asset->specs['cpu'] ?? '' }}" placeholder="i5 / i7 / M2" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">RAM</label>
                                <input type="text" name="specs_ram" value="{{ $asset->specs['ram'] ?? '' }}" placeholder="16GB DDR4" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Storage</label>
                                <input type="text" name="specs_storage" value="{{ $asset->specs['storage'] ?? '' }}" placeholder="512GB SSD" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">OS</label>
                                <input type="text" name="specs_os" value="{{ $asset->specs['os'] ?? '' }}" placeholder="Win 11 Pro" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">IP Address</label>
                                <input type="text" name="specs_ip" value="{{ $asset->specs['ip'] ?? '' }}" placeholder="192.168.10.x" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Section 6: Supplier & Notes -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Supplier / Vendor</label>
                            <input type="text" name="vendor_supplier" value="{{ $asset->vendor_supplier }}" placeholder="e.g. Anana Computer, Epson Cambodia, Kfour" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Technical Notes</label>
                            <textarea name="notes" rows="2" placeholder="e.g. Dedicated for board meetings, spare stock shelf location, setup notes..." class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">{{ $asset->notes }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Fixed Pinned Footer (Always visible, never cut off!) -->
                <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-200/80 bg-slate-50/95 shrink-0">
                    <div class="text-[11px] text-slate-500 hidden sm:flex items-center space-x-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>Saving immediately updates registry, history, and status badges.</span>
                    </div>
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button type="button" @click="editAssetModal = false" class="px-4 py-2.5 text-xs text-slate-600 font-bold hover:bg-slate-200/70 rounded-xl transition cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer flex items-center space-x-1.5">
                            <span>💾</span>
                            <span>Update Profile</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: INSTALL COMPONENT / RAM INTO ASSET                 -->
    <!-- ======================================================== -->
    <div x-show="installCompModal" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:leave="transition ease-in duration-150" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto" 
         x-cloak>
        <div @click.away="installCompModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                        🧠
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Install Component into {{ $asset->asset_tag }}</h3>
                        <p class="text-[11px] text-slate-500">Assign a spare RAM stick, SSD, or PSU to this computer.</p>
                    </div>
                </div>
                <button type="button" @click="installCompModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('portal.assets.components.store', $asset->id) }}" class="space-y-4">
                @csrf

                <!-- Choose Source: From IT Storage vs New Part -->
                <div class="grid grid-cols-2 gap-3 p-1 bg-slate-100 rounded-xl">
                    <button type="button" @click="compSourceMode = 'from_stock'" :class="compSourceMode === 'from_stock' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="py-2 text-xs font-bold rounded-lg transition">
                        📦 From IT Spare Stock
                    </button>
                    <button type="button" @click="compSourceMode = 'new'" :class="compSourceMode === 'new' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="py-2 text-xs font-bold rounded-lg transition">
                        ➕ Register New Part
                    </button>
                </div>
                <input type="hidden" name="source_mode" :value="compSourceMode">

                <!-- Mode 1: Select From Available Spares in Stock -->
                <div x-show="compSourceMode === 'from_stock'" class="space-y-3">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Available Parts in IT Storage</label>
                    <select name="spare_component_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white font-medium">
                        <option value="">-- Choose spare part in stock ({{ $availableSpares->count() }} available) --</option>
                        @foreach($availableSpares as $spare)
                            <option value="{{ $spare->id }}">
                                {{ match($spare->component_type) { 'ram' => '🧠', 'ssd' => '💾', 'psu' => '⚡', default => '⚙️' } }}
                                [{{ $spare->type_label }}] {{ $spare->brand_model }} ({{ $spare->capacity_or_specs }}) - SN: {{ $spare->serial_number ?: 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Mode 2: Direct Input New Part -->
                <div x-show="compSourceMode === 'new'" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Component Type</label>
                            <select name="component_type" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                                <option value="ram">🧠 RAM Memory</option>
                                <option value="ssd">💾 SSD Storage</option>
                                <option value="hdd">💽 Hard Disk (HDD)</option>
                                <option value="psu">⚡ Power Supply (PSU)</option>
                                <option value="gpu">🎮 Graphics Card (GPU)</option>
                                <option value="other">⚙️ Other Component</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Capacity / Specs</label>
                            <input type="text" name="capacity_or_specs" placeholder="e.g. 8GB DDR4 3200" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Brand &amp; Model</label>
                        <input type="text" name="brand_model" placeholder="e.g. Kingston Fury 8GB" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Serial Number</label>
                        <input type="text" name="serial_number" placeholder="Optional" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Installation Note</label>
                    <input type="text" name="notes" placeholder="e.g. Upgraded slot 2 for accounting workload" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="installCompModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Confirm Installation</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

