<div @click="openTicketDetail(t.id)"
     class="tg-theme-card rounded-2xl border border-slate-200/70 p-3.5 shadow-xs hover:border-blue-400 cursor-pointer active:scale-[0.97] active:bg-slate-50/90 transition-all duration-150 ease-out space-y-2.5 bg-white text-slate-900"
     style="background-color: #ffffff !important; color: #0f172a !important;">
    
    <!-- Card Header: Code, Type & Status Badge -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-1.5 flex-wrap gap-y-1">
            <span class="text-xs font-mono font-bold text-blue-600" x-text="t.ticket_code"></span>
            <span x-show="t.ticket_type === 'service_request'" class="text-[9px] bg-blue-50 text-blue-700 border border-blue-200 px-1.5 py-0.2 rounded font-semibold uppercase" x-text="locale === 'km' ? 'សេវាកម្ម' : 'Request'">Request</span>
            <span x-show="t.ticket_type !== 'service_request'" class="text-[9px] bg-rose-50 text-rose-700 border border-rose-200 px-1.5 py-0.2 rounded font-semibold uppercase" x-text="locale === 'km' ? 'បញ្ហាខូច' : 'Incident'">Incident</span>
            <span x-show="t.has_new_reply" class="text-[9px] bg-blue-600 text-white font-bold px-1.5 py-0.2 rounded-full uppercase tracking-wider flex items-center gap-1 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                <span x-text="locale === 'km' ? 'សារថ្មី' : 'New Reply'">New Reply</span>
            </span>
        </div>
        
        <span :class="getStatusBadgeClass(t.status)"
              class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
              x-text="formatStatus(t.status)"></span>
    </div>

    <!-- Subject -->
    <h4 class="text-sm font-semibold text-slate-800 line-clamp-1" x-text="t.subject" style="color: #1e293b !important;"></h4>

    <!-- Latest Message Preview Snippet (Telegram/WhatsApp style) -->
    <div x-show="t.latest_message"
         :class="t.has_new_reply ? 'bg-blue-50/70 border-blue-200' : 'bg-slate-50 border-slate-200/80'"
         class="border rounded-xl px-2.5 py-1.5 text-xs transition">
        <div class="flex items-center justify-between text-[11px] mb-0.5">
            <span :class="t.latest_message?.is_staff ? 'text-blue-600 font-bold' : 'text-slate-600 font-medium'" class="flex items-center gap-1 truncate max-w-[190px]">
                <span x-show="t.latest_message?.is_staff" class="flex items-center gap-1">
                    <span>💬 IT:</span>
                    <span class="truncate" x-text="t.latest_message?.sender_name"></span>
                </span>
                <span x-show="!t.latest_message?.is_staff" class="flex items-center gap-1">
                    <span>💬 <span x-text="locale === 'km' ? 'អ្នក' : 'You'"></span>:</span>
                    <span class="truncate" x-text="t.latest_message?.sender_name"></span>
                </span>
            </span>
            <span class="text-[10px] text-slate-400 shrink-0" x-text="t.latest_message?.created_at_human"></span>
        </div>
        <p class="text-[11px] text-slate-700 line-clamp-1 italic">
            &ldquo;<span x-text="t.latest_message?.body"></span>&rdquo;
        </p>
    </div>

    <!-- Requester Info for HOD visibility -->
    <div x-show="t.requester_name && isHod" class="text-[10px] text-slate-500 flex items-center gap-1">
        <span class="font-medium text-slate-400" x-text="locale === 'km' ? '👤 អ្នកស្នើ៖' : '👤 Requester:'">👤 Requester:</span>
        <span class="font-bold text-slate-700" x-text="t.requester_name"></span>
        <span x-show="t.requester_dept" class="text-slate-400 font-medium">(<span x-text="t.requester_dept"></span>)</span>
    </div>

    <!-- Approved by me tracking badge (Shows HOD where their approved request is) -->
    <div x-show="t.approved_by_me" class="bg-emerald-50/90 border border-emerald-200/90 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-emerald-800">
        <div class="flex items-center space-x-1.5 min-w-0">
            <span class="shrink-0 text-emerald-600 font-bold">✅</span>
            <span class="font-medium truncate text-[11px]"><span x-text="locale === 'km' ? 'បានអនុម័តដោយអ្នក' : 'Approved by You'"></span> &bull; <span class="font-bold text-emerald-900" x-text="formatStatus(t.status)"></span></span>
        </div>
        <span class="text-[10px] font-semibold text-emerald-700 shrink-0 ml-1.5" x-text="t.technician_name || (locale === 'km' ? 'ក្នុងជួររង់ចាំ IT' : 'In IT Queue')"></span>
    </div>

    <!-- Rejected by me tracking badge (Shows HOD that they declined this request) -->
    <div x-show="t.rejected_by_me" class="bg-rose-50 border border-rose-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-rose-800">
        <div class="flex items-center space-x-1.5 min-w-0">
            <span class="shrink-0 text-rose-600 font-bold">❌</span>
            <span class="font-medium truncate text-[11px]"><span x-text="locale === 'km' ? 'បានបដិសេធដោយអ្នក' : 'Rejected by You'"></span> &bull; <span class="font-bold text-rose-900" x-text="t.my_approval_comments ? ((locale === 'km' ? 'មូលហេតុ៖ ' : 'Reason: ') + t.my_approval_comments) : (locale === 'km' ? 'មិនយល់ព្រម' : 'Declined')"></span></span>
        </div>
        <span class="text-[10px] font-semibold text-rose-700 shrink-0 ml-1.5" x-text="locale === 'km' ? 'បានបិទ' : 'Closed'">Closed</span>
    </div>

    <!-- Approval prompt if pending approval -->
    <div x-show="t.is_pending_approval" class="bg-amber-50 border border-amber-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-amber-800">
        <span class="font-medium">⏳ <span x-text="locale === 'km' ? 'ការអនុម័ត៖' : 'Approval:'"></span> <span class="font-bold" x-text="t.current_approval_step?.step_name || (locale === 'km' ? 'រង់ចាំពិនិត្យ' : 'Pending Review')"></span></span>
        <span x-show="t.can_approve" class="text-amber-700 font-bold bg-amber-200/70 px-2 py-0.5 rounded-full text-[10px]" x-text="locale === 'km' ? 'ពិនិត្យអនុម័ត &rarr;' : 'Your Review &rarr;'">Your Review &rarr;</span>
        <span x-show="!t.can_approve" class="text-slate-500 text-[10px]" x-text="t.current_approval_step?.step_number === 1 ? (locale === 'km' ? 'រង់ចាំប្រធានផ្នែក' : 'Awaiting Dept HOD') : (locale === 'km' ? 'រង់ចាំប្រធាន IT' : 'Awaiting IT HOD')"></span>
    </div>

    <!-- Action Prompt Alert if confirmation or clarification required -->
    <div x-show="t.is_pending_confirmation" class="bg-emerald-50 border border-emerald-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-emerald-800">
        <span class="font-medium" x-text="locale === 'km' ? '✨ IT បានដោះស្រាយរួចរាល់! សូមបញ្ជាក់' : '✨ Tech completed! Please confirm resolution'">✨ Tech completed! Please confirm resolution</span>
        <span class="text-emerald-600 font-bold" x-text="locale === 'km' ? 'តម្រូវឱ្យបញ្ជាក់ &rarr;' : 'Action Needed &rarr;'">Action Needed &rarr;</span>
    </div>

    <div x-show="t.is_clarifying" class="bg-amber-50 border border-amber-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-amber-800">
        <span class="font-medium" x-text="locale === 'km' ? '💬 IT បានស្នើសុំព័ត៌មានបន្ថែម' : '💬 Help Desk requested information'">💬 Help Desk requested information</span>
        <span class="text-amber-600 font-bold" x-text="locale === 'km' ? 'ឆ្លើយតប &rarr;' : 'Reply &rarr;'">Reply &rarr;</span>
    </div>

    <!-- Footer: Category, Tech/Requester & Time -->
    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-100">
        <div class="flex items-center space-x-1.5 truncate max-w-[200px]">
            <span class="truncate" x-text="t.category_name"></span>
        </div>
        <span x-text="t.created_at_human"></span>
    </div>
</div>
