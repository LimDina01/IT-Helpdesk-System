    <!-- DEDICATED TICKET DETAILS & WORKFLOW PAGE -->
    <div x-show="showTicketInfo"
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
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0"
                             x-text="(selectedTicket?.technician_name || 'IT').charAt(0).toUpperCase()"></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-slate-900 truncate" x-text="selectedTicket?.technician_name || (locale === 'km' ? 'ក្រុមការងារ IT Help Desk' : 'IT Support Desk')"></div>
                            <div class="text-xs text-slate-500 truncate" x-text="selectedTicket?.technician_title || (locale === 'km' ? 'អ្នកឯកទេស IT Help Desk' : 'IT Technician Specialist')"></div>
                            <div class="flex items-center space-x-1.5 mt-1 text-[11px] text-emerald-600 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span x-text="locale === 'km' ? 'កំពុងទទួលបន្ទុកដោះស្រាយ' : 'Active & Handling Ticket'">Active & Handling Ticket</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visual Workflow Progression Card ("Peace of Mind") -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400" x-text="t('workflow_progress')">Workflow Progression</h3>
                        <span class="text-[10px] font-mono text-slate-400" x-text="selectedTicket?.created_at || selectedTicket?.full_created_timestamp"></span>
                    </div>

                    <!-- Service Request Stepper -->
                    <template x-if="selectedTicket?.ticket_type === 'service_request'">
                        <div class="space-y-3">
                            <div class="grid grid-cols-5 gap-1 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold">✔</div>
                                    <span class="text-[9px] font-bold text-emerald-700 mt-1" x-text="locale === 'km' ? 'បានដាក់ស្នើ' : 'Submitted'">Submitted</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="getServiceStepClass(selectedTicket, 1)" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="getServiceStepIcon(selectedTicket, 1)"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'ប្រធានផ្នែក' : 'Dept HOD'">Dept HOD</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="getServiceStepClass(selectedTicket, 2)" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="getServiceStepIcon(selectedTicket, 2)"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'ប្រធាន IT' : 'IT Manager'">IT Manager</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="getFulfillmentStepClass(selectedTicket)" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="getFulfillmentStepIcon(selectedTicket)"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'ចាត់តាំងដោះស្រាយ' : 'Fulfillment'">Fulfillment</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="selectedTicket?.is_closed ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="selectedTicket?.is_closed ? '✔' : '5'"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'រួចរាល់' : 'Done'">Done</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Incident Stepper -->
                    <template x-if="selectedTicket?.ticket_type !== 'service_request'">
                        <div class="space-y-3">
                            <div class="grid grid-cols-5 gap-1 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold">✔</div>
                                    <span class="text-[9px] font-bold text-emerald-700 mt-1" x-text="locale === 'km' ? 'បានរាយការណ៍' : 'Reported'">Reported</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="selectedTicket?.status !== 'submitted' ? 'bg-emerald-500 text-white' : 'bg-blue-600 text-white ring-2 ring-blue-300'" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="selectedTicket?.status !== 'submitted' ? '✔' : '2'"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'ពិនិត្យបឋម' : 'Triage'">Triage</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="['in_progress', 'blocked', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? 'bg-emerald-500 text-white' : (selectedTicket?.status === 'assigned' ? 'bg-blue-600 text-white ring-2 ring-blue-300' : 'bg-slate-200 text-slate-400')" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="['in_progress', 'blocked', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? '✔' : '3'"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'កំពុងដោះស្រាយ' : 'Working'">Working</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="['resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? 'bg-emerald-500 text-white' : (selectedTicket?.status === 'pending_confirmation' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300 animate-pulse' : 'bg-slate-200 text-slate-400')" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="['resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? '✔' : '4'"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'បានដោះស្រាយ' : 'Resolved'">Resolved</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div :class="selectedTicket?.is_closed ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
                                        <span x-text="selectedTicket?.is_closed ? '✔' : '5'"></span>
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-600 mt-1" x-text="locale === 'km' ? 'បានបិទ' : 'Closed'">Closed</span>
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
                        <span class="font-semibold text-slate-800" x-text="selectedTicket?.category_name || 'IT Support'"></span>
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
