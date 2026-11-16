<div id="chatPanel"
     x-data="{
         showJumpToBottom: false,
         scrollToBottom() {
             this.$nextTick(() => {
                 const el = this.$refs.chatStream;
                 if (el) {
                     el.scrollTop = el.scrollHeight;
                     this.showJumpToBottom = false;
                 }
             });
         },
         handleScroll(el) {
             if (!el) return;
             this.showJumpToBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) > 120;
         },
         handlePaste(event) {
             if (!event.clipboardData || !event.clipboardData.items) return;
             for (let i = 0; i < event.clipboardData.items.length; i++) {
                 const item = event.clipboardData.items[i];
                 if (item.type.indexOf('image') !== -1) {
                     const blob = item.getAsFile();
                     const ext = item.type.split('/')[1] || 'png';
                     const file = new File([blob], 'screenshot_' + Date.now() + '.' + ext, { type: item.type });
                     @this.upload('attachments', file);
                 }
             }
         }
     }"
     x-init="scrollToBottom()"
     x-on:scroll-chat-to-bottom.window="scrollToBottom()"
     class="bg-white rounded-3xl border border-slate-200/80 shadow-xs flex flex-col overflow-hidden h-[540px] sm:h-[600px] lg:h-[calc(100vh-6.25rem)] lg:min-h-[480px] lg:max-h-[820px]">

    <!-- Chat Panel Header -->
    <div class="px-4 sm:px-5 py-3 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 shrink-0">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $isRequesterActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' }}"></span>
            <div class="text-xs font-bold text-slate-800 flex items-center gap-2">
                <span>Ticket Discussion &amp; Notes</span>
                <span class="bg-slate-200/80 text-slate-600 font-semibold px-2 py-0.5 rounded-full text-[10px]">
                    {{ $messages->count() }} messages
                </span>
            </div>

            <!-- Stream Filter Pills -->
            <div class="inline-flex bg-slate-200/70 p-0.5 rounded-lg text-[10px]">
                <button type="button" wire:click="setChatFilter('all')"
                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ $chatFilter === 'all' ? 'bg-white text-slate-800 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900' }}">
                    All
                </button>
                <button type="button" wire:click="setChatFilter('conversation')"
                        class="px-2 py-0.5 rounded-md transition cursor-pointer {{ $chatFilter === 'conversation' ? 'bg-white text-blue-700 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900' }}">
                    💬 Chat
                </button>
                <button type="button" wire:click="setChatFilter('notes')"
                        class="px-2 py-0.5 rounded-md transition cursor-pointer inline-flex items-center gap-1 {{ $chatFilter === 'notes' ? 'bg-white text-amber-800 font-bold shadow-2xs' : 'text-slate-600 font-medium hover:text-slate-900' }}">
                    <span>🔒 Notes</span>
                    @if($internalNotesCount > 0)
                        <span class="px-1 rounded bg-amber-100 text-amber-800 text-[9px] font-bold">{{ $internalNotesCount }}</span>
                    @endif
                </button>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <!-- Toast notification -->
            @if($toastMessage)
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => { show = false; $wire.clearToast(); }, 3500)"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="bg-emerald-600 text-white text-[11px] font-semibold px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-xs">
                    <span>✔</span>
                    <span>{{ $toastMessage }}</span>
                </div>
            @endif

            @if($isRequesterActive)
                <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    Employee Active in TMA
                </span>
            @else
                <span class="text-[11px] text-slate-500 bg-slate-100 border border-slate-200/70 px-2.5 py-0.5 rounded-full flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                    Employee Away (Telegram push enabled)
                </span>
            @endif
        </div>
    </div>

    <!-- Contained Scrollable Chat Viewport (Fills remaining panel height) -->
    <div class="relative flex-1 min-h-0 flex flex-col">
        <div id="portalChatStream"
             x-ref="chatStream"
             @scroll="handleScroll($event.target)"
             wire:poll.4s
             class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3"
             style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">

            @forelse($messages as $msg)
                @if($msg->visibility === 'system_event')
                    <!-- System Event: Compact Centered Pill -->
                    <div class="flex justify-center my-2 select-none" wire:key="msg-{{ $msg->id }}">
                        <span class="bg-slate-200/80 border border-slate-300/40 text-slate-600 text-[10px] font-medium px-3 py-1 rounded-full shadow-2xs">
                            ⚙️ {{ $msg->body }} • {{ $msg->created_at->diffForHumans() }}
                        </span>
                    </div>
                @elseif($msg->visibility === 'internal_staff_only')
                    <!-- Confidential Internal Note (Distinct Amber Card) -->
                    <div class="max-w-xl mx-auto my-2.5" wire:key="msg-{{ $msg->id }}">
                        <div class="bg-amber-50/95 border-2 border-amber-300/80 rounded-2xl p-4 shadow-2xs space-y-2">
                            <div class="flex items-center justify-between text-xs border-b border-amber-200/70 pb-1.5">
                                <div class="flex items-center space-x-1.5">
                                    <span class="bg-amber-200 text-amber-900 font-black text-[9px] uppercase px-1.5 py-0.5 rounded tracking-wider flex items-center gap-1">
                                        <span>🔒</span>
                                        <span>Staff Only Note</span>
                                    </span>
                                    <span class="font-bold text-amber-900 text-xs">{{ $msg->sender?->name ?? 'System' }}</span>
                                </div>
                                <span class="text-[10px] text-amber-700/80 font-mono">{{ $msg->created_at->format('M d, H:i') }}</span>
                            </div>
                            @if($msg->body)
                                <p class="text-xs text-amber-950 font-medium leading-relaxed whitespace-pre-wrap">{{ $msg->body }}</p>
                            @endif

                            <!-- Photo Attachments -->
                            @php
                                $images = $msg->attachments->filter(fn($a) => str_starts_with($a->mime_type, 'image/'));
                                $nonImages = $msg->attachments->filter(fn($a) => !str_starts_with($a->mime_type, 'image/'));
                            @endphp

                            @if($images->isNotEmpty())
                                <div class="flex flex-wrap gap-2 pt-1">
                                    @foreach($images as $att)
                                        <div class="relative group">
                                            <div class="relative rounded-2xl overflow-hidden cursor-pointer shadow-sm border border-amber-300/80 bg-amber-100/50 hover:shadow-md active:scale-[0.98] transition max-w-[280px] sm:max-w-[340px]"
                                                 @click="$dispatch('open-lightbox', { url: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}', name: '{{ addslashes($att->original_filename) }}', download: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}' })">
                                                <img src="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}" alt="{{ $att->original_filename }}" loading="lazy" class="w-full max-h-72 object-cover block">
                                                <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">HD</div>
                                                <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                    <span>{{ $att->formatted_size }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Document Attachments -->
                            @if($nonImages->isNotEmpty())
                                <div class="space-y-1.5 pt-1 max-w-sm">
                                    @foreach($nonImages as $att)
                                        <a href="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}" target="_blank"
                                           class="flex items-center space-x-2.5 p-2 rounded-xl bg-amber-100/80 hover:bg-amber-100 border border-amber-300 text-amber-950 transition shadow-2xs">
                                            <div class="{{ $this->getFileBadgeClass($att->original_filename, false) }} w-8 h-8 rounded-lg flex items-center justify-center font-bold text-[9px] shrink-0">
                                                <span>{{ $this->getFileExtension($att->original_filename) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-semibold truncate">{{ $att->original_filename }}</div>
                                                <div class="text-[10px] text-amber-700/80 font-mono">{{ $att->formatted_size }}</div>
                                            </div>
                                            <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="text-[9px] text-amber-700/70 italic pt-0.5">
                                Hidden from employee. Only visible to IT Help Desk and Technicians.
                            </div>
                        </div>
                    </div>
                @elseif($msg->sender && $msg->sender->isStaff())
                    <!-- IT Staff Message (Right-aligned Modern Messenger Pill) -->
                    <div class="flex flex-col items-end w-full my-2 space-y-1.5" wire:key="msg-{{ $msg->id }}">
                        <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 pr-1 select-none">
                            <span class="font-semibold text-blue-600">{{ $msg->sender?->name ?? 'System' }}</span>
                            <span class="text-[8px] bg-blue-100 text-blue-700 font-bold px-1.5 py-0.2 rounded uppercase">IT Staff</span>
                            <span>•</span>
                            <span class="font-mono">{{ $msg->created_at->format('M d, H:i') }}</span>
                        </div>

                        @php
                            $images = $msg->attachments->filter(fn($a) => str_starts_with($a->mime_type, 'image/'));
                            $nonImages = $msg->attachments->filter(fn($a) => !str_starts_with($a->mime_type, 'image/'));
                        @endphp

                        <!-- Photos -->
                        @if($images->isNotEmpty())
                            <div class="flex flex-col items-end space-y-1.5">
                                @foreach($images as $att)
                                    <div class="relative group">
                                        <div class="relative rounded-2xl rounded-tr-xs overflow-hidden cursor-pointer shadow-md border border-black/5 bg-slate-100 hover:shadow-lg active:scale-[0.98] transition max-w-[280px] sm:max-w-[360px]"
                                             @click="$dispatch('open-lightbox', { url: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}', name: '{{ addslashes($att->original_filename) }}', download: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}' })">
                                            <img src="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}" alt="{{ $att->original_filename }}" loading="lazy" class="w-full max-h-80 object-cover block">
                                            <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">HD</div>
                                            <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                <span>{{ $att->formatted_size }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Text Bubble -->
                        @if($msg->body && $msg->body !== '(Attachment)' && trim($msg->body) !== '')
                            <div class="max-w-[85%] sm:max-w-[75%] rounded-2xl rounded-tr-xs px-4 py-2.5 shadow-sm text-white select-text"
                                 style="background: linear-gradient(135deg, #0084ff 0%, #0066f6 100%); box-shadow: 0 2px 8px -1px rgba(0, 132, 255, 0.35);">
                                <p class="text-[13px] leading-relaxed whitespace-pre-wrap font-normal">{{ $msg->body }}</p>
                            </div>
                        @endif

                        <!-- Documents -->
                        @if($nonImages->isNotEmpty())
                            <div class="space-y-1.5 max-w-[85%] sm:max-w-[75%]">
                                @foreach($nonImages as $att)
                                    <a href="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}" target="_blank"
                                       class="flex items-center space-x-2.5 p-2.5 rounded-2xl bg-white border border-slate-200/90 hover:bg-slate-50 transition shadow-xs">
                                        <div class="{{ $this->getFileBadgeClass($att->original_filename, true) }} w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[10px] shrink-0 shadow-2xs">
                                            <span>{{ $this->getFileExtension($att->original_filename) }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-semibold text-slate-800 truncate">{{ $att->original_filename }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $att->formatted_size }}</div>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Requester (Employee) Message (Left-aligned Modern Messenger Pill) -->
                    <div class="flex items-start space-x-2.5 w-full my-2" wire:key="msg-{{ $msg->id }}">
                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold shrink-0 mt-1 shadow-2xs select-none">
                            {{ strtoupper(substr($msg->sender?->name ?? 'U', 0, 1)) }}
                        </div>

                        <div class="flex flex-col items-start max-w-[85%] sm:max-w-[75%] space-y-1.5 min-w-0">
                            <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 pl-1 select-none">
                                <span class="font-semibold text-slate-700">{{ $msg->sender?->name ?? 'Requester' }}</span>
                                <span class="text-[8px] bg-slate-200 text-slate-700 font-bold px-1.5 py-0.2 rounded uppercase">Requester</span>
                                <span>•</span>
                                <span class="font-mono">{{ $msg->created_at->format('M d, H:i') }}</span>
                            </div>

                            @php
                                $images = $msg->attachments->filter(fn($a) => str_starts_with($a->mime_type, 'image/'));
                                $nonImages = $msg->attachments->filter(fn($a) => !str_starts_with($a->mime_type, 'image/'));
                            @endphp

                            <!-- Photos -->
                            @if($images->isNotEmpty())
                                <div class="flex flex-col items-start space-y-1.5">
                                    @foreach($images as $att)
                                        <div class="relative group">
                                            <div class="relative rounded-2xl rounded-tl-xs overflow-hidden cursor-pointer shadow-md border border-slate-200/90 bg-slate-100 hover:shadow-lg active:scale-[0.98] transition max-w-[280px] sm:max-w-[360px]"
                                                 @click="$dispatch('open-lightbox', { url: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}', name: '{{ addslashes($att->original_filename) }}', download: '{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}' })">
                                                <img src="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id, 'preview' => 1]) }}" alt="{{ $att->original_filename }}" loading="lazy" class="w-full max-h-80 object-cover block">
                                                <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">HD</div>
                                                <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                    <span>{{ $att->formatted_size }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Text Bubble -->
                            @if($msg->body && $msg->body !== '(Attachment)' && trim($msg->body) !== '')
                                <div class="bg-white border border-slate-200/90 text-slate-800 rounded-2xl rounded-tl-xs px-4 py-2.5 shadow-2xs select-text">
                                    <p class="text-[13px] leading-relaxed whitespace-pre-wrap font-normal">{{ $msg->body }}</p>
                                </div>
                            @endif

                            <!-- Documents -->
                            @if($nonImages->isNotEmpty())
                                <div class="space-y-1.5 w-full">
                                    @foreach($nonImages as $att)
                                        <a href="{{ route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $att->id]) }}" target="_blank"
                                           class="flex items-center space-x-2.5 p-2.5 rounded-2xl bg-white border border-slate-200/90 hover:bg-slate-50 transition shadow-xs">
                                            <div class="{{ $this->getFileBadgeClass($att->original_filename, false) }} w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[10px] shrink-0 shadow-2xs">
                                                <span>{{ $this->getFileExtension($att->original_filename) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-semibold text-slate-800 truncate">{{ $att->original_filename }}</div>
                                                <div class="text-[10px] text-slate-400 font-mono">{{ $att->formatted_size }}</div>
                                            </div>
                                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @empty
                <div class="text-center py-12 text-xs text-slate-400">
                    @if($chatFilter === 'notes')
                        <span>🔒 No internal staff notes yet on this ticket.</span>
                    @elseif($chatFilter === 'conversation')
                        <span>💬 No staff/employee chat messages yet (system events hidden).</span>
                    @else
                        <span>💬 No conversation messages yet. Send a reply or post a note below.</span>
                    @endif
                </div>
            @endforelse
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
            <button type="button" wire:click="setMessageTab('requester_visible')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer {{ $messageTab === 'requester_visible' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900' }}">
                <span>💬</span>
                <span>Reply to Employee</span>
            </button>

            <button type="button" wire:click="setMessageTab('internal_staff_only')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer {{ $messageTab === 'internal_staff_only' ? 'bg-amber-400 text-amber-950 font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:text-slate-900' }}">
                <span>🔒</span>
                <span>Internal Note (IT Staff Only)</span>
            </button>
        </div>

        <form wire:submit="sendMessage" class="space-y-2.5">
            <div>
                <textarea wire:model="messageBody"
                          rows="2"
                          @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px'"
                          @keydown.enter.exact.prevent="$wire.sendMessage()"
                          @paste="handlePaste($event)"
                          placeholder="{{ $messageTab === 'internal_staff_only' ? 'Write confidential internal note (Press Enter to post, Shift+Enter for newline, Ctrl+V to paste screenshot)...' : 'Type reply that the employee will see on their Telegram Mini App (Press Enter to send, Shift+Enter for newline, Ctrl+V to paste screenshot)...' }}"
                          class="w-full text-xs p-2.5 sm:p-3 rounded-xl border focus:outline-none focus:ring-2 resize-none max-h-32 overflow-y-auto {{ $messageTab === 'internal_staff_only' ? 'bg-amber-50/40 border-amber-300 focus:ring-amber-500' : 'bg-slate-50/70 border-slate-300 focus:bg-white focus:ring-blue-600' }}"></textarea>
                @error('messageBody') <div class="text-xs text-rose-500 mt-1 font-semibold">{{ $message }}</div> @enderror
                @error('attachments.*') <div class="text-xs text-rose-500 mt-1 font-semibold">{{ $message }}</div> @enderror
            </div>

            <!-- Staged Attachment Chips -->
            @if(count($attachments) > 0)
                <div class="flex flex-wrap gap-2 pt-0.5">
                    @foreach($attachments as $idx => $file)
                        <span class="inline-flex items-center space-x-1.5 bg-blue-50 border border-blue-200 text-blue-800 text-[11px] font-medium px-2.5 py-1 rounded-xl shadow-2xs">
                            <span>📎</span>
                            <span class="truncate max-w-[140px]">{{ $file->getClientOriginalName() }}</span>
                            <span class="text-[9px] opacity-70">({{ number_format($file->getSize() / 1024, 0) }} KB)</span>
                            <button type="button" wire:click="removeAttachment({{ $idx }})" class="text-blue-500 hover:text-rose-600 font-bold ml-1 text-xs cursor-pointer">&times;</button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div wire:loading wire:target="attachments" class="text-xs text-blue-600 flex items-center gap-1.5">
                <span class="animate-spin text-xs">⏳</span>
                <span>Uploading file(s)...</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="flex items-center space-x-2">
                    <input type="file" wire:model="attachments" id="ticketChatFileInput" multiple accept="image/*,application/pdf" class="hidden">
                    <label for="ticketChatFileInput"
                           class="cursor-pointer inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition shadow-2xs">
                        <span>📎</span>
                        <span>Attach Files</span>
                    </label>
                    <span class="text-[11px] text-slate-400 hidden sm:inline">or paste screenshot (Ctrl+V)</span>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 font-bold text-xs rounded-xl shadow-xs transition whitespace-nowrap disabled:opacity-50 flex items-center space-x-1.5 cursor-pointer {{ $messageTab === 'internal_staff_only' ? 'bg-amber-500 hover:bg-amber-600 text-amber-950' : 'bg-blue-600 hover:bg-blue-700 text-white' }}">
                    <span wire:loading wire:target="sendMessage" class="animate-spin text-xs">⏳</span>
                    <span wire:loading.remove wire:target="sendMessage">
                        {{ $messageTab === 'internal_staff_only' ? 'Post Internal Note' : 'Send Reply to Employee' }}
                    </span>
                    <span wire:loading wire:target="sendMessage">Sending...</span>
                </button>
            </div>
        </form>
    </div>
</div>
