@extends('portal.layout')

@section('title', 'Central IT Operations & Asset Overview')

@section('content')
<div x-data="{
    branchFilter: '{{ $branchFilter ?? '' }}',
    filterBranch(id) {
        let url = new URL(window.location.href);
        if (id) {
            url.searchParams.set('branch_id', id);
        } else {
            url.searchParams.delete('branch_id');
        }
        window.location.href = url.toString();
    }
}" class="space-y-6">

    <!-- Top Executive Header & View Selector (GLPI-Inspired Central Cockpit) -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-900 to-indigo-900 text-white flex items-center justify-center font-black text-xl shadow-md border border-slate-700">
                ⚡
            </div>
            <div>
                <div class="flex items-center space-x-2.5">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Central IT Operations Overview</h1>
                    <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-300">
                        Live System
                    </span>
                    <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full border border-blue-200">
                        GLPI Cockpit Mode
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unified executive dashboard combining Help Desk service operations, hardware inventory, and lifecycle risk.
                </p>
            </div>
        </div>

        <!-- Entity / Branch Scope Selector & 1-Click Action Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Branch Filter Selector -->
            <div class="flex items-center space-x-1.5 bg-slate-100 p-1 rounded-2xl border border-slate-200">
                <span class="text-[11px] font-bold text-slate-500 pl-2 pr-1">🏢 Branch:</span>
                <select @change="filterBranch($event.target.value)" 
                        class="bg-white text-xs font-semibold text-slate-800 border-none rounded-xl py-1.5 pl-2.5 pr-8 focus:ring-2 focus:ring-blue-500 shadow-xs cursor-pointer">
                    <option value="" {{ empty($branchFilter) ? 'selected' : '' }}>All Branches (Global View)</option>
                    @foreach($branches as $b)
                        <option value="{{ $b['id'] }}" {{ ($branchFilter == $b['id']) ? 'selected' : '' }}>
                            {{ $b['name'] }} ({{ $b['code'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Quick Action Shortcuts -->
            <a href="{{ route('portal.assets.index') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>🖥️ Asset Fleet</span>
            </a>

            <a href="{{ route('portal.dashboard') }}" 
               class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>🎫 Ticket Queue</span>
            </a>

            <a href="{{ route('portal.assets.export', ['branch_id' => $branchFilter]) }}" 
               class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5"
               title="Export full stock CSV">
                <span>📥 CSV</span>
            </a>
        </div>
    </div>

    @if($selectedBranch)
        <div class="bg-blue-50 border border-blue-200 rounded-2xl px-4 py-2.5 text-xs text-blue-900 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="font-bold">Filtering by:</span>
                <span class="font-semibold">{{ $selectedBranch->name }} ({{ $selectedBranch->code }})</span>
                <span class="text-blue-500">📍 {{ $selectedBranch->location ?? 'Headquarters' }}</span>
            </div>
            <button @click="filterBranch('')" class="text-blue-700 hover:text-blue-900 font-bold underline text-[11px]">
                ✕ Reset to Global View
            </button>
        </div>
    @endif

    <!-- GLPI-STYLE SIGNATURE COLORFUL METRIC CARDS (Top Primary Grid) -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4 2xl:grid-cols-8 gap-3.5">
        
        <!-- 1. Computers & Laptops (Red / Rose) -->
        @php
            $pcCat = $categories->first(fn($c) => in_array($c->code, ['WORKSTATION', 'LAPTOP', 'PC']));
            $pcCount = $pcCat ? $pcCat->assets_count : 0;
        @endphp
        <a href="{{ route('portal.assets.index', array_filter(['category_id' => $pcCat?->id, 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br from-rose-500 to-rose-600 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $pcCount }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    💻
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-rose-100">Computers</div>
            <div class="text-[10px] text-rose-200 mt-0.5 truncate">Desktops & Laptops</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">PC</div>
        </a>

        <!-- 2. Displays & Meeting Equipment (Dark Red / Crimson) -->
        @php
            $dispCat = $categories->first(fn($c) => in_array($c->code, ['MEETING', 'DISPLAY', 'MONITOR']));
            $dispCount = $dispCat ? $dispCat->assets_count : 0;
        @endphp
        <a href="{{ route('portal.assets.index', array_filter(['category_id' => $dispCat?->id, 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br from-red-600 to-amber-700 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $dispCount }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    🖥️
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-red-100">Displays</div>
            <div class="text-[10px] text-red-200 mt-0.5 truncate">Screens & Projectors</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">DISP</div>
        </a>

        <!-- 3. Network Devices (Teal / Cyan) -->
        @php
            $netCat = $categories->first(fn($c) => in_array($c->code, ['NETWORK', 'INFRA', 'SWITCH']));
            $netCount = $netCat ? $netCat->assets_count : 0;
        @endphp
        <a href="{{ route('portal.assets.index', array_filter(['category_id' => $netCat?->id, 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br from-teal-500 to-emerald-600 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $netCount }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    🖧
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-teal-100">Network</div>
            <div class="text-[10px] text-teal-200 mt-0.5 truncate">Switches, Routers, APs</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">NET</div>
        </a>

        <!-- 4. Printers & Scanners (Dark Blue) -->
        @php
            $prnCat = $categories->first(fn($c) => in_array($c->code, ['PRINTER', 'IMAGING']));
            $prnCount = $prnCat ? $prnCat->assets_count : 0;
        @endphp
        <a href="{{ route('portal.assets.index', array_filter(['category_id' => $prnCat?->id, 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br from-blue-700 to-indigo-800 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $prnCount }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    🖨️
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-blue-100">Printers</div>
            <div class="text-[10px] text-blue-200 mt-0.5 truncate">Copiers & MFPs</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">PRN</div>
        </a>

        <!-- 5. Software & Digital Licenses (Emerald / Teal - Signature GLPI Tile) -->
        <a href="{{ route('portal.assets.index', ['tab' => 'licenses']) }}" 
           class="bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $totalLicenses }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    🔑
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-emerald-100">Software &amp; Licenses</div>
            <div class="text-[10px] text-emerald-200 mt-0.5 truncate">{{ $assignedSoftwareSeats }}/{{ $totalSoftwareSeats }} seats &bull; {{ $totalLicenses }} apps</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">LIC</div>
        </a>

        <!-- 6. Spare Components & Parts (Green) -->
        <a href="{{ route('portal.assets.index', ['tab' => 'components']) }}" 
           class="bg-gradient-to-br from-emerald-600 to-green-700 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $sparesInStock }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    📦
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-emerald-100">Spares & Stock</div>
            <div class="text-[10px] text-emerald-200 mt-0.5 truncate">RAM, SSDs, Loose Parts</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">PARTS</div>
        </a>

        <!-- 7. Active Helpdesk Tickets (Amber / Yellow) -->
        <a href="{{ route('portal.dashboard', array_filter(['view' => 'all_active', 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $allActiveTickets }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    🎫
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-amber-100">Active Tickets</div>
            <div class="text-[10px] text-amber-200 mt-0.5 truncate">{{ $triageTickets }} triage • {{ $inProgressTickets }} in progress</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">TKT</div>
        </a>

        <!-- 8. Late / SLA Breached Tickets (Orange / Red Warning) -->
        <a href="{{ route('portal.dashboard', array_filter(['view' => 'sla_breached', 'branch_id' => $branchFilter])) }}" 
           class="bg-gradient-to-br {{ $slaBreaches > 0 ? 'from-orange-600 to-red-600 animate-pulse' : 'from-slate-700 to-slate-800' }} text-white p-4 rounded-2xl shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 block group relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="text-3xl font-black tracking-tight">{{ $slaBreaches }}</div>
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-xl">
                    ⏰
                </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide uppercase text-orange-100">Late Tickets</div>
            <div class="text-[10px] text-orange-200 mt-0.5 truncate">{{ $slaBreaches > 0 ? '>30m SLA Breached' : 'Zero Breaches' }}</div>
            <div class="absolute -right-2 -bottom-2 text-6xl opacity-10 font-black pointer-events-none">SLA</div>
        </a>

    </div>

    <!-- MAIN DUAL-PANEL EXECUTIVE ANALYTICS SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT PANEL (7 Cols): HARDWARE FLEET VALUATION & LIFECYCLE HEALTH (GLPI ITAM) -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs space-y-6">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
                        📊
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 leading-tight">Hardware Fleet & Lifecycle Health</h2>
                        <p class="text-xs text-slate-400">Total capital valuation and financial risk exposure</p>
                    </div>
                </div>

                <a href="{{ route('portal.assets.index', ['tab' => 'report']) }}" 
                   class="text-xs text-blue-600 hover:text-blue-800 font-bold flex items-center space-x-1 group">
                    <span>Full Valuation Report</span>
                    <span class="group-hover:translate-x-0.5 transition">→</span>
                </a>
            </div>

            <!-- Total Valuation Banner -->
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    @if($canViewFinancials)
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Hardware Fleet Valuation</div>
                        <div class="text-3xl font-black text-white mt-1">${{ number_format($totalValuation, 2) }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Across {{ $totalAssets }} machines & equipment</div>
                    @else
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Hardware Fleet Size</div>
                        <div class="text-3xl font-black text-white mt-1">{{ $totalAssets }} Deployed Units</div>
                        <div class="text-xs text-slate-400 mt-0.5">Hardware asset fleet under active management</div>
                    @endif
                </div>
                <div class="flex items-center space-x-4 border-t sm:border-t-0 sm:border-l border-slate-800 pt-3 sm:pt-0 sm:pl-5 text-xs">
                    <div>
                        <div class="text-slate-400 font-semibold">In Active Use</div>
                        <div class="text-lg font-black text-emerald-400">{{ $inUseAssets }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400 font-semibold">Spare Stock</div>
                        <div class="text-lg font-black text-blue-400">{{ $spareStockAssets }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400 font-semibold">In Repair</div>
                        <div class="text-lg font-black text-amber-400">{{ $underRepairAssets }}</div>
                    </div>
                </div>
            </div>

            <!-- Lifecycle Exposure Breakdown Cards (2x2 Grid) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                
                <!-- Active Warranty -->
                <div class="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-900">🛡️ Active Warranty (EOW)</span>
                        <span class="bg-emerald-200 text-emerald-900 text-[10px] font-black px-2 py-0.5 rounded-full">{{ $activeWarrantyCount }} Assets</span>
                    </div>
                    @if($canViewFinancials)
                        <div class="text-xl font-black text-emerald-700 mt-2">${{ number_format($activeWarrantyValuation, 2) }}</div>
                    @else
                        <div class="text-xl font-black text-emerald-700 mt-2">{{ $activeWarrantyCount }} Covered Units</div>
                    @endif
                    <div class="text-[11px] text-emerald-800/80 mt-0.5">Covered under supplier warranty</div>
                </div>

                <!-- Expired Warranty -->
                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/70">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">⚠️ Out of Warranty</span>
                        <span class="bg-slate-200 text-slate-800 text-[10px] font-black px-2 py-0.5 rounded-full">{{ $outOfWarrantyCount }} Assets</span>
                    </div>
                    @if($canViewFinancials)
                        <div class="text-xl font-black text-slate-800 mt-2">${{ number_format($outOfWarrantyValuation, 2) }}</div>
                    @else
                        <div class="text-xl font-black text-slate-800 mt-2">{{ $outOfWarrantyCount }} Expired Units</div>
                    @endif
                    <div class="text-[11px] text-slate-500 mt-0.5">Self-maintenance / third-party parts</div>
                </div>

                <!-- EOD Degradation -->
                <div class="p-4 rounded-2xl border border-amber-200 bg-amber-50/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-900">📉 Depreciation / EOD</span>
                        <span class="bg-amber-200 text-amber-900 text-[10px] font-black px-2 py-0.5 rounded-full">{{ $pastEodCount }} Assets</span>
                    </div>
                    @if($canViewFinancials)
                        <div class="text-xl font-black text-amber-700 mt-2">${{ number_format($pastEodValuation, 2) }}</div>
                    @else
                        <div class="text-xl font-black text-amber-700 mt-2">{{ $pastEodCount }} Overhaul Due</div>
                    @endif
                    <div class="text-[11px] text-amber-800/80 mt-0.5">Recommended for overhaul or replacement</div>
                </div>

                <!-- EOL Obsolescence -->
                <div class="p-4 rounded-2xl border border-red-200 bg-red-50/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-red-900">🚫 End of Life (EOL)</span>
                        <span class="bg-red-200 text-red-900 text-[10px] font-black px-2 py-0.5 rounded-full">{{ $pastEolCount }} Assets</span>
                    </div>
                    @if($canViewFinancials)
                        <div class="text-xl font-black text-red-700 mt-2">${{ number_format($pastEolValuation, 2) }}</div>
                    @else
                        <div class="text-xl font-black text-red-700 mt-2">{{ $pastEolCount }} Obsolete Units</div>
                    @endif
                    <div class="text-[11px] text-red-800/80 mt-0.5">High failure risk — target retirement</div>
                </div>

            </div>

            <!-- Categories Quick Distribution Bar -->
            <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>Category Distribution</span>
                    <span>{{ $totalAssets }} Total Machines</span>
                </div>
                <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden flex">
                    @php
                        $colors = ['bg-rose-500', 'bg-red-600', 'bg-teal-500', 'bg-blue-600', 'bg-indigo-500', 'bg-amber-500', 'bg-purple-500'];
                    @endphp
                    @foreach($categories as $idx => $cat)
                        @if($totalAssets > 0 && $cat->assets_count > 0)
                            @php $pct = round(($cat->assets_count / $totalAssets) * 100, 1); @endphp
                            <div class="{{ $colors[$idx % count($colors)] }}" 
                                 style="width: {{ $pct }}%" 
                                 title="{{ $cat->name }}: {{ $cat->assets_count }} ({{ $pct }}%)"></div>
                        @endif
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3 pt-1 text-[11px] text-slate-500">
                    @foreach($categories as $idx => $cat)
                        @if($cat->assets_count > 0)
                            <div class="flex items-center space-x-1.5">
                                <span class="w-2.5 h-2.5 rounded-full {{ $colors[$idx % count($colors)] }}"></span>
                                <span class="font-semibold text-slate-700">{{ $cat->name }}</span>
                                <span class="text-slate-400">({{ $cat->assets_count }})</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

        </div>

        <!-- RIGHT PANEL (5 Cols): HELPDESK OPERATIONS & WORKFLOW (GLPI Assistance) -->
        <div class="lg:col-span-5 bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs space-y-6">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg">
                        🎫
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 leading-tight">Help Desk Operations Pulse</h2>
                        <p class="text-xs text-slate-400">Real-time ticket throughput & SLA delivery</p>
                    </div>
                </div>

                <a href="{{ route('portal.dashboard') }}" 
                   class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1 group">
                    <span>Open Queues</span>
                    <span class="group-hover:translate-x-0.5 transition">→</span>
                </a>
            </div>

            <!-- Helpdesk Resolution Summary -->
            <div class="grid grid-cols-2 gap-3 text-center">
                <div class="bg-indigo-50/70 border border-indigo-100 p-4 rounded-2xl">
                    <div class="text-3xl font-black text-indigo-700">{{ $allActiveTickets }}</div>
                    <div class="text-xs font-bold text-indigo-900 mt-1">Open Tickets</div>
                    <div class="text-[10px] text-indigo-500 mt-0.5">Currently running queue</div>
                </div>

                <div class="bg-emerald-50/70 border border-emerald-100 p-4 rounded-2xl">
                    <div class="text-3xl font-black text-emerald-700">{{ $solvedToday }}</div>
                    <div class="text-xs font-bold text-emerald-900 mt-1">Solved Today</div>
                    <div class="text-[10px] text-emerald-600 mt-0.5">{{ $solvedTickets }} Total resolved tickets</div>
                </div>
            </div>

            <!-- Queue Status List -->
            <div class="space-y-2.5">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Queue Pipeline</div>
                
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        <span class="font-semibold text-slate-700">Triage Queue (Unassigned)</span>
                    </div>
                    <span class="font-black text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">{{ $triageTickets }}</span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span class="font-semibold text-slate-700">In Progress / Assigned</span>
                    </div>
                    <span class="font-black text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded-full">{{ $inProgressTickets }}</span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                        <span class="font-semibold text-slate-700">HOD Multi-Step Approvals</span>
                    </div>
                    <span class="font-black text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full">{{ $pendingApprovals }}</span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="font-semibold text-slate-700">Blocked / Vendor Hold</span>
                    </div>
                    <span class="font-black text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">{{ $blockedTickets }}</span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-semibold text-slate-700">Pending User Confirmation</span>
                    </div>
                    <span class="font-black text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded-full">{{ $pendingConfirmation }}</span>
                </div>
            </div>

            <!-- Priority & Division Pills -->
            <div class="border-t border-slate-100 pt-4 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-700">Urgency Breakdown:</span>
                    <div class="flex items-center space-x-1.5">
                        <span class="bg-red-100 text-red-800 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $criticalTickets }} Critical</span>
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $highTickets }} High</span>
                        <span class="bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $mediumTickets + $lowTickets }} Med/Low</span>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-700">IT Division Split:</span>
                    <div class="flex items-center space-x-1.5">
                        <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full">IT-OPR: {{ $oprTickets }}</span>
                        <span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded-full">IT-PRD: {{ $prdTickets }}</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- MULTI-ENTITY (BRANCH) OPERATIONS MATRIX & TECHNICIAN LOAD (GLPI Entities & Profiles) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- BRANCH ENTITY DIRECTORY (8 Cols) -->
        <div class="lg:col-span-8 bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900 leading-tight">Branch Entities Matrix (GLPI Multi-Entity View)</h3>
                    <p class="text-xs text-slate-400">Hardware deployment and support demand mapped by physical location</p>
                </div>
                <span class="text-xs bg-slate-100 text-slate-600 font-bold px-2.5 py-1 rounded-xl">
                    {{ count($branches) }} Branches
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase text-[10px] font-bold tracking-wider">
                            <th class="py-2.5 px-3">Branch Entity</th>
                            <th class="py-2.5 px-3">Manager / In-Charge</th>
                            <th class="py-2.5 px-3 text-center">Hardware Assets</th>
                            <th class="py-2.5 px-3 text-right">{{ $canViewFinancials ? 'Fleet Capital' : 'Hardware Share' }}</th>
                            <th class="py-2.5 px-3 text-center">Active Tickets</th>
                            <th class="py-2.5 px-3 text-center">SLA Alerts</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($branches as $br)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900">{{ $br['name'] }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $br['code'] }} • {{ $br['location'] ?? 'Cambodia' }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="font-semibold text-slate-800">{{ $br['manager'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span class="font-black text-slate-900 bg-slate-100 px-2.5 py-1 rounded-lg">
                                        {{ $br['assets_count'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right font-black text-slate-900">
                                    @if($canViewFinancials)
                                        ${{ number_format($br['assets_valuation'], 2) }}
                                    @else
                                        {{ $br['assets_count'] }} Units
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center">
                                    @if($br['active_tickets_count'] > 0)
                                        <span class="font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                            {{ $br['active_tickets_count'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">0</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center">
                                    @if($br['sla_breaches_count'] > 0)
                                        <span class="font-black text-red-600 bg-red-100 px-2 py-0.5 rounded-full animate-pulse">
                                            {{ $br['sla_breaches_count'] }} Breached
                                        </span>
                                    @else
                                        <span class="text-emerald-600 font-bold">✓ Clear</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <button @click="filterBranch('{{ $br['id'] }}')" 
                                            class="text-blue-600 hover:text-blue-800 font-bold hover:underline">
                                        Scope View →
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TECHNICIAN LOAD ROSTER (4 Cols) -->
        <div class="lg:col-span-4 bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900 leading-tight">Technician Load Roster</h3>
                    <p class="text-xs text-slate-400">Active assigned service workload</p>
                </div>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping" title="Duty Active"></span>
            </div>

            <div class="space-y-3">
                @forelse($technicians as $tech)
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 bg-slate-50/60 hover:bg-slate-50 transition">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center text-xs shadow-xs">
                                {{ strtoupper(substr($tech->name, 0, 2)) }}
                            </div>
                            <div>
                                <div class="font-bold text-xs text-slate-900">{{ $tech->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ $tech->email }}</div>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-xs font-black {{ $tech->active_tasks_count > 3 ? 'text-amber-600' : 'text-slate-800' }}">
                                {{ $tech->active_tasks_count }} Tasks
                            </div>
                            <div class="text-[10px] text-slate-400">
                                {{ $tech->active_tasks_count > 0 ? 'Assigned' : 'Available' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-xs text-slate-400">
                        No active technicians on roster.
                    </div>
                @endforelse
            </div>

            <!-- Telegram Bot & Mini App Status Card -->
            <div class="mt-4 p-4 rounded-2xl bg-gradient-to-tr from-sky-50 to-blue-50 border border-sky-200 text-xs text-sky-900">
                <div class="flex items-center space-x-2 font-bold text-sky-950">
                    <span>📱 Telegram Integration</span>
                    <span class="bg-sky-200 text-sky-900 text-[9px] font-black px-1.5 py-0.2 rounded">ONLINE</span>
                </div>
                <p class="text-[11px] text-sky-800/90 mt-1">
                    Requester Mini App & Bot connected. Requesters can submit tickets and view equipment directly inside Telegram.
                </p>
                <div class="mt-2.5">
                    <a href="{{ route('portal.link-requests.index') }}" class="font-bold text-sky-700 hover:text-sky-900 underline text-[11px]">
                        Review Telegram Link Requests →
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- RECENT OPERATIONAL ACTIVITY STREAM -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Recent Tickets -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <h4 class="text-sm font-bold text-slate-900">Recent Help Desk Tickets</h4>
                <a href="{{ route('portal.dashboard') }}" class="text-xs font-bold text-blue-600 hover:underline">View All Tickets →</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recentTickets as $rt)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="space-y-0.5 truncate">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900">{{ $rt->ticket_code }}</span>
                                <span class="text-[10px] font-semibold text-slate-500 truncate">{{ $rt->subject }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                Requester: {{ $rt->requester?->name ?? 'Staff' }} • {{ $rt->branch?->name ?? 'Branch' }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $rt->status === 'resolved_confirmed' || $rt->status === 'closed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst(str_replace('_', ' ', $rt->status)) }}
                            </span>
                            <div class="text-[9px] text-slate-400 mt-0.5">{{ $rt->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-400">No recent tickets logged.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Assets -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <h4 class="text-sm font-bold text-slate-900">Recent Equipment & Assets Registered</h4>
                <a href="{{ route('portal.assets.index') }}" class="text-xs font-bold text-blue-600 hover:underline">View All Assets →</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recentAssets as $ra)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="space-y-0.5 truncate">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900">{{ $ra->asset_tag }}</span>
                                <span class="text-[10px] font-semibold text-slate-500 truncate">{{ $ra->name }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                {{ $ra->category?->name ?? 'Hardware' }} • {{ $ra->branch?->name ?? 'Branch' }} • 
                                {{ $ra->assignedUser ? 'Assigned to ' . $ra->assignedUser->name : 'Shared / Room' }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $ra->status === 'in_use' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                {{ ucfirst(str_replace('_', ' ', $ra->status)) }}
                            </span>
                            @if($canViewFinancials)
                                <div class="text-[9px] text-slate-400 mt-0.5">${{ number_format($ra->purchase_cost, 2) }}</div>
                            @else
                                <div class="text-[9px] text-slate-400 mt-0.5">{{ $ra->brand ?? 'Asset' }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-400">No assets registered yet.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
