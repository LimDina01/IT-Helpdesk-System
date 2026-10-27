        <!-- ========================================================= -->
        <!-- VIEW 4: TICKETS LIST (All Requests with 3 Tabs)           -->
        <!-- ========================================================= -->
        <div x-show="currentView === 'tickets_list'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex-1 flex flex-col"
             style="padding-bottom: max(env(safe-area-inset-bottom, 28px), 24px) !important;">
            <!-- Header Bar with Back Button -->
            <header class="bg-white border-b border-slate-200/80 px-4 py-3 sticky top-0 z-20 shadow-xs flex items-center justify-between">
                <button @click="currentView = 'home'; updateBackButton()" class="flex items-center space-x-1 text-blue-600 font-bold text-xs hover:text-blue-700 active:scale-95 active:opacity-75 transition-all duration-150 py-1 px-1.5 rounded-lg -ml-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span x-text="t('back')">Back</span>
                </button>
                <div class="text-center">
                    <h2 class="text-sm font-extrabold text-slate-800"
                        x-text="currentTab === 'approvals' ? t('tab_approvals') : (currentTab === 'open' ? (locale === 'km' ? 'សំណើបើកថ្មី' : 'Open Requests') : (currentTab === 'in_progress' ? (locale === 'km' ? 'សំណើកំពុងដំណើរការ' : 'In Progress') : (currentTab === 'resolved' ? (locale === 'km' ? 'សំណើបានដោះស្រាយ' : 'Resolved Requests') : (locale === 'km' ? 'សំណើទាំងអស់' : 'All Requests'))))"></h2>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="t('requester_portal')">mini app</p>
                </div>
                <!-- Right spacer to balance the back button and keep header title centered -->
                <div class="w-12"></div>
            </header>

            <!-- Tab Ticket Lists -->
            <main class="flex-1 flex flex-col">
                <!-- APPROVALS CATEGORY (Dedicated view for HOD) -->
                <div x-show="currentTab === 'approvals'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1.5"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="flex-1 px-4 py-2 space-y-4">
                    <!-- Section 1: Awaiting My Review -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between px-0.5">
                            <div class="flex items-center space-x-1.5">
                                <span class="text-xs font-bold text-slate-800" x-text="t('awaiting_my_review')">Awaiting My Review</span>
                                <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.2 rounded-full" x-text="approvalTickets.length"></span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium" x-text="locale === 'km' ? 'តម្រូវឱ្យអ្នកពិនិត្យ' : 'Requires your action'">Requires your action</span>
                        </div>

                        <!-- Empty State if no pending approvals -->
                        <div x-show="approvalTickets.length === 0 && !loadingTickets" class="tg-theme-card rounded-2xl border border-slate-200/70 p-6 text-center space-y-2 bg-white shadow-xs">
                            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-lg">
                                ✔
                            </div>
                            <h3 class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'មិនមានសំណើរង់ចាំការអនុម័តទេ' : 'No Pending Approvals'">No Pending Approvals</h3>
                            <p class="text-[11px] text-slate-500 max-w-xs mx-auto" x-text="t('no_pending_approvals')">
                                You're all caught up! There are currently no requests awaiting your approval stage.
                            </p>
                        </div>

                        <!-- Pending Tickets List -->
                        <template x-for="t in approvalTickets" :key="'pending-' + t.id">
                            @include('tma.partials.ticket-card')
                        </template>
                    </div>

                    <!-- Section 2: Approved Requests & In-Flight Tracking -->
                    <div class="space-y-2.5 pt-3 border-t border-slate-200/80">
                        <div class="flex items-center justify-between px-0.5">
                            <div class="flex items-center space-x-1.5">
                                <span class="text-xs font-bold text-slate-800" x-text="locale === 'km' ? 'សំណើដែលខ្ញុំបានសម្រេច' : 'Actioned by Me'">Actioned by Me</span>
                                <span class="bg-slate-100 text-slate-800 text-[10px] font-bold px-1.5 py-0.2 rounded-full" x-text="approvedTickets.length"></span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium" x-text="locale === 'km' ? 'ប្រវត្តិនៃការសម្រេច' : 'Decisions history'">Decisions history</span>
                        </div>

                        <!-- Empty State if no actioned tickets yet -->
                        <div x-show="approvedTickets.length === 0 && !loadingTickets" class="tg-theme-card rounded-2xl border border-dashed border-slate-200/90 p-5 text-center space-y-1 bg-white">
                            <p class="text-xs font-semibold text-slate-600" x-text="t('no_actioned_approvals')">No Actioned Requests Yet</p>
                            <p class="text-[10px] text-slate-400" x-text="locale === 'km' ? 'សំណើដែលអ្នកបានអនុម័ត ឬបដិសេធ នឹងបង្ហាញនៅទីនេះ។' : 'Requests you approve or decline will appear here so you can track them.'">Requests you approve or decline will appear here so you can track them.</p>
                        </div>

                        <!-- Approved Tickets List (showing where they currently stand) -->
                        <template x-for="t in approvedTickets" :key="'approved-' + t.id">
                            @include('tma.partials.ticket-card')
                        </template>
                    </div>
                </div>

                <!-- STANDARD TICKETS LIST (Open, In Progress, Resolved, View All) -->
                <div x-show="currentTab !== 'approvals'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1.5"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="flex-1 px-4 py-2 space-y-3">
                    <!-- Empty State -->
                    <div x-show="currentList.length === 0 && !loadingTickets"
                         class="tg-theme-card rounded-2xl border border-slate-200/60 p-8 text-center space-y-3 mt-4"
                         style="background-color: #ffffff !important;">
                        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-xl">
                            <span x-show="currentTab === 'open'">📋</span>
                            <span x-show="currentTab === 'in_progress'">⏳</span>
                            <span x-show="currentTab === 'resolved'">📁</span>
                            <span x-show="currentTab === 'all'">📑</span>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-700"
                            x-text="currentTab === 'open' ? (locale === 'km' ? 'មិនមានសំណើបើកថ្មីទេ' : 'No Open Tickets') : (currentTab === 'in_progress' ? (locale === 'km' ? 'មិនមានសំណើកំពុងដោះស្រាយទេ' : 'No Tickets In Progress') : (currentTab === 'resolved' ? (locale === 'km' ? 'មិនមានសំណើដែលបានដោះស្រាយទេ' : 'No Resolved Tickets') : (locale === 'km' ? 'រកមិនឃើញសំណើទេ' : 'No Tickets Found')))"></h3>
                        <p class="text-xs text-slate-500 max-w-xs mx-auto"
                           x-text="currentTab === 'open' ? (locale === 'km' ? 'អ្នកមិនមានសំណើបើកថ្មីនៅពេលនេះទេ។' : 'You don\'t have any open tickets right now.') : (currentTab === 'in_progress' ? (locale === 'km' ? 'មិនមានសំណើកំពុងដោះស្រាយដោយ IT ទេ។' : 'No tickets are currently being worked on by IT.') : (currentTab === 'resolved' ? (locale === 'km' ? 'មិនទាន់មានសំណើដែលបានដោះស្រាយ ឬបិទនៅឡើយទេ។' : 'No resolved or closed tickets found.') : (locale === 'km' ? 'អ្នកមិនទាន់បានដាក់ស្នើសំណើណាមួយនៅឡើយទេ។' : 'You have not submitted any tickets yet.')))"></p>
                        <button x-show="currentTab === 'open' || currentTab === 'all'"
                                @click="startServiceRequestFlow()" class="text-xs text-blue-600 font-semibold underline"
                                x-text="t('submit_new_request')">
                            Submit a new request
                        </button>
                    </div>

                    <!-- Ticket Cards Loop -->
                    <template x-for="t in currentList" :key="t.id">
                        @include('tma.partials.ticket-card')
                    </template>
                </div>
            </main>
        </div>
