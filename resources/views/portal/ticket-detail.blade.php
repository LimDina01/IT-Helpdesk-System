@extends('portal.layout')

@section('title', 'Ticket ' . $ticket->ticket_code)

@section('content')
@php
    $defaultInspectorTab = \Illuminate\Support\Str::contains((string) session('status', ''), ['Equipment', 'stock', 'Component', 'asset', 'maintenance'])
        || $errors->hasAny(['asset_id', 'component_id', 'description'])
        ? 'equipment'
        : 'overview';
@endphp
<div x-data="{
    inspectorTab: '{{ $defaultInspectorTab }}',
    chatFilter: 'all',
    showJumpToBottom: false,
    clarifyModal: false,
    blockModal: false,
    rejectHoldModal: false,
    reassignDrawer: false,
    reclassifyModal: false,
    reclassifyType: '{{ $ticket->ticket_type }}',
    reclassifyPriority: '{{ $ticket->priority }}',
    reclassifyParentId: {{ $ticket->category->parent_id ?? $ticket->category_id }},
    reclassifySubId: {{ $ticket->category_id }},
    categoryHierarchy: {{ Js::from($categoriesHierarchy) }},

    getParentCategories() {
        const targetType = this.reclassifyType === 'incident' ? 'incident' : 'service_request';
        return this.categoryHierarchy.filter(p => p.type === targetType);
    },

    getSubcategories() {
        const parent = this.categoryHierarchy.find(p => p.id == this.reclassifyParentId);
        if (!parent || !parent.children || parent.children.length === 0) {
            return parent ? [{ id: parent.id, name: parent.name }] : [];
        }
        return parent.children;
    },

    onTypeChange() {
        const parents = this.getParentCategories();
        if (parents.length > 0) {
            this.reclassifyParentId = parents[0].id;
            this.onParentChange();
        }
    },

    onParentChange() {
        const subs = this.getSubcategories();
        if (subs.length > 0) {
            this.reclassifySubId = subs[0].id;
        } else {
            this.reclassifySubId = this.reclassifyParentId;
        }
    },
    messageTab: 'requester_visible',
    messageBody: '',
    messageFiles: [],
    sendingMessage: false,
    chatToast: '',
    chatToastTimer: null,
    approveModal: false,
    rejectModal: false,
    escalateModal: false,
    forwardModal: false,
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
        'created_at_full' => $m->created_at->format('M d, H:i'),
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
    getFilteredMessages() {
        if (this.chatFilter === 'conversation') {
            return this.chatMessages.filter(m => m.visibility !== 'system_event');
        }
        if (this.chatFilter === 'notes') {
            return this.chatMessages.filter(m => m.visibility === 'internal_staff_only');
        }
        return this.chatMessages;
    },
    getInternalNotesCount() {
        return this.chatMessages.filter(m => m.visibility === 'internal_staff_only').length;
    },
    handleChatScroll(el) {
        if (!el) return;
        this.showJumpToBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) > 120;
    },
    jumpToAttachAsset() {
        this.inspectorTab = 'equipment';
        this.$nextTick(() => {
            window.dispatchEvent(new CustomEvent('open-attach-asset'));
        });
    },
    scrollToBottom() {
        this.$nextTick(() => {
            const el = document.getElementById('portalChatStream');
            if (el) {
                el.scrollTop = el.scrollHeight;
                this.showJumpToBottom = false;
            }
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
                if (this.chatFilter === 'notes' && this.messageTab === 'requester_visible') {
                    this.chatFilter = 'all';
                }
                await this.fetchLiveMessages();
                this.scrollToBottom();
                if (inputEl) {
                    inputEl.value = '';
                    inputEl.focus();
                }
            } else {
                const errData = await res.json().catch(() => ({}));
                if (!this.messageBody) {
                    this.messageBody = text;
                    if (inputEl) inputEl.value = text;
                }
                this.showToast(errData.message || Object.values(errData.errors || {})[0]?.[0] || 'Failed to send message. Please retry.');
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
}" x-init="initLivePoll()" class="space-y-4">

    <!-- Top Ticket Header & Action Command Center -->
    <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-3">
        
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
                @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                    @if($ticket->isIncident())
                        <button type="button" @click="reclassifyModal = true" title="Click to change ticket type / category" class="bg-rose-100 hover:bg-rose-200 text-rose-700 border border-rose-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 cursor-pointer transition shadow-2xs">
                            <span>🔴</span> <span>Incident</span>
                            <svg class="w-2.5 h-2.5 text-rose-500 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    @else
                        <button type="button" @click="reclassifyModal = true" title="Click to change ticket type / category" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 border border-indigo-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 cursor-pointer transition shadow-2xs">
                            <span>🔵</span> <span>Service Request</span>
                            <svg class="w-2.5 h-2.5 text-indigo-500 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    @endif
                @else
                    @if($ticket->isIncident())
                        <span class="bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 shadow-2xs">
                            <span>🔴</span> <span>Incident</span>
                        </span>
                    @else
                        <span class="bg-indigo-100 text-indigo-700 border border-indigo-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 shadow-2xs">
                            <span>🔵</span> <span>Service Request</span>
                        </span>
                    @endif
                @endif

                <!-- IT Division Pill (IT-OPR vs IT-PRD) -->
                @if(($ticket->it_division ?? '') === 'prd')
                    <span class="bg-purple-100 text-purple-800 border border-purple-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 shadow-2xs">
                        <span>💻</span> <span>IT-PRD</span>
                    </span>
                @else
                    <span class="bg-blue-100 text-blue-800 border border-blue-200 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg tracking-wider flex items-center space-x-1 shadow-2xs">
                        <span>🛠️</span> <span>IT-OPR</span>
                    </span>
                @endif

                <!-- Inline Priority Switcher (Helpdesk / HOD / Admin) or Static Badge (Technician) -->
                @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
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
                @else
                    <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-lg border shadow-2xs {{ $ticket->priority === 'critical' ? 'bg-rose-600 text-white border-rose-700' : ($ticket->priority === 'high' ? 'bg-amber-500 text-white border-amber-600' : ($ticket->priority === 'medium' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-slate-100 text-slate-700 border-slate-200')) }}">
                        @if($ticket->priority === 'critical') 🔴 Critical Priority
                        @elseif($ticket->priority === 'high') 🟠 High Priority
                        @elseif($ticket->priority === 'medium') 🔵 Medium Priority
                        @else ⚪ Low Priority
                        @endif
                    </span>
                @endif

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
                    <span>Category: <strong class="text-slate-700">{{ $ticket->category->name }}</strong>
                        @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                            <button type="button" @click="reclassifyModal = true" class="text-blue-600 hover:text-blue-800 text-[11px] underline ml-1 font-semibold cursor-pointer">(Change)</button>
                        @endif
                    </span>
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
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                            <span>⚡</span>
                            <span>Assign to Me</span>
                        </button>
                    </form>
                    @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                        <button type="button" @click="reassignDrawer = true" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-slate-200 transition cursor-pointer flex items-center space-x-1.5">
                            <span>Assign Technician...</span>
                        </button>
                    @endif
                @endif

                {{-- If Assigned or Reopened: Accept & Start Work / Resume Work --}}
                @if(in_array($ticket->status, ['assigned', 'reopened']) && ($ticket->current_technician_id === Auth::id() || Auth::user()->hasRole(['system_admin', 'hod'])))
                    <form method="POST" action="{{ route('portal.tickets.accept', $ticket->id) }}">
                        @csrf
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                            <span>⚡</span>
                            <span>{{ $ticket->status === 'reopened' ? 'Resume Work' : 'Accept & Start Work' }}</span>
                        </button>
                    </form>
                @endif

                @if($ticket->status === 'reopened' && Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                    <button type="button" @click="reassignDrawer = true" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-slate-200 transition cursor-pointer flex items-center space-x-1.5">
                        <span>Reassign Technician...</span>
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

                {{-- Fast Reclassify Type & Category button (Helpdesk / HOD / Admin) --}}
                @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                    <button type="button" @click="reclassifyModal = true" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-indigo-200 transition flex items-center space-x-1.5 shadow-2xs cursor-pointer" title="Switch Ticket Type or Change Category">
                        <span>🔄</span>
                        <span>Reclassify</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Integrated Problem Description Callout (Cleanly embedded, bounded height for long logs) -->
        @if($ticket->description)
            <div class="mt-1 pt-2.5 border-t border-slate-100 text-xs bg-slate-50/70 border border-slate-200/60 rounded-2xl p-3 flex items-start space-x-2.5">
                <span class="text-sm leading-none select-none text-slate-400 mt-0.5">📝</span>
                <div class="flex-1 min-w-0">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Initial Problem Statement</span>
                    <p class="leading-relaxed whitespace-pre-wrap text-slate-800 font-medium text-xs max-h-32 overflow-y-auto pr-1">{{ $ticket->description }}</p>
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

    <!-- Multi-Stage Approval Workflow Card (Auto-collapsed once completed to keep chat above the fold) -->
    @if($ticket->approvals->isNotEmpty())
        <div x-data="{ approvalsExpanded: {{ ($ticket->isPendingApproval() || $ticket->isRejected() || $canApproveCurrent) ? 'true' : 'false' }} }"
             class="bg-white rounded-3xl px-4 sm:px-5 border border-slate-200/80 shadow-xs transition-all"
             :class="approvalsExpanded ? 'py-4 sm:py-5 space-y-4' : 'py-3'">
            <div @click="approvalsExpanded = !approvalsExpanded"
                 class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 cursor-pointer select-none"
                 :class="approvalsExpanded ? 'border-b border-slate-100 pb-3' : ''">
                <div class="flex items-center space-x-2.5 min-w-0">
                    <span class="text-lg">📋</span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xs sm:text-sm font-extrabold text-slate-900 uppercase tracking-wide">Multi-Step Service Approval Workflow</h2>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                {{ $ticket->approvals->where('status', 'approved')->count() }}/{{ $ticket->approvals->count() }} Steps
                            </span>
                        </div>
                        <p x-show="approvalsExpanded" class="text-xs text-slate-500">Requires formal administrative sign-offs before IT team starts work.</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 shrink-0">
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
                    <button type="button" class="text-[11px] font-bold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition cursor-pointer">
                        <span x-text="approvalsExpanded ? 'Hide ▲' : 'Details ▾'"></span>
                    </button>
                </div>
            </div>

            <!-- Approval Steps Visual Cards -->
            <div x-show="approvalsExpanded" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-4" x-cloak>
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
                <div x-show="approvalsExpanded" class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-md">
                    <div class="space-y-0.5">
                        <div class="font-extrabold text-sm flex items-center space-x-1.5">
                            <span>⚡</span>
                            <span>Action Required: {{ $currentApproval->step_name }}</span>
                        </div>
                        <p class="text-xs text-purple-100">You are authorized to review and decide on this service request step.</p>
                    </div>

                    <div class="flex items-center space-x-2 flex-wrap gap-y-1.5">
                        <button type="button" @click="approveModal = true" class="bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1 cursor-pointer">
                            <span>✔</span>
                            <span>Approve Step</span>
                        </button>
                        <button type="button" @click="escalateModal = true" class="bg-indigo-500 hover:bg-indigo-600 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1 cursor-pointer" title="Escalate to Supervisor or Director">
                            <span>⬆️</span>
                            <span>Escalate</span>
                        </button>
                        <button type="button" @click="forwardModal = true" class="bg-sky-500 hover:bg-sky-600 text-white font-extrabold text-xs px-3 py-2 rounded-xl shadow-xs transition flex items-center space-x-1 cursor-pointer" title="Delegate to peer or colleague">
                            <span>🔄</span>
                            <span>Forward</span>
                        </button>
                        <button type="button" @click="rejectModal = true" class="bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1 cursor-pointer">
                            <span>✖</span>
                            <span>Reject Step</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @php
        $hasRepeatAssetIssue = $ticket->assets->contains(function ($linkedAsset) use ($ticket) {
            return $linkedAsset->tickets
                ->where('id', '!=', $ticket->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->count() >= 1;
        });
        $historyCount = $ticket->assignments->count() + $ticket->auditLogs->count();
    @endphp

    <!-- 2-Column Operational Workspace (Compact Tabbed Inspector + Sticky Full-Height Chat) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- Left Column: Compact Summary + Tabbed Details Inspector -->
        <div class="lg:col-span-5 xl:col-span-4 space-y-4 order-2 lg:order-1">

            <!-- Compact Triage & At-a-Glance Summary Card -->
            <div class="bg-white rounded-3xl p-4 border border-slate-200/80 shadow-xs space-y-3">

                <!-- 1. Current Assignee (Compact) -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Assignee</span>
                        @if($ticket->technician && Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                            <button type="button" @click="reassignDrawer = true" class="text-[11px] text-blue-600 hover:underline font-semibold cursor-pointer">
                                Reassign
                            </button>
                        @endif
                    </div>

                    @if($ticket->technician)
                        <div class="flex items-center justify-between gap-2.5 bg-slate-50/80 rounded-2xl p-2.5 border border-slate-100">
                            <div class="flex items-center space-x-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs shrink-0">
                                    {{ substr($ticket->technician->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 truncate">{{ $ticket->technician->name }}</div>
                                    <div class="text-[10px] text-slate-500 truncate">{{ $ticket->technician->email }}</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                @if($ticket->acknowledged_at)
                                    <span class="inline-block text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-lg">
                                        ✔ Accepted
                                    </span>
                                @else
                                    <span class="inline-block text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-lg">
                                        ⏳ Pending
                                    </span>
                                @endif
                                <div class="text-[9px] text-slate-400 mt-0.5">
                                    {{ $ticket->assigned_at ? $ticket->assigned_at->format('M d, H:i') : 'N/A' }}
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- High-Visibility Unassigned Triage Card -->
                        <div class="p-3.5 bg-amber-50/70 border border-amber-200/80 rounded-2xl space-y-2.5">
                            <div class="flex items-center space-x-2 text-amber-800 text-xs font-bold">
                                <span>⚠️</span>
                                <span>Awaiting Assignment</span>
                            </div>
                            <p class="text-[11px] text-amber-900/80">This ticket has not been assigned to any technician yet.</p>

                            <form method="POST" action="{{ route('portal.tickets.assign-me', $ticket->id) }}">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center space-x-1.5 cursor-pointer">
                                    <span>⚡</span>
                                    <span>Assign to Myself</span>
                                </button>
                            </form>

                            @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
                                <div class="pt-2 border-t border-amber-200/60">
                                    <form method="POST" action="{{ route('portal.tickets.assign', $ticket->id) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        <select name="technician_id" required class="flex-1 min-w-0 text-xs px-2.5 py-1.5 rounded-xl border border-slate-300 bg-white font-medium focus:ring-2 focus:ring-blue-500">
                                            <option value="">Assign teammate...</option>
                                            @foreach($technicians as $tech)
                                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl transition cursor-pointer shrink-0">
                                            Assign
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- 2. SLA Live Health Strip -->
                <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-2xl bg-slate-50/90 border border-slate-200/60 text-xs">
                    <div class="flex items-center space-x-2 min-w-0">
                        @if($ticket->isSlaPaused())
                            <span class="bg-amber-100 text-amber-800 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full shrink-0">⏸️ SLA Paused</span>
                        @elseif($ticket->isLunchBreak() && !in_array($ticket->status, ['resolved_confirmed', 'resolved_auto_closed', 'closed']))
                            <span class="bg-amber-100 text-amber-800 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full shrink-0">🍽️ Lunch Break</span>
                        @elseif($ticket->isSlaOverdue())
                            <span class="bg-rose-100 text-rose-800 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full animate-pulse shrink-0">⚠️ Overdue</span>
                        @else
                            <span class="bg-emerald-100 text-emerald-800 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full shrink-0">✔ On Track</span>
                        @endif

                        <span class="text-[11px] font-semibold text-slate-700 truncate">
                            @if($ticket->getSlaRemainingHours() !== null)
                                @if($ticket->getSlaRemainingHours() < 0)
                                    <span class="text-rose-600">+{{ abs($ticket->getSlaRemainingHours()) }}h over</span>
                                @else
                                    <span class="text-emerald-700">{{ $ticket->getSlaRemainingHours() }}h left</span>
                                @endif
                            @else
                                {{ $ticket->getActiveHoursElapsed() }}h elapsed
                            @endif
                        </span>
                    </div>

                    <button type="button" @click="inspectorTab = 'overview'" class="text-[11px] font-semibold text-slate-500 hover:text-blue-600 shrink-0 cursor-pointer">
                        Target: {{ $ticket->category?->target_resolve_hours ? "{$ticket->category->target_resolve_hours}h" : 'N/A' }}
                    </button>
                </div>

                <!-- 3. Quick Linked Equipment Strip (Always glanceable; 1-click jumps to Assets tab) -->
                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 text-xs">
                    @if($ticket->assets->isNotEmpty())
                        <div class="flex items-center flex-wrap gap-1.5 min-w-0">
                            @foreach($ticket->assets as $quickAsset)
                                <button type="button" @click="inspectorTab = 'equipment'"
                                        class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/80 text-[11px] font-bold transition cursor-pointer max-w-full">
                                    <span>{{ $quickAsset->category?->icon ?? '🖥️' }}</span>
                                    <span class="font-mono truncate">{{ $quickAsset->asset_tag }}</span>
                                </button>
                            @endforeach
                        </div>
                        <button type="button" @click="inspectorTab = 'equipment'" class="text-[11px] font-bold text-blue-600 hover:underline shrink-0 cursor-pointer">
                            Manage &rarr;
                        </button>
                    @else
                        <span class="text-[11px] text-slate-400">🖥️ No hardware linked</span>
                        <button type="button" @click="jumpToAttachAsset()" class="text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline shrink-0 cursor-pointer">
                            + Link Machine
                        </button>
                    @endif
                </div>
            </div>

            <!-- Modular Tabbed Inspector Card (Prevents endless downward column growth) -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">

                <!-- Inspector Tab Bar -->
                <div class="grid grid-cols-3 bg-slate-100/80 p-1.5 gap-1 border-b border-slate-200/80">
                    <button type="button" @click="inspectorTab = 'overview'"
                            :class="inspectorTab === 'overview' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="py-2 px-2 rounded-xl text-[11px] transition flex items-center justify-center space-x-1 cursor-pointer">
                        <span>📋</span>
                        <span>Info &amp; SLA</span>
                    </button>

                    <button type="button" @click="inspectorTab = 'equipment'"
                            :class="inspectorTab === 'equipment' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="py-2 px-2 rounded-xl text-[11px] transition flex items-center justify-center space-x-1 cursor-pointer relative">
                        <span>🖥️</span>
                        <span>Assets</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $ticket->assets->isNotEmpty() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                            {{ $ticket->assets->count() }}
                        </span>
                        @if($hasRepeatAssetIssue)
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse" title="Repeat issue detected in last 90 days"></span>
                        @endif
                    </button>

                    <button type="button" @click="inspectorTab = 'history'"
                            :class="inspectorTab === 'history' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="py-2 px-2 rounded-xl text-[11px] transition flex items-center justify-center space-x-1 cursor-pointer">
                        <span>🕘</span>
                        <span>History</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600">
                            {{ $historyCount }}
                        </span>
                    </button>
                </div>

                <!-- Tab 1: SLA Commitments & Requester Info -->
                <div x-show="inspectorTab === 'overview'" class="p-4 space-y-4">
                    <!-- SLA Breakdown (Compact 2x2 Grid) -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">SLA Commitments</span>
                            @if($ticket->isSlaPaused())
                                <span class="bg-amber-100 text-amber-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full">⏸️ SLA Paused</span>
                            @elseif($ticket->isLunchBreak() && !in_array($ticket->status, ['resolved_confirmed', 'resolved_auto_closed', 'closed']))
                                <span class="bg-amber-100 text-amber-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full">🍽️ Lunch Break</span>
                            @elseif($ticket->isSlaOverdue())
                                <span class="bg-rose-100 text-rose-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full">⚠️ Overdue</span>
                            @else
                                <span class="bg-emerald-100 text-emerald-800 font-extrabold text-[9px] uppercase px-2 py-0.5 rounded-full">✔ On Track</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                <div class="text-[10px] text-slate-400 font-medium">Target Resolution</div>
                                <div class="font-bold text-slate-800 mt-0.5">{{ $ticket->category?->target_resolve_hours ? "{$ticket->category->target_resolve_hours} hours" : 'N/A' }}</div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                <div class="text-[10px] text-slate-400 font-medium">Active IT Time</div>
                                <div class="font-bold text-blue-700 mt-0.5">{{ $ticket->getActiveHoursElapsed() }} hours</div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                <div class="text-[10px] text-slate-400 font-medium">Wall-Clock Age</div>
                                <div class="font-semibold text-slate-700 mt-0.5">{{ $ticket->created_at->diffForHumans(null, true) }}</div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                <div class="text-[10px] text-slate-400 font-medium">Target Deadline</div>
                                <div class="font-bold text-slate-800 mt-0.5 text-[11px]">{{ $ticket->getSlaDeadline() ? $ticket->getSlaDeadline()->format('D, d-M h:i A') : 'N/A' }}</div>
                            </div>
                        </div>

                        @if($ticket->getPausedMinutes() > 0)
                            <div class="flex justify-between items-center text-amber-800 bg-amber-50 px-3 py-2 rounded-xl text-xs border border-amber-200/60">
                                <span class="font-medium">⏸️ On-Hold Time:</span>
                                <span class="font-bold">{{ $ticket->getPausedHours() }}h ({{ $ticket->getPausedMinutes() }}m)</span>
                            </div>
                        @endif

                        <div class="pt-1 flex justify-between items-center text-xs">
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

                    <!-- Requester Profile -->
                    <div class="pt-3 border-t border-slate-100 space-y-2.5 text-xs">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Requester Info</span>

                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs border border-slate-200 shrink-0">
                                {{ substr($ticket->requester->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-800 truncate">{{ $ticket->requester->name }}</div>
                                <div class="text-blue-600 font-mono font-medium text-[10px]">{{ $ticket->requester->employee?->employee_code ?? 'EMP' }}</div>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-slate-600 bg-slate-50/70 rounded-2xl p-3 border border-slate-100">
                            <div class="flex items-center space-x-2">
                                <span>🏢</span>
                                <span class="truncate"><strong>Branch:</strong> {{ $ticket->branch->name }} ({{ $ticket->branch->code }})</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span>📂</span>
                                <span class="truncate"><strong>Dept:</strong> {{ $ticket->requester->employee?->department?->name ?? 'General' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center space-x-2 truncate min-w-0">
                                    <span>📧</span>
                                    <a href="mailto:{{ $ticket->requester->email }}" class="text-blue-600 hover:underline truncate">{{ $ticket->requester->email ?? 'N/A' }}</a>
                                </div>
                                @if($ticket->requester->email)
                                    <button type="button" @click="copyEmail('{{ $ticket->requester->email }}')" class="text-slate-400 hover:text-slate-700 text-[10px] font-semibold shrink-0 cursor-pointer">
                                        <span x-show="!copiedEmail">Copy</span>
                                        <span x-show="copiedEmail" class="text-emerald-600">Copied!</span>
                                    </button>
                                @endif
                            </div>
                            <div class="flex items-center space-x-2">
                                <span>📱</span>
                                <span class="truncate"><strong>Telegram:</strong>
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

                <!-- Tab 2: Linked Equipment & Asset -->
                <div x-show="inspectorTab === 'equipment'" class="p-4 space-y-3" x-cloak>
                    <div class="flex items-center justify-between">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Linked Equipment / Asset</div>
                        @if($ticket->assets->isNotEmpty())
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {{ $ticket->assets->count() }} Linked
                            </span>
                        @endif
                    </div>

                    @if($ticket->assets->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($ticket->assets as $linkedAsset)
                                @php
                                    $priorTickets = $linkedAsset->tickets->where('id', '!=', $ticket->id);
                                    $priorCount = $priorTickets->count();
                                    $recent90Count = $priorTickets->where('created_at', '>=', now()->subDays(90))->count();
                                    $parts = $ticket->assetParts->where('asset_id', $linkedAsset->id);
                                @endphp
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center space-x-2.5 min-w-0">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 border border-blue-200/60 flex items-center justify-center text-sm font-black shrink-0">
                                                {{ $linkedAsset->category?->icon ?? '🖥️' }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route('portal.assets.show', $linkedAsset->id) }}" class="font-bold text-xs text-blue-600 hover:text-blue-800 hover:underline block truncate">
                                                    {{ $linkedAsset->asset_tag }}
                                                </a>
                                                <div class="text-[11px] text-slate-800 font-semibold leading-snug">{{ $linkedAsset->name }}</div>
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('portal.tickets.assets.detach', [$ticket->id, $linkedAsset->id]) }}" onsubmit="return confirm('Detach equipment {{ $linkedAsset->asset_tag }} from this ticket?');" class="shrink-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-400 hover:text-rose-600 text-xs font-bold p-1 cursor-pointer" title="Detach Asset">✕</button>
                                        </form>
                                    </div>

                                    <div class="text-[10px] text-slate-500 flex flex-wrap gap-x-2 gap-y-0.5">
                                        <span>📍 {{ $linkedAsset->branch?->name ?? 'HQ' }}</span>
                                        @if($linkedAsset->ownership_type === 'personal' && $linkedAsset->assignedUser)
                                            <span>&bull; 👤 {{ $linkedAsset->assignedUser->name }}</span>
                                        @elseif($linkedAsset->location_details)
                                            <span>&bull; {{ $linkedAsset->location_details }}</span>
                                        @endif
                                    </div>

                                    <!-- Prior Service History / Repeat Issue Indicator -->
                                    <div class="pt-0.5">
                                        @if($recent90Count >= 1)
                                            <a href="{{ route('portal.assets.show', $linkedAsset->id) }}" class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition">
                                                <span>⚠️ {{ $priorCount }} prior {{ \Illuminate\Support\Str::plural('ticket', $priorCount) }} ({{ $recent90Count }} in 90d)</span>
                                                <span>&rarr;</span>
                                            </a>
                                        @elseif($priorCount > 0)
                                            <a href="{{ route('portal.assets.show', $linkedAsset->id) }}" class="inline-flex items-center space-x-1 text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition">
                                                <span>🕘 {{ $priorCount }} prior {{ \Illuminate\Support\Str::plural('ticket', $priorCount) }}</span>
                                                <span>&rarr;</span>
                                            </a>
                                        @else
                                            <a href="{{ route('portal.assets.show', $linkedAsset->id) }}" class="inline-flex items-center space-x-1 text-[10px] font-medium px-2 py-0.5 rounded-lg bg-white text-slate-500 border border-slate-200/80 hover:text-blue-600 transition">
                                                <span>✨ First logged ticket &bull; View Asset</span>
                                            </a>
                                        @endif
                                    </div>

                                    <!-- Parts replaced on this asset for this ticket -->
                                    @if($parts->isNotEmpty())
                                        <div class="pt-2 border-t border-slate-200/60 space-y-1">
                                            <div class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Parts Service Log:</div>
                                            @foreach($parts as $part)
                                                <div class="text-[11px] text-slate-700 bg-amber-50/80 border border-amber-200/60 rounded-lg p-1.5 flex items-start justify-between gap-2">
                                                    <div class="leading-snug">
                                                        <span>⚙️ {{ $part->description }}</span>
                                                        @if($part->component_id)
                                                            <span class="inline-block ml-1 text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">From Stock</span>
                                                        @endif
                                                    </div>
                                                    <span class="text-[9px] text-amber-700 font-bold uppercase shrink-0">{{ $part->action }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <!-- Quick Action: Record Part Replacement / Service -->
                            <div x-data="{ recordPartOpen: false, selectedSpareId: '', partAction: 'replaced' }" class="pt-1">
                                <button type="button" @click="recordPartOpen = !recordPartOpen"
                                        :class="recordPartOpen ? 'bg-slate-100 text-slate-700 border-slate-300' : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200'"
                                        class="w-full py-1.5 px-2.5 border rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1.5 cursor-pointer">
                                    <span x-text="recordPartOpen ? '✕' : '⚙️'"></span>
                                    <span x-text="recordPartOpen ? 'Close Part Entry' : 'Record Part Replaced (RAM/SSD)'"></span>
                                </button>

                                <div x-show="recordPartOpen" class="mt-2.5 p-3 bg-amber-50/50 border border-amber-200 rounded-2xl space-y-2.5" x-cloak>
                                    <form method="POST" action="{{ route('portal.tickets.assets.parts', $ticket->id) }}" class="space-y-2.5">
                                        @csrf
                                        <div>
                                            <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Target Asset</label>
                                            <select name="asset_id" required class="w-full text-xs p-1.5 rounded-lg border border-amber-300 bg-white">
                                                @foreach($ticket->assets as $la)
                                                    <option value="{{ $la->id }}">{{ $la->asset_tag }} &bull; {{ $la->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Action Taken</label>
                                            <select name="action" x-model="partAction" class="w-full text-xs p-1.5 rounded-lg border border-amber-300 bg-white">
                                                <option value="replaced">Replaced Component (Faulty &rarr; New)</option>
                                                <option value="installed">Installed New Upgrade Component</option>
                                                <option value="removed">Removed Component</option>
                                                <option value="serviced">Cleaned / Serviced Existing Component</option>
                                            </select>
                                        </div>
                                        @if(isset($spareComponents) && $spareComponents->isNotEmpty())
                                            <div x-show="['replaced', 'installed'].includes(partAction)">
                                                <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Use Part from IT Spare Stock (Optional)</label>
                                                <select name="component_id" x-model="selectedSpareId" class="w-full text-xs p-1.5 rounded-lg border border-amber-300 bg-white">
                                                    <option value="">-- Custom / No Stock Deduction --</option>
                                                    @foreach($spareComponents as $sp)
                                                        <option value="{{ $sp->id }}">
                                                            [{{ strtoupper($sp->component_type) }}] {{ $sp->brand_model }}{{ $sp->capacity_or_specs ? ' (' . $sp->capacity_or_specs . ')' : '' }} &bull; {{ $sp->branch?->code ?? 'HQ' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <p x-show="selectedSpareId" class="text-[10px] text-emerald-700 font-semibold mt-1 leading-snug">
                                                    ✓ Will install this stock unit into the machine and deduct 1 from IT Spare Stock.
                                                </p>
                                            </div>
                                        @endif
                                        <div>
                                            <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">
                                                <span x-text="selectedSpareId ? 'Notes (Optional - Auto-filled from stock)' : 'Description / Specs Replaced'"></span>
                                            </label>
                                            <input type="text" name="description" :required="!selectedSpareId"
                                                   :placeholder="selectedSpareId ? 'Optional e.g. Installed in RAM Slot 1' : 'e.g. Replaced 8GB DDR4 RAM or cleaned tray roller'"
                                                   class="w-full text-xs p-1.5 rounded-lg border border-amber-300 bg-white">
                                        </div>
                                        <button type="submit" class="w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition cursor-pointer">
                                            Log Part to Asset History
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-xs text-slate-400 py-1">
                            No equipment linked yet. Only IT can link hardware.
                        </div>
                    @endif

                    <!-- Attach Equipment / Machine (Smart Searchable Asset Combobox) -->
                    <div x-data="{
                        attachOpen: false,
                        assetSearch: '',
                        assetDropdownOpen: false,
                        assetScope: '{{ $availableAssetsJson->contains(fn ($a) => $a['is_requester_asset'] || $a['is_same_branch']) ? 'same' : 'all' }}',
                        selectedAssetId: '',
                        assets: {{ $availableAssetsJson->toJson() }},
                        getSelectedAsset() {
                            if (!this.selectedAssetId) return null;
                            return this.assets.find(a => a.id == this.selectedAssetId) || null;
                        },
                        getSameScopeCount() {
                            return this.assets.filter(a => a.is_requester_asset || a.is_same_branch).length;
                        },
                        getFilteredAssets() {
                            let q = (this.assetSearch || '').toLowerCase().trim();
                            let list = this.assets;
                            if (this.assetScope === 'same') {
                                list = list.filter(a => a.is_requester_asset || a.is_same_branch);
                            }
                            if (q) {
                                list = list.filter(a =>
                                    (a.asset_tag && a.asset_tag.toLowerCase().includes(q)) ||
                                    (a.name && a.name.toLowerCase().includes(q)) ||
                                    (a.category_name && a.category_name.toLowerCase().includes(q)) ||
                                    (a.owner_name && a.owner_name.toLowerCase().includes(q)) ||
                                    (a.location_details && a.location_details.toLowerCase().includes(q)) ||
                                    (a.branch_name && a.branch_name.toLowerCase().includes(q))
                                );
                            }
                            return [...list].sort((a, b) => {
                                let scoreA = (a.is_requester_asset ? 10 : 0) + (a.is_same_branch ? 3 : 0) + (a.prior_tickets_count > 0 ? 1 : 0);
                                let scoreB = (b.is_requester_asset ? 10 : 0) + (b.is_same_branch ? 3 : 0) + (b.prior_tickets_count > 0 ? 1 : 0);
                                if (scoreB !== scoreA) return scoreB - scoreA;
                                return a.name.localeCompare(b.name);
                            });
                        }
                    }" @open-attach-asset.window="attachOpen = true; assetDropdownOpen = true" class="pt-2 border-t border-slate-100">
                        <button type="button"
                                @click="attachOpen = !attachOpen; if (attachOpen && !selectedAssetId) { assetDropdownOpen = true; } else if (!attachOpen) { assetDropdownOpen = false; }"
                                :class="attachOpen ? 'bg-slate-100 text-slate-700 border-slate-300' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200'"
                                class="w-full py-1.5 px-2.5 border rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1.5 cursor-pointer">
                            <span x-text="attachOpen ? '✕' : '➕'"></span>
                            <span x-text="attachOpen ? 'Cancel' : 'Attach Equipment / Machine'"></span>
                        </button>

                        <div x-show="attachOpen" class="mt-2.5 p-3 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5" x-cloak>
                            <form method="POST" action="{{ route('portal.tickets.assets.attach', $ticket->id) }}" class="space-y-2.5">
                                @csrf
                                <input type="hidden" name="asset_id" :value="selectedAssetId">

                                <div class="flex items-center justify-between gap-1 flex-wrap">
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase">Select Machine / Asset</label>
                                    <div class="inline-flex bg-slate-200/70 p-0.5 rounded-lg text-[10px]">
                                        <button type="button" @click="assetScope = 'same'; assetDropdownOpen = true"
                                                :class="assetScope === 'same' ? 'bg-white text-blue-700 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-800'"
                                                class="px-2 py-0.5 rounded-md transition cursor-pointer">
                                            Branch (<span x-text="getSameScopeCount()"></span>)
                                        </button>
                                        <button type="button" @click="assetScope = 'all'; assetDropdownOpen = true"
                                                :class="assetScope === 'all' ? 'bg-white text-blue-700 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-800'"
                                                class="px-2 py-0.5 rounded-md transition cursor-pointer">
                                            All (<span x-text="assets.length"></span>)
                                        </button>
                                    </div>
                                </div>

                                <!-- Selected Asset Summary Card -->
                                <template x-if="getSelectedAsset()">
                                    <div class="p-2.5 rounded-xl border border-blue-300 bg-blue-50/70 flex items-start justify-between gap-2">
                                        <div class="flex items-start space-x-2 min-w-0">
                                            <span class="text-sm shrink-0 mt-0.5" x-text="getSelectedAsset().category_icon"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center flex-wrap gap-1">
                                                    <span class="text-[11px] font-mono font-bold text-blue-700" x-text="getSelectedAsset().asset_tag"></span>
                                                    <template x-if="getSelectedAsset().is_requester_asset">
                                                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">⭐ Requester's</span>
                                                    </template>
                                                </div>
                                                <div class="text-xs font-bold text-slate-900 leading-snug break-words" x-text="getSelectedAsset().name"></div>
                                                <div class="text-[10px] text-slate-500 mt-0.5 break-words" x-text="getSelectedAsset().owner_name ? ('👤 ' + getSelectedAsset().owner_name) : (getSelectedAsset().location_details || getSelectedAsset().branch_name)"></div>
                                            </div>
                                        </div>
                                        <button type="button" @click="selectedAssetId = ''; assetDropdownOpen = true"
                                                class="text-[10px] text-rose-600 hover:text-rose-800 font-bold px-1.5 py-0.5 rounded hover:bg-rose-50 shrink-0 cursor-pointer">
                                            ✕
                                        </button>
                                    </div>
                                </template>

                                <!-- Searchable Trigger & Inline Combobox -->
                                <template x-if="!getSelectedAsset()">
                                    <div @click="assetDropdownOpen = !assetDropdownOpen"
                                         class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white hover:border-blue-400 cursor-pointer flex items-center justify-between shadow-2xs">
                                        <span class="text-slate-500 truncate">🔍 Search tag, owner, room, printer...</span>
                                        <span class="text-slate-400 text-[10px] ml-1 shrink-0" x-text="assetDropdownOpen ? '▲' : '▼'"></span>
                                    </div>
                                </template>

                                <!-- Contained Dropdown List (Stays 100% Inside Card Width) -->
                                <div x-show="assetDropdownOpen" class="w-full bg-white rounded-xl border border-slate-200 shadow-md overflow-hidden" x-cloak>
                                    <div class="p-2 border-b border-slate-100 bg-slate-50/70">
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1.5 text-slate-400 text-xs">🔍</span>
                                            <input type="text" x-model="assetSearch"
                                                   placeholder="Filter by tag, staff, room, category..."
                                                   class="w-full text-xs pl-7 pr-6 py-1.5 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <button type="button" x-show="assetSearch" @click="assetSearch = ''" class="absolute right-2 top-1.5 text-xs text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                                        </div>
                                    </div>

                                    <div class="max-h-56 overflow-y-auto divide-y divide-slate-100 p-1">
                                        <template x-for="item in getFilteredAssets()" :key="item.id">
                                            <div @click="selectedAssetId = item.id; assetDropdownOpen = false; assetSearch = ''"
                                                 class="p-2 hover:bg-blue-50/80 rounded-lg cursor-pointer transition space-y-1">
                                                <div class="flex items-center justify-between gap-1 flex-wrap">
                                                    <div class="flex items-center space-x-1.5 min-w-0">
                                                        <span class="text-xs shrink-0" x-text="item.category_icon"></span>
                                                        <span class="text-[11px] font-mono font-bold text-blue-700" x-text="item.asset_tag"></span>
                                                    </div>
                                                    <div class="flex items-center gap-1 flex-wrap">
                                                        <template x-if="item.is_requester_asset">
                                                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">⭐ Requester</span>
                                                        </template>
                                                        <template x-if="!item.is_requester_asset && item.ownership_type === 'shared'">
                                                            <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">Shared</span>
                                                        </template>
                                                        <template x-if="item.recent_90d_count >= 1">
                                                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 border border-amber-200" x-text="'⚠️ ' + item.prior_tickets_count + ' prev'"></span>
                                                        </template>
                                                        <template x-if="item.recent_90d_count === 0 && item.prior_tickets_count > 0">
                                                            <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200" x-text="'🕘 ' + item.prior_tickets_count + ' prev'"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="text-xs font-semibold text-slate-800 leading-snug break-words" x-text="item.name"></div>
                                                <div class="text-[10px] text-slate-500 leading-snug break-words">
                                                    <span x-text="item.owner_name ? ('👤 ' + item.owner_name) : ('📍 ' + (item.location_details || item.branch_name))"></span>
                                                    <span class="text-slate-300 mx-1">&bull;</span>
                                                    <span x-text="item.category_name"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <div x-show="getFilteredAssets().length === 0" class="py-4 px-2 text-center text-xs text-slate-400 space-y-1.5">
                                            <div>No matching equipment found.</div>
                                            <template x-if="assetScope === 'same'">
                                                <button type="button" @click="assetScope = 'all'; assetSearch = ''" class="text-blue-600 font-bold hover:underline text-[11px] cursor-pointer">
                                                    🌐 Show All Branches (<span x-text="assets.length"></span>)
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" :disabled="!selectedAssetId"
                                        class="w-full py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl transition cursor-pointer shadow-xs">
                                    Confirm &amp; Attach
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Assignment History & Audit Trail -->
                <div x-show="inspectorTab === 'history'" class="p-4 space-y-4" x-cloak>
                    <!-- Assignment History -->
                    <div class="space-y-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Assignment History</span>
                        <div class="space-y-2 max-h-52 overflow-y-auto pr-1 text-xs">
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

                    <!-- System Audit Trail -->
                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Activity Audit Log</span>
                        <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1 text-xs">
                            @forelse($ticket->auditLogs->sortByDesc('id') as $log)
                                <div class="p-2 rounded-xl bg-slate-50/70 border border-slate-100 text-[11px] flex items-start justify-between gap-2">
                                    <div class="min-w-0 space-y-0.5">
                                        <div class="flex items-center flex-wrap gap-1.5">
                                            <span class="font-mono font-bold text-[9px] px-1.5 py-0.5 rounded bg-slate-200/80 text-slate-700">
                                                {{ str_replace('_', ' ', $log->action) }}
                                            </span>
                                            <span class="text-slate-600 font-medium truncate">{{ $log->actor?->name ?? 'System' }}</span>
                                        </div>
                                        @if($log->new_value)
                                            <div class="text-[10px] text-slate-500 truncate">{{ $log->new_value }}</div>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-slate-400 shrink-0 font-mono">
                                        {{ $log->created_at?->format('M d, H:i') ?? '' }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-slate-400 text-xs py-2 text-center">No audit events recorded yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Sticky Full-Height Conversation, Internal Notes & Reply Workspace -->
        <div class="lg:col-span-7 xl:col-span-8 lg:sticky lg:top-20 order-1 lg:order-2">

            <!-- Unified Modern Chat Panel (Viewport-locked & sticky alongside left inspector) -->
            <div id="chatPanel" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs flex flex-col overflow-hidden h-[540px] sm:h-[600px] lg:h-[calc(100vh-6.25rem)] lg:min-h-[480px] lg:max-h-[820px]">
                
                <!-- Chat Panel Header -->
                <div class="px-4 sm:px-5 py-3 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="requesterActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'"></span>
                        <div class="text-xs font-bold text-slate-800 flex items-center gap-2">
                            <span>Ticket Discussion &amp; Notes</span>
                            <span class="bg-slate-200/80 text-slate-600 font-semibold px-2 py-0.5 rounded-full text-[10px]" x-text="chatMessages.length + ' messages'"></span>
                        </div>

                        <!-- Stream Filter Pills -->
                        <div class="inline-flex bg-slate-200/70 p-0.5 rounded-lg text-[10px]">
                            <button type="button" @click="chatFilter = 'all'; scrollToBottom()"
                                    :class="chatFilter === 'all' ? 'bg-white text-slate-800 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900'"
                                    class="px-2 py-0.5 rounded-md transition cursor-pointer">
                                All
                            </button>
                            <button type="button" @click="chatFilter = 'conversation'; scrollToBottom()"
                                    :class="chatFilter === 'conversation' ? 'bg-white text-blue-700 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900'"
                                    class="px-2 py-0.5 rounded-md transition cursor-pointer">
                                💬 Chat
                            </button>
                            <button type="button" @click="chatFilter = 'notes'; scrollToBottom()"
                                    :class="chatFilter === 'notes' ? 'bg-white text-amber-800 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900'"
                                    class="px-2 py-0.5 rounded-md transition cursor-pointer inline-flex items-center gap-1">
                                <span>🔒 Notes</span>
                                <span x-show="getInternalNotesCount() > 0" class="px-1 rounded bg-amber-100 text-amber-800 text-[9px] font-bold" x-text="getInternalNotesCount()" x-cloak></span>
                            </button>
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

                <!-- Contained Scrollable Chat Viewport (Fills remaining panel height) -->
                <div class="relative flex-1 min-h-0 flex flex-col">
                    <div id="portalChatStream"
                         @scroll="handleChatScroll($event.target)"
                         class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3"
                         style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">
                        <template x-for="msg in getFilteredMessages()" :key="msg.id">
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

                    <div x-show="getFilteredMessages().length === 0" class="text-center py-12 text-xs text-slate-400" x-cloak>
                        <span x-show="chatFilter === 'notes'">🔒 No internal staff notes yet on this ticket.</span>
                        <span x-show="chatFilter === 'conversation'">💬 No staff/employee chat messages yet (system events hidden).</span>
                        <span x-show="chatFilter === 'all'">💬 No conversation messages yet. Send a reply or post a note below.</span>
                    </div>
                </div>

                <!-- Floating Jump-to-Latest Pill -->
                <button type="button"
                        x-show="showJumpToBottom"
                        x-transition
                        @click="scrollToBottom()"
                        class="absolute bottom-3 right-4 z-10 bg-slate-900/85 hover:bg-slate-900 text-white text-[11px] font-bold px-3 py-1.5 rounded-full shadow-md backdrop-blur-xs transition cursor-pointer" x-cloak>
                    &darr; Latest
                </button>
                </div>

                <!-- Modern Bottom Composer -->
                <div class="p-3.5 sm:p-4 bg-white border-t border-slate-200 space-y-2.5 shrink-0">
                    
                    <!-- Mode Toggle: Reply vs Internal Note -->
                    <div class="flex items-center space-x-2">
                        <button type="button" @click="messageTab = 'requester_visible'"
                                :class="messageTab === 'requester_visible' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer">
                            <span>💬</span>
                            <span>Reply to Employee</span>
                        </button>

                        <button type="button" @click="messageTab = 'internal_staff_only'"
                                :class="messageTab === 'internal_staff_only' ? 'bg-amber-400 text-amber-950 font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer">
                            <span>🔒</span>
                            <span>Internal Note (IT Staff Only)</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitChatMessage()" class="space-y-2.5">
                        <div>
                            <textarea id="chatMessageInput"
                                      x-model="messageBody"
                                      rows="2"
                                      @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px'"
                                      @keydown.enter.exact.prevent="submitChatMessage()"
                                      @paste="handlePaste($event)"
                                      :placeholder="messageTab === 'internal_staff_only' ? 'Write confidential internal note (Press Enter to post, Shift+Enter for newline, Ctrl+V to paste screenshot)...' : 'Type reply that the employee will see on their Telegram Mini App (Press Enter to send, Shift+Enter for newline, Ctrl+V to paste screenshot)...'"
                                      :class="messageTab === 'internal_staff_only' ? 'bg-amber-50/40 border-amber-300 focus:ring-amber-500' : 'bg-slate-50/70 border-slate-300 focus:bg-white focus:ring-blue-600'"
                                      class="w-full text-xs p-2.5 sm:p-3 rounded-xl border focus:outline-none focus:ring-2 resize-none max-h-32 overflow-y-auto"></textarea>
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
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Approval Notes / Comments (Mandatory)</label>
                        <textarea name="comments" required minlength="3" rows="3" placeholder="e.g. Approved, proceed with standard specs..." class="w-full text-xs p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="approveModal = false" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700">Confirm Approval</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- ESCALATE STEP MODAL -->
    <div x-show="escalateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="escalateModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center space-x-2.5">
                <span class="text-xl">⬆️</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Escalate Service Request</h3>
                    <p class="text-[11px] text-slate-500">Endorse this request and escalate to Level 2 (Supervisor / Director) for higher authority sign-off.</p>
                </div>
            </div>

            @if($currentApproval)
                <form method="POST" action="{{ route('portal.tickets.approvals.escalate', [$ticket->id, $currentApproval->id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Endorsement Note / Justification (Mandatory)</label>
                        <textarea name="comments" required minlength="3" rows="3" placeholder="e.g. Endorsed by Team Leader: Requires supervisor approval for special software license..." class="w-full text-xs p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Superior (Optional)</label>
                        @php
                            $eligibleEscalateUsers = $currentApproval ? app(\App\Services\TicketApprovalService::class)->getEligibleEscalateUsers($currentApproval, Auth::user()) : collect();
                        @endphp
                        <select name="target_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <option value="">-- Auto: Next Level Supervisor / Dept Manager --</option>
                            @foreach($eligibleEscalateUsers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->email }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">If unselected, the system automatically routes to the department's Level 2 Supervisor or Parent Head.</p>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="escalateModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Confirm & Escalate</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- FORWARD / DELEGATE STEP MODAL -->
    <div x-show="forwardModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="forwardModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center space-x-2.5">
                <span class="text-xl">🔄</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Forward / Delegate Review</h3>
                    <p class="text-[11px] text-slate-500">Hand over this approval step to a peer approver or colleague.</p>
                </div>
            </div>

            @if($currentApproval)
                <form method="POST" action="{{ route('portal.tickets.approvals.forward', [$ticket->id, $currentApproval->id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Delegate To</label>
                        @php
                            $eligibleForwardUsers = $currentApproval ? app(\App\Services\TicketApprovalService::class)->getEligibleForwardUsers($currentApproval, Auth::user()) : collect();
                        @endphp
                        @if($eligibleForwardUsers->isNotEmpty())
                            <select name="target_user_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-sky-500 bg-white">
                                <option value="">-- Select Eligible Approver --</option>
                                @foreach($eligibleForwardUsers as $colleague)
                                    <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ $colleague->email }})</option>
                                @endforeach
                            </select>
                        @else
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 leading-snug">
                                ⚠️ <strong>No peer approvers available</strong> at this level in this branch. If you cannot review this request, please use <strong>Escalate</strong> to pass to your department manager.
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Handover Note / Reason for Delegation (Mandatory)</label>
                        <textarea name="comments" required minlength="3" rows="2" placeholder="e.g. Delegating this review to colleague for second opinion or coverage..." class="w-full text-xs p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-sky-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="forwardModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                        <button type="submit" @disabled($eligibleForwardUsers->isEmpty()) class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition disabled:opacity-50 disabled:pointer-events-none">Confirm Delegation</button>
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

    @if(Auth::user()->hasAnyRole(['helpdesk', 'system_admin', 'hod']))
    <!-- RECLASSIFY / CHANGE TICKET TYPE & CATEGORY MODAL -->
    <div x-show="reclassifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="reclassifyModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">
                        🔄
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Reclassify Ticket</h3>
                        <p class="text-[11px] text-slate-500">Switch ticket type between Incident & Service Request, or change Category</p>
                    </div>
                </div>
                <button type="button" @click="reclassifyModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ route('portal.tickets.reclassify', $ticket->id) }}" class="space-y-4">
                @csrf
                <!-- Ticket Type Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">Ticket Classification Type</label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <label :class="reclassifyType === 'incident' ? 'border-rose-500 bg-rose-50/50 ring-2 ring-rose-500/20' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex items-center space-x-2.5 p-3 rounded-2xl border cursor-pointer transition">
                            <input type="radio" name="ticket_type" value="incident" x-model="reclassifyType"
                                   @change="onTypeChange()"
                                   class="text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900 flex items-center space-x-1">
                                    <span>🔴</span> <span>Incident</span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Broken or malfunctioning</div>
                            </div>
                        </label>
                        <label :class="reclassifyType === 'service_request' ? 'border-indigo-500 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex items-center space-x-2.5 p-3 rounded-2xl border cursor-pointer transition">
                            <input type="radio" name="ticket_type" value="service_request" x-model="reclassifyType"
                                   @change="onTypeChange()"
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900 flex items-center space-x-1">
                                    <span>🔵</span> <span>Service Request</span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Hardware / access (Needs Approval)</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Dynamic Notice Banner when Type Changes -->
                <div x-show="reclassifyType === 'service_request' && '{{ $ticket->ticket_type }}' === 'incident'" class="p-3 bg-amber-50 border border-amber-200 rounded-2xl text-[11px] text-amber-900 flex items-start space-x-2">
                    <span class="text-base leading-none">⏳</span>
                    <div>
                        <strong>Converting to Service Request:</strong>
                        <p class="mt-0.5 text-amber-800">This will initiate the Department HOD & IT Manager approval workflow. Ticket status will transition to <em>Pending Approval</em> until authorized.</p>
                    </div>
                </div>

                <div x-show="reclassifyType === 'incident' && '{{ $ticket->ticket_type }}' === 'service_request'" class="p-3 bg-blue-50 border border-blue-200 rounded-2xl text-[11px] text-blue-900 flex items-start space-x-2">
                    <span class="text-base leading-none">⚡</span>
                    <div>
                        <strong>Converting to Incident:</strong>
                        <p class="mt-0.5 text-blue-800">Pending approval steps will be cancelled. The ticket will immediately become ready for technical assignment & triage.</p>
                    </div>
                </div>

                <!-- Main Category & Subcategory Selection (Individually, like TMA) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- 1. Main Category -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Main Category</label>
                        <select x-model="reclassifyParentId"
                                @change="onParentChange()"
                                class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-800 font-medium cursor-pointer">
                            <template x-for="parent in getParentCategories()" :key="parent.id">
                                <option :value="parent.id" x-text="parent.name" :selected="parent.id == reclassifyParentId"></option>
                            </template>
                        </select>
                    </div>

                    <!-- 2. Subcategory -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Subcategory</label>
                        <select name="category_id"
                                x-model="reclassifySubId"
                                required
                                class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-800 font-medium cursor-pointer">
                            <template x-for="sub in getSubcategories()" :key="sub.id">
                                <option :value="sub.id" x-text="(sub.it_division === 'prd' ? '[IT-PRD] ' : '[IT-OPR] ') + sub.name" :selected="sub.id == reclassifySubId"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Priority Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">Ticket Priority</label>
                    <div class="grid grid-cols-4 gap-2">
                        <label :class="reclassifyPriority === 'low' ? 'border-slate-500 bg-slate-50 ring-2 ring-slate-500/20 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center">
                            <input type="radio" name="priority" value="low" x-model="reclassifyPriority" class="sr-only">
                            <span class="text-xs">⚪</span>
                            <span class="text-[11px] font-bold text-slate-700 mt-0.5">Low</span>
                        </label>
                        <label :class="reclassifyPriority === 'medium' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-500/20 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center">
                            <input type="radio" name="priority" value="medium" x-model="reclassifyPriority" class="sr-only">
                            <span class="text-xs">🔵</span>
                            <span class="text-[11px] font-bold text-blue-700 mt-0.5">Medium</span>
                        </label>
                        <label :class="reclassifyPriority === 'high' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-500/20 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center">
                            <input type="radio" name="priority" value="high" x-model="reclassifyPriority" class="sr-only">
                            <span class="text-xs">🟠</span>
                            <span class="text-[11px] font-bold text-amber-700 mt-0.5">High</span>
                        </label>
                        <label :class="reclassifyPriority === 'critical' ? 'border-rose-500 bg-rose-50 ring-2 ring-rose-500/20 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'"
                               class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center">
                            <input type="radio" name="priority" value="critical" x-model="reclassifyPriority" class="sr-only">
                            <span class="text-xs">🔴</span>
                            <span class="text-[11px] font-bold text-rose-700 mt-0.5">Critical</span>
                        </label>
                    </div>
                </div>

                <!-- Reason Field -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Reason for Reclassification <span class="text-slate-400 font-normal">(Optional note for audit)</span></label>
                    <textarea name="reason" rows="2" placeholder="e.g. Requester submitted request as incident; converting to service request for HOD signoff..." class="w-full text-xs px-3.5 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="reclassifyModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center space-x-1.5">
                        <span>Save & Apply</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ASSIGN / REASSIGN TECHNICIAN MODAL -->
    <div x-show="reassignDrawer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="reassignDrawer = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-bold">
                        👤
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ $ticket->technician ? 'Reassign Ticket' : 'Assign Technician' }}
                        </h3>
                        <p class="text-[11px] text-slate-500">
                            {{ $ticket->technician ? 'Transfer ticket responsibility to another technician' : 'Assign an available technician to triage and resolve this ticket' }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="reassignDrawer = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
                    &times;
                </button>
            </div>

            @if($ticket->technician)
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Currently Assigned To:</span>
                    <span class="font-bold text-slate-800">{{ $ticket->technician->name }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.tickets.assign', $ticket->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Select Technician <span class="text-rose-500">*</span></label>
                    <select name="technician_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                        <option value="">-- Choose a technician --</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" {{ $ticket->current_technician_id == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }} ({{ $tech->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                        {{ $ticket->technician ? 'Transfer Reason / Note' : 'Assignment Note (Optional)' }}
                    </label>
                    <input type="text" name="assignment_note" placeholder="{{ $ticket->technician ? 'e.g. Workload rebalancing, specialist needed' : 'e.g. Assigned for hardware triage' }}" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="reassignDrawer = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center space-x-1.5">
                        <span>{{ $ticket->technician ? 'Transfer Ticket' : 'Assign Ticket' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

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
