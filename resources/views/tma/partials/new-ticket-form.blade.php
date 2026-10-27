        <!-- ========================================================= -->
        <!-- VIEW 3: NEW TICKET FORM (Matching Image 3)                -->
        <!-- ========================================================= -->
        <div x-show="currentView === 'new_ticket_form'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex-1 flex flex-col pb-8">
            <!-- Header Bar with Back Button -->
            <header class="bg-white border-b border-slate-200/80 px-4 py-3 sticky top-0 z-20 shadow-xs flex items-center justify-between">
                <button @click="currentView = 'category_select'; updateBackButton()" class="flex items-center space-x-1 text-blue-600 font-bold text-xs hover:text-blue-700 active:scale-95 active:opacity-75 transition-all duration-150 py-1 px-1.5 rounded-lg -ml-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span x-text="t('back')">Back</span>
                </button>
                <div class="text-center">
                    <h2 class="text-sm font-extrabold text-slate-800" x-text="formTicketType === 'service_request' ? t('new_service_request') : t('report_issue')"></h2>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="t('requester_portal')">mini app</p>
                </div>
                <div class="w-12"></div>
            </header>

            <!-- Form Body -->
            <form @submit.prevent="submitNewTicket()" class="flex-1 p-5 space-y-4 overflow-y-auto"
                  @touchstart.passive="handleTimelineTouchStart($event)"
                  @touchmove.passive="handleTimelineTouchMove($event)">
                
                <!-- Category Display Card -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <span x-text="t('category')">Category</span> <span class="text-rose-500">*</span>
                    </label>
                    <div @click="currentView = 'category_select'; updateBackButton()"
                         class="bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 flex items-center justify-between cursor-pointer hover:border-blue-400 active:scale-[0.98] active:bg-slate-50 shadow-xs transition-all duration-150 ease-out">
                        <span class="text-xs font-bold text-slate-800" x-text="getCategoryName(selectedParentCategory) || t('select_category')"></span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7-7 7-7"/></svg>
                    </div>
                </div>

                <!-- Subcategory (Auto-set to "Other" / optional as instructed) -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700">
                            <span x-text="locale === 'km' ? 'ប្រភេទទ្វេដង / លម្អិត' : 'Subcategory'">Subcategory</span> <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] text-slate-400 font-medium" x-text="locale === 'km' ? 'ស្វ័យប្រវត្តិ៖ ផ្សេងៗ (មិនបង្ខំ)' : 'Auto-set: Other (Optional)'">Auto-set: Other (Optional)</span>
                    </div>
                    <select x-model="formSubCatId"
                            class="tma-select w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-slate-900 font-medium shadow-xs"
                            style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                        <template x-for="sub in filteredSubcategories" :key="sub.id">
                            <option :value="sub.id" x-text="sub.name" style="color: #0f172a !important; background-color: #ffffff !important;"></option>
                        </template>
                        <option x-show="filteredSubcategories.length === 0" :value="formParentCatId" style="color: #0f172a !important; background-color: #ffffff !important;">Other / General</option>
                    </select>
                </div>

                <!-- Requester Department & Branch (Auto-detected from employee profile) -->
                <div class="flex items-center space-x-2.5 text-xs bg-slate-50 border border-slate-200/90 rounded-2xl px-3.5 py-2 text-slate-700">
                    <span class="text-base shrink-0">🏢</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider" x-text="locale === 'km' ? 'ផ្នែក & សាខា' : 'Department & Branch'">Department & Branch</div>
                        <div class="font-bold text-slate-800 truncate text-xs">
                            <span x-text="userDepartment || 'Department'"></span>
                            <span class="text-slate-300 font-normal"> &bull; </span>
                            <span class="text-blue-700" x-text="userBranch || 'Branch'"></span>
                        </div>
                    </div>
                </div>

                <!-- Title * -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <span x-text="t('subject')">Title</span> <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" x-model="formSubject" required
                           :placeholder="t('subject_placeholder')"
                           class="tma-input w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-slate-900 placeholder:text-slate-400 font-medium shadow-xs"
                           style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                </div>

                <!-- Description * -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <span x-text="t('description')">Description</span> <span class="text-rose-500">*</span>
                    </label>
                    <textarea x-model="formDescription" required rows="3"
                              :placeholder="t('description_placeholder')"
                              class="tma-textarea w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-slate-900 placeholder:text-slate-400 font-medium shadow-xs"
                              style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;"></textarea>
                </div>

                <!-- Attachment (optional) - Dashed upload box matching Image 3 -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <span x-text="t('attachments')">Attachment</span> <span class="text-slate-400 font-normal" x-text="locale === 'km' ? '(មិនបង្ខំ)' : '(optional)'">(optional)</span>
                    </label>
                    <div @click="$refs.ticketUploadInput.click()"
                         class="border-2 border-dashed border-slate-300 hover:border-blue-400 bg-slate-50/70 hover:bg-blue-50/30 rounded-2xl p-4 text-center cursor-pointer active:scale-[0.98] transition-all duration-150 ease-out flex flex-col items-center justify-center space-y-1.5 shadow-xs">
                        <div class="w-10 h-10 rounded-full bg-white shadow-xs flex items-center justify-center text-slate-600 border border-slate-200">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.373L8.559 18.32a1.5 1.5 0 01-2.122-2.122l8.83-8.83"/>
                            </svg>
                        </div>
                        <div class="text-xs font-bold text-slate-700" x-text="locale === 'km' ? 'ចុចដើម្បីភ្ជាប់ឯកសារ' : 'Tap to upload file'">Tap to upload file</div>
                        <div class="text-[11px] text-slate-400 font-medium" x-text="locale === 'km' ? '(រូបភាព, PDF, ឯកសារ)' : '(Image, PDF, Document)'">(Image, PDF, Document)</div>
                    </div>
                    <input type="file" x-ref="ticketUploadInput" multiple @change="handleNewTicketFiles" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" class="hidden">

                    <!-- Selected Files Preview Chips -->
                    <div x-show="formAttachments.length > 0" class="mt-2.5 space-y-1.5">
                        <template x-for="(file, idx) in formAttachments" :key="idx">
                            <div class="bg-white border border-slate-200 rounded-xl px-3 py-2 flex items-center justify-between text-xs shadow-xs">
                                <div class="flex items-center space-x-2 truncate">
                                    <span class="text-blue-600 font-bold">📎</span>
                                    <span class="font-medium text-slate-800 truncate" x-text="file.name"></span>
                                    <span class="text-[10px] text-slate-400" x-text="formatFileSize(file.size)"></span>
                                </div>
                                <button type="button" @click="removeTicketFile(idx)" class="text-slate-400 hover:text-rose-600 p-1 font-bold text-sm">
                                    &times;
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="formError" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-xl font-medium" x-text="formError"></div>

                <!-- Gold / Amber Action Button matching Image 3 -->
                <div class="pt-2">
                    <button type="submit" :disabled="submittingTicket"
                            class="w-full py-3.5 bg-gradient-to-r from-[#D4A044] to-[#C29035] hover:from-[#C29035] hover:to-[#B07F27] active:scale-[0.97] active:shadow-sm text-white rounded-2xl font-bold text-base shadow-md shadow-amber-600/20 transition-all duration-150 ease-out flex items-center justify-center space-x-2 disabled:opacity-50">
                        <span x-show="!submittingTicket" x-text="formTicketType === 'service_request' ? (locale === 'km' ? 'ដាក់ស្នើសុំសេវាកម្ម' : 'Submit Service Request') : (locale === 'km' ? 'ដាក់ស្នើរាយការណ៍បញ្ហា' : 'Submit Incident Report')"></span>
                        <span x-show="submittingTicket" x-text="t('submitting')">Submitting Request...</span>
                        <svg x-show="!submittingTicket" class="w-5 h-5 ml-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </form>
        </div>
