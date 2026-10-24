@extends('portal.layout')

@section('title', 'Telegram Account Link Requests')

@section('content')
<div x-data="{ rejectModal: false, selectedRequest: null, rejectReason: '' }" class="space-y-6">

    <!-- Header & Subtitle -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Telegram Account Verification</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Review and approve employees binding their Telegram identities to their official company records.
            </p>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex items-center space-x-1 bg-white p-1 rounded-xl border border-slate-200 text-xs font-semibold shadow-xs">
            <a href="{{ route('portal.link-requests.index', ['status' => 'pending']) }}"
               class="{{ $status === 'pending' ? 'bg-amber-100 text-amber-900' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg transition">
                Pending Approval ({{ $pendingCount }})
            </a>
            <a href="{{ route('portal.link-requests.index', ['status' => 'approved']) }}"
               class="{{ $status === 'approved' ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg transition">
                Approved ({{ $approvedCount }})
            </a>
            <a href="{{ route('portal.link-requests.index', ['status' => 'rejected']) }}"
               class="{{ $status === 'rejected' ? 'bg-rose-100 text-rose-900' : 'text-slate-600 hover:text-slate-900' }} px-3 py-1.5 rounded-lg transition">
                Rejected ({{ $rejectedCount }})
            </a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Telegram Identity</th>
                        <th class="py-3 px-4">Requested Employee Roster</th>
                        <th class="py-3 px-4">Branch & Department</th>
                        <th class="py-3 px-4">Submitted</th>
                        <th class="py-3 px-4">Status & Reviewer</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $req)
                        <tr class="hover:bg-slate-50/70 transition">
                            
                            <!-- Telegram Info -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ $req->first_name }} {{ $req->last_name }}</div>
                                <div class="text-[11px] text-blue-600 font-mono mt-0.5">
                                    @if($req->telegram_username)
                                        @<span>{{ $req->telegram_username }}</span>
                                    @else
                                        ID: {{ $req->telegram_id }}
                                    @endif
                                </div>
                            </td>

                            <!-- Matched Employee -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($req->employee)
                                    <div class="font-bold text-slate-900">{{ $req->employee->full_name }}</div>
                                    <div class="text-[11px] font-mono font-semibold text-blue-600">{{ $req->employee_code }}</div>
                                @else
                                    <span class="text-rose-600 font-bold">Unmatched ID: {{ $req->employee_code }}</span>
                                @endif
                            </td>

                            <!-- Branch / Dept -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-600">
                                @if($req->employee)
                                    <div>🏢 {{ $req->employee->branch->name ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $req->employee->department->name ?? 'N/A' }}</div>
                                @else
                                    <span class="text-slate-400">N/A</span>
                                @endif
                            </td>

                            <!-- Submitted Time -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-500">
                                <div>{{ $req->created_at->format('M d, H:i') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $req->created_at->diffForHumans() }}</div>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($req->status === 'pending')
                                    <span class="bg-amber-100 text-amber-900 font-bold text-[10px] uppercase px-2 py-0.5 rounded">
                                        Pending Review
                                    </span>
                                @elseif($req->status === 'approved')
                                    <div class="flex items-center space-x-1 text-emerald-600 font-bold text-xs">
                                        <span>✔ Approved</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        by {{ $req->reviewer->name ?? 'Admin' }} • {{ $req->reviewed_at?->format('M d, H:i') }}
                                    </div>
                                @else
                                    <div class="text-rose-600 font-bold text-xs">❌ Rejected</div>
                                    <div class="text-[10px] text-slate-400">"{{ $req->rejection_reason }}"</div>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                @if($req->status === 'pending')
                                    <div class="flex items-center justify-end space-x-1.5">
                                        <!-- Approve Form -->
                                        <form method="POST" action="{{ route('portal.link-requests.approve', $req->id) }}">
                                            @csrf
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-xs transition">
                                                Approve
                                            </button>
                                        </form>

                                        <!-- Reject Modal Button -->
                                        <button type="button"
                                                @click="selectedRequest = { id: {{ $req->id }}, name: '{{ $req->first_name }} {{ $req->last_name }}' }; rejectModal = true"
                                                class="bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-semibold text-xs px-2.5 py-1.5 rounded-lg border border-slate-200 transition">
                                            Reject
                                        </button>
                                    </div>
                                @else
                                    <span class="text-slate-300 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <div class="text-2xl mb-2">🎉</div>
                                <div class="font-semibold text-sm text-slate-600">No {{ $status }} requests found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $requests->links() }}
            </div>
        @endif
    </div>

    <!-- REJECTION REASON MODAL -->
    <div x-show="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="rejectModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">Reject Account Link Request</h3>
            <p class="text-xs text-slate-500">
                Please provide a rejection reason for <span class="font-bold text-slate-800" x-text="selectedRequest?.name"></span>:
            </p>
            <form :action="'/portal/link-requests/' + selectedRequest?.id + '/reject'" method="POST" class="space-y-4">
                @csrf
                <textarea name="rejection_reason" required rows="3" placeholder="e.g. Employee ID does not match records..." class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                <div class="flex justify-end space-x-2">
                    <button type="button" @click="rejectModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white font-bold text-xs rounded-xl">Reject Request</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
