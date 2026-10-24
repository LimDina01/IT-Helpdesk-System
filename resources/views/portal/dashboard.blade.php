@extends('portal.layout')

@section('title', 'Ticket Operations')

@section('content')
<div x-data="{
    assignModal: false,
    selectedTicket: null,
    targetTechId: '',
    targetPriority: '',
    assignNote: '',
    initAutoRefresh() {
        setInterval(() => {
            if (!this.assignModal) window.location.reload();
        }, 30000);
    }
}" x-init="initAutoRefresh()" class="space-y-6">

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        
        <!-- Triage Queue -->
        <a href="{{ route('portal.dashboard', ['view' => 'triage']) }}"
           class="{{ $viewTab === 'triage' ? 'ring-2 ring-blue-600 bg-blue-50/50' : 'bg-white' }} p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-blue-400 transition block">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Triage Queue</div>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $triageCount }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Unassigned submissions</div>
        </a>

        <!-- Pending Approvals -->
        <a href="{{ route('portal.dashboard', ['view' => 'pending_approvals']) }}"
           class="{{ $viewTab === 'pending_approvals' ? 'ring-2 ring-purple-600 bg-purple-50/50' : 'bg-white' }} p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-purple-400 transition block">
            <div class="text-[11px] font-bold uppercase tracking-wider text-purple-700">Approvals</div>
            <div class="text-2xl font-black text-purple-700 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">HOD & IT sign-offs</div>
        </a>

        <!-- My Tasks -->
        <a href="{{ route('portal.dashboard', ['view' => 'my_tasks']) }}"
           class="{{ $viewTab === 'my_tasks' ? 'ring-2 ring-indigo-600 bg-indigo-50/50' : 'bg-white' }} p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-indigo-400 transition block">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">My Tasks</div>
            <div class="text-2xl font-black text-indigo-600 mt-1">{{ $myTasksCount }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Assigned to you</div>
        </a>

        <!-- 30m SLA Breaches -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">30m SLA Breaches</div>
            <div class="text-2xl font-black {{ $slaBreachCount > 0 ? 'text-red-600 animate-pulse' : 'text-slate-800' }} mt-1">
                {{ $slaBreachCount }}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Awaiting tech accept >30m</div>
            @if($slaBreachCount > 0)
                <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-600"></span>
            @endif
        </div>

        <!-- Blocked / Hold -->
        <a href="{{ route('portal.dashboard', ['view' => 'blocked']) }}"
           class="{{ $viewTab === 'blocked' ? 'ring-2 ring-amber-600 bg-amber-50/50' : 'bg-white' }} p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 transition block">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Blocked / Hold</div>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $blockedCount }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Parts or vendor hold</div>
        </a>

        <!-- Pending Confirmation -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pending Sign-off</div>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $pendingConfirmCount }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Requester review window</div>
        </div>
    </div>

    <!-- Main Workspace Container -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">

        <!-- Tab & Filter Bar -->
        <div class="p-4 sm:p-5 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <!-- Queue Tabs -->
            <div class="flex items-center space-x-1 bg-slate-100 p-1 rounded-xl text-xs font-semibold overflow-x-auto">
                <a href="{{ route('portal.dashboard', ['view' => 'triage', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
                   class="{{ $viewTab === 'triage' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg whitespace-nowrap transition">
                    Triage Queue ({{ $triageCount }})
                </a>
                <a href="{{ route('portal.dashboard', ['view' => 'pending_approvals', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
                   class="{{ $viewTab === 'pending_approvals' ? 'bg-white text-purple-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg whitespace-nowrap transition flex items-center space-x-1">
                    <span>⏳</span>
                    <span>Approvals ({{ $pendingApprovalsCount }})</span>
                </a>
                <a href="{{ route('portal.dashboard', ['view' => 'my_tasks', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
                   class="{{ $viewTab === 'my_tasks' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg whitespace-nowrap transition">
                    My Tasks ({{ $myTasksCount }})
                </a>
                <a href="{{ route('portal.dashboard', ['view' => 'all_active', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
                   class="{{ $viewTab === 'all_active' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg whitespace-nowrap transition">
                    All Active Tickets
                </a>
                <a href="{{ route('portal.dashboard', ['view' => 'blocked', 'branch_id' => $branchFilter, 'priority' => $priorityFilter]) }}"
                   class="{{ $viewTab === 'blocked' ? 'bg-white text-amber-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg whitespace-nowrap transition">
                    Blocked / On Hold
                </a>
            </div>

            <!-- Filter Controls -->
            <form method="GET" action="{{ route('portal.dashboard') }}" class="flex items-center space-x-2">
                <input type="hidden" name="view" value="{{ $viewTab }}">

                <select name="branch_id" onchange="this.form.submit()" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-medium focus:ring-2 focus:ring-blue-500">
                    <option value="">All Branches</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchFilter == $b->id ? 'selected' : '' }}>{{ $b->code }} - {{ $b->name }}</option>
                    @endforeach
                </select>

                <select name="priority" onchange="this.form.submit()" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-medium focus:ring-2 focus:ring-blue-500">
                    <option value="">All Priorities</option>
                    <option value="critical" {{ $priorityFilter == 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="high" {{ $priorityFilter == 'high' ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ $priorityFilter == 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ $priorityFilter == 'low' ? 'selected' : '' }}>Low</option>
                </select>

                @if($branchFilter || $priorityFilter)
                    <a href="{{ route('portal.dashboard', ['view' => $viewTab]) }}" class="text-xs text-rose-600 hover:underline px-1 font-semibold">Clear</a>
                @endif

                <div class="hidden sm:flex items-center space-x-1.5 text-[10px] text-slate-500 font-semibold px-2 py-1 bg-slate-100 rounded-lg" title="Live dashboard auto-refreshes every 30 seconds">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live 30s</span>
                </div>
            </form>
        </div>

        <!-- Ticket Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Ticket</th>
                        <th class="py-3 px-4">Subject & Details</th>
                        <th class="py-3 px-4">Requester / Branch</th>
                        <th class="py-3 px-4">Technician</th>
                        <th class="py-3 px-4">Status & SLA</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $t)
                        <tr class="hover:bg-slate-50/70 transition">
                            
                            <!-- Ticket Code & Priority -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <a href="{{ route('portal.tickets.show', $t->id) }}" class="font-mono font-bold text-blue-600 hover:underline">
                                    {{ $t->ticket_code }}
                                </a>
                                <div class="mt-1">
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
                            <td class="py-3.5 px-4 max-w-sm">
                                <div class="flex items-center space-x-1.5 mb-1">
                                    @if($t->isIncident())
                                        <span class="text-[9px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.2 rounded">🔴 Incident</span>
                                    @else
                                        <span class="text-[9px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.2 rounded">🔵 Service Request</span>
                                    @endif
                                </div>
                                <a href="{{ route('portal.tickets.show', $t->id) }}" class="font-bold text-slate-800 hover:text-blue-600 line-clamp-1">
                                    {{ $t->subject }}
                                </a>
                                <div class="text-[11px] text-slate-500 flex items-center space-x-1.5 mt-0.5">
                                    <span>{{ $t->category->name ?? 'Uncategorized' }}</span>
                                    @if($t->category->parent)
                                        <span class="text-slate-400">({{ $t->category->parent->name }})</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Requester & Location -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-700">{{ $t->requester->name }}</div>
                                <div class="text-[11px] text-slate-500 flex items-center space-x-1 mt-0.5">
                                    <span class="font-medium text-slate-600">{{ $t->branch->code }}</span>
                                    <span>•</span>
                                    <span>{{ $t->branch->name }}</span>
                                </div>
                            </td>

                            <!-- Technician Assignment -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($t->technician)
                                    <div class="font-semibold text-slate-800 flex items-center space-x-1">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <span>{{ $t->technician->name }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        Assigned {{ $t->assigned_at ? $t->assigned_at->diffForHumans() : '' }}
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium bg-amber-50 px-2 py-0.5 rounded text-[11px]">
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            <!-- Status & 30m SLA -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @php
                                    $isBreached = $t->status === 'assigned' && !$t->acknowledged_at && $t->assigned_at && now()->diffInMinutes($t->assigned_at) > 30;
                                @endphp

                                <div class="flex items-center space-x-1.5">
                                    @if($t->status === 'pending_approval')
                                        <span class="bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase animate-pulse">Pending Approval</span>
                                    @elseif($t->status === 'rejected')
                                        <span class="bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Rejected</span>
                                    @elseif($t->status === 'submitted')
                                        <span class="bg-blue-100 text-blue-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Triage</span>
                                    @elseif($t->status === 'assigned')
                                        <span class="bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Awaiting Accept</span>
                                    @elseif($t->status === 'in_progress')
                                        <span class="bg-indigo-100 text-indigo-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">In Progress</span>
                                    @elseif($t->status === 'clarifying')
                                        <span class="bg-amber-100 text-amber-900 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Needs Info</span>
                                    @elseif($t->status === 'blocked')
                                        <span class="bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Blocked</span>
                                    @elseif($t->status === 'pending_confirmation')
                                        <span class="bg-emerald-100 text-emerald-900 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Pending Confirm</span>
                                    @elseif($t->status === 'reopened')
                                        <span class="bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Reopened</span>
                                    @else
                                        <span class="bg-slate-100 text-slate-700 font-bold px-2 py-0.5 rounded text-[10px] uppercase">{{ $t->status }}</span>
                                    @endif

                                    @if($isBreached)
                                        <span class="bg-red-600 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.5 rounded animate-pulse" title="Acknowledgement target (30 min) breached!">
                                            Overdue Ack ({{ now()->diffInMinutes($t->assigned_at) }}m)
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-400 mt-1">Age: {{ $t->created_at->diffForHumans() }}</div>
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end space-x-1.5">
                                    
                                    <!-- Technician Quick Accept Button -->
                                    @if($t->status === 'assigned' && $t->current_technician_id === Auth::id())
                                        <form method="POST" action="{{ route('portal.tickets.accept', $t->id) }}">
                                            @csrf
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-2.5 py-1.5 rounded-lg shadow-xs transition">
                                                Accept & Start
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Assign Modal Button -->
                                    @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                                        <button type="button"
                                                @click="selectedTicket = { id: {{ $t->id }}, code: '{{ $t->ticket_code }}', priority: '{{ $t->priority }}' }; targetPriority = '{{ $t->priority }}'; assignModal = true"
                                                class="bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 font-semibold text-xs px-2.5 py-1.5 rounded-lg border border-slate-200 transition">
                                            Assign
                                        </button>
                                    @endif

                                    <!-- Open Workspace -->
                                    <a href="{{ route('portal.tickets.show', $t->id) }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-3 py-1.5 rounded-lg shadow-xs transition">
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <div class="text-2xl mb-2">🎉</div>
                                <div class="font-semibold text-sm text-slate-600">No tickets found in this queue</div>
                                <div class="text-xs text-slate-400">All submissions in this category have been triaged or addressed.</div>
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

</div>
@endsection
