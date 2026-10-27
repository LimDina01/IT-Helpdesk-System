        <!-- ========================================================= -->
        <!-- VIEW 2: CATEGORY SELECTION (Matching Image 2)             -->
        <!-- ========================================================= -->
        <div x-show="currentView === 'category_select'"
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
                    <h2 class="text-sm font-extrabold text-slate-800" x-text="formTicketType === 'service_request' ? t('new_service_request') : t('report_issue')"></h2>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="t('requester_portal')">mini app</p>
                </div>
                <div class="w-12"></div>
            </header>

            <!-- Search Input (Matching Image 2) -->
            <div class="p-4 bg-white border-b border-slate-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" x-model="categorySearchQuery"
                           :placeholder="t('search_categories')"
                           class="tma-input w-full pl-9 pr-4 py-2.5 bg-slate-100 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                </div>
            </div>

            <!-- Category Cards List (Matching Image 2) -->
            <div class="flex-1 p-4 space-y-2.5 overflow-y-auto">
                <template x-for="cat in filteredCategoryList" :key="cat.id">
                    <div @click="selectCategory(cat)"
                         class="bg-white border border-slate-200/80 hover:border-blue-400 rounded-2xl p-3.5 shadow-xs flex items-center space-x-3.5 cursor-pointer active:scale-[0.97] active:bg-slate-50 transition-all duration-150 ease-out">
                        
                        <!-- Colored Square Icon matching Image 2 -->
                        <div :class="getCategoryIconBg(cat)"
                             class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-xs">
                            <!-- Account & Access / User -->
                            <template x-if="cat.name.toLowerCase().includes('account') || cat.name.toLowerCase().includes('access')">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </template>
                            <!-- Hardware & Equipment / Laptop -->
                            <template x-if="cat.name.toLowerCase().includes('hardware') || cat.name.toLowerCase().includes('equipment')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg>
                            </template>
                            <!-- Software & Application / Grid -->
                            <template x-if="cat.name.toLowerCase().includes('software') || cat.name.toLowerCase().includes('application')">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/></svg>
                            </template>
                            <!-- Email & Collaboration / Envelope -->
                            <template x-if="cat.name.toLowerCase().includes('email') || cat.name.toLowerCase().includes('collaboration')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            </template>
                            <!-- Network & Connectivity / Wi-Fi -->
                            <template x-if="cat.name.toLowerCase().includes('network') || cat.name.toLowerCase().includes('connectivity') || cat.name.toLowerCase().includes('internet')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                            </template>
                            <!-- Business Systems / Database -->
                            <template x-if="cat.name.toLowerCase().includes('business') || cat.name.toLowerCase().includes('erp')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </template>
                            <!-- Facility & Other / Building -->
                            <template x-if="cat.name.toLowerCase().includes('facility') || cat.name.toLowerCase().includes('cctv') || cat.name.toLowerCase().includes('other') || cat.name.toLowerCase().includes('phone')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                            </template>
                            <!-- POS & Cashier Hardware -->
                            <template x-if="cat.name.toLowerCase().includes('pos') || cat.name.toLowerCase().includes('cashier')">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                            </template>
                        </div>

                        <!-- Title & Subtitle Description -->
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-slate-800 leading-tight" x-text="getCategoryName(cat)"></div>
                            <div class="text-xs text-slate-500 mt-0.5 leading-snug line-clamp-1" x-text="getCategorySubtitle(cat)"></div>
                        </div>

                        <!-- Right Chevron -->
                        <div class="text-slate-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </template>

                <!-- Search Empty State -->
                <div x-show="filteredCategoryList.length === 0" class="text-center py-12 space-y-2">
                    <div class="text-3xl">🔍</div>
                    <div class="text-sm font-bold text-slate-700" x-text="t('no_matching_categories')">No matching categories found</div>
                    <p class="text-xs text-slate-400" x-text="t('try_different_keyword')">Try searching for a different keyword or check spelling.</p>
                </div>
            </div>
        </div>
