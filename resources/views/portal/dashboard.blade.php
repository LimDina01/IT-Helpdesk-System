@extends('portal.layout')

@section('title', 'Ticket Operations')

@section('content')
<div x-data="{
    assignModal: false,
    selectedTicket: null,
    targetTechId: '',
    targetPriority: '',
    assignNote: '',
    clientSearch: '{{ addslashes($searchQuery ?? '') }}',
    autoRefreshSecs: 30,
    timer: null,
    isPaused: false,
    isRefreshing: false,
    toastMsg: '',
    toastTimer: null,
    init() {
        this.startTimer();
        window.addEventListener('keydown', (e) => {
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                this.$refs.searchInput?.focus();
            }
        });
    },
    startTimer() {
        if (this.timer) clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (this.isPaused || this.assignModal || this.isInputActive() || this.clientSearch) {
                return;
            }
            if (this.autoRefreshSecs > 1) {
                this.autoRefreshSecs--;
            } else {
                this.refreshLive(false);
            }
        }, 1000);
    },
    isInputActive() {
        const active = document.activeElement;
        return active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
    },
    togglePause() {
        this.isPaused = !this.isPaused;
        if (!this.isPaused) {
            this.autoRefreshSecs = 30;
        }
        this.showToast(this.isPaused ? '⏸️ Auto-refresh paused' : '▶️ Auto-refresh resumed');
    },
    async refreshLive(showFeedback = false) {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        try {
            const url = new URL(window.location.href);
            const res = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (res.ok) {
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newKpis = doc.getElementById('kpiCardsContainer');
                const curKpis = document.getElementById('kpiCardsContainer');
                if (newKpis && curKpis) curKpis.innerHTML = newKpis.innerHTML;
                
                const newTable = doc.getElementById('ticketTableContainer');
                const curTable = document.getElementById('ticketTableContainer');
                if (newTable && curTable) curTable.innerHTML = newTable.innerHTML;
                
                this.autoRefreshSecs = 30;
                if (showFeedback) this.showToast('✅ Dashboard updated with latest data');
            } else if (showFeedback) {
                window.location.reload();
            }
        } catch (e) {
            if (showFeedback) window.location.reload();
        } finally {
            this.isRefreshing = false;
        }
    },
    matchesSearch(el) {
        const q = (this.clientSearch || '').toLowerCase().trim();
        if (!q) return true;
        const haystack = (el.dataset.search || '').toLowerCase();
        return haystack.includes(q);
    },
    copyCode(code) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(() => {
                this.showToast('📋 Copied ' + code + ' to clipboard');
            });
        }
    },
    showToast(msg) {
        this.toastMsg = msg;
        if (this.toastTimer) clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toastMsg = ''; }, 3000);
    }
}" x-init="init()" class="space-y-6">

    <!-- Big Queue Navigation & KPI Cards Container (Dynamically Swappable via AJAX) -->
    <div id="kpiCardsContainer" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 xl:grid-cols-8 gap-3">
        
        <!-- All Active Tickets -->
        <a href="{{ route('portal.dashboard', ['view' => 'all_active', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'all_active' ? 'ring-2 ring-slate-800 bg-slate-100 border-slate-800' : 'bg-white hover:border-slate-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-700 group-hover:text-slate-900">All Active</div>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $allActiveCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">Running queue</div>
        </a>

        <!-- Triage Queue -->
        <a href="{{ route('portal.dashboard', ['view' => 'triage', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'triage' ? 'ring-2 ring-blue-600 bg-blue-50/70 border-blue-600' : 'bg-white hover:border-blue-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="flex items-center justify-between">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-blue-600">Triage Queue</div>
                @if(isset($reopenedCount) && $reopenedCount > 0)
                    <span class="bg-amber-100 text-amber-800 text-[9px] font-black px-1.5 py-0.5 rounded-full border border-amber-300 animate-pulse">
                        {{ $reopenedCount }} Reopened
                    </span>
                @endif
            </div>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $triageCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">{{ isset($reopenedCount) && $reopenedCount > 0 ? 'Unassigned & Reopened' : 'Unassigned' }}</div>
        </a>

        <!-- Approvals -->
        <a href="{{ route('portal.dashboard', ['view' => 'pending_approvals', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'pending_approvals' ? 'ring-2 ring-purple-600 bg-purple-50/70 border-purple-600' : 'bg-white hover:border-purple-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-purple-700">Approvals</div>
            <div class="text-2xl font-black text-purple-700 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">HOD & IT sign-off</div>
        </a>

        <!-- My Tasks -->
        <a href="{{ route('portal.dashboard', ['view' => 'my_tasks', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'my_tasks' ? 'ring-2 ring-indigo-600 bg-indigo-50/70 border-indigo-600' : 'bg-white hover:border-indigo-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-indigo-600">My Tasks</div>
            <div class="text-2xl font-black text-indigo-600 mt-1">{{ $myTasksCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">Assigned to you</div>
        </a>

        <!-- Blocked / Hold -->
        <a href="{{ route('portal.dashboard', ['view' => 'blocked', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'blocked' ? 'ring-2 ring-amber-600 bg-amber-50/70 border-amber-600' : 'bg-white hover:border-amber-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-amber-600">Blocked</div>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $blockedCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">Vendor / parts hold</div>
        </a>

        <!-- Pending Confirmation (Clickable Queue) -->
        <a href="{{ route('portal.dashboard', ['view' => 'pending_confirmation', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'pending_confirmation' ? 'ring-2 ring-emerald-600 bg-emerald-50/70 border-emerald-600' : 'bg-white hover:border-emerald-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-emerald-700">Sign-off Window</div>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $pendingConfirmCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">Awaiting user check</div>
        </a>

        <!-- Solved / Closed Tickets (Clickable Queue) -->
        <a href="{{ route('portal.dashboard', ['view' => 'solved', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'solved' ? 'ring-2 ring-teal-600 bg-teal-50/70 border-teal-600' : 'bg-white hover:border-teal-400' }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs transition block group">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-teal-700">Solved / Closed</div>
            <div class="text-2xl font-black text-teal-700 mt-1">{{ $solvedCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">Completed tasks</div>
        </a>

        <!-- 30m SLA Breaches (Clickable Queue) -->
        <a href="{{ route('portal.dashboard', ['view' => 'sla_breached', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
           class="{{ $viewTab === 'sla_breached' ? 'ring-2 ring-red-600 bg-red-100/90 border-red-600' : ($slaBreachCount > 0 ? 'border-red-300 bg-red-50/60 ring-1 ring-red-400 hover:border-red-500' : 'bg-white hover:border-red-400') }} p-3.5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden block group transition">
            <div class="text-[10px] font-bold uppercase tracking-wider {{ $slaBreachCount > 0 || $viewTab === 'sla_breached' ? 'text-red-700' : 'text-slate-500 group-hover:text-red-600' }}">SLA Breaches</div>
            <div class="text-2xl font-black {{ $slaBreachCount > 0 || $viewTab === 'sla_breached' ? 'text-red-600 animate-pulse' : 'text-slate-800' }} mt-1">
                {{ $slaBreachCount }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5 truncate">>30m unaccepted</div>
            @if($slaBreachCount > 0)
                <span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
            @endif
        </a>
    </div>

    <!-- Main Workspace Container -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">

        <!-- Filter Controls & Live Controls Toolbar -->
        <div class="px-5 py-3.5 border-b border-slate-200/80 bg-white flex flex-col md:flex-row md:items-center justify-between gap-3">
            
            <!-- Instant Client & Server Search Bar -->
            <form method="GET" action="{{ route('portal.dashboard') }}" class="relative w-full md:w-80">
                <input type="hidden" name="view" value="{{ $viewTab }}">
                @if($branchFilter)<input type="hidden" name="branch_id" value="{{ $branchFilter }}">@endif
                @if($priorityFilter)<input type="hidden" name="priority" value="{{ $priorityFilter }}">@endif
                
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text"
                       name="q"
                       x-ref="searchInput"
                       x-model="clientSearch"
                       placeholder="Filter tickets... (press /)"
                       class="w-full text-xs pl-9 pr-7 py-2 rounded-xl border border-slate-200 bg-slate-50/70 placeholder-slate-400 font-medium focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                <button type="button"
                        x-show="clientSearch"
                        @click="clientSearch = ''; $nextTick(() => $refs.searchInput.focus())"
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600"
                        x-cloak>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </form>

            <!-- Dropdown Filters & Live Auto-Refresh Controller -->
            <div class="flex items-center space-x-2.5 flex-wrap gap-y-2">
                <form method="GET" action="{{ route('portal.dashboard') }}" class="flex items-center space-x-2">
                    <input type="hidden" name="view" value="{{ $viewTab }}">
                    @if($searchQuery)<input type="hidden" name="q" value="{{ $searchQuery }}">@endif

                    <select name="branch_id" onchange="this.form.submit()" class="text-xs px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/70 font-medium focus:bg-white focus:ring-2 focus:ring-blue-500 shadow-2xs">
                        <option value="">All Branches</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ $branchFilter == $b->id ? 'selected' : '' }}>{{ $b->code }} - {{ $b->name }}</option>
                        @endforeach
                    </select>

                    <select name="priority" onchange="this.form.submit()" class="text-xs px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/70 font-medium focus:bg-white focus:ring-2 focus:ring-blue-500 shadow-2xs">
                        <option value="">All Priorities</option>
                        <option value="critical" {{ $priorityFilter == 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="high" {{ $priorityFilter == 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ $priorityFilter == 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ $priorityFilter == 'low' ? 'selected' : '' }}>Low</option>
                    </select>

                    @if($branchFilter || $priorityFilter || $searchQuery)
                        <a href="{{ route('portal.dashboard', ['view' => $viewTab]) }}" class="text-xs text-rose-600 hover:underline px-1.5 font-semibold">Clear</a>
                    @endif
                </form>

                <div class="w-px h-5 bg-slate-200 hidden sm:block"></div>

                <!-- Live Auto-Refresh Controller -->
                <div class="flex items-center space-x-1.5 bg-slate-100 border border-slate-200/80 px-2.5 py-1.5 rounded-xl shadow-2xs text-[11px] font-semibold text-slate-700">
                    <button type="button"
                            @click="refreshLive(true)"
                            :disabled="isRefreshing"
                            class="hover:text-blue-600 transition flex items-center space-x-1"
                            title="Refresh now without reloading page">
                        <svg class="w-3.5 h-3.5 text-slate-500" :class="{ 'animate-spin text-blue-600': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                    <div class="w-px h-3 bg-slate-300"></div>
                    <button type="button"
                            @click="togglePause()"
                            class="flex items-center space-x-1.5 hover:text-slate-900 transition"
                            :title="isPaused ? 'Auto-refresh is paused. Click to resume.' : 'Auto-refresh is active. Click to pause.'">
                        <span class="w-2 h-2 rounded-full"
                              :class="isPaused ? 'bg-amber-400' : 'bg-emerald-500 animate-pulse'"></span>
                        <span x-show="!isPaused" x-text="autoRefreshSecs + 's'"></span>
                        <span x-show="isPaused" class="text-amber-700 font-bold">Paused</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- SLA Breach Context Banner -->
        @if($viewTab === 'sla_breached')
            <div class="bg-red-50/90 border-b border-red-200 px-5 py-3 flex items-center justify-between text-xs text-red-900">
                <div class="flex items-center space-x-2.5">
                    <span class="text-lg">⚠️</span>
                    <div>
                        <span class="font-bold">SLA Breach Queue:</span>
                        <span class="text-red-700">These assigned tickets have exceeded the 30-minute acknowledgement window without being accepted by the assigned technician.</span>
                    </div>
                </div>
                <a href="{{ route('portal.dashboard') }}" class="text-red-700 hover:text-red-900 font-bold underline whitespace-nowrap ml-4">Back to Triage</a>
            </div>
        @endif

        <!-- Ticket Table Container -->
        <div id="ticketTableContainer" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-3">Ticket</th>
                        <th class="py-2.5 px-3">Subject & Details</th>
                        <th class="py-2.5 px-3">Requester / Branch</th>
                        <th class="py-2.5 px-3">Technician</th>
                        <th class="py-2.5 px-3">Status & SLA</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $t)
                        <tr class="group hover:bg-slate-50/70 transition"
                            data-search="{{ strtolower($t->ticket_code . ' ' . $t->subject . ' ' . $t->requester->name . ' ' . $t->branch->name . ' ' . $t->branch->code . ' ' . ($t->technician->name ?? '') . ' ' . ($t->category->name ?? '') . ' ' . $t->priority . ' ' . $t->status) }}"
                            x-show="matchesSearch($el)">
                            
                            <!-- Ticket Code & Priority -->
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <div class="flex items-center space-x-1">
                                    <a href="{{ route('portal.tickets.show', $t->id) }}" class="font-mono font-bold text-blue-600 hover:underline text-xs">
                                        {{ $t->ticket_code }}
                                    </a>
                                    <button type="button"
                                            @click.prevent.stop="copyCode('{{ $t->ticket_code }}')"
                                            class="opacity-0 group-hover:opacity-100 hover:text-blue-700 text-slate-400 transition"
                                            title="Copy ticket code">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                                <div class="mt-0.5">
                                    @if($t->priority === 'critical')
                                        <span class="bg-red-600 text-white font-bold text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider">Critical</span>
                                    @elseif($t->priority === 'high')
                                        <span class="bg-orange-500 text-white font-bold text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider">High</span>
                                    @elseif($t->priority === 'medium')
                                        <span class="bg-blue-100 text-blue-800 font-semibold text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider">Medium</span>
                                    @else
                                        <span class="bg-slate-100 text-slate-700 font-semibold text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider">Low</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Subject & Category -->
                            <td class="py-2.5 px-3 min-w-[150px] max-w-[240px]">
                                <div class="flex items-center space-x-1 mb-0.5">
                                    @if($t->isIncident())
                                        <span class="text-[9px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-1 py-0.2 rounded leading-tight">🔴 Incident</span>
                                    @else
                                        <span class="text-[9px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1 py-0.2 rounded leading-tight">🔵 Service Request</span>
                                    @endif
                                </div>
                                <a href="{{ route('portal.tickets.show', $t->id) }}" class="font-bold text-slate-800 hover:text-blue-600 line-clamp-1 text-xs">
                                    {{ $t->subject }}
                                </a>
                                <div class="text-[10px] text-slate-400 truncate mt-0.5">
                                    {{ $t->category->name ?? 'Uncategorized' }}
                                </div>
                            </td>

                            <!-- Requester & Location -->
                            <td class="py-2.5 px-3">
                                <div class="font-semibold text-slate-700 text-xs truncate max-w-[120px] 2xl:max-w-none" title="{{ $t->requester->name }}">{{ $t->requester->name }}</div>
                                <div class="text-[10px] text-slate-500 flex items-center space-x-1 mt-0.5">
                                    <span class="font-bold text-slate-700">{{ $t->branch->code }}</span>
                                    <span class="text-slate-400 truncate max-w-[90px] 2xl:max-w-none" title="{{ $t->branch->name }}">• {{ $t->branch->name }}</span>
                                </div>
                            </td>

                            <!-- Technician Assignment -->
                            <td class="py-2.5 px-3">
                                @if($t->technician)
                                    <div class="font-semibold text-slate-800 text-xs flex items-center space-x-1">
                                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></div>
                                        <span class="truncate max-w-[120px] 2xl:max-w-none" title="{{ $t->technician->name }}">{{ $t->technician->name }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        Assigned {{ $t->assigned_at ? $t->assigned_at->diffForHumans(null, true) : '' }}
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium bg-amber-50 px-1.5 py-0.5 rounded text-[10px]">
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            <!-- Status & Live SLA Tracking -->
                            <td class="py-2.5 px-3">
                                @php
                                    $ackBreached = false;
                                    $ackRemainingMins = null;
                                    $minsElapsed = 0;
                                    if ($t->status === 'assigned' && !$t->acknowledged_at && $t->assigned_at) {
                                        $minsElapsed = abs((int) now()->diffInMinutes($t->assigned_at));
                                        if ($minsElapsed > 30) {
                                            $ackBreached = true;
                                        } else {
                                            $ackRemainingMins = 30 - $minsElapsed;
                                        }
                                    }

                                    $targetResolveHours = $t->category?->target_resolve_hours;
                                    $hoursElapsed = abs((int) now()->diffInHours($t->created_at));
                                    $hoursRemaining = $targetResolveHours ? ($targetResolveHours - $hoursElapsed) : null;
                                @endphp

                                <div class="flex flex-col space-y-0.5">
                                    <div class="flex items-center flex-wrap gap-1">
                                        @if($t->status === 'pending_approval')
                                            <span class="bg-purple-100 text-purple-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase animate-pulse">Approval</span>
                                        @elseif($t->status === 'rejected')
                                            <span class="bg-rose-100 text-rose-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Rejected</span>
                                        @elseif($t->status === 'submitted')
                                            <span class="bg-blue-100 text-blue-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Triage</span>
                                        @elseif($t->status === 'assigned')
                                            <span class="bg-purple-100 text-purple-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Awaiting Accept</span>
                                        @elseif($t->status === 'in_progress')
                                            <span class="bg-indigo-100 text-indigo-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">In Progress</span>
                                        @elseif($t->status === 'clarifying')
                                            <span class="bg-amber-100 text-amber-900 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Needs Info</span>
                                        @elseif($t->status === 'blocked')
                                            <span class="bg-red-100 text-red-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Blocked</span>
                                        @elseif($t->status === 'pending_confirmation')
                                            <span class="bg-emerald-100 text-emerald-900 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Pending Confirm</span>
                                        @elseif($t->status === 'reopened')
                                            <span class="bg-amber-100 text-amber-900 border border-amber-300 font-extrabold px-1.5 py-0.5 rounded text-[9px] uppercase flex items-center space-x-1 animate-pulse">
                                                <span>⚠️</span>
                                                <span>Reopened</span>
                                            </span>
                                        @elseif($t->status === 'resolved_confirmed' || $t->status === 'resolved_auto_closed')
                                            <span class="bg-teal-100 text-teal-800 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">✔ Solved</span>
                                        @elseif($t->status === 'closed')
                                            <span class="bg-slate-100 text-slate-700 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">Closed</span>
                                        @else
                                            <span class="bg-slate-100 text-slate-700 font-bold px-1.5 py-0.5 rounded text-[9px] uppercase">{{ $t->status }}</span>
                                        @endif

                                        <!-- Live 30m Ack SLA Badge -->
                                        @if($ackBreached)
                                            <span class="bg-red-600 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.5 rounded-full animate-pulse flex items-center space-x-0.5" title="30-minute tech acceptance SLA breached!">
                                                <span>⚠️</span>
                                                <span>Overdue Ack (+{{ $minsElapsed }}m)</span>
                                            </span>
                                        @elseif($ackRemainingMins !== null)
                                            <span class="bg-blue-50 text-blue-700 border border-blue-200 font-bold text-[9px] uppercase px-1.5 py-0.5 rounded-full flex items-center space-x-0.5" title="Acknowledgement target deadline">
                                                <span>⏱️</span>
                                                <span>{{ $ackRemainingMins }}m left</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Target Resolution SLA Info -->
                                    <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                        <span>Age: {{ $t->created_at->diffForHumans(null, true) }}</span>
                                        @if($hoursRemaining !== null && in_array($t->status, ['assigned', 'in_progress', 'blocked', 'reopened']))
                                            <span>•</span>
                                            @if($hoursRemaining < 0)
                                                <span class="text-rose-600 font-bold">Overdue (+{{ abs($hoursRemaining) }}h)</span>
                                            @elseif($hoursRemaining <= 8)
                                                <span class="text-amber-600 font-bold">Due in {{ $hoursRemaining }}h</span>
                                            @else
                                                <span>Target: {{ $targetResolveHours }}h</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end space-x-1">
                                    <!-- Technician or Admin Quick Accept or Resume Button -->
                                    @if(in_array($t->status, ['assigned', 'reopened']) && ($t->current_technician_id === Auth::id() || Auth::user()->hasAnyRole(['system_admin', 'hod'])))
                                        <form method="POST" action="{{ route('portal.tickets.accept', $t->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-2 py-1 rounded-lg shadow-2xs transition" title="{{ $t->status === 'reopened' ? 'Resume work on this reopened ticket' : 'Accept & Start this ticket' }}">
                                                {{ $t->status === 'reopened' ? 'Resume' : 'Accept' }}
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Assign / Reassign Modal Button -->
                                    @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                                        <button type="button"
                                                @click="selectedTicket = { id: {{ $t->id }}, code: '{{ $t->ticket_code }}', priority: '{{ $t->priority }}' }; targetPriority = '{{ $t->priority }}'; assignModal = true"
                                                class="bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 font-semibold text-[11px] px-2 py-1 rounded-lg border border-slate-200 transition">
                                            {{ $t->current_technician_id ? 'Reassign' : 'Assign' }}
                                        </button>
                                    @endif

                                    <!-- Open Workspace -->
                                    <a href="{{ route('portal.tickets.show', $t->id) }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-[11px] px-2.5 py-1 rounded-lg shadow-2xs transition">
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                @if($viewTab === 'sla_breached')
                                    <div class="text-2xl mb-2">🎉</div>
                                    <div class="font-semibold text-sm text-emerald-700">No SLA Breaches</div>
                                    <div class="text-xs text-slate-400">All assigned tickets have been accepted by technicians within the 30-minute window!</div>
                                @else
                                    <div class="text-2xl mb-2">🎉</div>
                                    <div class="font-semibold text-sm text-slate-600">No tickets found in this queue</div>
                                    <div class="text-xs text-slate-400">All submissions in this category have been triaged or addressed.</div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($tickets->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    <!-- QUICK ASSIGNMENT MODAL -->
    <div x-show="assignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="assignModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 p-6 space-y-5">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Assign Ticket</h3>
                    <div class="text-xs font-mono font-bold text-blue-600" x-text="selectedTicket?.code"></div>
                </div>
                <button @click="assignModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-xl">&times;</button>
            </div>

            <form :action="'/portal/tickets/' + selectedTicket?.id + '/assign'" method="POST" class="space-y-4">
                @csrf

                <!-- Technician Picker -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Technician</label>
                    <select name="technician_id" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-600">
                        <option value="">-- Choose Technical Specialist --</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}">
                                {{ $tech->name }} ({{ $tech->active_tasks_count }} active tasks)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Priority Override -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Priority Level</label>
                    <select name="priority" x-model="targetPriority" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-600">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <!-- Assignment Note -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Assignment Note (Optional)</label>
                    <textarea name="assignment_note" rows="2" placeholder="e.g. Please bring a spare thermal paper roll and test cable..."
                              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-600"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button type="button" @click="assignModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FLOATING TOAST NOTIFICATION -->
    <div x-show="toastMsg"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="fixed bottom-6 right-6 z-50 bg-slate-900/95 text-white px-4 py-2.5 rounded-2xl shadow-2xl flex items-center space-x-2.5 text-xs font-semibold border border-slate-700/80 backdrop-blur-xs"
         x-cloak>
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

</div>
@endsection
