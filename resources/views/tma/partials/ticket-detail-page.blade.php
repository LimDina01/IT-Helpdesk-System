    <!-- DEDICATED TICKET DETAILS & WORKFLOW PAGE -->
    <div x-show="showTicketInfo"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-x-8"
         x-transition:enter-end="opacity-100 translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-x-0"
         x-transition:leave-end="opacity-0 translate-x-8"
         class="fixed inset-0 z-50 flex flex-col bg-slate-50 w-full h-full overflow-hidden"
         style="background-color: #f8fafc !important;">
        <div class="flex-1 flex flex-col max-w-md w-full mx-auto bg-slate-50 overflow-hidden h-full relative"
             style="background-color: #f8fafc !important;">
            
            <!-- Details Page Header -->
            <div class="bg-white px-3.5 py-3 border-b border-slate-200/90 flex items-center justify-between shrink-0 shadow-2xs select-none">
                <div class="flex items-center space-x-2">
                    <button type="button" @click="showTicketInfo = false"
                            class="w-9 h-9 rounded-full text-slate-700 hover:text-blue-600 hover:bg-slate-100 active:bg-slate-200 active:scale-95 transition flex items-center justify-center -ml-1"
                            title="Back to Conversation">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 leading-none" x-text="t('ticket_details')">Ticket Info</h2>
                        <span class="text-[10px] font-mono text-slate-400" x-text="selectedTicket?.ticket_code"></span>
                    </div>
                </div>
                <span :class="getStatusBadgeClass(selectedTicket?.status)"
                      class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full shadow-2xs"
                      x-text="formatStatus(selectedTicket?.status)"></span>
            </div>

            <!-- Scrollable Details & Workflow Content -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                
                <!-- Hero Summary Card -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="copyTicketCode(selectedTicket?.ticket_code)"
                                class="text-xs font-mono font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 active:bg-blue-200 border border-blue-200/80 px-2.5 py-1 rounded-lg flex items-center space-x-1.5 transition"
                                title="Tap to copy code">
                            <span x-text="selectedTicket?.ticket_code"></span>
                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <span class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded-full"
                              :class="selectedTicket?.priority === 'critical' ? 'bg-red-100 text-red-700' : (selectedTicket?.priority === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-700')"
                              x-text="formatPriority(selectedTicket?.priority) + (locale === 'km' ? ' អាទិភាព' : ' Priority')"></span>
                    </div>
                    <h1 class="text-base font-bold text-slate-900 leading-snug" x-text="selectedTicket?.subject"></h1>
                    <p x-show="selectedTicket?.description" class="text-xs text-slate-600 leading-relaxed whitespace-pre-wrap bg-slate-50 p-3 rounded-xl border border-slate-100" x-text="selectedTicket?.description"></p>
                </div>

                <!-- Assigned Specialist Profile Card -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="t('specialist')">Assigned IT Specialist</h3>
                    
                    <!-- When technician IS assigned -->
                    <template x-if="selectedTicket?.has_technician && selectedTicket?.technician_name">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0"
                                 x-text="selectedTicket.technician_name.charAt(0).toUpperCase()"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-slate-900 truncate" x-text="selectedTicket.technician_name"></div>
                                <div class="text-xs text-slate-500 truncate" x-text="selectedTicket.technician_title || (locale === 'km' ? 'អ្នកឯកទេស IT Help Desk' : 'IT Technician Specialist')"></div>
                                <div class="flex items-center space-x-1.5 mt-1 text-[11px] text-emerald-600 font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span x-text="locale === 'km' ? 'កំពុងទទួលបន្ទុកដោះស្រាយ' : 'Active & Handling Ticket'">Active & Handling Ticket</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- When technician is NOT assigned yet -->
                    <template x-if="!selectedTicket?.has_technician || !selectedTicket?.technician_name">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center font-bold text-xl shadow-2xs shrink-0">
                                🛠
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-slate-700 truncate" x-text="selectedTicket?.is_pending_approval ? (locale === 'km' ? 'រង់ចាំការអនុម័ត' : 'Awaiting Approval') : (locale === 'km' ? 'រង់ចាំចាត់តាំងបច្ចេកទេស' : 'Awaiting Assignment')"></div>
                                <div class="text-xs text-slate-500 truncate" x-text="selectedTicket?.is_pending_approval ? (selectedTicket?.current_approval_step?.step_name || 'Approval in progress') : 'IT Helpdesk Queue'"></div>
                                <div class="flex items-center space-x-1.5 mt-1 text-[11px] font-semibold"
                                     :class="selectedTicket?.is_pending_approval ? 'text-amber-600' : 'text-sky-600'">
                                    <span class="w-2 h-2 rounded-full" :class="selectedTicket?.is_pending_approval ? 'bg-amber-400' : 'bg-sky-400'"></span>
                                    <span x-text="selectedTicket?.is_pending_approval ? (locale === 'km' ? 'រង់ចាំការអនុម័តមុននឹងចាត់តាំង IT' : 'Awaiting Approval Before IT Assignment') : (locale === 'km' ? 'ក្នុងជួររង់ចាំចាត់តាំងអ្នកបច្ចេកទេស' : 'Queued for Technician Assignment')"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Visual Workflow Progression Card ("Peace of Mind") -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="t('workflow_progress')">Workflow Progression</h3>
                        <span class="text-[10px] font-mono text-slate-400" x-text="selectedTicket?.created_at || selectedTicket?.full_created_timestamp"></span>
                    </div>

                    <!-- Service Request Stepper (Extensible Dynamic Vertical Timeline) -->
                    <template x-if="selectedTicket?.ticket_type === 'service_request'">
                        <div class="relative pl-7 space-y-4 pt-1">
                            <!-- Vertical Connecting Track Line -->
                            <div class="absolute left-3.5 top-3.5 bottom-3.5 w-0.5 bg-slate-200"></div>

                            <!-- Step 1: Request Submitted -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'បានដាក់ស្នើសុំ' : 'Request Submitted'">Request Submitted</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="locale === 'km' ? 'រួចរាល់' : 'Submitted'">Submitted</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <span x-text="selectedTicket?.requester_name || (locale === 'km' ? 'អ្នកស្នើ' : 'Requester')"></span>
                                        <span class="text-slate-300 mx-1">•</span>
                                        <span class="font-mono text-[10px]" x-text="selectedTicket?.created_at || selectedTicket?.full_created_timestamp"></span>
                                    </p>
                                </div>
                            </div>

                            <!-- Dynamic Approvals (Slot 1, Slot 2, Slot 3, etc.) -->
                            <template x-for="(approval, aIdx) in (selectedTicket?.approvals || [])" :key="approval.id || aIdx">
                                <div class="relative flex items-start space-x-3">
                                    <!-- Step Circle -->
                                    <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                         :class="getTimelineApprovalCircleClass(approval, selectedTicket)">
                                        <template x-if="approval.status === 'approved'">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        </template>
                                        <template x-if="approval.status === 'rejected'">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </template>
                                        <template x-if="approval.status === 'pending'">
                                            <span x-text="isCurrentApprovalStep(approval, selectedTicket) ? '⏳' : (approval.step_number || (aIdx + 2))"></span>
                                        </template>
                                    </div>

                                    <!-- Step Content -->
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div class="flex items-center justify-between gap-1">
                                            <h4 class="text-xs font-bold text-slate-800 truncate" x-text="formatApprovalStepTitle(approval)"></h4>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                                  :class="getTimelineApprovalBadgeClass(approval, selectedTicket)"
                                                  x-text="getTimelineApprovalBadgeText(approval, selectedTicket)"></span>
                                        </div>

                                        <!-- Approver details / Actioned at -->
                                        <p class="text-[11px] text-slate-500 mt-0.5" x-text="getTimelineApprovalDetailText(approval, selectedTicket)"></p>

                                        <!-- Optional Approver Comments/Reason -->
                                        <template x-if="approval.comments">
                                            <div class="mt-1 text-[11px] rounded-lg px-2.5 py-1.5 border leading-relaxed"
                                                 :class="approval.status === 'rejected' ? 'bg-rose-50/80 border-rose-200 text-rose-800' : 'bg-slate-50 border-slate-200/80 text-slate-600'">
                                                <span class="font-medium text-slate-400 select-none">“</span><span x-text="approval.comments"></span><span class="font-medium text-slate-400 select-none">”</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Fulfillment Step -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                     :class="getTimelineFulfillmentCircleClass(selectedTicket)">
                                    <template x-if="['pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status)">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </template>
                                    <template x-if="['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>—</span>
                                    </template>
                                    <template x-if="!['pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed', 'rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>🛠</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'ការចាត់តាំង & ដោះស្រាយ' : 'IT Fulfillment'">IT Fulfillment</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                              :class="getTimelineFulfillmentBadgeClass(selectedTicket)"
                                              x-text="getTimelineFulfillmentBadgeText(selectedTicket)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5" x-text="getTimelineFulfillmentDetailText(selectedTicket)"></p>
                                </div>
                            </div>

                            <!-- Resolution & Closure Step -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                     :class="getTimelineResolutionCircleClass(selectedTicket)">
                                    <template x-if="selectedTicket?.is_closed">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'pending_confirmation'">
                                        <span>✨</span>
                                    </template>
                                    <template x-if="['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>✕</span>
                                    </template>
                                    <template x-if="!selectedTicket?.is_closed && selectedTicket?.status !== 'pending_confirmation' && !['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>✔</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'ការដោះស្រាយ & បិទបញ្ចប់' : 'Resolution & Signoff'">Resolution & Signoff</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                              :class="getTimelineResolutionBadgeClass(selectedTicket)"
                                              x-text="getTimelineResolutionBadgeText(selectedTicket)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5" x-text="getTimelineResolutionDetailText(selectedTicket)"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Incident Stepper (Extensible Dynamic Vertical Timeline) -->
                    <template x-if="selectedTicket?.ticket_type !== 'service_request'">
                        <div class="relative pl-7 space-y-4 pt-1">
                            <!-- Vertical Connecting Track Line -->
                            <div class="absolute left-3.5 top-3.5 bottom-3.5 w-0.5 bg-slate-200"></div>

                            <!-- Step 1: Incident Reported -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'បានរាយការណ៍បញ្ហា' : 'Incident Reported'">Incident Reported</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="locale === 'km' ? 'បានទទួល' : 'Reported'">Reported</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <span x-text="selectedTicket?.requester_name || (locale === 'km' ? 'អ្នកស្នើ' : 'Requester')"></span>
                                        <span class="text-slate-300 mx-1">•</span>
                                        <span class="font-mono text-[10px]" x-text="selectedTicket?.created_at || selectedTicket?.full_created_timestamp"></span>
                                    </p>
                                </div>
                            </div>

                            <!-- Step 2: Help Desk Triage & Assignment -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                     :class="getIncidentTriageCircleClass(selectedTicket)">
                                    <template x-if="selectedTicket?.status !== 'submitted' && !['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'submitted'">
                                        <span>📥</span>
                                    </template>
                                    <template x-if="['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>—</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'ការពិនិត្យបឋម & ចាត់តាំង' : 'Triage & Assignment'">Triage & Assignment</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                              :class="getIncidentTriageBadgeClass(selectedTicket)"
                                              x-text="getIncidentTriageBadgeText(selectedTicket)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5" x-text="getIncidentTriageDetailText(selectedTicket)"></p>
                                </div>
                            </div>

                            <!-- Step 3: Technical Troubleshooting -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                     :class="getIncidentTroubleshootingCircleClass(selectedTicket)">
                                    <template x-if="['pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status)">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </template>
                                    <template x-if="['in_progress', 'assigned'].includes(selectedTicket?.status)">
                                        <span>🛠</span>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'blocked'">
                                        <span>🛑</span>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'clarifying'">
                                        <span>💬</span>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'submitted' || ['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>🛠</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'ការចុះដោះស្រាយបច្ចេកទេស' : 'Technical Troubleshooting'">Technical Troubleshooting</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                              :class="getIncidentTroubleshootingBadgeClass(selectedTicket)"
                                              x-text="getIncidentTroubleshootingBadgeText(selectedTicket)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5" x-text="getIncidentTroubleshootingDetailText(selectedTicket)"></p>
                                </div>
                            </div>

                            <!-- Step 4: Resolution & Confirmation -->
                            <div class="relative flex items-start space-x-3">
                                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs shrink-0"
                                     :class="getTimelineResolutionCircleClass(selectedTicket)">
                                    <template x-if="selectedTicket?.is_closed">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </template>
                                    <template x-if="selectedTicket?.status === 'pending_confirmation'">
                                        <span>✨</span>
                                    </template>
                                    <template x-if="['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>✕</span>
                                    </template>
                                    <template x-if="!selectedTicket?.is_closed && selectedTicket?.status !== 'pending_confirmation' && !['rejected', 'cancelled'].includes(selectedTicket?.status)">
                                        <span>✔</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'ការដោះស្រាយចប់សព្វគ្រប់' : 'Resolved & Closed'">Resolved & Closed</h4>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                              :class="getTimelineResolutionBadgeClass(selectedTicket)"
                                              x-text="getTimelineResolutionBadgeText(selectedTicket)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5" x-text="getTimelineResolutionDetailText(selectedTicket)"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Stage Explanation Banner -->
                    <div :class="getStatusBannerClass(selectedTicket?.status)" class="p-3 rounded-xl text-xs flex items-center justify-between shadow-xs">
                        <div class="space-y-0.5">
                            <div class="font-bold text-xs" x-text="selectedTicket?.ticket_type === 'service_request' ? getCurrentStageTitle(selectedTicket) : getIncidentStageTitle(selectedTicket)"></div>
                            <div class="text-[11px] opacity-90 leading-tight" x-text="selectedTicket?.ticket_type === 'service_request' ? getCurrentStageDetail(selectedTicket) : getIncidentStageDetail(selectedTicket)"></div>
                        </div>
                        <span class="text-xl shrink-0 ml-2" x-text="selectedTicket?.ticket_type === 'service_request' ? getCurrentStageEmoji(selectedTicket) : getIncidentStageEmoji(selectedTicket)"></span>
                    </div>
                </div>

                <!-- Ticket Specifications Grid -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2.5 text-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2" x-text="t('specifications')">Specifications</h3>
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500" x-text="t('category')">Category</span>
                        <span class="font-semibold text-slate-800" x-text="(locale === 'km' && selectedTicket?.category_name_km) ? selectedTicket.category_name_km : (selectedTicket?.category_name || 'IT Support')"></span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500" x-text="t('branch')">Office / Branch</span>
                        <span class="font-semibold text-slate-800" x-text="selectedTicket?.branch_name || 'Head Office'"></span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500" x-text="locale === 'km' ? 'អ្នកស្នើ' : 'Requester'">Requester</span>
                        <span class="font-semibold text-slate-800" x-text="selectedTicket?.requester_name || (locale === 'km' ? 'អ្នក' : 'You')"></span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                        <span class="text-slate-500" x-text="t('created_at')">Submitted At</span>
                        <span class="font-mono text-[11px] text-slate-700" x-text="selectedTicket?.created_at || selectedTicket?.full_created_timestamp"></span>
                    </div>
                </div>

                <!-- Quick Return Button -->
                <button type="button" @click="showTicketInfo = false"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white rounded-xl font-bold text-xs shadow-xs text-center transition flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span x-text="t('back_to_chat')">Back to Chat Conversation</span>
                </button>
            </div>
        </div>
    </div>
