        <!-- ========================================================= -->
        <!-- VIEW 1: HOME DASHBOARD (Matching Image 1)                 -->
        <!-- ========================================================= -->
        <div x-show="currentView === 'home'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex-1 flex flex-col"
             style="padding-bottom: max(env(safe-area-inset-bottom, 28px), 24px) !important;">
            <!-- App Bar Header -->
            <header class="bg-white border-b border-slate-100 px-4 py-2.5 flex items-center justify-between sticky top-0 z-20 shadow-xs">
                <div class="flex items-center space-x-2 min-w-0 pr-2">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-xs shadow-xs shrink-0"
                         x-text="currentUser.name ? currentUser.name.charAt(0) : 'U'"></div>
                    <div class="text-left min-w-0">
                        <div class="text-xs font-bold text-slate-800 leading-tight truncate max-w-[120px]" x-text="currentUser.name"></div>
                        <div class="text-[10px] text-slate-500 font-medium flex items-center space-x-1 truncate max-w-[130px] mt-0.5">
                            <span class="font-mono text-slate-400 shrink-0" x-text="currentUser.employee_code"></span>
                            <span x-show="userDepartment" class="text-slate-300">•</span>
                            <span x-show="userDepartment" class="text-blue-600 font-semibold truncate" x-text="userDepartment"></span>
                        </div>
                    </div>
                </div>

                <div class="text-center shrink-0">
                    <h1 class="text-sm font-extrabold text-slate-900 leading-tight">CBVH Helpdesk</h1>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="t('requester_portal')">mini app</p>
                </div>

                <!-- Right Action Controls (Language Switcher & Logout) -->
                <div class="flex items-center space-x-1.5 shrink-0">
                    <!-- Language Toggle Pill -->
                    <button @click="toggleLocale()"
                            type="button"
                            title="Switch Language / ប្តូរភាសា"
                            class="inline-flex items-center bg-slate-100 p-0.5 rounded-full border border-slate-200/90 active:scale-95 transition-all duration-150">
                        <span :class="locale === 'en' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                              class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">EN</span>
                        <span :class="locale === 'km' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                              class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">ខ្មែរ</span>
                    </button>

                    <button @click="logout()" title="Sign Out" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 active:scale-95 rounded-lg transition-all duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </div>
            </header>

            <!-- Luxury Gold Silk Banner (Matching Image 1) -->
            <div class="px-4 pt-3.5">
                <div class="w-full rounded-3xl overflow-hidden shadow-xs border border-amber-200/60 bg-[#FAF4E3]">
                    <img src="/images/cbvh-banner.png" alt="CBVH Cheang Bak Van Hong Jewelry" class="w-full h-auto block select-none pointer-events-none" />
                </div>
            </div>

            <!-- Personal Greeting & Department Pill -->
            <div class="px-5 pt-4 pb-1 flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                        <span x-text="t('welcome_back')">Hello,</span> <span x-text="greetingName"></span>
                    </h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5" x-text="locale === 'km' ? 'តើពួកយើងអាចជួយអ្វីដល់អ្នកនៅថ្ងៃនេះ?' : 'How can we help you today?'">How can we help you today?</p>
                </div>
                <template x-if="userDepartment">
                    <div class="bg-blue-50/90 border border-blue-200/80 rounded-2xl px-3 py-1.5 text-right shrink-0 mt-0.5 shadow-2xs select-none"
                         :title="userDepartment"
                         x-data="{
                             isSliding: false,
                             slideDist: 0,
                             slideDuration: 5,
                             checkMarquee() {
                                 this.$nextTick(() => {
                                     const text = this.$refs.deptText;
                                     const box = this.$refs.deptBox;
                                     if (!text || !box) return;
                                     const overflow = text.scrollWidth - box.clientWidth;
                                     if (overflow > 4) {
                                         this.slideDist = overflow + 2;
                                         this.slideDuration = Math.max(4, Math.min(9, 3 + overflow / 20));
                                         this.isSliding = true;
                                     } else {
                                         this.slideDist = 0;
                                         this.isSliding = false;
                                     }
                                 });
                             }
                         }"
                         x-init="checkMarquee(); $watch('userDepartment', () => checkMarquee()); $watch('currentView', (v) => { if (v === 'home') checkMarquee(); }); if (document.fonts?.ready) { document.fonts.ready.then(() => checkMarquee()); } setTimeout(() => checkMarquee(), 350);"
                         @resize.window.debounce.150ms="checkMarquee()">
                        <div class="text-[9px] font-bold uppercase tracking-wider text-blue-600" x-text="locale === 'km' ? 'ផ្នែក' : 'Department'">Department</div>
                        <div x-ref="deptBox"
                             class="overflow-hidden w-[120px] max-w-[120px]"
                             :class="isSliding ? 'text-left' : 'text-right'">
                            <span x-ref="deptText"
                                  class="text-xs font-bold text-slate-800 whitespace-nowrap inline-block"
                                  :class="{ 'animate-dept-slide': isSliding }"
                                  :style="isSliding ? `--dept-slide-dist: -${slideDist}px; --dept-slide-duration: ${slideDuration}s;` : ''"
                                  x-text="userDepartment"></span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Two Hero Action Cards (Matching Image 1) -->
            <div class="px-4 py-2.5 grid grid-cols-2 gap-3.5">
                <!-- 🔴 Report an Incident -->
                <div @click="startIncidentFlow()"
                     class="bg-[#EF4444] text-white rounded-3xl p-4 flex flex-col items-center text-center shadow-lg shadow-red-500/20 active:scale-[0.96] active:shadow-md transition-all duration-150 ease-out cursor-pointer select-none">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center mb-2.5">
                        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="font-extrabold text-sm leading-snug" x-text="t('report_issue')">Incident</div>
                    <p class="text-[10px] text-white/85 mt-1 leading-snug line-clamp-2" x-text="t('report_issue_desc')">Something is broken or malfunctioning</p>
                    <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center mt-3 shadow-xs">
                        <svg class="w-4 h-4 text-[#EF4444]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </div>
                </div>

                <!-- 🔵 Service Request -->
                <div @click="startServiceRequestFlow()"
                     class="bg-[#2563EB] text-white rounded-3xl p-4 flex flex-col items-center text-center shadow-lg shadow-blue-500/20 active:scale-[0.96] active:shadow-md transition-all duration-150 ease-out cursor-pointer select-none">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center mb-2.5">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <div class="font-extrabold text-sm leading-snug" x-text="t('new_service_request')">Service Request</div>
                    <p class="text-[10px] text-white/85 mt-1 leading-snug line-clamp-2" x-text="t('new_service_request_desc')">Hardware, software, access or setup</p>
                    <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center mt-3 shadow-xs">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </div>
                </div>
            </div>

            <!-- PENDING APPROVALS BUTTON (Always visible for HOD & IT HOD) -->
            <div class="px-4 py-1.5" x-show="isHod">
                <div @click="openApprovalsList()"
                     class="bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-2xl p-3.5 shadow-sm shadow-amber-500/20 flex items-center justify-between cursor-pointer active:scale-[0.97] active:shadow-xs transition-all duration-150 ease-out">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-lg shrink-0">
                            ⏳
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-xs flex items-center space-x-2">
                                <span x-text="t('tab_approvals')">Pending Approvals</span>
                                <span class="bg-white text-amber-800 text-[10px] font-black px-1.5 py-0.2 rounded-full" x-text="pendingApprovalsCount"></span>
                            </div>
                            <p class="text-[10px] text-amber-100 truncate mt-0.5">
                                <span x-show="pendingApprovalsCount > 0"><span x-text="pendingApprovalsCount"></span> <span x-text="locale === 'km' ? 'សំណើរង់ចាំការពិនិត្យ' : 'request(s) awaiting your review'"></span></span>
                                <span x-show="pendingApprovalsCount === 0" x-text="locale === 'km' ? '0 រង់ចាំ • បានពិនិត្យគ្រប់សំណើក្នុងផ្នែក' : '0 pending • All departmental requests reviewed'">0 pending &bull; All departmental requests reviewed</span>
                            </p>
                        </div>
                    </div>
                    <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center shrink-0 ml-2">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </div>
            </div>

            <!-- My Requests Section Header (Matching Image 1) -->
            <div class="px-4 pt-3 pb-1 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 tracking-tight" x-text="t('my_active_requests')">My Requests</h3>
                    <p class="text-[10px] text-slate-400" x-text="locale === 'km' ? 'ចុចលើស្ថានភាពដើម្បីមើល ឬដាក់សំណើថ្មី' : 'Tap status to view or submit new tickets'">Tap status to view or submit new tickets</p>
                </div>
                <button @click="openTicketsListTab('all')" class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center space-x-0.5 active:scale-95 active:bg-blue-200 transition-all duration-150 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded-lg">
                    <span x-text="t('view_all')">View All</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- Three Stat Cards (Open = Blue, In Progress = Amber/Orange, Resolved = Green) -->
            <div class="px-4 py-1.5 grid grid-cols-3 gap-2.5">
                <!-- 1. Open (BLUE) -->
                <div @click="openTicketsListTab('open')"
                     class="bg-blue-50/70 border border-blue-200 hover:border-blue-400 rounded-2xl p-2.5 cursor-pointer active:scale-[0.96] active:bg-blue-100 transition-all duration-150 ease-out flex items-center space-x-2 shadow-2xs">
                    <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>
                    </div>
                    <div>
                        <div class="text-base font-bold text-slate-800 leading-tight" x-text="openCount">0</div>
                        <div class="text-[10px] font-bold text-blue-600" x-text="t('metric_open')">Open</div>
                    </div>
                </div>

                <!-- 2. In Progress (YELLOW / ORANGISH) -->
                <div @click="openTicketsListTab('in_progress')"
                     class="bg-amber-50/80 border border-amber-200 hover:border-amber-400 rounded-2xl p-2.5 cursor-pointer active:scale-[0.96] active:bg-amber-100 transition-all duration-150 ease-out flex items-center space-x-2 shadow-2xs">
                    <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 0l2.5 2.5"/></svg>
                    </div>
                    <div>
                        <div class="text-base font-bold text-slate-800 leading-tight" x-text="inProgressCount">0</div>
                        <div class="text-[10px] font-bold text-amber-700" x-text="t('metric_in_progress')">In Progress</div>
                    </div>
                </div>

                <!-- 3. Resolved (EMERALD GREEN) -->
                <div @click="openTicketsListTab('resolved')"
                     class="bg-emerald-50/70 border border-emerald-100 hover:border-emerald-300 rounded-2xl p-2.5 cursor-pointer active:scale-[0.96] active:bg-emerald-100 transition-all duration-150 ease-out flex items-center space-x-2 shadow-2xs">
                    <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/></svg>
                    </div>
                    <div>
                        <div class="text-base font-bold text-slate-800 leading-tight" x-text="resolvedCount">0</div>
                        <div class="text-[10px] font-bold text-emerald-600" x-text="t('metric_resolved')">Resolved</div>
                    </div>
                </div>
            </div>
        </div>
