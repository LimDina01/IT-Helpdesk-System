    <!-- DEDICATED CHAT PAGE (NATIVE MESSENGER FULL-SCREEN STYLE) -->
    <div x-show="activeTicketModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-x-4"
         x-transition:enter-end="opacity-100 translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-x-0"
         x-transition:leave-end="opacity-0 translate-x-4"
         class="fixed inset-0 z-50 flex flex-col bg-slate-50 w-full h-full overflow-hidden"
         style="background-color: #f8fafc !important;">
        <div class="flex-1 flex flex-col max-w-md w-full mx-auto bg-slate-50 overflow-hidden h-full relative"
             style="background-color: #f8fafc !important;">
            
            <!-- Top Navigation Bar (Minimal Messenger Header - Spacious & Clean) -->
            <div class="bg-white px-3.5 py-2.5 border-b border-slate-200/90 flex items-center justify-between shrink-0 z-10 shadow-2xs select-none"
                 @click="if (isMobileDevice()) dismissKeyboard()">
                <div class="flex items-center space-x-2.5 min-w-0 flex-1 pr-2">
                    <!-- Native Back Button (Returns to Ticket List) -->
                    <button type="button" @click="closeTicketModal()"
                            class="w-9 h-9 rounded-full text-slate-700 hover:text-blue-600 hover:bg-slate-100 active:bg-slate-200 active:scale-95 transition flex items-center justify-center shrink-0 -ml-1"
                            title="Back to Tickets">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Avatar & Header Click Target (Opens Ticket Details Page) -->
                    <div class="flex items-center space-x-2.5 min-w-0 flex-1 cursor-pointer" @click="showTicketInfo = true" title="View Ticket Details & Workflow">
                        <div class="relative shrink-0">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs"
                                 x-text="(selectedTicket?.technician_name || selectedTicket?.category_name || 'IT').charAt(0).toUpperCase()">
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 border-2 border-white"></span>
                            </span>
                        </div>

                        <!-- Subject (Bold & Truncated) and Subtitle (Code • Status) -->
                        <div class="min-w-0 flex-1">
                            <h2 class="text-xs font-bold text-slate-900 truncate leading-snug" x-text="selectedTicket?.subject"></h2>
                            <div class="flex items-center space-x-1.5 text-[10px] text-slate-500 mt-0.5 truncate">
                                <span class="font-mono font-semibold text-blue-600" x-text="selectedTicket?.ticket_code"></span>
                                <span class="text-slate-300">•</span>
                                <span class="font-medium text-slate-600 truncate" x-text="formatStatus(selectedTicket?.status)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Details Button -->
                <div class="flex items-center shrink-0">
                    <button type="button" @click="showTicketInfo = true"
                            class="flex items-center space-x-1 px-2.5 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 text-xs font-semibold transition shadow-2xs"
                            title="View Ticket Details & Workflow">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-[11px] font-bold" x-text="t('details')">Details</span>
                    </button>
                </div>
            </div>

            <!-- HOD APPROVAL ACTION PANEL (If current user is authorized to approve this step) -->
            <div x-show="selectedTicket?.can_approve_current_step" class="bg-amber-50 border-b border-amber-200 p-3.5 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-amber-600 font-bold">🔔</span>
                        <span class="font-bold text-xs text-amber-900" x-text="locale === 'km' ? 'តម្រូវឱ្យមានការអនុម័តរបស់អ្នក' : 'Your Approval is Required'">Your Approval is Required</span>
                    </div>
                    <span class="text-[10px] bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full font-bold" x-text="selectedTicket?.current_approval_step?.step_name"></span>
                </div>
                <p class="text-[11px] text-amber-800 leading-snug">
                    <span x-text="locale === 'km' ? 'អ្នកស្នើ' : 'Requester'"></span> <span class="font-bold" x-text="selectedTicket?.requester_name"></span> (<span x-text="selectedTicket?.requester_dept"></span>) <span x-text="locale === 'km' ? 'បានដាក់ស្នើសុំសេវាកម្មនេះដើម្បីឱ្យមានការអនុម័ត។' : 'has submitted this service request for company approval.'">has submitted this service request for company approval.</span>
                </p>

                <div class="flex items-center space-x-2 pt-1">
                    <button @click="showApprovePrompt = true" :disabled="actioningApproval"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl font-bold text-xs shadow-xs text-center transition-all duration-150">
                        ✅ <span x-text="t('approve')">Approve Request</span>
                    </button>
                    <button @click="showRejectPrompt = true" :disabled="actioningApproval"
                            class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white rounded-xl font-bold text-xs shadow-xs text-center transition-all duration-150">
                        ❌ <span x-text="t('reject')">Reject Request</span>
                    </button>
                </div>
            </div>

            <!-- Approval Comment Prompt Overlay -->
            <div x-show="showApprovePrompt"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="bg-emerald-50 p-3 border-b border-emerald-300 space-y-2">
                <label class="block text-xs font-bold text-emerald-900" x-text="t('comment_optional')">Approval Comment / Instructions (Optional):</label>
                <input type="text" x-model="approvalComment" :placeholder="t('comment_placeholder')"
                       class="tma-input w-full text-xs p-2.5 rounded-xl border border-emerald-300 focus:outline-none bg-white text-slate-900 font-medium"
                       style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                <div class="flex justify-end space-x-2">
                    <button @click="showApprovePrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold active:scale-95 transition-all duration-150" x-text="t('cancel_action')">Cancel</button>
                    <button @click="submitApprove()" :disabled="actioningApproval" class="text-xs px-3 py-1 bg-emerald-600 active:bg-emerald-700 active:scale-95 text-white font-bold rounded-md transition-all duration-150" x-text="t('confirm_approve')">Confirm Approval</button>
                </div>
            </div>

            <!-- Rejection Reason Prompt Overlay -->
            <div x-show="showRejectPrompt"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="bg-rose-50 p-3 border-b border-rose-300 space-y-2">
                <label class="block text-xs font-bold text-rose-900" x-text="t('rejection_reason')">Rejection Reason (Mandatory):</label>
                <textarea x-model="rejectionReason" rows="2" :placeholder="t('rejection_reason_placeholder')"
                          class="tma-textarea w-full text-xs p-2.5 rounded-xl border border-rose-300 focus:outline-none bg-white text-slate-900 font-medium"
                          style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;"></textarea>
                <div class="flex justify-end space-x-2">
                    <button @click="showRejectPrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold active:scale-95 transition-all duration-150" x-text="t('cancel_action')">Cancel</button>
                    <button @click="submitReject()" :disabled="actioningApproval || !rejectionReason" class="text-xs px-3 py-1 bg-rose-600 active:bg-rose-700 active:scale-95 text-white font-bold rounded-md transition-all duration-150" x-text="t('confirm_reject')">Confirm Rejection</button>
                </div>
            </div>

            <!-- Resolution Confirmation Banner (Option A/B Decision Point) -->
            <div x-show="selectedTicket?.is_pending_confirmation" class="bg-emerald-600 text-white p-3.5 space-y-2 shadow-sm">
                <div class="flex items-start space-x-2">
                    <span class="text-xl">🛠</span>
                    <div>
                        <div class="font-bold text-xs" x-text="t('satisfaction_title')">Technician marked this ticket as resolved!</div>
                        <p class="text-[11px] text-emerald-100" x-text="t('satisfaction_subtitle')">Please verify if your IT issue is fully fixed.</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-1">
                    <button @click="confirmResolution()" :disabled="confirmingAction"
                            class="flex-1 py-2 bg-white text-emerald-800 hover:bg-emerald-50 active:scale-95 rounded-xl font-bold text-xs shadow-xs text-center transition-all duration-150">
                        ✔ <span x-text="locale === 'km' ? 'បាទ/ចាស ដោះស្រាយរួចរាល់!' : 'Yes, Solved!'">Yes, Solved!</span>
                    </button>
                    <button @click="showReopenPrompt = true" :disabled="confirmingAction"
                            class="flex-1 py-2 bg-emerald-800/80 hover:bg-emerald-800 active:scale-95 text-white rounded-xl font-bold text-xs text-center transition-all duration-150">
                        ↩ <span x-text="locale === 'km' ? 'នៅមានបញ្ហា' : 'Still Broken'">Still Broken</span>
                    </button>
                </div>
            </div>

            <!-- Reopen Reason Input Overlay -->
            <div x-show="showReopenPrompt"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="bg-amber-100 p-3 border-b border-amber-300 space-y-2">
                <label class="block text-xs font-bold text-amber-900" x-text="t('reopen_reason')">Explain why it is still broken:</label>
                <textarea x-model="reopenReason" rows="2" :placeholder="t('reopen_placeholder')"
                          class="tma-textarea w-full text-xs p-2.5 rounded-xl border border-amber-300 focus:outline-none bg-white text-slate-900 font-medium"
                          style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;"></textarea>
                <div class="flex justify-end space-x-2">
                    <button @click="showReopenPrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold" x-text="t('cancel_action')">Cancel</button>
                    <button @click="submitReopen()" class="text-xs px-3 py-1 bg-red-600 text-white font-bold rounded-md" x-text="t('confirm_reopen')">Reopen Ticket</button>
                </div>
            </div>

            <!-- Conversation Timeline (Executive Luxury Theme) -->
            <div class="flex-1 overflow-y-auto px-3.5 py-4 space-y-3 relative" id="timelineContainer"
                 style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);"
                 @scroll.passive="onTimelineScroll($event)"
                 @touchstart.passive="handleTimelineTouchStart($event)"
                 @touchmove.passive="handleTimelineTouchMove($event)"
                 @click="handleTimelineClick($event)">
                
                <template x-for="(m, idx) in messages" :key="m.id">
                    <div>
                        <!-- Date Separator Badge (Centered Frosted Glass Pill) -->
                        <template x-if="isNewDay(idx)">
                            <div class="flex items-center justify-center my-3 select-none">
                                <span class="px-3 py-0.5 text-[10px] font-semibold tracking-wide text-slate-500 bg-white/85 backdrop-blur-xs border border-slate-200/80 rounded-full shadow-2xs"
                                      x-text="getDayLabel(m)"></span>
                            </div>
                        </template>

                        <!-- Mid-Conversation Time Gap Separator (Messenger style e.g. "1:05 PM") -->
                        <template x-if="shouldShowTimeSeparator(idx)">
                            <div class="flex items-center justify-center my-2.5 select-none">
                                <span class="text-[10px] font-semibold text-slate-400 select-none tracking-wide"
                                      x-text="formatBubbleTime(m)"></span>
                            </div>
                        </template>

                        <!-- System Events (Centered status events) -->
                        <template x-if="m.visibility === 'system_event'">
                            <div class="flex justify-center my-2 select-none">
                                <span class="bg-slate-200/80 text-slate-600 text-[11px] font-medium px-3 py-1 rounded-full shadow-2xs border border-slate-300/40 text-center"
                                      x-text="m.body"></span>
                            </div>
                        </template>

                        <!-- Chat Message Bubbles (Modern Messenger Image & Text Separation) -->
                        <template x-if="m.visibility !== 'system_event'">
                            <div class="w-full">
                                
                                <!-- USER / SENT MESSAGE (Clean Messenger Layout) -->
                                <template x-if="m.is_me">
                                    <div class="flex flex-col items-end w-full space-y-1.5 my-1.5">
                                        
                                        <!-- 1. Standalone Borderless Photo Card(s) with Generous Aspect & HD Badge -->
                                        <template x-if="m.attachments && m.attachments.some(a => a.is_image)">
                                            <div class="flex flex-col items-end space-y-1.5">
                                                <template x-for="att in m.attachments.filter(a => a.is_image)" :key="att.id">
                                                    <div class="relative flex items-center space-x-2 group">
                                                        <!-- Quick View / Lightbox Action -->
                                                        <button type="button" @click="openLightbox(att.download_url, att.original_filename, att.download_url)"
                                                                class="w-7 h-7 rounded-full bg-slate-200/80 hover:bg-slate-300 text-slate-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-2xs shrink-0"
                                                                title="View full image">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        </button>

                                                        <!-- Clean Image Bubble -->
                                                        <div class="relative rounded-2xl rounded-tr-xs overflow-hidden cursor-pointer shadow-md border border-black/5 bg-slate-100 active:scale-[0.98] transition max-w-[270px] sm:max-w-[300px]"
                                                             @click="openLightbox(att.download_url, att.original_filename, att.download_url)">
                                                            <img :src="att.preview_url" :alt="att.original_filename"
                                                                 loading="lazy"
                                                                 class="w-full max-h-72 object-cover block">
                                                            
                                                            <!-- HD Badge in top right corner (as seen in Messenger) -->
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
                                        <template x-if="m.body && m.body !== '(Attachment)' && m.body.trim().length > 0">
                                            <div class="relative max-w-[85%] sm:max-w-[78%] rounded-2xl rounded-tr-xs px-4 py-2.5 shadow-sm text-white select-text"
                                                 style="background: linear-gradient(135deg, #0084ff 0%, #0066f6 100%); box-shadow: 0 2px 8px -1px rgba(0, 132, 255, 0.35);">
                                                <div class="text-[13.5px] leading-relaxed break-words font-normal">
                                                    <span x-text="m.body"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- 3. Non-Image Document Cards (PDFs, Docs, etc.) -->
                                        <template x-if="m.attachments && m.attachments.some(a => !a.is_image)">
                                            <div class="space-y-1.5 max-w-[85%] sm:max-w-[78%]">
                                                <template x-for="att in m.attachments.filter(a => !a.is_image)" :key="att.id">
                                                    <a :href="att.download_url" target="_blank"
                                                       @click="openAttachmentLink($event, att.download_url)"
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

                                        <!-- 4. Timestamp & Messenger "Seen" Receipt with Technician Mini Avatar -->
                                        <div class="flex items-center justify-end space-x-1.5 text-[10px] text-slate-400 select-none pt-0.5 pr-0.5">
                                            <span x-text="formatBubbleTime(m)"></span>
                                            
                                            <!-- Messenger-style "Seen" indicator on latest message -->
                                            <template x-if="idx === messages.length - 1">
                                                <div class="flex items-center space-x-1 pl-1">
                                                    <template x-if="selectedTicket?.status !== 'submitted' && selectedTicket?.technician_name">
                                                        <div class="flex items-center space-x-1">
                                                            <span class="font-semibold text-slate-400 text-[10px]">Seen</span>
                                                            <div class="w-3.5 h-3.5 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-[7px] font-bold shadow-2xs"
                                                                 :title="'Seen by ' + selectedTicket.technician_name"
                                                                 x-text="(selectedTicket.technician_name || 'IT').charAt(0).toUpperCase()">
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="selectedTicket?.status === 'submitted' || !selectedTicket?.technician_name">
                                                        <span class="text-blue-500 font-medium">Sent ✔</span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- RECEIVED / STAFF MESSAGE (Clean Messenger Layout) -->
                                <template x-if="!m.is_me">
                                    <div class="flex items-start space-x-2 w-full my-1.5">
                                        <!-- Staff Avatar -->
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-1 shadow-2xs"
                                             x-text="(m.sender_name || 'IT').charAt(0).toUpperCase()">
                                        </div>

                                        <!-- Staff Content Column -->
                                        <div class="flex flex-col items-start max-w-[85%] sm:max-w-[78%] space-y-1.5 min-w-0">
                                            <!-- Sender Name & Staff Badge -->
                                            <div class="flex items-center space-x-1.5 text-[11px] font-bold text-slate-700 select-none pb-0.5 pl-1">
                                                <span class="truncate" x-text="m.sender_name"></span>
                                                <span class="bg-blue-50 border border-blue-200/80 text-blue-700 text-[9px] px-1.5 py-0.2 rounded font-semibold uppercase tracking-wider">IT Staff</span>
                                            </div>

                                            <!-- 1. Standalone Photo Card(s) with Generous Aspect & HD Badge -->
                                            <template x-if="m.attachments && m.attachments.some(a => a.is_image)">
                                                <div class="flex flex-col items-start space-y-1.5">
                                                    <template x-for="att in m.attachments.filter(a => a.is_image)" :key="att.id">
                                                        <div class="relative rounded-2xl rounded-tl-xs overflow-hidden cursor-pointer shadow-sm border border-slate-200 bg-slate-100 active:scale-[0.98] transition max-w-[270px] sm:max-w-[300px]"
                                                             @click="openLightbox(att.download_url, att.original_filename, att.download_url)">
                                                            <img :src="att.preview_url" :alt="att.original_filename"
                                                                 loading="lazy"
                                                                 class="w-full max-h-72 object-cover block">
                                                            
                                                            <!-- HD Badge in top right corner -->
                                                            <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/45 backdrop-blur-xs text-[9px] font-bold text-white tracking-wider uppercase select-none shadow-xs">
                                                                HD
                                                            </div>

                                                            <!-- Subtle bottom file size indicator -->
                                                            <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/50 backdrop-blur-xs text-[10px] text-white/90 font-mono select-none">
                                                                <span x-text="att.file_size"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <!-- 2. Standalone Text Message Bubble (Pearlescent White) -->
                                            <template x-if="m.body && m.body !== '(Attachment)' && m.body.trim().length > 0">
                                                <div class="relative max-w-full rounded-2xl rounded-tl-xs px-4 py-2.5 shadow-xs bg-white text-slate-800 border border-slate-200/90 select-text"
                                                     style="box-shadow: 0 1px 4px 0 rgba(0, 0, 0, 0.05);">
                                                    <div class="text-[13.5px] leading-relaxed break-words font-normal text-slate-800">
                                                        <span x-text="m.body"></span>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- 3. Non-Image Document Cards -->
                                            <template x-if="m.attachments && m.attachments.some(a => !a.is_image)">
                                                <div class="space-y-1.5 w-full">
                                                    <template x-for="att in m.attachments.filter(a => !a.is_image)" :key="att.id">
                                                        <a :href="att.download_url" target="_blank"
                                                           @click="openAttachmentLink($event, att.download_url)"
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

                                            <!-- 4. Staff Timestamp -->
                                            <div class="text-[10px] text-slate-400 select-none pl-1 pt-0.5">
                                                <span x-text="formatBubbleTime(m)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Floating "Scroll to Latest" Pill Button -->
            <div x-show="showScrollBottomBtn"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                 class="absolute right-4 bottom-20 z-20">
                <button type="button" @click="scrollToBottom(true, true)"
                        class="relative flex items-center space-x-1.5 px-3 py-1.5 bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 rounded-full shadow-lg hover:bg-slate-50 active:scale-95 transition">
                    <svg class="w-4 h-4 text-blue-600 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    <span class="text-xs font-semibold">Latest</span>
                    <span x-show="hasNewIncomingMessage" class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                </button>
            </div>

            <!-- Executive Floating Studio Reply Dock (Squeezed & Tucked in away from iPhone rounded corners & home indicator) -->
            <div x-show="!selectedTicket?.is_closed"
                 class="bg-white/95 backdrop-blur-md border-t border-slate-200/80 shrink-0 z-10 shadow-lg transition-all"
                 :style="isReplyFocused ? 'padding: 8px 16px 10px 16px !important;' : 'padding: 10px max(env(safe-area-inset-right, 18px), 18px) max(env(safe-area-inset-bottom, 28px), 24px) max(env(safe-area-inset-left, 18px), 18px) !important;'">
                <form @submit.prevent="sendReply()" class="space-y-2 max-w-md mx-auto">
                    <div x-show="replyError" class="text-xs text-rose-600 bg-rose-50 border border-rose-200 p-2 rounded-xl font-medium" x-text="replyError"></div>

                    <!-- iOS Keyboard Dismiss Bar (Shown only on mobile when keyboard is active) -->
                    <div x-show="isMobileDevice() && isReplyFocused"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="flex items-center justify-between pb-1.5 pt-0.5 text-xs text-slate-500 border-b border-slate-100">
                        <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            <span x-text="locale === 'km' ? 'ចុចលើការសន្ទនា ឬ «រួចរាល់» ដើម្បីលាក់ក្តារចុច' : 'Tap chat or “Done” to hide keyboard'">Tap chat or &ldquo;Done&rdquo; to hide keyboard</span>
                        </span>
                        <button type="button"
                                @mousedown.prevent
                                @touchstart.prevent="dismissKeyboard()"
                                @click="dismissKeyboard()"
                                class="text-xs font-bold text-blue-600 bg-blue-50 active:bg-blue-100 px-2.5 py-1 rounded-lg flex items-center space-x-1 transition shadow-2xs">
                            <span x-text="locale === 'km' ? 'រួចរាល់' : 'Done'">Done</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>

                    <!-- File preview badges -->
                    <div x-show="replyFiles.length > 0" class="flex flex-wrap gap-1.5 text-[11px] text-slate-600 pb-1">
                        <template x-for="(f, i) in replyFiles" :key="i">
                            <span class="bg-blue-50 border border-blue-200/80 text-blue-900 px-2.5 py-1 rounded-xl flex items-center space-x-1.5 font-medium shadow-2xs">
                                <span>📎</span>
                                <span class="truncate max-w-[140px]" x-text="f.name"></span>
                                <button type="button" @click="removeReplyFile(i)" class="text-rose-500 hover:text-rose-700 font-bold ml-1 text-sm leading-none">&times;</button>
                            </span>
                        </template>
                    </div>

                    <!-- Input Controls Row (Executive Native Messenger Style - Squeezed & Tucked inward) -->
                    <div class="flex items-center space-x-2 px-1">
                        <!-- Photo Gallery Button (Crisp Native iOS Camera Icon) -->
                        <label class="cursor-pointer text-blue-600 hover:text-blue-700 active:scale-95 w-9 h-9 rounded-full hover:bg-blue-50 active:bg-blue-100 transition shrink-0 flex items-center justify-center"
                               :title="t('photo_gallery')">
                            <svg width="22" height="22" class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <input type="file" id="chatPhotoInput" multiple @change="handleReplyFiles" class="hidden" accept="image/*">
                        </label>

                        <!-- Document / File Button (Refined Paperclip Icon) -->
                        <label class="cursor-pointer text-slate-500 hover:text-blue-600 active:scale-95 w-9 h-9 rounded-full hover:bg-slate-100 active:bg-slate-200 transition shrink-0 flex items-center justify-center"
                               :title="t('document_file')">
                            <svg width="22" height="22" class="w-5 h-5 text-slate-500 hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            <input type="file" id="chatFileInput" multiple @change="handleReplyFiles" class="hidden" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain">
                        </label>
                        
                        <!-- Rounded-full Messenger Pill Input -->
                        <input type="text" id="chatTextInput" x-model="replyText" :placeholder="t('type_message')"
                               @focus="isReplyFocused = true"
                               @blur="isReplyFocused = false"
                               class="tma-input flex-1 bg-slate-100 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-full px-4 py-2.5 text-xs text-slate-900 font-medium placeholder:text-slate-400 focus:outline-none transition shadow-inner"
                               style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #f1f5f9 !important;">

                        <!-- Hide Keyboard Button (Visible only on mobile when typing) -->
                        <button type="button"
                                x-show="isMobileDevice() && isReplyFocused"
                                @mousedown.prevent
                                @touchstart.prevent="dismissKeyboard()"
                                @click="dismissKeyboard()"
                                class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition flex items-center justify-center shrink-0 shadow-2xs"
                                :title="t('hide_keyboard')">
                            <svg width="16" height="16" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Executive Circular Send Button (Centered Send Plane - Original Beloved Version) -->
                        <button type="submit" :disabled="sendingReply || (!replyText && replyFiles.length === 0)"
                                class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30 hover:shadow-blue-500/50 active:scale-95 disabled:opacity-35 disabled:shadow-none transition flex items-center justify-center shrink-0"
                                :title="t('send')">
                            <span x-show="sendingReply" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <svg x-show="!sendingReply" width="20" height="20" class="w-5 h-5 text-white ml-0.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
