    <!-- WEB / DIRECT BROWSER LOGIN (When Telegram is unavailable, down, or accessed via normal web browser) -->
    <div x-show="!loading && authState === 'web_login_required'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex-1 flex flex-col justify-center p-5">
        <div class="tg-theme-card rounded-3xl shadow-xl border border-slate-200/80 p-6 space-y-6 relative">
            <!-- Language Switcher in Auth Card Top Right -->
            <div class="absolute top-4 right-4">
                <button @click="toggleLocale()"
                        type="button"
                        title="Switch Language / ប្តូរភាសា"
                        class="inline-flex items-center bg-slate-100 p-0.5 rounded-full border border-slate-200/90 active:scale-95 transition-all duration-150">
                    <span :class="locale === 'en' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">EN</span>
                    <span :class="locale === 'km' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">ខ្មែរ</span>
                </button>
            </div>

            <div class="text-center space-y-2">
                <div class="w-16 h-16 bg-blue-600 text-white rounded-2xl flex items-center justify-center mx-auto text-2xl font-black shadow-lg shadow-blue-500/30">
                    IT
                </div>
                <h1 class="text-xl font-extrabold text-slate-800" x-text="t('sign_in_title')">CBVH Help Desk</h1>
                <p class="text-xs text-slate-500" x-text="t('sign_in_subtitle')">Web Portal &mdash; Sign in with your Employee ID, Email, or Telegram username to submit tickets.</p>
            </div>

            <form @submit.prevent="submitWebLogin()" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1" x-text="locale === 'km' ? 'លេខកូដបុគ្គលិក អ៊ីមែល ឬគណនី @Telegram' : 'Employee ID, Email, or @Username'">Employee ID, Email, or @Username</label>
                    <input type="text" x-model="webLoginIdentifier" required placeholder="e.g. EMP-1001, email, or @username"
                           class="tma-input w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium bg-white text-slate-900"
                           style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                    <p class="text-[10px] text-slate-400 mt-1" x-text="locale === 'km' ? 'អាចប្រើប្រាស់លេខកូដបុគ្គលិក អ៊ីមែលក្រុមហ៊ុន ឬគណនី Telegram។' : 'Accepts Company Employee Code, Corporate Email, or Telegram username.'">Accepts Company Employee Code, Corporate Email, or Telegram username.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1" x-text="locale === 'km' ? 'ពាក្យសម្ងាត់' : 'Password'">Password</label>
                    <input type="password" x-model="webLoginPassword" required placeholder="Enter password (default: Password123!)"
                           class="tma-input w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium bg-white text-slate-900"
                           style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                </div>

                <div x-show="webLoginError" class="text-xs text-red-600 bg-red-50 border border-red-200 p-2.5 rounded-xl font-medium" x-text="webLoginError"></div>

                <button type="submit" :disabled="loggingIn"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white rounded-xl font-bold text-sm shadow-md shadow-blue-500/20 transition-all duration-150 disabled:opacity-50">
                    <span x-show="!loggingIn" x-text="t('sign_in')">Sign In to Help Desk</span>
                    <span x-show="loggingIn" x-text="t('signing_in')">Signing in...</span>
                </button>
            </form>

            <div class="border-t border-slate-200/70 pt-4 text-center space-y-1.5">
                <p class="text-[11px] text-slate-500" x-text="t('open_in_telegram_hint')">Using Telegram? Open in Telegram for automatic password-free sign-in.</p>
                <a href="https://t.me/cbvh_it_bot" target="_blank"
                   class="inline-flex items-center space-x-1 text-xs text-blue-600 font-bold hover:underline">
                    <span x-text="t('open_in_telegram')">Open in Telegram Bot</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- SUSPENDED ACCOUNT SCREEN -->
    <div x-show="!loading && authState === 'suspended'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex-1 flex flex-col justify-center p-5">
        <div class="tg-theme-card rounded-3xl shadow-xl border border-rose-200/80 p-6 space-y-6 relative text-center">
            <!-- Language Switcher in Card Top Right -->
            <div class="absolute top-4 right-4">
                <button @click="toggleLocale()"
                        type="button"
                        title="Switch Language / ប្តូរភាសា"
                        class="inline-flex items-center bg-slate-100 p-0.5 rounded-full border border-slate-200/90 active:scale-95 transition-all duration-150">
                    <span :class="locale === 'en' ? 'bg-rose-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">EN</span>
                    <span :class="locale === 'km' ? 'bg-rose-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">ខ្មែរ</span>
                </button>
            </div>

            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto text-3xl font-black shadow-lg shadow-rose-500/10 border border-rose-200">
                🚫
            </div>

            <div class="space-y-1.5">
                <h1 class="text-xl font-extrabold text-slate-800" x-text="locale === 'km' ? 'គណនីត្រូវបានផ្អាក' : 'Account Suspended'">Account Suspended</h1>
                <p class="text-xs text-rose-600 font-semibold leading-relaxed" x-text="suspendedMessage || (locale === 'km' ? 'គណនីរបស់អ្នកត្រូវបានផ្អាកដោយអ្នកគ្រប់គ្រង IT។' : 'Your account access has been suspended by an administrator.')"></p>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed" x-text="locale === 'km' ? 'ប្រសិនបើអ្នកគិតថាមានការភាន់ច្រឡំ ឬត្រូវការបើកដំណើរការឡើងវិញ សូមទាក់ទងមកកាន់ IT Help Desk ឬធនធានមនុស្ស (HR)។' : 'If you believe this is an error or require access restored, please contact the IT Help Desk or HR department.'"></p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-left text-xs space-y-1.5">
                <div class="flex items-center justify-between text-slate-600">
                    <span class="font-medium">Status:</span>
                    <span class="text-rose-600 font-bold uppercase tracking-wider text-[10px] bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">Suspended / Deactivated</span>
                </div>
                <template x-if="telegramUser && telegramUser.id">
                    <div class="flex items-center justify-between text-slate-600">
                        <span class="font-medium">Telegram ID:</span>
                        <span class="font-mono text-slate-800 font-semibold" x-text="telegramUser.id"></span>
                    </div>
                </template>
                <template x-if="telegramUser && telegramUser.username">
                    <div class="flex items-center justify-between text-slate-600">
                        <span class="font-medium">Username:</span>
                        <span class="font-mono text-slate-800 font-semibold" x-text="'@' + telegramUser.username"></span>
                    </div>
                </template>
            </div>

            <div class="pt-2">
                <button type="button" @click="checkAuth()"
                        class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] text-slate-700 rounded-xl font-bold text-xs transition">
                    <span x-text="locale === 'km' ? 'ពិនិត្យឡើងវិញ' : 'Check Status Again'">Check Status Again</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ONBOARDING & LINK REQUEST SCREEN (Option B: Requires Help Desk Approval) -->
    <div x-show="!loading && authState === 'unlinked'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex-1 flex flex-col justify-center p-5">
        <div class="tg-theme-card rounded-2xl shadow-sm border border-slate-200/60 p-6 space-y-6 relative">
            <!-- Language Switcher in Link Card Top Right -->
            <div class="absolute top-4 right-4">
                <button @click="toggleLocale()"
                        type="button"
                        title="Switch Language / ប្តូរភាសា"
                        class="inline-flex items-center bg-slate-100 p-0.5 rounded-full border border-slate-200/90 active:scale-95 transition-all duration-150">
                    <span :class="locale === 'en' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">EN</span>
                    <span :class="locale === 'km' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-500 font-medium'"
                          class="px-1.5 py-0.5 rounded-full text-[10px] transition-all">ខ្មែរ</span>
                </button>
            </div>

            <div class="text-center space-y-2">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold">
                    IT
                </div>
                <h1 class="text-xl font-bold" x-text="t('telegram_linking_title')">CBVH Help Desk</h1>
                <p class="text-xs text-slate-500" x-text="t('telegram_linking_subtitle')">Link your Telegram account to your employee record to submit and follow up on IT tickets.</p>
            </div>

            <!-- If pending approval -->
            <template x-if="hasPendingRequest">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center space-y-2">
                    <div class="text-amber-600 font-semibold text-sm">⏳ Approval Pending</div>
                    <p class="text-xs text-amber-800">
                        Your link request for Employee <span class="font-bold font-mono" x-text="pendingEmployeeCode"></span> has been submitted.
                    </p>
                    <p class="text-xs text-slate-500">The IT Help Desk will review and activate your account. You can check back shortly.</p>
                    <button @click="checkAuth()" class="mt-2 text-xs bg-amber-200 hover:bg-amber-300 active:scale-95 text-amber-900 font-semibold px-3 py-1.5 rounded-lg transition-all duration-150">
                        Refresh Status
                    </button>
                </div>
            </template>

            <!-- If needs to submit employee ID -->
            <template x-if="!hasPendingRequest">
                <form @submit.prevent="submitLinkRequest()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1" x-text="locale === 'km' ? 'លេខកូដសម្គាល់បុគ្គលិកក្រុមហ៊ុន' : 'Company Employee ID'">Company Employee ID</label>
                        <input type="text" x-model="linkEmployeeCode" required placeholder="e.g. EMP-1001"
                                class="tma-input w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium uppercase tracking-wide bg-white text-slate-900"
                                style="color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; background-color: #ffffff !important;">
                        <p class="text-[11px] text-slate-400 mt-1" x-text="locale === 'km' ? 'ត្រូវតែត្រូវគ្នានឹងលេខកូដបុគ្គលិកផ្លូវការរបស់ក្រុមហ៊ុន។' : 'Must match your official company employee code.'">Must match your official company employee code.</p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-3 text-xs text-slate-500 space-y-1">
                        <div><span x-text="locale === 'km' ? 'គណនី Telegram៖ ' : 'Telegram Account: '"></span><span class="font-semibold text-slate-700" x-text="telegramUser.first_name + ' ' + (telegramUser.last_name || '')"></span></div>
                        <div x-show="telegramUser.username">Username: <span class="text-blue-600 font-mono">@<span x-text="telegramUser.username"></span></span></div>
                    </div>

                    <div x-show="linkError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded-lg font-medium" x-text="linkError"></div>

                    <button type="submit" :disabled="submittingLink"
                            class="w-full py-3 tg-theme-button rounded-xl font-semibold text-sm shadow-sm active:scale-[0.98] transition-all duration-150 hover:opacity-95 disabled:opacity-50">
                        <span x-show="!submittingLink" x-text="t('submit_verification')">Submit for Verification</span>
                        <span x-show="submittingLink" x-text="t('submitting')">Submitting...</span>
                    </button>
                </form>
            </template>
        </div>
    </div>
