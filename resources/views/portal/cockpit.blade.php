@extends('portal.layout')

@section('title', 'HOD Operations Cockpit')

@section('content')
<div x-data="{ priorityModal: false, targetTicketId: null, targetTicketCode: '', newPriority: 'critical', overrideReason: '' }" class="space-y-6">

    <!-- Executive Header -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center space-x-2 text-xs text-indigo-300 font-bold tracking-wider uppercase">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                <span>Live Management Cockpit</span>
                <span>•</span>
                <span>CBVH Head of IT Operations</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black mt-2 tracking-tight">Technical Operations & Intervention Cockpit</h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                Real-time oversight of technician capacity, SLA compliance breaches, blocked procurement items, and multi-stage service approvals.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('portal.dashboard') }}" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl border border-slate-700 transition flex items-center space-x-1.5 shadow-sm">
                <span>📋</span>
                <span>Standard Queues</span>
            </a>
            <a href="{{ route('portal.admin.index') }}" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center space-x-1.5 shadow-sm">
                <span>⚙️</span>
                <span>Admin Settings</span>
            </a>
        </div>
    </div>

    <!-- Executive Intervention KPIs (Clickable Drill-Down) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        
        <!-- 30m SLA Breaches -->
        <a href="{{ route('portal.dashboard', ['view' => 'sla_breached']) }}" 
           class="group bg-white hover:bg-rose-50/50 rounded-2xl p-4 border {{ $slaBreachCount > 0 ? 'border-rose-300 ring-1 ring-rose-200' : 'border-slate-200/80' }} hover:border-rose-400 hover:shadow-md transition-all duration-150 relative overflow-hidden block">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-rose-600">30m SLA Breaches</div>
                <span class="text-xs text-rose-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="text-3xl font-black {{ $slaBreachCount > 0 ? 'text-rose-600 animate-pulse' : 'text-slate-800' }} mt-1">
                {{ $slaBreachCount }}
            </div>
            <div class="text-[10px] text-slate-400 group-hover:text-rose-700 mt-0.5">Awaiting tech accept >30m</div>
            @if($slaBreachCount > 0)
                <span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>
            @endif
        </a>

        <!-- Blocked / Hold -->
        <a href="{{ route('portal.dashboard', ['view' => 'blocked']) }}" 
           class="group bg-white hover:bg-amber-50/50 rounded-2xl p-4 border border-slate-200/80 hover:border-amber-400 hover:shadow-md transition-all duration-150 block relative">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-amber-600">Blocked / Hold</div>
                <span class="text-xs text-amber-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="flex items-baseline space-x-2 mt-1">
                <span class="text-3xl font-black text-amber-600">{{ $blockedCount }}</span>
                @if(isset($pendingHoldCount) && $pendingHoldCount > 0)
                    <span class="text-[10px] font-bold bg-amber-500 text-slate-950 px-2 py-0.5 rounded-full animate-pulse" title="{{ $pendingHoldCount }} hold requests waiting for your approval">
                        ⏸ {{ $pendingHoldCount }} Approval Req
                    </span>
                @endif
            </div>
            <div class="text-[10px] text-slate-400 group-hover:text-amber-700 mt-0.5">
                @if(isset($pendingHoldCount) && $pendingHoldCount > 0)
                    {{ $pendingHoldCount }} hold approval pending
                @else
                    Vendor or procurement hold
                @endif
            </div>
        </a>

        <!-- Pending Approvals -->
        <a href="{{ route('portal.dashboard', ['view' => 'pending_approvals']) }}" 
           class="group bg-white hover:bg-purple-50/50 rounded-2xl p-4 border border-slate-200/80 hover:border-purple-400 hover:shadow-md transition-all duration-150 block">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700">Pending Approvals</div>
                <span class="text-xs text-purple-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="text-3xl font-black text-purple-700 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[10px] text-slate-400 group-hover:text-purple-700 mt-0.5">HOD & IT sign-off chain</div>
        </a>

        <!-- Escalated / Critical -->
        <a href="{{ route('portal.dashboard', ['view' => 'all_active', 'priority' => 'critical_high']) }}" 
           class="group bg-white hover:bg-red-50/50 rounded-2xl p-4 border border-slate-200/80 hover:border-red-400 hover:shadow-md transition-all duration-150 block">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-red-700">Critical / High</div>
                <span class="text-xs text-red-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="text-3xl font-black text-red-700 mt-1">{{ $escalatedCount }}</div>
            <div class="text-[10px] text-slate-400 group-hover:text-red-700 mt-0.5">Severe operational impact</div>
        </a>

        <!-- Reopened -->
        <a href="{{ route('portal.dashboard', ['view' => 'reopened']) }}" 
           class="group bg-white hover:bg-indigo-50/50 rounded-2xl p-4 border border-slate-200/80 hover:border-indigo-400 hover:shadow-md transition-all duration-150 block">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-700">Reopened Fixes</div>
                <span class="text-xs text-indigo-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="text-3xl font-black text-indigo-700 mt-1">{{ $reopenedCount }}</div>
            <div class="text-[10px] text-slate-400 group-hover:text-indigo-700 mt-0.5">Requester rejected resolution</div>
        </a>

        <!-- Unassigned Triage -->
        <a href="{{ route('portal.dashboard', ['view' => 'triage']) }}" 
           class="group bg-white hover:bg-blue-50/50 rounded-2xl p-4 border border-slate-200/80 hover:border-blue-400 hover:shadow-md transition-all duration-150 block">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-blue-600">Unassigned Backlog</div>
                <span class="text-xs text-blue-400 group-hover:translate-x-0.5 transition-transform font-bold">→</span>
            </div>
            <div class="text-3xl font-black text-blue-600 mt-1">{{ $unassignedTriageCount }}</div>
            <div class="text-[10px] text-slate-400 group-hover:text-blue-700 mt-0.5">New tickets needing tech</div>
        </a>
    </div>

    <!-- Live Technician Workload Distribution Board -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 flex items-center space-x-2">
                    <span>👥</span>
                    <span>Live Technician Workload & Capacity Board</span>
                </h2>
                <p class="text-xs text-slate-500">Real-time task distribution to prevent bottlenecks and spot idle capacity.</p>
            </div>
            <div class="text-xs font-semibold text-slate-500">
                Active in Fulfillment: <strong class="text-blue-600 font-mono">{{ $totalFulfillmentCount }}</strong> tickets
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($technicians as $tech)
                <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:shadow-xs transition space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-800 font-black text-xs flex items-center justify-center">
                                {{ substr($tech->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="font-bold text-xs text-slate-900 line-clamp-1">{{ $tech->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ $tech->email }}</div>
                            </div>
                        </div>
                        <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full border {{ $tech->load_badge }}">
                            {{ $tech->load_status }}
                        </span>
                    </div>

                    <!-- Workload Metrics -->
                    <div class="grid grid-cols-3 gap-2 text-center bg-white rounded-xl p-2.5 border border-slate-100">
                        <div>
                            <div class="text-base font-black text-slate-800">{{ $tech->active_tasks_count }}</div>
                            <div class="text-[9px] text-slate-400 font-bold uppercase">Active</div>
                        </div>
                        <div>
                            <div class="text-base font-black {{ $tech->overdue_sla_count > 0 ? 'text-red-600' : 'text-slate-800' }}">
                                {{ $tech->overdue_sla_count }}
                            </div>
                            <div class="text-[9px] text-slate-400 font-bold uppercase">SLA Breached</div>
                        </div>
                        <div>
                            <div class="text-base font-black text-emerald-600">{{ $tech->resolved_today_count }}</div>
                            <div class="text-[9px] text-slate-400 font-bold uppercase">Done Today</div>
                        </div>
                    </div>

                    <!-- Workload Progress Bar -->
                    <div>
                        <div class="flex justify-between text-[10px] font-semibold text-slate-500 mb-1">
                            <span>Capacity Load</span>
                            <span>{{ min(100, $tech->active_tasks_count * 20) }}%</span>
                        </div>
                        <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full {{ $tech->load_indicator }}" style="width: {{ min(100, $tech->active_tasks_count * 20) }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-xs text-slate-400">
                    No active technicians registered in the system.
                </div>
            @endforelse
        </div>
    </div>

    <!-- 2-Column Section: High-Attention Queue & Audit Trail Visualizer -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: High-Attention Queue -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center space-x-2">
                        <span>🚨</span>
                        <span>High-Attention Incident & Request Queue</span>
                    </h2>
                    <p class="text-xs text-slate-500">Tickets requiring executive awareness, approval, or priority adjustment.</p>
                </div>
                <span class="text-xs font-mono font-bold text-slate-400">{{ $urgentTickets->count() }} items</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Ticket</th>
                            <th class="py-2.5 px-3">Subject & Reason</th>
                            <th class="py-2.5 px-3">Technician</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($urgentTickets as $ticket)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <a href="{{ route('portal.tickets.show', $ticket->id) }}" class="font-mono font-bold text-blue-600 hover:underline">
                                        {{ $ticket->ticket_code }}
                                    </a>
                                    <div class="mt-0.5">
                                        @if($ticket->priority === 'critical')
                                            <span class="bg-red-600 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded">Critical</span>
                                        @elseif($ticket->priority === 'high')
                                            <span class="bg-orange-500 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded">High</span>
                                        @else
                                            <span class="bg-slate-100 text-slate-700 font-bold text-[9px] uppercase px-1.5 py-0.2 rounded">{{ $ticket->priority }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3 px-3 max-w-xs">
                                    <a href="{{ route('portal.tickets.show', $ticket->id) }}" class="font-bold text-slate-800 hover:text-blue-600 line-clamp-1">
                                        {{ $ticket->subject }}
                                    </a>
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        @if($ticket->isHoldPending())
                                            <span class="text-amber-800 font-bold">⏸ Hold Requested: {{ Str::limit($ticket->hold_requested_reason, 40) }}</span>
                                        @elseif($ticket->status === 'blocked')
                                            <span class="text-amber-700 font-semibold">🛑 Reason: {{ Str::limit($ticket->blocked_reason, 40) }}</span>
                                        @elseif($ticket->status === 'reopened')
                                            <span class="text-rose-700 font-semibold">🔄 Reopened by requester</span>
                                        @elseif($ticket->status === 'pending_approval')
                                            <span class="text-purple-700 font-semibold">⏳ Awaiting {{ $ticket->currentApproval()?->step_name ?? 'approval' }}</span>
                                        @else
                                            <span>{{ $ticket->requester->name }} • {{ $ticket->branch->code }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($ticket->technician)
                                        <span class="font-semibold text-slate-800">{{ $ticket->technician->name }}</span>
                                    @else
                                        <span class="text-amber-600 font-medium bg-amber-50 px-1.5 py-0.5 rounded text-[10px]">Unassigned</span>
                                    @endif
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($ticket->isHoldPending())
                                        <span class="bg-amber-100 text-amber-900 border border-amber-300 font-bold px-2 py-0.5 rounded text-[10px] uppercase flex items-center space-x-1 animate-pulse">
                                            <span>⏸</span>
                                            <span>Hold Approval</span>
                                        </span>
                                    @elseif($ticket->status === 'blocked')
                                        <span class="bg-amber-100 text-amber-900 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Blocked</span>
                                    @elseif($ticket->status === 'reopened')
                                        <span class="bg-rose-100 text-rose-900 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Reopened</span>
                                    @elseif($ticket->status === 'pending_approval')
                                        <span class="bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded text-[10px] uppercase">Pending Approval</span>
                                    @else
                                        <span class="bg-slate-100 text-slate-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">{{ str_replace('_', ' ', $ticket->status) }}</span>
                                    @endif
                                </td>

                                <td class="py-3 px-3 text-right whitespace-nowrap space-x-1">
                                    @if($ticket->isHoldPending())
                                        <a href="{{ route('portal.tickets.show', $ticket->id) }}"
                                           class="text-[11px] bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-2.5 py-1 rounded-lg shadow-xs transition">
                                            Review Hold &rarr;
                                        </a>
                                    @else
                                        <button type="button"
                                                @click="targetTicketId = {{ $ticket->id }}; targetTicketCode = '{{ $ticket->ticket_code }}'; newPriority = '{{ $ticket->priority }}'; priorityModal = true"
                                                class="text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 transition">
                                            ⚡ Override
                                        </button>
                                        <a href="{{ route('portal.tickets.show', $ticket->id) }}"
                                           class="text-[11px] bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-blue-200 transition">
                                            Open &rarr;
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-slate-400">
                                    🎉 Great news! No high-attention bottlenecks, SLA breaches, or blocked tickets currently.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right 1 Col: Live Audit Trail Visualizer -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-extrabold text-slate-900 flex items-center space-x-2">
                    <span>📜</span>
                    <span>System Audit Trail</span>
                </h2>
                <p class="text-xs text-slate-500">Immutable operational activity stream.</p>
            </div>

            <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                @forelse($auditLogs as $log)
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 text-xs space-y-1">
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="font-extrabold uppercase px-1.5 py-0.2 rounded bg-slate-200 text-slate-700">
                                {{ str_replace('_', ' ', $log->action) }}
                            </span>
                            <span class="text-slate-400 font-mono">{{ $log->created_at->format('H:i:s') }}</span>
                        </div>

                        <div class="font-bold text-slate-800">
                            {{ $log->actor->name ?? 'System' }}
                            @if($log->ticket)
                                &bull; <a href="{{ route('portal.tickets.show', $log->ticket_id) }}" class="text-blue-600 hover:underline font-mono">{{ $log->ticket->ticket_code }}</a>
                            @endif
                        </div>

                        @if($log->old_value || $log->new_value)
                            <div class="text-[11px] text-slate-600 flex items-center space-x-1">
                                <span class="text-slate-400 line-through">{{ $log->old_value }}</span>
                                <span>&rarr;</span>
                                <span class="font-bold text-slate-800">{{ $log->new_value }}</span>
                            </div>
                        @endif

                        <div class="text-[10px] text-slate-400">
                            {{ $log->created_at->format('d-M-Y') }} ({{ $log->created_at->diffForHumans() }})
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-400">No recent audit activity.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- OVERRIDE PRIORITY MODAL -->
    <div x-show="priorityModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="priorityModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800 flex items-center space-x-2">
                <span>⚡</span>
                <span>Executive Priority Override (<span x-text="targetTicketCode"></span>)</span>
            </h3>
            <p class="text-xs text-slate-500">
                Overriding priority will reorder this ticket in the dispatch queue and notify the assigned technician.
            </p>

            <form :action="'{{ url('portal/cockpit/override-priority') }}/' + targetTicketId" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">New Priority Level</label>
                    <select name="priority" x-model="newPriority" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                        <option value="critical">Critical (Immediate dispatch)</option>
                        <option value="high">High (Within 4 hours)</option>
                        <option value="medium">Medium (Within 24 hours)</option>
                        <option value="low">Low (Standard backlog)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Executive Justification (Required)</label>
                    <textarea name="reason" x-model="overrideReason" required rows="3" placeholder="e.g. VIP escalation, business-critical branch downtime..." class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" @click="priorityModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                        Confirm Override
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
