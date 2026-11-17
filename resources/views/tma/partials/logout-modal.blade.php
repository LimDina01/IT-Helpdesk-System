<!-- LOGOUT CONFIRMATION MODAL -->
<div x-show="showLogoutModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
     x-cloak>
    <div @click.away="showLogoutModal = false"
         class="bg-white rounded-3xl max-w-xs w-full p-6 shadow-2xl space-y-4 border border-slate-100 text-center animate-fadeIn">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto text-xl shadow-2xs border border-rose-100">
            🚪
        </div>
        <div>
            <h3 class="text-base font-extrabold text-slate-800" x-text="t('logout_confirm_title')">Sign Out Confirmation</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed" x-text="t('logout_confirm_msg')">
                Are you sure you want to sign out of CBVH IT Help Desk?
            </p>
        </div>
        <div class="grid grid-cols-2 gap-2.5 pt-2">
            <button type="button" @click="showLogoutModal = false"
                    class="w-full py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 active:scale-95 transition"
                    x-text="t('cancel')">
                Cancel
            </button>
            <button type="button" @click="executeLogout()"
                    class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-rose-500/20 transition"
                    x-text="t('logout_confirm_btn')">
                Yes, Sign Out
            </button>
        </div>
    </div>
</div>
