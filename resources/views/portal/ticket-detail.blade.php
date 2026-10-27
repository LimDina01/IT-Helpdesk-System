@extends('portal.layout')

@section('title', 'Ticket ' . $ticket->ticket_code)

@section('content')
<div x-data="{
    clarifyModal: false,
    blockModal: false,
    reassignDrawer: false,
    messageTab: 'requester_visible',
    messageBody: '',
    messageFiles: [],
    sendingMessage: false,
    chatToast: '',
    chatToastTimer: null,
    approveModal: false,
    rejectModal: false,
    lightbox: false,
    lightboxUrl: '',
    lightboxName: '',
    lightboxDownload: '',
    requesterActive: {{ \Illuminate\Support\Facades\Cache::has("ticket_presence_{$ticket->id}_{$ticket->requester_id}") ? 'true' : 'false' }},
    chatMessages: {{ Js::from($messages->map(fn($m) => [
        'id' => $m->id,
        'sender_name' => $m->sender->name,
        'is_staff' => $m->sender->isStaff(),
        'visibility' => $m->visibility,
        'body' => $m->body,
        'created_at_human' => $m->created_at->diffForHumans(),
        'created_at_full' => $m->created_at->format('H:i'),
        'attachments' => $m->attachments->map(fn($a) => [
            'id' => $a->id,
            'original_filename' => $a->original_filename,
            'file_size' => $a->formatted_size,
            'is_image' => str_starts_with($a->mime_type, 'image/'),
            'download_url' => route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $a->id]),
            'preview_url' => route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $a->id, 'preview' => 1]),
        ]),
    ])) }},
    pollTimer: null,
    scrollToBottom() {
        this.$nextTick(() => {
            const el = document.getElementById('portalChatStream');
            if (el) el.scrollTop = el.scrollHeight;
        });
    },
    showToast(msg) {
        this.chatToast = msg;
        if (this.chatToastTimer) clearTimeout(this.chatToastTimer);
        this.chatToastTimer = setTimeout(() => {
            this.chatToast = '';
        }, 3500);
    },
    async fetchLiveMessages() {
        try {
            const res = await fetch('{{ route('portal.tickets.poll', $ticket->id) }}', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                this.requesterActive = data.is_requester_active;
                if (data.messages && data.messages.length !== this.chatMessages.length) {
                    this.chatMessages = data.messages;
                    this.scrollToBottom();
                }
            }
        } catch (e) {}
    },
    async submitChatMessage() {
        if (this.sendingMessage) return;
        const text = (this.messageBody || '').trim();
        const files = this.messageFiles || [];
        if (!text && files.length === 0) return;

        // Instantly empty the textbox and files (optimistic UI like WhatsApp/Telegram/Slack)
        this.messageBody = '';
        const inputEl = document.getElementById('chatMessageInput');
        if (inputEl) {
            inputEl.value = '';
            inputEl.style.height = 'auto';
        }
        this.$nextTick(() => {
            if (inputEl) inputEl.value = '';
        });

        const filesToSend = [...files];
        this.messageFiles = [];
        const fileInput = document.getElementById('chatFileInput');
        if (fileInput) fileInput.value = '';

        this.sendingMessage = true;

        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('visibility', this.messageTab);
        if (text) {
            formData.append('body', text);
        }
        for (let i = 0; i < filesToSend.length; i++) {
            formData.append('attachments[]', filesToSend[i]);
        }

        try {
            const res = await fetch('{{ route('portal.tickets.messages.store', $ticket->id) }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData
            });

            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                this.showToast(data.message || (this.messageTab === 'internal_staff_only' ? 'Internal note added.' : 'Reply sent to employee.'));
                await this.fetchLiveMessages();
                this.scrollToBottom();
                if (inputEl) {
                    inputEl.value = '';
                    inputEl.focus();
                }
            } else {
                // Only restore text if server returned error and user hasn't typed anything new
                if (!this.messageBody) {
                    this.messageBody = text;
                    if (inputEl) inputEl.value = text;
                }
                this.showToast('Failed to send message. Please retry.');
            }
        } catch (e) {
            if (!this.messageBody) {
                this.messageBody = text;
                if (inputEl) inputEl.value = text;
            }
            this.showToast('Network error while sending.');
        } finally {
            this.sendingMessage = false;
        }
    },
    initLivePoll() {
        this.scrollToBottom();
        this.pollTimer = setInterval(() => {
            this.fetchLiveMessages();
        }, 3500);
    }
}" x-init="initLivePoll()" class="space-y-6">

    <!-- Top Ticket Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('portal.dashboard') }}" class="text-xs text-slate-400 hover:text-slate-700 font-bold flex items-center space-x-1">
                    <span>&larr;</span> <span>Back to Queues</span>
                </a>
                <span>•</span>
                <span class="font-mono font-bold text-blue-600 text-sm">{{ $ticket->ticket_code }}</span>
                
                @if($ticket->isIncident())
                    <span class="bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider flex items-center space-x-1">
                        <span>🔴</span> <span>Incident</span>
                    </span>
                @else
                    <span class="bg-indigo-100 text-indigo-700 border border-indigo-200 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider flex items-center space-x-1">
                        <span>🔵</span> <span>Service Request</span>
                    </span>
                @endif

                @if($ticket->priority === 'critical')
                    <span class="bg-red-600 text-white font-bold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider">Critical</span>
                @elseif($ticket->priority === 'high')
                    <span class="bg-orange-500 text-white font-bold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider">High</span>
                @elseif($ticket->priority === 'medium')
                    <span class="bg-blue-100 text-blue-800 font-bold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider">Medium</span>
                @else
                    <span class="bg-slate-100 text-slate-700 font-bold text-[9px] uppercase px-2 py-0.5 rounded tracking-wider">Low</span>
                @endif

                @if($ticket->status === 'pending_approval')
                    <span class="bg-purple-100 text-purple-800 font-bold text-[10px] uppercase px-2.5 py-0.5 rounded-full border border-purple-200 animate-pulse">
                        ⏳ Pending Approval
                    </span>
                @elseif($ticket->status === 'rejected')
                    <span class="bg-rose-100 text-rose-800 font-bold text-[10px] uppercase px-2.5 py-0.5 rounded-full border border-rose-200">
                        ✖ Rejected
                    </span>
                @else
                    <span class="bg-slate-100 text-slate-800 font-bold text-[10px] uppercase px-2.5 py-0.5 rounded-full border border-slate-200">
                        Status: {{ str_replace('_', ' ', strtoupper($ticket->status)) }}
                    </span>
                @endif

                <!-- Dynamic Live Requester Presence Badge -->
                <template x-if="requesterActive">
                    <span class="inline-flex items-center space-x-1.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>🟢 Requester In Chat</span>
                    </span>
                </template>
                <template x-if="!requesterActive">
                    <span class="inline-flex items-center space-x-1 bg-slate-100 text-slate-500 text-[10px] font-medium px-2 py-0.5 rounded-full" title="Replies will send a Telegram push alert">
                        <span>📱 Telegram Alert Ready</span>
                    </span>
                </template>
            </div>
            
            <h1 class="text-xl font-extrabold text-slate-900 mt-2">{{ $ticket->subject }}</h1>
            
            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-3 mt-1.5">
                <span>Created: <strong>{{ $ticket->created_at->format('M d, Y H:i:s') }}</strong> ({{ $ticket->created_at->diffForHumans() }})</span>
                <span>•</span>
                <span>Category: <strong>{{ $ticket->category->name }}</strong></span>
                <span>•</span>
                <span>Branch: <strong>{{ $ticket->branch->name }} ({{ $ticket->branch->code }})</strong></span>
            </div>
        </div>

        <!-- Quick Lifecycle Actions -->
        <div class="flex items-center space-x-2">
            @if($ticket->status === 'assigned' && ($ticket->current_technician_id === Auth::id() || Auth::user()->hasRole(['system_admin', 'hod'])))
                <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                    @csrf
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                        <span>⚡</span>
                        <span>Accept & Start Work</span>
                    </button>
                </form>
            @endif

            @if($ticket->status === 'in_progress')
                <!-- Mark Done -->
                <form method="POST" action="{{ route('portal.tickets.done', $ticket->id) }}">
                    @csrf
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                        <span>✔</span>
                        <span>Mark as Done</span>
                    </button>
                </form>

                <!-- Mark Blocked -->
                <button type="button" @click="blockModal = true" class="bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs px-3 py-2.5 rounded-xl transition">
                    🛑 Blocked / Hold
                </button>

                <!-- Ask Clarification -->
                <button type="button" @click="clarifyModal = true" class="bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 font-semibold text-xs px-3 py-2.5 rounded-xl border border-slate-200 transition">
                    ❓ Ask Info
                </button>
            @endif

            @if($ticket->status === 'blocked')
                <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition">
                        ▶ Resume Work
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Alert Banners -->
    @if($isOverdueAcknowledgement)
        <div class="bg-red-500 text-white p-4 rounded-2xl shadow-md flex items-center justify-between animate-pulse">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">🚨</span>
                <div>
                    <div class="font-extrabold text-sm">30-Minute Acknowledgement SLA Breached!</div>
                    <div class="text-xs text-red-100">
                        This ticket was assigned {{ $ticket->assigned_at->diffForHumans() }} to 
                        <strong>{{ $ticket->technician->name ?? 'Technician' }}</strong> and has not been accepted yet.
                    </div>
                </div>
            </div>
            <button @click="reassignDrawer = true" class="bg-white text-red-700 font-bold text-xs px-3 py-1.5 rounded-lg shadow-xs hover:bg-red-50">
                Reassign Immediately
            </button>
        </div>
    @endif

    @if($ticket->status === 'blocked')
        <div class="bg-amber-50 border border-amber-300 text-amber-900 p-4 rounded-2xl flex items-start space-x-3">
            <span class="text-xl">🛑</span>
            <div>
                <div class="font-bold text-xs uppercase tracking-wider">Ticket Currently On Hold / Blocked</div>
                <p class="text-xs text-amber-800 mt-0.5"><strong>Reason:</strong> {{ $ticket->blocked_reason }}</p>
            </div>
        </div>
    @endif

    @if($ticket->status === 'pending_confirmation')
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 p-4 rounded-2xl flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-xl">⏳</span>
                <div>
                    <div class="font-bold text-xs uppercase tracking-wider">Awaiting Requester Sign-off</div>
                    <p class="text-xs text-emerald-800">
                        Technician finished work on {{ $ticket->resolved_at?->format('M d, H:i') }}. 
                        Auto-close deadline: <strong>{{ $ticket->auto_close_deadline?->format('l, M d, Y') }}</strong> (excluding non-working Sundays & company holidays).
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Multi-Stage Approval Workflow Card (For Service Requests) -->
    @if($ticket->approvals->isNotEmpty())
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📋</span>
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">Multi-Step Service Approval Workflow</h2>
                        <p class="text-xs text-slate-500">Requires formal administrative sign-offs before IT team starts work.</p>
                    </div>
                </div>

                @if($ticket->isPendingApproval() && $currentApproval)
                    <span class="bg-purple-100 text-purple-800 font-bold text-xs px-3 py-1 rounded-full border border-purple-200">
                        Waiting on: {{ $currentApproval->step_name }}
                    </span>
                @elseif($ticket->isRejected())
                    <span class="bg-rose-100 text-rose-800 font-bold text-xs px-3 py-1 rounded-full border border-rose-200">
                        ✖ Request Rejected
                    </span>
                @else
                    <span class="bg-emerald-100 text-emerald-800 font-bold text-xs px-3 py-1 rounded-full border border-emerald-200">
                        ✔ All Approvals Completed
                    </span>
                @endif
            </div>

            <!-- Approval Steps Visual Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($ticket->approvals as $approval)
                    <div class="p-4 rounded-2xl border transition {{ $approval->status === 'approved' ? 'bg-emerald-50/60 border-emerald-200' : ($approval->status === 'rejected' ? 'bg-rose-50/60 border-rose-200' : ($currentApproval && $currentApproval->id === $approval->id ? 'bg-purple-50/70 border-purple-300 ring-2 ring-purple-400' : 'bg-slate-50 border-slate-200 opacity-60')) }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded {{ $approval->status === 'approved' ? 'bg-emerald-200 text-emerald-900' : ($approval->status === 'rejected' ? 'bg-rose-200 text-rose-900' : 'bg-purple-200 text-purple-900') }}">
                                Step {{ $approval->step_number }}
                            </span>
                            
                            @if($approval->status === 'approved')
                                <span class="text-emerald-700 text-xs font-bold flex items-center space-x-1">
                                    <span>✔</span> <span>Approved</span>
                                </span>
                            @elseif($approval->status === 'rejected')
                                <span class="text-rose-700 text-xs font-bold flex items-center space-x-1">
                                    <span>✖</span> <span>Rejected</span>
                                </span>
                            @else
                                <span class="text-purple-700 text-xs font-bold flex items-center space-x-1 animate-pulse">
                                    <span>⏳</span> <span>Pending</span>
                                </span>
                            @endif
                        </div>

                        <div class="font-bold text-xs text-slate-800">{{ $approval->step_name }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            Role: <strong>{{ ucwords(str_replace('_', ' ', $approval->role_name)) }}</strong>
                            @if($approval->department_id && $ticket->requester->employee?->department)
                                <span>({{ $ticket->requester->employee->department->name }})</span>
                            @endif
                        </div>

                        @if($approval->status !== 'pending')
                            <div class="mt-2 pt-2 border-t border-slate-200/60 text-[11px] space-y-1">
                                <div class="text-slate-600">
                                    Actioned by: <strong>{{ $approval->approver->name ?? 'System' }}</strong>
                                    ({{ $approval->actioned_at?->format('d-M-Y H:i:s') }})
                                </div>
                                @if($approval->comments)
                                    <div class="text-slate-700 italic bg-white/70 p-2 rounded-lg border border-slate-200">
                                        "{{ $approval->comments }}"
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Action Prompt for Authorized Approver -->
            @if($canApproveCurrent && $currentApproval && $ticket->isPendingApproval())
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-md">
                    <div class="space-y-0.5">
                        <div class="font-extrabold text-sm flex items-center space-x-1.5">
                            <span>⚡</span>
                            <span>Action Required: {{ $currentApproval->step_name }}</span>
                        </div>
                        <p class="text-xs text-purple-100">You are authorized to review and decide on this service request step.</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="button" @click="approveModal = true" class="bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold text-xs px-4 py-2 rounded-xl shadow-xs transition flex items-center space-x-1">
                            <span>✔</span>
                            <span>Approve Step</span>
                        </button>
                        <button type="button" @click="rejectModal = true" class="bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-xs px-4 py-2 rounded-xl shadow-xs transition flex items-center space-x-1">
                            <span>✖</span>
                            <span>Reject Step</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- 2-Column Operational Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Details, SLA, Requester & Audit History -->
        <div class="space-y-6">
            
            <!-- Assigned Technician & Delegation Card -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Current Assignee</span>
                    <button type="button" @click="reassignDrawer = !reassignDrawer" class="text-xs text-blue-600 hover:underline font-semibold">
                        Reassign
                    </button>
                </div>

                @if($ticket->technician)
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                            {{ substr($ticket->technician->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-800">{{ $ticket->technician->name }}</div>
                            <div class="text-xs text-slate-500">{{ $ticket->technician->email }}</div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 text-slate-600">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Assigned:</span>
                            <span class="font-medium">{{ $ticket->assigned_at ? $ticket->assigned_at->format('M d, H:i') : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Accepted:</span>
                            <span class="font-medium">
                                @if($ticket->acknowledged_at)
                                    <span class="text-emerald-600 font-bold">✔ {{ $ticket->acknowledged_at->format('M d, H:i') }}</span>
                                @else
                                    <span class="text-amber-600 font-semibold">Pending Acceptance</span>
                                @endif
                            </span>
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-amber-50 text-amber-800 rounded-xl text-xs font-medium text-center">
                        Not assigned to any technician yet.
                    </div>
                @endif

                <!-- Reassign Drawer -->
                <div x-show="reassignDrawer" class="pt-3 border-t border-slate-100 space-y-3">
                    <form method="POST" action="{{ route('portal.tickets.assign', $ticket->id) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">New Technician</label>
                            <select name="technician_id" required class="w-full text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white">
                                @foreach($technicians as $tech)
                                    <option value="{{ $tech->id }}" {{ $ticket->current_technician_id == $tech->id ? 'selected' : '' }}>
                                        {{ $tech->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Transfer Reason</label>
                            <input type="text" name="assignment_note" placeholder="e.g. Workload rebalancing" class="w-full text-xs px-2.5 py-1.5 rounded-lg border border-slate-300">
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" @click="reassignDrawer = false" class="text-xs text-slate-500 px-2 py-1">Cancel</button>
                            <button type="submit" class="text-xs bg-blue-600 text-white font-bold px-3 py-1.5 rounded-lg">Update</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Requester Profile Card -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block border-b border-slate-100 pb-2">Requester Info</span>
                
                <div class="space-y-2 text-xs">
                    <div>
                        <div class="font-bold text-sm text-slate-800">{{ $ticket->requester->name }}</div>
                        <div class="text-blue-600 font-mono font-medium text-[11px]">{{ $ticket->requester->employee->employee_code ?? 'EMP' }}</div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 space-y-1 text-slate-600">
                        <div>🏢 <strong>Branch:</strong> {{ $ticket->branch->name }} ({{ $ticket->branch->code }})</div>
                        <div>📂 <strong>Department:</strong> {{ $ticket->requester->employee->department->name ?? 'General' }}</div>
                        <div>📧 <strong>Email:</strong> {{ $ticket->requester->email ?? 'N/A' }}</div>
                        <div>📱 <strong>Telegram:</strong> 
                            @if($ticket->requester->telegram_username)
                                <span class="text-blue-600 font-mono">@<span x-text="'{{ $ticket->requester->telegram_username }}'"></span></span>
                            @else
                                <span class="text-slate-400">ID: {{ $ticket->requester->telegram_id ?? 'Not linked' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Trail & Assignment History -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block border-b border-slate-100 pb-2">Assignment History</span>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-1 text-xs">
                    @forelse($ticket->assignments as $assign)
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-[11px] space-y-0.5">
                            <div class="font-semibold text-slate-700">
                                Assigned to <strong>{{ $assign->technician->name }}</strong>
                            </div>
                            <div class="text-slate-400 text-[10px]">
                                by {{ $assign->assignedBy->name }} • {{ $assign->assigned_at->diffForHumans() }}
                            </div>
                            @if($assign->reassignment_reason)
                                <div class="text-slate-500 italic text-[10px]">"{{ $assign->reassignment_reason }}"</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-slate-400 text-xs">No assignment history.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column: Conversation, Internal Notes & Reply Workspace -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Issue Description Box -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Initial Problem Statement</span>
                <div class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap font-medium">
                    {{ $ticket->description }}
                </div>
            </div>

            <!-- Unified Modern Chat Panel -->
            <div id="chatPanel" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs flex flex-col overflow-hidden">
                
                <!-- Chat Panel Header -->
                <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-2.5 h-2.5 rounded-full" :class="requesterActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'"></span>
                        <div class="text-xs font-bold text-slate-800 flex items-center gap-2">
                            <span>Ticket Discussion & Notes</span>
                            <span class="bg-slate-200/80 text-slate-600 font-semibold px-2 py-0.5 rounded-full text-[10px]" x-text="chatMessages.length + ' messages'"></span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Inline Success Notification Toast -->
                        <div x-show="chatToast"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="bg-emerald-600 text-white text-[11px] font-semibold px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-xs">
                            <span>✔</span>
                            <span x-text="chatToast"></span>
                        </div>

                        <span x-show="requesterActive" class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                            Employee Active in TMA
                        </span>
                        <span x-show="!requesterActive" class="text-[11px] text-slate-500 bg-slate-100 border border-slate-200/70 px-2.5 py-0.5 rounded-full flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            Employee Away (Telegram push enabled)
                        </span>
                    </div>
                </div>

                <!-- Contained Scrollable Chat Viewport (Prevents infinite page height) -->
                <div id="portalChatStream" class="flex-1 max-h-[500px] min-h-[320px] overflow-y-auto p-4 sm:p-5 bg-slate-50/40 space-y-3.5">
                    <template x-for="msg in chatMessages" :key="msg.id">
                        <div>
                            <!-- System Event: Compact Centered Pill -->
                            <template x-if="msg.visibility === 'system_event'">
                                <div class="flex justify-center my-1.5">
                                    <span class="bg-slate-200/80 text-slate-600 text-[10px] font-medium px-3 py-1 rounded-full shadow-2xs"
                                          x-text="'⚙️ ' + msg.body + ' • ' + msg.created_at_human"></span>
                                </div>
                            </template>

                            <!-- Confidential Internal Note (Centered Distinct Card) -->
                            <template x-if="msg.visibility === 'internal_staff_only'">
                                <div class="max-w-xl mx-auto my-2">
                                    <div class="bg-amber-50/95 border-2 border-amber-300/80 rounded-2xl p-3.5 shadow-2xs space-y-1.5">
                                        <div class="flex items-center justify-between text-xs border-b border-amber-200/70 pb-1">
                                            <div class="flex items-center space-x-1.5">
                                                <span class="bg-amber-200 text-amber-900 font-black text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider flex items-center gap-1">
                                                    <span>🔒</span>
                                                    <span>Staff Only Note</span>
                                                </span>
                                                <span class="font-bold text-amber-900 text-xs" x-text="msg.sender_name"></span>
                                            </div>
                                            <span class="text-[10px] text-amber-700/80 font-mono" x-text="msg.created_at_full"></span>
                                        </div>
                                        <p class="text-xs text-amber-950 font-medium leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>

                                        <!-- Attachments -->
                                        <template x-if="msg.attachments && msg.attachments.length > 0">
                                            <div class="pt-1.5 space-y-1.5 border-t border-amber-200/60">
                                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5" x-show="msg.attachments.some(a => a.is_image)">
                                                    <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                        <div class="group relative rounded-xl overflow-hidden border border-amber-300/70 bg-amber-100/40 cursor-pointer aspect-square shadow-2xs"
                                                             @click="lightbox = true; lightboxUrl = att.preview_url; lightboxName = att.original_filename; lightboxDownload = att.download_url">
                                                            <img :src="att.preview_url" :alt="att.original_filename" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                                                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-1 flex items-center justify-between text-[9px] text-white">
                                                                <span class="truncate max-w-[80px]" x-text="att.original_filename"></span>
                                                                <span class="font-mono text-[8px] opacity-90" x-text="att.file_size"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <div class="flex flex-wrap gap-1.5" x-show="msg.attachments.some(a => !a.is_image)">
                                                    <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                        <a :href="att.download_url"
                                                           class="bg-amber-100 hover:bg-amber-200 text-amber-900 text-[11px] px-2 py-0.5 rounded-lg flex items-center space-x-1 transition">
                                                            <span>📎</span>
                                                            <span class="truncate max-w-[120px]" x-text="att.original_filename"></span>
                                                            <span class="font-mono text-[9px] opacity-75" x-text="'(' + att.file_size + ')'"></span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <div class="text-[9px] text-amber-700/70 italic pt-0.5">
                                            Hidden from employee. Only visible to IT Help Desk and Technicians.
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Staff Message (Right-aligned sleek blue bubble) -->
                            <template x-if="msg.visibility !== 'system_event' && msg.visibility !== 'internal_staff_only' && msg.is_staff">
                                <div class="flex justify-end my-1">
                                    <div class="max-w-[82%] sm:max-w-[70%] space-y-1">
                                        <div class="flex items-center justify-end space-x-1.5 text-[10px] text-slate-400 px-1">
                                            <span class="font-semibold text-blue-600" x-text="msg.sender_name"></span>
                                            <span class="text-[8px] bg-blue-100 text-blue-700 font-bold px-1 rounded uppercase">IT Staff</span>
                                            <span>•</span>
                                            <span class="font-mono" x-text="msg.created_at_full"></span>
                                        </div>

                                        <div class="bg-blue-600 text-white rounded-2xl rounded-tr-xs px-4 py-2.5 shadow-xs space-y-2">
                                            <p class="text-xs leading-relaxed whitespace-pre-wrap font-normal" x-text="msg.body"></p>

                                            <!-- Attachments -->
                                            <template x-if="msg.attachments && msg.attachments.length > 0">
                                                <div class="pt-2 space-y-1.5 border-t border-white/20">
                                                    <div class="grid grid-cols-2 gap-1.5" x-show="msg.attachments.some(a => a.is_image)">
                                                        <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                            <div class="group relative rounded-xl overflow-hidden border border-white/20 bg-black/10 cursor-pointer aspect-square shadow-2xs"
                                                                 @click="lightbox = true; lightboxUrl = att.preview_url; lightboxName = att.original_filename; lightboxDownload = att.download_url">
                                                                <img :src="att.preview_url" :alt="att.original_filename" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                                                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-1 flex items-center justify-between text-[9px] text-white">
                                                                    <span class="truncate max-w-[80px]" x-text="att.original_filename"></span>
                                                                    <span class="font-mono text-[8px] opacity-90" x-text="att.file_size"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="flex flex-wrap gap-1.5" x-show="msg.attachments.some(a => !a.is_image)">
                                                        <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                            <a :href="att.download_url"
                                                               class="bg-blue-700/70 hover:bg-blue-700 text-white text-[11px] px-2.5 py-1 rounded-lg flex items-center space-x-1 transition">
                                                                <span>📎</span>
                                                                <span class="truncate max-w-[120px]" x-text="att.original_filename"></span>
                                                                <span class="font-mono text-[9px] opacity-80" x-text="'(' + att.file_size + ')'"></span>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Requester (Employee) Message (Left-aligned clean white bubble) -->
                            <template x-if="msg.visibility !== 'system_event' && msg.visibility !== 'internal_staff_only' && !msg.is_staff">
                                <div class="flex justify-start my-1">
                                    <div class="max-w-[82%] sm:max-w-[70%] space-y-1">
                                        <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 px-1">
                                            <span class="font-semibold text-slate-700" x-text="msg.sender_name"></span>
                                            <span class="text-[8px] bg-slate-200 text-slate-700 font-bold px-1 rounded uppercase">Requester</span>
                                            <span>•</span>
                                            <span class="font-mono" x-text="msg.created_at_full"></span>
                                        </div>

                                        <div class="bg-white border border-slate-200/90 text-slate-800 rounded-2xl rounded-tl-xs px-4 py-2.5 shadow-2xs space-y-2">
                                            <p class="text-xs leading-relaxed whitespace-pre-wrap font-normal" x-text="msg.body"></p>

                                            <!-- Attachments -->
                                            <template x-if="msg.attachments && msg.attachments.length > 0">
                                                <div class="pt-2 space-y-1.5 border-t border-slate-100">
                                                    <div class="grid grid-cols-2 gap-1.5" x-show="msg.attachments.some(a => a.is_image)">
                                                        <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                            <div class="group relative rounded-xl overflow-hidden border border-slate-200 bg-black/5 cursor-pointer aspect-square shadow-2xs"
                                                                 @click="lightbox = true; lightboxUrl = att.preview_url; lightboxName = att.original_filename; lightboxDownload = att.download_url">
                                                                <img :src="att.preview_url" :alt="att.original_filename" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                                                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-1 flex items-center justify-between text-[9px] text-white">
                                                                    <span class="truncate max-w-[80px]" x-text="att.original_filename"></span>
                                                                    <span class="font-mono text-[8px] opacity-90" x-text="att.file_size"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="flex flex-wrap gap-1.5" x-show="msg.attachments.some(a => !a.is_image)">
                                                        <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                            <a :href="att.download_url"
                                                               class="bg-slate-100 hover:bg-slate-200 text-slate-800 text-[11px] px-2.5 py-1 rounded-lg flex items-center space-x-1 transition">
                                                                <span>📎</span>
                                                                <span class="truncate max-w-[120px]" x-text="att.original_filename"></span>
                                                                <span class="font-mono text-[9px] opacity-75" x-text="'(' + att.file_size + ')'"></span>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div x-show="chatMessages.length === 0" class="text-center py-10 text-xs text-slate-400">
                        💬 No conversation messages yet. Send a reply or post a note below.
                    </div>
                </div>

                <!-- Integrated Bottom Composer -->
                <div class="p-4 bg-white border-t border-slate-200 space-y-3">
                    
                    <!-- Segmented Mode Toggle: Reply vs Internal Note -->
                    <div class="flex items-center space-x-2">
                        <button type="button" @click="messageTab = 'requester_visible'"
                                :class="messageTab === 'requester_visible' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5">
                            <span>💬</span>
                            <span>Reply to Employee</span>
                        </button>

                        <button type="button" @click="messageTab = 'internal_staff_only'"
                                :class="messageTab === 'internal_staff_only' ? 'bg-amber-400 text-amber-950 font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5">
                            <span>🔒</span>
                            <span>Internal Note (IT Staff Only)</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitChatMessage()" class="space-y-3">
                        <div>
                            <textarea id="chatMessageInput"
                                      x-model="messageBody"
                                      rows="2"
                                      @keydown.enter.exact.prevent="submitChatMessage()"
                                      :placeholder="messageTab === 'internal_staff_only' ? 'Write confidential internal note (Press Enter to post, Shift+Enter for newline)...' : 'Type reply that the employee will see on their Telegram Mini App (Press Enter to send, Shift+Enter for newline)...'"
                                      :class="messageTab === 'internal_staff_only' ? 'bg-amber-50/40 border-amber-300 focus:ring-amber-500' : 'bg-slate-50/70 border-slate-300 focus:bg-white focus:ring-blue-600'"
                                      class="w-full text-xs p-3 rounded-xl border focus:outline-none focus:ring-2"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                            <div class="flex items-center space-x-2">
                                <input type="file" id="chatFileInput" multiple accept="image/*,application/pdf"
                                       @change="messageFiles = Array.from($event.target.files)"
                                       class="text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                                <span x-show="messageFiles.length > 0" class="text-[11px] text-blue-600 font-semibold" x-text="messageFiles.length + ' file(s) attached'"></span>
                            </div>

                            <button type="submit"
                                    :disabled="sendingMessage || (!messageBody.trim() && messageFiles.length === 0)"
                                    :class="messageTab === 'internal_staff_only' ? 'bg-amber-500 hover:bg-amber-600 text-amber-950' : 'bg-blue-600 hover:bg-blue-700 text-white'"
                                    class="px-4 py-2 font-bold text-xs rounded-xl shadow-xs transition whitespace-nowrap disabled:opacity-50 flex items-center space-x-1.5">
                                <span x-show="sendingMessage" class="animate-spin text-xs">⏳</span>
                                <span x-text="sendingMessage ? 'Sending...' : (messageTab === 'internal_staff_only' ? 'Post Internal Note' : 'Send Reply to Employee')"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ASK CLARIFICATION MODAL -->
    <div x-show="clarifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="clarifyModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">Ask Clarification from Employee</h3>
            <p class="text-xs text-slate-500">The ticket status will transition to <strong>Needs Info</strong> until the employee replies.</p>
            <form method="POST" action="{{ route('portal.tickets.clarify', $ticket->id) }}" class="space-y-4">
                @csrf
                <textarea name="question" required rows="3" placeholder="What additional information do you need?" class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                <div class="flex justify-end space-x-2">
                    <button type="button" @click="clarifyModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-bold text-xs rounded-xl">Send Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MARK BLOCKED MODAL -->
    <div x-show="blockModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="blockModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">Mark Ticket as Blocked / On Hold</h3>
            <p class="text-xs text-slate-500">Explain why work cannot continue (e.g., waiting for vendor parts, procurement approval).</p>
            <form method="POST" action="{{ route('portal.tickets.block', $ticket->id) }}" class="space-y-4">
                @csrf
                <textarea name="blocked_reason" required rows="3" placeholder="State vendor or procurement blockage reason..." class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                <div class="flex justify-end space-x-2">
                    <button type="button" @click="blockModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white font-bold text-xs rounded-xl">Confirm Block</button>
                </div>
            </form>
        </div>
    </div>

    <!-- APPROVE STEP MODAL -->
    <div x-show="approveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="approveModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">Approve Service Request</h3>
            <p class="text-xs text-slate-500">
                You are approving <strong>{{ $currentApproval->step_name ?? 'this step' }}</strong>. 
                The next approval or fulfillment stage will be triggered automatically.
            </p>
            @if($currentApproval)
                <form method="POST" action="{{ route('portal.tickets.approvals.approve', [$ticket->id, $currentApproval->id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Approval Notes / Comments (Optional)</label>
                        <textarea name="comments" rows="3" placeholder="e.g. Approved, proceed with standard specs..." class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="approveModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700">Confirm Approval</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- REJECT STEP MODAL -->
    <div x-show="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="rejectModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">Reject Service Request</h3>
            <p class="text-xs text-slate-500">
                Rejecting this request will stop the workflow and inform the requester.
            </p>
            @if($currentApproval)
                <form method="POST" action="{{ route('portal.tickets.approvals.reject', [$ticket->id, $currentApproval->id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Reason for Rejection (Required)</label>
                        <textarea name="reason" required rows="3" placeholder="Explain why this request is not approved..." class="w-full text-xs p-3 rounded-xl border border-slate-300"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="rejectModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-rose-600 text-white font-bold text-xs rounded-xl hover:bg-rose-700">Confirm Rejection</button>
                    </div>
                </form>
            @endif
        </div>
    <!-- FULL-SCREEN IMAGE LIGHTBOX MODAL -->
    <div x-show="lightbox"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="lightbox = false"
         class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md flex flex-col p-6" x-cloak>
        <div class="flex items-center justify-between text-white pb-4 max-w-4xl mx-auto w-full">
            <span class="text-sm font-semibold truncate max-w-md" x-text="lightboxName"></span>
            <div class="flex items-center space-x-3">
                <a :href="lightboxDownload" download class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-3 py-1.5 rounded-lg transition">
                    Download Original
                </a>
                <button type="button" @click="lightbox = false" class="text-white hover:text-slate-300 text-2xl font-bold px-2">
                    &times;
                </button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center max-w-4xl mx-auto w-full overflow-hidden">
            <img :src="lightboxUrl" :alt="lightboxName" class="max-h-[80vh] max-w-full rounded-2xl shadow-2xl object-contain">
        </div>
    </div>

</div>
@endsection
