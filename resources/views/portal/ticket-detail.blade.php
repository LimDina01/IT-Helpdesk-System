@extends('portal.layout')

@section('title', 'Ticket ' . $ticket->ticket_code)

@section('content')
<div x-data="{
    clarifyModal: false,
    blockModal: false,
    rejectHoldModal: false,
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
    copiedTicket: false,
    copiedEmail: false,
    requesterActive: {{ \Illuminate\Support\Facades\Cache::has("ticket_presence_{$ticket->id}_{$ticket->requester_id}") ? 'true' : 'false' }},
    chatMessages: {{ Js::from($messages->map(fn($m) => [
        'id' => $m->id,
        'sender_name' => $m->sender?->name ?? 'System',
        'is_staff' => $m->sender ? $m->sender->isStaff() : false,
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
    copyCode() {
        navigator.clipboard.writeText('{{ $ticket->ticket_code }}');
        this.copiedTicket = true;
        setTimeout(() => { this.copiedTicket = false; }, 2000);
    },
    copyEmail(email) {
        navigator.clipboard.writeText(email);
        this.copiedEmail = true;
        setTimeout(() => { this.copiedEmail = false; }, 2000);
    },
    openLightbox(url, name, download) {
        this.lightboxUrl = url;
        this.lightboxName = name || 'Image Preview';
        this.lightboxDownload = download || url;
        this.lightbox = true;
    },
    getFileExtension(filename) {
        if (!filename) return 'FILE';
        const parts = filename.split('.');
        const ext = parts.length > 1 ? parts.pop() : '';
        return ext ? ext.slice(0, 4).toUpperCase() : 'FILE';
    },
    getFileBadgeClass(filename, isMe = false) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        if (['pdf'].includes(ext)) return 'bg-rose-500 text-white';
        if (['doc', 'docx'].includes(ext)) return 'bg-blue-600 text-white';
        if (['xls', 'xlsx', 'csv'].includes(ext)) return 'bg-emerald-600 text-white';
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'bg-amber-600 text-white';
        return isMe ? 'bg-blue-800 text-white' : 'bg-slate-700 text-white';
    },
    handleFileSelect(event) {
        if (event.target.files) {
            this.messageFiles = [...this.messageFiles, ...Array.from(event.target.files)];
        }
    },
    handlePaste(event) {
        if (!event.clipboardData || !event.clipboardData.items) return;
        for (let i = 0; i < event.clipboardData.items.length; i++) {
            const item = event.clipboardData.items[i];
            if (item.type.indexOf('image') !== -1) {
                const blob = item.getAsFile();
                const ext = item.type.split('/')[1] || 'png';
                const file = new File([blob], `screenshot_${Date.now()}.${ext}`, { type: item.type });
                this.messageFiles.push(file);
                this.showToast('Screenshot attached from clipboard!');
            }
        }
    },
    removeFile(index) {
        this.messageFiles.splice(index, 1);
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

    <!-- Top Ticket Header & Action Command Center -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
        
        <!-- Row 1: Breadcrumbs, Badges, Priority Switcher & Live Presence -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('portal.dashboard') }}" class="text-xs text-slate-500 hover:text-slate-800 font-bold flex items-center space-x-1.5 transition bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg">
                    <span>&larr;</span> <span>Queues</span>
                </a>

                <div class="w-px h-4 bg-slate-200"></div>

                <!-- 1-Click Copy Ticket Code -->
                <button type="button"
                        @click="copyCode()"
                        class="group inline-flex items-center space-x-1.5 font-mono font-bold text-blue-600 text-xs hover:text-blue-700 bg-blue-50/80 hover:bg-blue-100 px-2.5 py-1 rounded-lg border border-blue-200 transition"
                        title="Click to copy ticket code">
                    <span>{{ $ticket->ticket_code }}</span>
                    <svg class="w-3.5 h-3.5 text-blue-500 group-hover:scale-110 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span x-show="copiedTicket" x-cloak class="text-[10px] text-emerald-600 font-bold ml-0.5">Copied!</span>
                </button>

                <!-- Ticket Type Pill -->
                @if($ticket->isIncident())
                    <span class="bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1">
                        <span>🔴</span> <span>Incident</span>
                    </span>
                @else
                    <span class="bg-indigo-100 text-indigo-700 border border-indigo-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1">
                        <span>🔵</span> <span>Service Request</span>
                    </span>
                @endif

                <!-- Inline Priority Quick Switcher -->
                <form method="POST" action="{{ route('portal.tickets.priority', $ticket->id) }}" class="inline-flex items-center">
                    @csrf
                    <select name="priority" onchange="this.form.submit()"
                            title="Change Priority"
                            class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-lg border cursor-pointer transition shadow-2xs {{ $ticket->priority === 'critical' ? 'bg-rose-600 text-white border-rose-700' : ($ticket->priority === 'high' ? 'bg-amber-500 text-white border-amber-600' : ($ticket->priority === 'medium' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-slate-100 text-slate-700 border-slate-200')) }}">
                        <option value="critical" {{ $ticket->priority === 'critical' ? 'selected' : '' }}>🔴 Critical Priority</option>
                        <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>🟠 High Priority</option>
                        <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>🔵 Medium Priority</option>
                        <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>⚪ Low Priority</option>
                    </select>
                </form>

                <!-- Status Badge -->
                @if($ticket->status === 'pending_approval')
                    <span class="bg-purple-100 text-purple-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-purple-200 animate-pulse">
                        ⏳ Pending Approval
                    </span>
                @elseif($ticket->status === 'rejected')
                    <span class="bg-rose-100 text-rose-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-rose-200">
                        ✖ Rejected
                    </span>
                @elseif($ticket->status === 'submitted')
                    <span class="bg-amber-100 text-amber-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-amber-200">
                        ⚠️ Submitted / Unassigned
                    </span>
                @elseif($ticket->status === 'assigned')
                    <span class="bg-blue-100 text-blue-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-blue-200">
                        👤 Assigned
                    </span>
                @elseif($ticket->status === 'in_progress')
                    <span class="bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-emerald-200 flex items-center space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-ping"></span>
                        <span>In Progress</span>
                    </span>
                @elseif($ticket->status === 'blocked')
                    <span class="bg-amber-100 text-amber-900 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-amber-300">
                        🛑 Blocked / Hold
                    </span>
                @elseif($ticket->status === 'pending_confirmation')
                    <span class="bg-indigo-100 text-indigo-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-indigo-200">
                        ⏳ Pending Sign-off
                    </span>
                @else
                    <span class="bg-slate-100 text-slate-800 font-bold text-[10px] uppercase px-2.5 py-1 rounded-lg border border-slate-200">
                        {{ str_replace('_', ' ', strtoupper($ticket->status)) }}
                    </span>
                @endif
            </div>

            <!-- Dynamic Live Requester Presence Badge -->
            <div class="flex items-center space-x-2">
                <template x-if="requesterActive">
                    <span class="inline-flex items-center space-x-1.5 bg-emerald-50 text-emerald-800 text-[10px] font-bold px-3 py-1 rounded-full border border-emerald-200 shadow-2xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>🟢 Requester In Chat</span>
                    </span>
                </template>
                <template x-if="!requesterActive">
                    <span class="inline-flex items-center space-x-1.5 bg-slate-100 text-slate-500 text-[10px] font-medium px-3 py-1 rounded-full border border-slate-200/70" title="Replies will send a Telegram push alert">
                        <span>📱 Telegram Push Ready</span>
                    </span>
                </template>
            </div>
        </div>

        <!-- Row 2: Subject & Primary Lifecycle Command Actions -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="space-y-1 flex-1">
                <h1 class="text-xl font-black text-slate-900 leading-snug">{{ $ticket->subject }}</h1>
                <div class="text-xs text-slate-500 flex flex-wrap items-center gap-2.5">
                    <span>Created: <strong>{{ $ticket->created_at->format('M d, Y H:i') }}</strong> ({{ $ticket->created_at->diffForHumans() }})</span>
                    <span>•</span>
                    <span>Category: <strong class="text-slate-700">{{ $ticket->category->name }}</strong></span>
                    <span>•</span>
                    <span>Branch: <strong class="text-slate-700">{{ $ticket->branch->name }} ({{ $ticket->branch->code }})</strong></span>
                </div>
            </div>

            <!-- Fast Action Command Buttons -->
            <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                {{-- If Unassigned: Fast "Assign to Me" + Assign Technician Drawer --}}
                @if(!$ticket->technician)
                    <form method="POST" action="{{ route('portal.tickets.assign-me', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                            <span>⚡</span>
                            <span>Assign to Me</span>
                        </button>
                    </form>
                    <button type="button" @click="reassignDrawer = !reassignDrawer" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-slate-200 transition">
                        Assign Technician...
                    </button>
                @endif

                {{-- If Assigned or Reopened: Accept & Start Work / Resume Work --}}
                @if(in_array($ticket->status, ['assigned', 'reopened']) && ($ticket->current_technician_id === Auth::id() || Auth::user()->hasRole(['system_admin', 'hod'])))
                    <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                            <span>⚡</span>
                            <span>{{ $ticket->status === 'reopened' ? 'Resume Work' : 'Accept & Start Work' }}</span>
                        </button>
                    </form>
                @endif

                @if($ticket->status === 'reopened')
                    <button type="button" @click="reassignDrawer = !reassignDrawer" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-slate-200 transition">
                        Reassign Technician...
                    </button>
                @endif

                {{-- If In Progress: Mark Done, Block, Ask Clarification --}}
                @if($ticket->status === 'in_progress')
                    <form method="POST" action="{{ route('portal.tickets.done', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                            <span>✔</span>
                            <span>Mark as Done</span>
                        </button>
                    </form>

                    @if($ticket->isHoldPending())
                        <div class="inline-flex items-center space-x-1.5 bg-amber-50 text-amber-800 border border-amber-300 font-bold text-xs px-3 py-2 rounded-xl">
                            <span>⏳</span>
                            <span>Hold Pending Approval</span>
                        </div>
                        @if(Auth::user()->hasRole(['system_admin', 'hod']))
                            <form method="POST" action="{{ route('portal.tickets.block.approve', $ticket->id) }}">
                                @csrf
                                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1">
                                    <span>⏸️</span>
                                    <span>Approve Hold</span>
                                </button>
                            </form>
                            <button type="button" @click="rejectHoldModal = true" class="bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-xs px-3 py-2.5 rounded-xl transition">
                                Reject Hold
                            </button>
                        @endif
                    @else
                        <button type="button" @click="blockModal = true" class="bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs px-3 py-2.5 rounded-xl transition flex items-center space-x-1">
                            <span>⏸️</span>
                            <span>{{ Auth::user()->hasRole(['system_admin', 'hod']) ? 'Block / Hold' : 'Request Hold' }}</span>
                        </button>
                    @endif

                    <button type="button" @click="clarifyModal = true" class="bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 font-semibold text-xs px-3 py-2.5 rounded-xl border border-slate-200 transition">
                        ❓ Ask Info
                    </button>
                @endif

                {{-- If Blocked: Resume Work --}}
                @if($ticket->status === 'blocked')
                    <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                            <span>▶</span>
                            <span>Resume Work</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Integrated Problem Description Callout (Cleanly embedded, replaces redundant card) -->
        @if($ticket->description)
            <div class="mt-2 pt-3 border-t border-slate-100 text-xs bg-slate-50/70 border border-slate-200/60 rounded-2xl p-3.5 flex items-start space-x-3">
                <span class="text-base leading-none select-none text-slate-400">📝</span>
                <div class="flex-1 min-w-0">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Initial Problem Statement</span>
                    <p class="leading-relaxed whitespace-pre-wrap text-slate-800 font-medium text-xs">{{ $ticket->description }}</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Alert Banners -->
    @if($ticket->status === 'reopened')
        <div class="bg-amber-500 text-white p-4 rounded-2xl shadow-md flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <div class="font-extrabold text-sm">Ticket Reopened by Requester!</div>
                    <div class="text-xs text-amber-100">
                        The employee indicated the issue is still unresolved. Check their feedback in the chat stream below, resume work, or reassign to another technician.
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                @if($ticket->current_technician_id === Auth::id() || Auth::user()->hasRole(['system_admin', 'hod']))
                    <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-white text-amber-900 hover:bg-amber-50 font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1">
                            <span>⚡</span>
                            <span>Resume Work</span>
                        </button>
                    </form>
                @endif
                <button type="button" @click="reassignDrawer = true" class="bg-black/20 hover:bg-black/30 text-white font-bold text-xs px-3 py-2 rounded-xl transition">
                    Reassign
                </button>
            </div>
        </div>
    @endif

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

    @if($ticket->isHoldPending())
        <div class="bg-amber-50 border-2 border-amber-300 text-amber-900 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start space-x-3">
                <span class="text-2xl">⏳</span>
                <div>
                    <div class="flex items-center space-x-2">
                        <div class="font-bold text-xs uppercase tracking-wider text-amber-950">Hold Requested (Pending IT HOD Approval)</div>
                        <span class="bg-amber-200 text-amber-900 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full">⏱️ SLA Active (Not Paused)</span>
                    </div>
                    <p class="text-xs text-amber-900 mt-1">
                        <strong>Category:</strong> {{ $ticket->hold_requested_category }} &bull;
                        <strong>Requested by:</strong> {{ $ticket->holdRequestedBy?->name ?? 'Technician' }} ({{ $ticket->hold_requested_at->format('M d, Y H:i') }})
                    </p>
                    <p class="text-xs text-amber-800 mt-0.5"><strong>Reason:</strong> {{ $ticket->hold_requested_reason }}</p>
                </div>
            </div>

            @if(Auth::user()->hasRole(['system_admin', 'hod']))
                <div class="flex items-center space-x-2 shrink-0">
                    <button type="button" @click="rejectHoldModal = true" class="px-3.5 py-2 text-xs font-bold text-rose-700 bg-rose-100 hover:bg-rose-200 rounded-xl transition">
                        Reject Hold
                    </button>
                    <form method="POST" action="{{ route('portal.tickets.block.approve', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-xs transition">
                            Approve Hold (Pause SLA)
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    @if($ticket->status === 'blocked')
        <div class="bg-amber-50 border border-amber-300 text-amber-900 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start space-x-3">
                <span class="text-xl">🛑</span>
                <div>
                    <div class="flex items-center space-x-2">
                        <div class="font-bold text-xs uppercase tracking-wider">Ticket Currently On Hold / Blocked</div>
                        <span class="bg-amber-200 text-amber-900 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full">⏸️ SLA Clock Paused</span>
                    </div>
                    <p class="text-xs text-amber-800 mt-1"><strong>Reason:</strong> {{ $ticket->blocked_reason }}</p>
                    <div class="text-[11px] text-amber-700 mt-1 flex items-center space-x-2 flex-wrap">
                        <span>Blocked since: <strong>{{ $ticket->blocked_at ? $ticket->blocked_at->format('M d, Y H:i') : $ticket->updated_at->format('M d, Y H:i') }}</strong></span>
                        @if($ticket->getPausedHours() > 0)
                            <span>•</span>
                            <span>Total Paused: <strong>{{ $ticket->getPausedHours() }} hours</strong> (excluded from SLA)</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($ticket->current_technician_id === Auth::id() || Auth::user()->hasAnyRole(['system_admin', 'hod']))
                <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                        <span>▶️</span>
                        <span>Resume Work</span>
                    </button>
                </form>
            @endif
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
                            @if($approval->role_name === 'department_hod')
                                @php
                                    $stepDept = $approval->department ?? $ticket->requester->employee?->department;
                                    $stepBranch = $approval->branch ?? $ticket->branch;
                                @endphp
                                @if($stepDept || $stepBranch)
                                    <span class="text-slate-600 font-medium">({{ $stepDept?->name ?? 'Dept' }} &bull; {{ $stepBranch?->name ?? 'Branch' }})</span>
                                @endif
                            @elseif($approval->role_name === 'it_manager')
                                <span class="text-indigo-600 font-medium">(Central IT &bull; Head Office)</span>
                            @endif
                        </div>

                        @if($approval->status !== 'pending')
                            <div class="mt-2 pt-2 border-t border-slate-200/60 text-[11px] space-y-1">
                                <div class="text-slate-600">
                                    Actioned by: <strong>{{ $approval->approver->name ?? 'System' }}</strong>
                                    ({{ $approval->actioned_at?->format('d-M-Y H:i:s') }})
                                </div>
                                @if($approval->comments)
                                    <div class="text-slate-500 italic text-[11px] bg-white/70 p-1.5 rounded">
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

        <!-- Left Column: Details, Assignee, Requester & Audit History -->
        <div class="space-y-6">
            
            <!-- Assigned Technician & Delegation Card -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Current Assignee</span>
                    @if($ticket->technician)
                        <button type="button" @click="reassignDrawer = !reassignDrawer" class="text-xs text-blue-600 hover:underline font-semibold">
                            Reassign
                        </button>
                    @endif
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
                    <!-- High-Visibility Unassigned Triage Card -->
                    <div class="p-4 bg-amber-50/70 border border-amber-200/80 rounded-2xl space-y-3">
                        <div class="flex items-center space-x-2 text-amber-800 text-xs font-bold">
                            <span>⚠️</span>
                            <span>Awaiting Assignment</span>
                        </div>
                        <p class="text-[11px] text-amber-900/80">This ticket has not been assigned to any technician yet.</p>
                        
                        <form method="POST" action="{{ route('portal.tickets.assign-me', $ticket->id) }}">
                            @csrf
                            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center space-x-1.5">
                                <span>⚡</span>
                                <span>Assign to Myself</span>
                            </button>
                        </form>

                        <div class="pt-2 border-t border-amber-200/60">
                            <div class="text-[11px] font-bold text-slate-600 mb-1.5">Or assign to a teammate:</div>
                            <form method="POST" action="{{ route('portal.tickets.assign', $ticket->id) }}" class="space-y-2">
                                @csrf
                                <select name="technician_id" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white font-medium focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Technician...</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl transition">
                                    Assign Technician
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Reassign Drawer (For already assigned tickets) -->
                @if($ticket->technician)
                    <div x-show="reassignDrawer" class="pt-3 border-t border-slate-100 space-y-3" x-cloak>
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
                @endif
            </div>

            <!-- SLA & Resolution Commitment Card -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">SLA Commitments</span>
                    @if($ticket->isSlaPaused())
                        <span class="bg-amber-100 text-amber-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full flex items-center space-x-1">
                            <span>⏸️</span> <span>SLA Paused</span>
                        </span>
                    @elseif($ticket->isLunchBreak() && !in_array($ticket->status, ['resolved_confirmed', 'resolved_auto_closed', 'closed']))
                        <span class="bg-amber-100 text-amber-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full flex items-center space-x-1">
                            <span>🍽️</span> <span>Lunch Break</span>
                        </span>
                    @elseif($ticket->isSlaOverdue())
                        <span class="bg-rose-100 text-rose-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full animate-pulse">
                            ⚠️ Overdue
                        </span>
                    @else
                        <span class="bg-emerald-100 text-emerald-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full">
                            ✔ On Track
                        </span>
                    @endif
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between items-center text-slate-600">
                        <span class="text-slate-400">Target Resolution:</span>
                        <span class="font-bold text-slate-800">{{ $ticket->category?->target_resolve_hours ? "{$ticket->category->target_resolve_hours} hours" : 'N/A' }}</span>
                    </div>

                    <div class="flex justify-between items-center text-slate-600">
                        <span class="text-slate-400">Wall-Clock Age:</span>
                        <span class="font-medium text-slate-700">{{ $ticket->created_at->diffForHumans(null, true) }}</span>
                    </div>

                    @if($ticket->getPausedMinutes() > 0)
                        <div class="flex justify-between items-center text-amber-800 bg-amber-50 p-2 rounded-xl">
                            <span class="font-medium">⏸️ On-Hold Time:</span>
                            <span class="font-bold">{{ $ticket->getPausedHours() }}h ({{ $ticket->getPausedMinutes() }}m)</span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center text-slate-600">
                        <span class="text-slate-400">Active IT Time:</span>
                        <span class="font-bold text-blue-700">{{ $ticket->getActiveHoursElapsed() }} hours</span>
                    </div>

                    @if($ticket->getSlaDeadline())
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Target Deadline:</span>
                            <span class="font-bold text-slate-800">{{ $ticket->getSlaDeadline()->format('D, d-M h:i A') }}</span>
                        </div>
                    @endif

                    <div class="pt-2 border-t border-slate-100 flex justify-between items-center">
                        <span class="text-slate-400">SLA Status:</span>
                        @if($ticket->isSlaPaused())
                            <span class="font-bold text-amber-600">Clock Frozen (Vendor Hold)</span>
                        @elseif($ticket->isLunchBreak() && !in_array($ticket->status, ['resolved_confirmed', 'resolved_auto_closed', 'closed']))
                            <span class="font-bold text-amber-600">🍽️ Lunch Break (Frozen until 01:00 PM)</span>
                        @elseif($ticket->isOutsideBusinessHours() && !in_array($ticket->status, ['resolved_confirmed', 'resolved_auto_closed', 'closed']))
                            <span class="font-bold text-indigo-600">🌙 Off-Hours (Frozen until 08:00 AM)</span>
                        @elseif($ticket->getSlaRemainingHours() !== null)
                            @if($ticket->getSlaRemainingHours() < 0)
                                <span class="font-bold text-rose-600">Overdue (+{{ abs($ticket->getSlaRemainingHours()) }}h)</span>
                            @else
                                <span class="font-bold text-emerald-600">{{ $ticket->getSlaRemainingHours() }}h remaining</span>
                            @endif
                        @else
                            <span class="text-slate-400">No Target Defined</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Requester Profile Card -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block border-b border-slate-100 pb-2">Requester Info</span>
                
                <div class="space-y-3 text-xs">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-sm border border-slate-200">
                            {{ substr($ticket->requester->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="font-bold text-sm text-slate-800">{{ $ticket->requester->name }}</div>
                            <div class="text-blue-600 font-mono font-medium text-[11px]">{{ $ticket->requester->employee?->employee_code ?? 'EMP' }}</div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 space-y-2 text-slate-600">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm">🏢</span>
                            <span><strong>Branch:</strong> {{ $ticket->branch->name }} ({{ $ticket->branch->code }})</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm">📂</span>
                            <span><strong>Department:</strong> {{ $ticket->requester->employee?->department?->name ?? 'General' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2 truncate">
                                <span class="text-sm">📧</span>
                                <a href="mailto:{{ $ticket->requester->email }}" class="text-blue-600 hover:underline truncate">{{ $ticket->requester->email ?? 'N/A' }}</a>
                            </div>
                            @if($ticket->requester->email)
                                <button type="button" @click="copyEmail('{{ $ticket->requester->email }}')" class="text-slate-400 hover:text-slate-600 text-[10px] ml-2 font-semibold">
                                    <span x-show="!copiedEmail">Copy</span>
                                    <span x-show="copiedEmail" class="text-emerald-600">Copied!</span>
                                </button>
                            @endif
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm">📱</span>
                            <span><strong>Telegram:</strong>
                                @if($ticket->requester->telegram_username)
                                    <a href="https://t.me/{{ ltrim($ticket->requester->telegram_username, '@') }}" target="_blank" class="text-blue-600 font-mono hover:underline font-bold">
                                        {{ Str::start($ticket->requester->telegram_username, '@') }} &nearr;
                                    </a>
                                @else
                                    <span class="text-slate-400">ID: {{ $ticket->requester->telegram_id ?? 'Not linked' }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Trail & Assignment History -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block border-b border-slate-100 pb-2">Assignment History</span>
                <div class="space-y-2 max-h-56 overflow-y-auto pr-1 text-xs">
                    @forelse($ticket->assignments as $assign)
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-[11px] space-y-0.5">
                            <div class="font-semibold text-slate-700">
                                Assigned to <strong>{{ $assign->technician?->name ?? 'Technician' }}</strong>
                            </div>
                            <div class="text-slate-400 text-[10px]">
                                by {{ $assign->assignedBy?->name ?? 'System' }} • {{ $assign->assigned_at ? $assign->assigned_at->diffForHumans() : '' }}
                            </div>
                            @if($assign->reassignment_reason)
                                <div class="text-slate-500 italic text-[10px] bg-white p-1 rounded border border-slate-100">
                                    "{{ $assign->reassignment_reason }}"
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-slate-400 text-xs py-2 text-center">No assignment history.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column: Conversation, Internal Notes & Reply Workspace -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Unified Modern Chat Panel (Starts directly at top of column) -->
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
                             class="bg-emerald-600 text-white text-[11px] font-semibold px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-xs" x-cloak>
                            <span>✔</span>
                            <span x-text="chatToast"></span>
                        </div>

                        <span x-show="requesterActive" class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full flex items-center gap-1.5" x-cloak>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                            Employee Active in TMA
                        </span>
                        <span x-show="!requesterActive" class="text-[11px] text-slate-500 bg-slate-100 border border-slate-200/70 px-2.5 py-0.5 rounded-full flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            Employee Away (Telegram push enabled)
                        </span>
                    </div>
                </div>

                <!-- Contained Scrollable Chat Viewport (Modern Messenger Layout) -->
                <div id="portalChatStream" class="flex-1 max-h-[560px] min-h-[380px] overflow-y-auto p-4 sm:p-5 space-y-3"
                     style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">
                    <template x-for="msg in chatMessages" :key="msg.id">
                        <div>
                            <!-- System Event: Compact Centered Pill -->
                            <template x-if="msg.visibility === 'system_event'">
                                <div class="flex justify-center my-2 select-none">
                                    <span class="bg-slate-200/80 border border-slate-300/40 text-slate-600 text-[10px] font-medium px-3 py-1 rounded-full shadow-2xs"
                                          x-text="'⚙️ ' + msg.body + ' • ' + msg.created_at_human"></span>
                                </div>
                            </template>

                            <!-- Confidential Internal Note (Distinct Amber Card) -->
                            <template x-if="msg.visibility === 'internal_staff_only'">
                                <div class="max-w-xl mx-auto my-2.5">
                                    <div class="bg-amber-50/95 border-2 border-amber-300/80 rounded-2xl p-4 shadow-2xs space-y-2">
                                        <div class="flex items-center justify-between text-xs border-b border-amber-200/70 pb-1.5">
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

                                        <!-- Photo Attachments (TMA Clean Style) -->
                                        <template x-if="msg.attachments && msg.attachments.some(a => a.is_image)">
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                    <div class="relative group">
                                                        <div class="relative rounded-2xl overflow-hidden cursor-pointer shadow-sm border border-amber-300/80 bg-amber-100/50 hover:shadow-md active:scale-[0.98] transition max-w-[280px] sm:max-w-[340px]"
                                                             @click="openLightbox(att.preview_url, att.original_filename, att.download_url)">
                                                            <img :src="att.preview_url" :alt="att.original_filename" loading="lazy" class="w-full max-h-72 object-cover block">
                                                            <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">HD</div>
                                                            <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                                <span x-text="att.file_size"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- Document Attachments -->
                                        <template x-if="msg.attachments && msg.attachments.some(a => !a.is_image)">
                                            <div class="space-y-1.5 pt-1 max-w-sm">
                                                <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                    <a :href="att.download_url" target="_blank"
                                                       class="flex items-center space-x-2.5 p-2 rounded-xl bg-amber-100/80 hover:bg-amber-100 border border-amber-300 text-amber-950 transition shadow-2xs">
                                                        <div :class="getFileBadgeClass(att.original_filename, false)" class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-[9px] shrink-0">
                                                            <span x-text="getFileExtension(att.original_filename)"></span>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="text-xs font-semibold truncate" x-text="att.original_filename"></div>
                                                            <div class="text-[10px] text-amber-700/80 font-mono" x-text="att.file_size"></div>
                                                        </div>
                                                        <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                </template>
                                            </div>
                                        </template>

                                        <div class="text-[9px] text-amber-700/70 italic pt-0.5">
                                            Hidden from employee. Only visible to IT Help Desk and Technicians.
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- IT Staff Message (Right-aligned Modern Messenger Pill) -->
                            <template x-if="msg.visibility !== 'system_event' && msg.visibility !== 'internal_staff_only' && msg.is_staff">
                                <div class="flex flex-col items-end w-full my-2 space-y-1.5">
                                    <!-- Sender & Timestamp -->
                                    <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 pr-1 select-none">
                                        <span class="font-semibold text-blue-600" x-text="msg.sender_name"></span>
                                        <span class="text-[8px] bg-blue-100 text-blue-700 font-bold px-1.5 py-0.2 rounded uppercase">IT Staff</span>
                                        <span>•</span>
                                        <span class="font-mono" x-text="msg.created_at_full"></span>
                                    </div>

                                    <!-- 1. Standalone Borderless Photo Card(s) with Generous Aspect & HD Badge (TMA Style) -->
                                    <template x-if="msg.attachments && msg.attachments.some(a => a.is_image)">
                                        <div class="flex flex-col items-end space-y-1.5">
                                            <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                <div class="relative group">
                                                    <div class="relative rounded-2xl rounded-tr-xs overflow-hidden cursor-pointer shadow-md border border-black/5 bg-slate-100 hover:shadow-lg active:scale-[0.98] transition max-w-[280px] sm:max-w-[360px]"
                                                         @click="openLightbox(att.preview_url, att.original_filename, att.download_url)">
                                                        <img :src="att.preview_url" :alt="att.original_filename"
                                                             loading="lazy"
                                                             class="w-full max-h-80 object-cover block">
                                                        
                                                        <!-- HD Badge in top right corner -->
                                                        <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">
                                                            HD
                                                        </div>

                                                        <!-- Subtle bottom file size indicator -->
                                                        <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                            <span x-text="att.file_size"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- 2. Standalone Text Message Bubble (Sleek Sapphire Messenger Pill) -->
                                    <template x-if="msg.body && msg.body !== '(Attachment)' && msg.body.trim().length > 0">
                                        <div class="max-w-[85%] sm:max-w-[75%] rounded-2xl rounded-tr-xs px-4 py-2.5 shadow-sm text-white select-text"
                                             style="background: linear-gradient(135deg, #0084ff 0%, #0066f6 100%); box-shadow: 0 2px 8px -1px rgba(0, 132, 255, 0.35);">
                                            <p class="text-[13px] leading-relaxed whitespace-pre-wrap font-normal" x-text="msg.body"></p>
                                        </div>
                                    </template>

                                    <!-- 3. Non-Image Document Cards (PDFs, Docs, etc.) -->
                                    <template x-if="msg.attachments && msg.attachments.some(a => !a.is_image)">
                                        <div class="space-y-1.5 max-w-[85%] sm:max-w-[75%]">
                                            <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                <a :href="att.download_url" target="_blank"
                                                   class="flex items-center space-x-2.5 p-2.5 rounded-2xl bg-white border border-slate-200/90 hover:bg-slate-50 transition shadow-xs">
                                                    <div :class="getFileBadgeClass(att.original_filename, true)" class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[10px] shrink-0 shadow-2xs">
                                                        <span x-text="getFileExtension(att.original_filename)"></span>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="text-xs font-semibold text-slate-800 truncate" x-text="att.original_filename"></div>
                                                        <div class="text-[10px] text-slate-400 font-mono" x-text="att.file_size"></div>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Requester (Employee) Message (Left-aligned Modern Messenger Pill) -->
                            <template x-if="msg.visibility !== 'system_event' && msg.visibility !== 'internal_staff_only' && !msg.is_staff">
                                <div class="flex items-start space-x-2.5 w-full my-2">
                                    <!-- Avatar Initial Circle -->
                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold shrink-0 mt-1 shadow-2xs select-none"
                                         x-text="(msg.sender_name || 'U').charAt(0).toUpperCase()">
                                    </div>

                                    <!-- Content Column -->
                                    <div class="flex flex-col items-start max-w-[85%] sm:max-w-[75%] space-y-1.5 min-w-0">
                                        <!-- Sender Name & Requester Badge -->
                                        <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 pl-1 select-none">
                                            <span class="font-semibold text-slate-700" x-text="msg.sender_name"></span>
                                            <span class="text-[8px] bg-slate-200 text-slate-700 font-bold px-1.5 py-0.2 rounded uppercase">Requester</span>
                                            <span>•</span>
                                            <span class="font-mono" x-text="msg.created_at_full"></span>
                                        </div>

                                        <!-- 1. Standalone Borderless Photo Card(s) with Generous Aspect & HD Badge (TMA Style) -->
                                        <template x-if="msg.attachments && msg.attachments.some(a => a.is_image)">
                                            <div class="flex flex-col items-start space-y-1.5">
                                                <template x-for="att in msg.attachments.filter(a => a.is_image)" :key="att.id">
                                                    <div class="relative group">
                                                        <div class="relative rounded-2xl rounded-tl-xs overflow-hidden cursor-pointer shadow-md border border-slate-200/90 bg-slate-100 hover:shadow-lg active:scale-[0.98] transition max-w-[280px] sm:max-w-[360px]"
                                                             @click="openLightbox(att.preview_url, att.original_filename, att.download_url)">
                                                            <img :src="att.preview_url" :alt="att.original_filename"
                                                                 loading="lazy"
                                                                 class="w-full max-h-80 object-cover block">
                                                            
                                                            <!-- HD Badge in top right corner -->
                                                            <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">
                                                                HD
                                                            </div>

                                                            <!-- Subtle bottom file size indicator -->
                                                            <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                                <span x-text="att.file_size"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- 2. Standalone Text Message Bubble (Sleek Clean Pill) -->
                                        <template x-if="msg.body && msg.body !== '(Attachment)' && msg.body.trim().length > 0">
                                            <div class="bg-white border border-slate-200/90 text-slate-800 rounded-2xl rounded-tl-xs px-4 py-2.5 shadow-2xs select-text">
                                                <p class="text-[13px] leading-relaxed whitespace-pre-wrap font-normal" x-text="msg.body"></p>
                                            </div>
                                        </template>

                                        <!-- 3. Non-Image Document Cards (PDFs, Docs, etc.) -->
                                        <template x-if="msg.attachments && msg.attachments.some(a => !a.is_image)">
                                            <div class="space-y-1.5 w-full">
                                                <template x-for="att in msg.attachments.filter(a => !a.is_image)" :key="att.id">
                                                    <a :href="att.download_url" target="_blank"
                                                       class="flex items-center space-x-2.5 p-2.5 rounded-2xl bg-white border border-slate-200/90 hover:bg-slate-50 transition shadow-xs">
                                                        <div :class="getFileBadgeClass(att.original_filename, false)" class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[10px] shrink-0 shadow-2xs">
                                                            <span x-text="getFileExtension(att.original_filename)"></span>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="text-xs font-semibold text-slate-800 truncate" x-text="att.original_filename"></div>
                                                            <div class="text-[10px] text-slate-400 font-mono" x-text="att.file_size"></div>
                                                        </div>
                                                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div x-show="chatMessages.length === 0" class="text-center py-12 text-xs text-slate-400">
                        💬 No conversation messages yet. Send a reply or post a note below.
                    </div>
                </div>

                <!-- Modern Bottom Composer -->
                <div class="p-4 bg-white border-t border-slate-200 space-y-3">
                    
                    <!-- Mode Toggle: Reply vs Internal Note -->
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
                                      @paste="handlePaste($event)"
                                      :placeholder="messageTab === 'internal_staff_only' ? 'Write confidential internal note (Press Enter to post, Shift+Enter for newline, Ctrl+V to paste screenshot)...' : 'Type reply that the employee will see on their Telegram Mini App (Press Enter to send, Shift+Enter for newline, Ctrl+V to paste screenshot)...'"
                                      :class="messageTab === 'internal_staff_only' ? 'bg-amber-50/40 border-amber-300 focus:ring-amber-500' : 'bg-slate-50/70 border-slate-300 focus:bg-white focus:ring-blue-600'"
                                      class="w-full text-xs p-3 rounded-xl border focus:outline-none focus:ring-2"></textarea>
                        </div>

                        <!-- Staged Attachment Chips -->
                        <div x-show="messageFiles.length > 0" class="flex flex-wrap gap-2 pt-0.5" x-cloak>
                            <template x-for="(file, idx) in messageFiles" :key="idx">
                                <span class="inline-flex items-center space-x-1.5 bg-blue-50 border border-blue-200 text-blue-800 text-[11px] font-medium px-2.5 py-1 rounded-xl shadow-2xs">
                                    <span>📎</span>
                                    <span class="truncate max-w-[140px]" x-text="file.name"></span>
                                    <span class="text-[9px] opacity-70" x-text="'(' + (file.size > 1048576 ? (file.size/1048576).toFixed(1) + ' MB' : Math.round(file.size/1024) + ' KB') + ')'"></span>
                                    <button type="button" @click="removeFile(idx)" class="text-blue-500 hover:text-rose-600 font-bold ml-1 text-xs">&times;</button>
                                </span>
                            </template>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                            <div class="flex items-center space-x-2">
                                <input type="file" id="chatFileInput" multiple accept="image/*,application/pdf"
                                       @change="handleFileSelect($event)"
                                       class="hidden">
                                <label for="chatFileInput"
                                       class="cursor-pointer inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition shadow-2xs">
                                    <span>📎</span>
                                    <span>Attach Files</span>
                                </label>
                                <span class="text-[11px] text-slate-400 hidden sm:inline">or paste screenshot (Ctrl+V)</span>
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

    <!-- MARK BLOCKED / REQUEST HOLD MODAL -->
    <div x-show="blockModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="blockModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center space-x-2">
                <span class="text-xl">⏸️</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">
                        @if(Auth::user()->hasRole(['system_admin', 'hod']))
                            Place Ticket On Hold / Blocked
                        @else
                            Request Ticket Hold
                        @endif
                    </h3>
                    <p class="text-xs text-slate-500">
                        @if(Auth::user()->hasRole(['system_admin', 'hod']))
                            Pauses the SLA countdown immediately upon confirmation.
                        @else
                            Requires IT HOD approval. SLA countdown continues until approved.
                        @endif
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('portal.tickets.block', $ticket->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Hold Category</label>
                    <select name="hold_category" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="Waiting for Vendor / Hardware Delivery">Waiting for Vendor / Hardware Delivery (e.g. RAM, SSD, screen)</option>
                        <option value="Waiting for 3rd-Party Software Support / ISP">Waiting for 3rd-Party Software Support / ISP</option>
                        <option value="User Unreachable / Extended Leave">User Unreachable / Extended Leave</option>
                        <option value="Awaiting Management Budget Approval">Awaiting Management Budget Approval</option>
                        <option value="External Dependency / Site Access">External Dependency / Site Access</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Detailed Explanation & Justification</label>
                    <textarea name="blocked_reason" required rows="3" placeholder="Explain the specific reason (e.g., Supplier pending delivery of 16GB DDR4 RAM PO#4021)..." class="w-full text-xs p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="blockModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                        @if(Auth::user()->hasRole(['system_admin', 'hod']))
                            Confirm Hold (Pause SLA)
                        @else
                            Submit Hold Request
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- REJECT HOLD MODAL (IT HOD) -->
    <div x-show="rejectHoldModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="rejectHoldModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center space-x-2 text-rose-600">
                <span class="text-xl">❌</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Reject Hold Request</h3>
                    <p class="text-xs text-slate-500">Provide feedback to the technician on why the hold is not approved.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('portal.tickets.block.reject', $ticket->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Rejection Reason (Required)</label>
                    <textarea name="rejection_reason" required rows="3" placeholder="Explain why the hold request is rejected (e.g., spare RAM is available in IT stock)..." class="w-full text-xs p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="rejectHoldModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Confirm Rejection</button>
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
