<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CBVH IT Helpdesk - Telegram Mini App</title>

    <!-- Telegram WebApp SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Tailwind CSS CDN & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--tg-theme-bg-color, #f8fafc);
            color: var(--tg-theme-text-color, #0f172a);
            -webkit-tap-highlight-color: transparent;
        }
        .tg-theme-card {
            background-color: var(--tg-theme-secondary-bg-color, #ffffff);
        }
        .tg-theme-button {
            background-color: var(--tg-theme-button-color, #2563eb);
            color: var(--tg-theme-button-text-color, #ffffff);
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen pb-20 select-none">

<div x-data="tmaApp()" x-init="initApp()" x-cloak class="max-w-md mx-auto min-h-screen flex flex-col">

    <!-- DEV MODE HEADER (Visible in local environment if opened in normal browser) -->
    @if(app()->environment('local'))
    <div x-show="isBrowserDevMode" class="bg-amber-500 text-amber-950 px-3 py-1.5 text-xs font-medium flex items-center justify-between">
        <span>🛠 Browser Dev Mode Active</span>
        <button @click="devLogin('EMP-1001')" class="underline hover:text-black">Quick Login: Sokha Chan (EMP-1001)</button>
    </div>
    @endif

    <!-- LOADING STATE -->
    <div x-show="loading" class="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-4">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm font-medium text-slate-500">Connecting to CBVH IT Help Desk...</p>
    </div>

    <!-- WEB / DIRECT BROWSER LOGIN (When Telegram is unavailable, down, or accessed via normal web browser) -->
    <div x-show="!loading && authState === 'web_login_required'" class="flex-1 flex flex-col justify-center p-5">
        <div class="tg-theme-card rounded-3xl shadow-xl border border-slate-200/80 p-6 space-y-6">
            <div class="text-center space-y-2">
                <div class="w-16 h-16 bg-blue-600 text-white rounded-2xl flex items-center justify-center mx-auto text-2xl font-black shadow-lg shadow-blue-500/30">
                    IT
                </div>
                <h1 class="text-xl font-extrabold text-slate-800">CBVH Help Desk</h1>
                <p class="text-xs text-slate-500">Web Portal &mdash; Sign in with your Employee ID, Email, or Telegram username to submit tickets.</p>
            </div>

            <form @submit.prevent="submitWebLogin()" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Employee ID, Email, or @Username</label>
                    <input type="text" x-model="webLoginIdentifier" required placeholder="e.g. EMP-1001, email, or @username"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium">
                    <p class="text-[10px] text-slate-400 mt-1">Accepts Company Employee Code, Corporate Email, or Telegram username.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
                    <input type="password" x-model="webLoginPassword" required placeholder="Enter password (default: Password123!)"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium">
                </div>

                <div x-show="webLoginError" class="text-xs text-red-600 bg-red-50 border border-red-200 p-2.5 rounded-xl font-medium" x-text="webLoginError"></div>

                <button type="submit" :disabled="loggingIn"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm shadow-md shadow-blue-500/20 transition hover:opacity-95 disabled:opacity-50">
                    <span x-show="!loggingIn">Sign In to Help Desk</span>
                    <span x-show="loggingIn">Signing in...</span>
                </button>
            </form>

            <div class="border-t border-slate-200/70 pt-4 text-center space-y-1.5">
                <p class="text-[11px] text-slate-500">Using Telegram? Open in Telegram for automatic password-free sign-in.</p>
                <a href="https://t.me/cbvh_it_bot" target="_blank"
                   class="inline-flex items-center space-x-1 text-xs text-blue-600 font-bold hover:underline">
                    <span>Open in Telegram Bot</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ONBOARDING & LINK REQUEST SCREEN (Option B: Requires Help Desk Approval) -->
    <div x-show="!loading && authState === 'unlinked'" class="flex-1 flex flex-col justify-center p-5">
        <div class="tg-theme-card rounded-2xl shadow-sm border border-slate-200/60 p-6 space-y-6">
            <div class="text-center space-y-2">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold">
                    IT
                </div>
                <h1 class="text-xl font-bold">CBVH Help Desk</h1>
                <p class="text-xs text-slate-500">Link your Telegram account to your employee record to submit and follow up on IT tickets.</p>
            </div>

            <!-- If pending approval -->
            <template x-if="hasPendingRequest">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center space-y-2">
                    <div class="text-amber-600 font-semibold text-sm">⏳ Approval Pending</div>
                    <p class="text-xs text-amber-800">
                        Your link request for Employee <span class="font-bold font-mono" x-text="pendingEmployeeCode"></span> has been submitted.
                    </p>
                    <p class="text-xs text-slate-500">The IT Help Desk will review and activate your account. You can check back shortly.</p>
                    <button @click="checkAuth()" class="mt-2 text-xs bg-amber-200 hover:bg-amber-300 text-amber-900 font-semibold px-3 py-1.5 rounded-lg transition">
                        Refresh Status
                    </button>
                </div>
            </template>

            <!-- If needs to submit employee ID -->
            <template x-if="!hasPendingRequest">
                <form @submit.prevent="submitLinkRequest()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Company Employee ID</label>
                        <input type="text" x-model="linkEmployeeCode" required placeholder="e.g. EMP-1001"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm font-medium uppercase tracking-wide">
                        <p class="text-[11px] text-slate-400 mt-1">Must match your official company employee code.</p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-3 text-xs text-slate-500 space-y-1">
                        <div>Telegram Account: <span class="font-semibold text-slate-700" x-text="telegramUser.first_name + ' ' + (telegramUser.last_name || '')"></span></div>
                        <div x-show="telegramUser.username">Username: <span class="text-blue-600 font-mono">@<span x-text="telegramUser.username"></span></span></div>
                    </div>

                    <div x-show="linkError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded-lg font-medium" x-text="linkError"></div>

                    <button type="submit" :disabled="submittingLink"
                            class="w-full py-3 tg-theme-button rounded-xl font-semibold text-sm shadow-sm transition hover:opacity-95 disabled:opacity-50">
                        <span x-show="!submittingLink">Submit for Verification</span>
                        <span x-show="submittingLink">Submitting...</span>
                    </button>
                </form>
            </template>
        </div>
    </div>

    <!-- MAIN REQUESTER PORTAL (When Authenticated) -->
    <div x-show="!loading && authState === 'authenticated'" class="flex-1 flex flex-col">

        <!-- Top Header Bar -->
        <header class="tg-theme-card border-b border-slate-200/80 px-4 py-3.5 sticky top-0 z-20 shadow-xs flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-sm shadow-xs"
                     x-text="currentUser.name ? currentUser.name.charAt(0) : 'U'"></div>
                <div>
                    <h2 class="text-sm font-bold text-slate-800 leading-tight" x-text="currentUser.name"></h2>
                    <div class="text-[11px] text-slate-500 flex items-center space-x-1.5">
                        <span class="font-mono font-medium text-blue-600" x-text="currentUser.employee_code"></span>
                        <span>•</span>
                        <span x-text="currentUser.branch"></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <button @click="openNewTicketModal()"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm flex items-center space-x-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>New Ticket</span>
                </button>
                <button @click="logout()" title="Sign Out"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </div>
        </header>

        <!-- Three Tabs Navigation -->
        <div class="px-4 pt-3 pb-1">
            <div class="bg-slate-200/70 p-1 rounded-xl flex text-xs font-semibold">
                <button @click="switchTab('active')"
                        :class="currentTab === 'active' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-1.5 rounded-lg text-center transition">
                    Active (<span x-text="tickets.length"></span>)
                </button>
                <button @click="switchTab('history')"
                        :class="currentTab === 'history' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-1.5 rounded-lg text-center transition">
                    History
                </button>
                <button x-show="currentUser?.is_hod || pendingApprovalsCount > 0"
                        @click="switchTab('approvals')"
                        :class="currentTab === 'approvals' ? 'bg-white text-amber-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-1.5 rounded-lg text-center transition flex items-center justify-center space-x-1">
                    <span>Approvals</span>
                    <span x-show="pendingApprovalsCount > 0" class="bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full" x-text="pendingApprovalsCount"></span>
                </button>
            </div>
        </div>

        <!-- Ticket List Container -->
        <main class="flex-1 px-4 py-2 space-y-3">

            <!-- Empty State -->
            <div x-show="tickets.length === 0 && !loadingTickets" class="tg-theme-card rounded-2xl border border-slate-200/60 p-8 text-center space-y-3 mt-4">
                <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-xl">
                    📋
                </div>
                <h3 class="text-sm font-semibold text-slate-700">No Tickets in this tab</h3>
                <p class="text-xs text-slate-500 max-w-xs mx-auto">
                    <span x-show="currentTab === 'active'">You don't have any ongoing or unresolved IT issues right now.</span>
                    <span x-show="currentTab === 'history'">No resolved or archived tickets found.</span>
                    <span x-show="currentTab === 'approvals'">No pending service requests awaiting your approval.</span>
                </p>
                <button x-show="currentTab === 'active'" @click="openNewTicketModal()" class="text-xs text-blue-600 font-semibold underline">
                    Submit your first ticket
                </button>
            </div>

            <!-- Ticket Cards -->
            <template x-for="t in tickets" :key="t.id">
                <div @click="openTicketDetail(t.id)"
                     class="tg-theme-card rounded-2xl border border-slate-200/70 p-3.5 shadow-xs hover:border-blue-400 cursor-pointer transition active:scale-[0.99] space-y-2.5">
                    
                    <!-- Card Header: Code, Type & Status Badge -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-1.5 flex-wrap gap-y-1">
                            <span class="text-xs font-mono font-bold text-blue-600" x-text="t.ticket_code"></span>
                            <span x-show="t.ticket_type === 'service_request'" class="text-[9px] bg-blue-50 text-blue-700 border border-blue-200 px-1.5 py-0.2 rounded font-semibold uppercase">Request</span>
                            <span x-show="t.ticket_type !== 'service_request'" class="text-[9px] bg-rose-50 text-rose-700 border border-rose-200 px-1.5 py-0.2 rounded font-semibold uppercase">Incident</span>
                            <span x-show="t.has_new_reply" class="text-[9px] bg-blue-600 text-white font-bold px-1.5 py-0.2 rounded-full uppercase tracking-wider flex items-center gap-1 shadow-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                New Reply
                            </span>
                        </div>
                        
                        <span :class="getStatusBadgeClass(t.status)"
                              class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                              x-text="formatStatus(t.status)"></span>
                    </div>

                    <!-- Subject -->
                    <h4 class="text-sm font-semibold text-slate-800 line-clamp-1" x-text="t.subject"></h4>

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
                                    <span>💬 You:</span>
                                    <span class="truncate" x-text="t.latest_message?.sender_name"></span>
                                </span>
                            </span>
                            <span class="text-[10px] text-slate-400 shrink-0" x-text="t.latest_message?.created_at_human"></span>
                        </div>
                        <p class="text-[11px] text-slate-700 line-clamp-1 italic" x-text="'\"' + t.latest_message?.body + '\"'"></p>
                    </div>

                    <!-- Approval prompt if pending approval -->
                    <div x-show="t.is_pending_approval" class="bg-amber-50 border border-amber-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-amber-800">
                        <span class="font-medium">⏳ Approval: <span class="font-bold" x-text="t.current_approval_step?.step_name || 'Pending Review'"></span></span>
                        <span class="text-amber-600 font-bold">Review &rarr;</span>
                    </div>

                    <!-- Action Prompt Alert if confirmation or clarification required -->
                    <div x-show="t.is_pending_confirmation" class="bg-emerald-50 border border-emerald-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-emerald-800">
                        <span class="font-medium">✨ Tech completed! Please confirm resolution</span>
                        <span class="text-emerald-600 font-bold">Action Needed &rarr;</span>
                    </div>

                    <div x-show="t.is_clarifying" class="bg-amber-50 border border-amber-200 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-xs text-amber-800">
                        <span class="font-medium">💬 Help Desk requested information</span>
                        <span class="text-amber-600 font-bold">Reply &rarr;</span>
                    </div>

                    <!-- Footer: Category, Tech/Requester & Time -->
                    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-100">
                        <div class="flex items-center space-x-1.5 truncate max-w-[200px]">
                            <span class="truncate" x-text="t.category_name"></span>
                        </div>
                        <span x-text="t.created_at_human"></span>
                    </div>
                </div>
            </template>
        </main>
    </div>

    <!-- TICKET DETAIL & CHAT MODAL -->
    <div x-show="activeTicketModal" class="fixed inset-0 z-50 flex flex-col bg-slate-900/60 backdrop-blur-xs">
        <div class="flex-1 flex flex-col max-w-md w-full mx-auto bg-slate-50 shadow-2xl overflow-hidden mt-4 rounded-t-3xl border-t border-slate-200">
            
            <!-- Modal Header -->
            <div class="bg-white px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-mono font-bold text-blue-600" x-text="selectedTicket?.ticket_code"></span>
                        <span :class="getStatusBadgeClass(selectedTicket?.status)"
                              class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                              x-text="formatStatus(selectedTicket?.status)"></span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800 line-clamp-1 mt-0.5" x-text="selectedTicket?.subject"></h3>
                </div>
                <button @click="closeTicketModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-lg hover:bg-slate-200">
                    &times;
                </button>
            </div>

            <!-- VISUAL PROGRESS STEPPER ("PEACE OF MIND") -->
            <div class="bg-white border-b border-slate-200 px-4 py-3 space-y-2.5">
                <div class="flex items-center justify-between text-[11px] font-bold">
                    <span class="text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <span x-show="selectedTicket?.ticket_type === 'service_request'" class="w-2 h-2 rounded-full bg-blue-500"></span>
                        <span x-show="selectedTicket?.ticket_type !== 'service_request'" class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <span x-text="selectedTicket?.ticket_type === 'service_request' ? 'Service Request Progress' : 'Incident Resolution Progress'"></span>
                    </span>
                    <span class="text-slate-400 font-mono text-[10px]" x-text="selectedTicket?.full_created_timestamp"></span>
                </div>

                <!-- Stepper for Service Request -->
                <template x-if="selectedTicket?.ticket_type === 'service_request'">
                    <div class="space-y-2">
                        <div class="grid grid-cols-5 gap-1 text-center">
                            <!-- Step 1: Submitted -->
                            <div class="flex flex-col items-center">
                                <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✔</div>
                                <span class="text-[9px] font-bold text-emerald-700 mt-1">Submitted</span>
                            </div>
                            <!-- Step 2: Dept HOD -->
                            <div class="flex flex-col items-center">
                                <div :class="getServiceStepClass(selectedTicket, 1)" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="getServiceStepIcon(selectedTicket, 1)"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Dept HOD</span>
                            </div>
                            <!-- Step 3: IT Manager -->
                            <div class="flex flex-col items-center">
                                <div :class="getServiceStepClass(selectedTicket, 2)" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="getServiceStepIcon(selectedTicket, 2)"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">IT Manager</span>
                            </div>
                            <!-- Step 4: Fulfillment -->
                            <div class="flex flex-col items-center">
                                <div :class="getFulfillmentStepClass(selectedTicket)" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="getFulfillmentStepIcon(selectedTicket)"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Fulfillment</span>
                            </div>
                            <!-- Step 5: Completed -->
                            <div class="flex flex-col items-center">
                                <div :class="selectedTicket?.is_closed ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="selectedTicket?.is_closed ? '✔' : '5'"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Done</span>
                            </div>
                        </div>

                        <!-- Live Status Explanation Card -->
                        <div :class="getStatusBannerClass(selectedTicket?.status)" class="p-2.5 rounded-xl text-xs flex items-center justify-between shadow-xs">
                            <div class="space-y-0.5">
                                <div class="font-bold text-[11px]" x-text="getCurrentStageTitle(selectedTicket)"></div>
                                <div class="text-[10px] opacity-90" x-text="getCurrentStageDetail(selectedTicket)"></div>
                            </div>
                            <span class="text-base" x-text="getCurrentStageEmoji(selectedTicket)"></span>
                        </div>
                    </div>
                </template>

                <!-- Stepper for Incident -->
                <template x-if="selectedTicket?.ticket_type !== 'service_request'">
                    <div class="space-y-2">
                        <div class="grid grid-cols-5 gap-1 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✔</div>
                                <span class="text-[9px] font-bold text-emerald-700 mt-1">Reported</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <div :class="selectedTicket?.status !== 'submitted' ? 'bg-emerald-500 text-white' : 'bg-blue-600 text-white ring-2 ring-blue-300'" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="selectedTicket?.status !== 'submitted' ? '✔' : '2'"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Triage</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <div :class="['in_progress', 'blocked', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? 'bg-emerald-500 text-white' : (selectedTicket?.status === 'assigned' ? 'bg-blue-600 text-white ring-2 ring-blue-300' : 'bg-slate-200 text-slate-400')" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="['in_progress', 'blocked', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? '✔' : '3'"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">In Progress</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <div :class="['resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? 'bg-emerald-500 text-white' : (selectedTicket?.status === 'pending_confirmation' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300 animate-pulse' : 'bg-slate-200 text-slate-400')" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="['resolved_confirmed', 'resolved_auto_closed'].includes(selectedTicket?.status) ? '✔' : '4'"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Resolved</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <div :class="selectedTicket?.is_closed ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">
                                    <span x-text="selectedTicket?.is_closed ? '✔' : '5'"></span>
                                </div>
                                <span class="text-[9px] font-medium text-slate-600 mt-1">Closed</span>
                            </div>
                        </div>

                        <!-- Live Status Explanation Card for Incident -->
                        <div :class="getStatusBannerClass(selectedTicket?.status)" class="p-2.5 rounded-xl text-xs flex items-center justify-between shadow-xs">
                            <div class="space-y-0.5">
                                <div class="font-bold text-[11px]" x-text="getIncidentStageTitle(selectedTicket)"></div>
                                <div class="text-[10px] opacity-90" x-text="getIncidentStageDetail(selectedTicket)"></div>
                            </div>
                            <span class="text-base" x-text="getIncidentStageEmoji(selectedTicket)"></span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- HOD APPROVAL ACTION PANEL (If current user is authorized to approve this step) -->
            <div x-show="selectedTicket?.can_approve_current_step" class="bg-amber-50 border-b border-amber-200 p-3.5 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-amber-600 font-bold">🔔</span>
                        <span class="font-bold text-xs text-amber-900">Your Approval is Required</span>
                    </div>
                    <span class="text-[10px] bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full font-bold" x-text="selectedTicket?.current_approval_step?.step_name"></span>
                </div>
                <p class="text-[11px] text-amber-800 leading-snug">
                    Requester <span class="font-bold" x-text="selectedTicket?.requester_name"></span> (<span x-text="selectedTicket?.requester_dept"></span>) has submitted this service request for company approval.
                </p>

                <div class="flex items-center space-x-2 pt-1">
                    <button @click="showApprovePrompt = true" :disabled="actioningApproval"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs text-center transition">
                        ✅ Approve Request
                    </button>
                    <button @click="showRejectPrompt = true" :disabled="actioningApproval"
                            class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-xs text-center transition">
                        ❌ Reject Request
                    </button>
                </div>
            </div>

            <!-- Approval Comment Prompt Overlay -->
            <div x-show="showApprovePrompt" class="bg-emerald-50 p-3 border-b border-emerald-300 space-y-2">
                <label class="block text-xs font-bold text-emerald-900">Approval Comment / Instructions (Optional):</label>
                <input type="text" x-model="approvalComment" placeholder="e.g. Approved. Budget verified."
                       class="w-full text-xs p-2 rounded-lg border border-emerald-300 focus:outline-none bg-white">
                <div class="flex justify-end space-x-2">
                    <button @click="showApprovePrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold">Cancel</button>
                    <button @click="submitApprove()" :disabled="actioningApproval" class="text-xs px-3 py-1 bg-emerald-600 text-white font-bold rounded-md">Confirm Approval</button>
                </div>
            </div>

            <!-- Rejection Reason Prompt Overlay -->
            <div x-show="showRejectPrompt" class="bg-rose-50 p-3 border-b border-rose-300 space-y-2">
                <label class="block text-xs font-bold text-rose-900">Rejection Reason (Mandatory):</label>
                <textarea x-model="rejectionReason" rows="2" placeholder="e.g. Budget not approved for this quarter..."
                          class="w-full text-xs p-2 rounded-lg border border-rose-300 focus:outline-none bg-white"></textarea>
                <div class="flex justify-end space-x-2">
                    <button @click="showRejectPrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold">Cancel</button>
                    <button @click="submitReject()" :disabled="actioningApproval || !rejectionReason" class="text-xs px-3 py-1 bg-rose-600 text-white font-bold rounded-md">Confirm Rejection</button>
                </div>
            </div>

            <!-- Resolution Confirmation Banner (Option A/B Decision Point) -->
            <div x-show="selectedTicket?.is_pending_confirmation" class="bg-emerald-600 text-white p-3.5 space-y-2 shadow-sm">
                <div class="flex items-start space-x-2">
                    <span class="text-xl">🛠</span>
                    <div>
                        <div class="font-bold text-xs">Technician marked this ticket as resolved!</div>
                        <p class="text-[11px] text-emerald-100">Please verify if your IT issue is fully fixed.</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-1">
                    <button @click="confirmResolution()" :disabled="confirmingAction"
                            class="flex-1 py-2 bg-white text-emerald-800 hover:bg-emerald-50 rounded-xl font-bold text-xs shadow-xs text-center transition">
                        ✔ Yes, Solved!
                    </button>
                    <button @click="showReopenPrompt = true" :disabled="confirmingAction"
                            class="flex-1 py-2 bg-emerald-800/80 hover:bg-emerald-800 text-white rounded-xl font-bold text-xs text-center transition">
                        ❌ No, Still Broken
                    </button>
                </div>
            </div>

            <!-- Reopen Reason Input Overlay -->
            <div x-show="showReopenPrompt" class="bg-amber-100 p-3 border-b border-amber-300 space-y-2">
                <label class="block text-xs font-bold text-amber-900">Explain why it is still broken:</label>
                <textarea x-model="reopenReason" rows="2" placeholder="e.g. Printer still won't turn on..."
                          class="w-full text-xs p-2 rounded-lg border border-amber-300 focus:outline-none"></textarea>
                <div class="flex justify-end space-x-2">
                    <button @click="showReopenPrompt = false" class="text-xs px-2.5 py-1 text-slate-600 font-semibold">Cancel</button>
                    <button @click="submitReopen()" class="text-xs px-3 py-1 bg-red-600 text-white font-bold rounded-md">Reopen Ticket</button>
                </div>
            </div>

            <!-- Conversation Timeline -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="timelineContainer">
                <template x-for="m in messages" :key="m.id">
                    <div>
                        <!-- System Events -->
                        <template x-if="m.visibility === 'system_event'">
                            <div class="text-center my-2">
                                <span class="bg-slate-200/80 text-slate-600 text-[11px] font-medium px-2.5 py-1 rounded-full" x-text="m.body"></span>
                            </div>
                        </template>

                        <!-- Chat Message Bubbles -->
                        <template x-if="m.visibility !== 'system_event'">
                            <div :class="m.is_me ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="m.is_me ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white text-slate-800 rounded-bl-none shadow-xs border border-slate-200/80'"
                                     class="max-w-[85%] rounded-2xl px-3.5 py-2.5 space-y-1">
                                    
                                    <!-- Sender Name for Staff -->
                                    <div x-show="!m.is_me" class="flex items-center space-x-1.5 text-[11px] font-bold text-blue-600">
                                        <span x-text="m.sender_name"></span>
                                        <span class="bg-blue-100 text-blue-800 text-[9px] px-1.5 py-0.2 rounded font-semibold uppercase">IT Staff</span>
                                    </div>

                                    <!-- Body -->
                                    <p class="text-xs leading-relaxed whitespace-pre-wrap" x-text="m.body"></p>

                                    <!-- Attachments -->
                                    <template x-if="m.attachments && m.attachments.length > 0">
                                        <div class="pt-1.5 space-y-1.5 border-t border-black/10">
                                            <!-- Mini Image Previews Grid (like Telegram) -->
                                            <div class="grid grid-cols-2 gap-1.5" x-show="m.attachments.some(a => a.is_image)">
                                                <template x-for="att in m.attachments.filter(a => a.is_image)" :key="att.id">
                                                    <div class="relative group rounded-xl overflow-hidden border border-black/10 bg-black/5 cursor-pointer aspect-square"
                                                         @click="openLightbox(att.preview_url, att.original_filename, att.download_url)">
                                                        <img :src="att.preview_url" :alt="att.original_filename"
                                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-1.5 flex items-center justify-between text-[10px] text-white">
                                                            <span class="truncate max-w-[80px]" x-text="att.original_filename"></span>
                                                            <span class="opacity-80 font-mono text-[9px]" x-text="att.file_size"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Non-Image Files -->
                                            <template x-for="att in m.attachments.filter(a => !a.is_image)" :key="att.id">
                                                <a :href="att.download_url" target="_blank"
                                                   :class="m.is_me ? 'bg-blue-700/60 text-white' : 'bg-slate-100 text-slate-700'"
                                                   class="flex items-center space-x-2 text-[11px] px-2.5 py-1.5 rounded-lg hover:opacity-90 transition">
                                                    <span>📎</span>
                                                    <span class="truncate flex-1 font-medium" x-text="att.original_filename"></span>
                                                    <span class="text-[10px] opacity-75 font-mono" x-text="att.file_size"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Timestamp: Full date/time + relative -->
                                    <div :class="m.is_me ? 'text-blue-100' : 'text-slate-400'" class="text-[10px] text-right font-medium"
                                         x-text="m.full_timestamp || (m.created_at + ' (' + m.created_at_human + ')')"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Reply Input Box (Only if not permanently closed) -->
            <div x-show="!selectedTicket?.is_closed" class="bg-white p-3 border-t border-slate-200">
                <form @submit.prevent="sendReply()" class="space-y-2">
                    <div class="flex items-center space-x-2">
                        <label class="cursor-pointer text-slate-400 hover:text-blue-600 p-1.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <input type="file" multiple @change="handleReplyFiles" class="hidden" accept="image/*,application/pdf">
                        </label>
                        <input type="text" x-model="replyText" placeholder="Write a reply..."
                                class="flex-1 bg-slate-100 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" :disabled="!replyText && replyFiles.length === 0"
                                class="bg-blue-600 text-white rounded-xl px-3.5 py-2 text-xs font-semibold hover:bg-blue-700 disabled:opacity-40 transition">
                            Send
                        </button>
                    </div>

                    <!-- File preview -->
                    <div x-show="replyFiles.length > 0" class="flex flex-wrap gap-1 text-[11px] text-slate-600">
                        <template x-for="(f, i) in replyFiles" :key="i">
                            <span class="bg-slate-100 px-2 py-0.5 rounded-md flex items-center space-x-1">
                                <span class="truncate max-w-[120px]" x-text="f.name"></span>
                                <button type="button" @click="replyFiles.splice(i, 1)" class="text-red-500 font-bold">&times;</button>
                            </span>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NEW TICKET CREATION MODAL -->
    <div x-show="newTicketModal" class="fixed inset-0 z-50 flex flex-col bg-slate-900/60 backdrop-blur-xs">
        <div class="flex-1 flex flex-col max-w-md w-full mx-auto bg-white shadow-2xl overflow-hidden mt-6 rounded-t-3xl border-t border-slate-200">
            
            <div class="bg-white px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-800">Submit New IT Request</h3>
                <button @click="newTicketModal = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-lg hover:bg-slate-200">
                    &times;
                </button>
            </div>

            <form @submit.prevent="submitNewTicket()" class="flex-1 overflow-y-auto p-5 space-y-4">

                <!-- 1. Segmented Toggle: Incident vs Service Request -->
                <div class="space-y-1.5 pb-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Classification</label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-2xl">
                        <button type="button" @click="setFormTicketType('incident')"
                                :class="formTicketType === 'incident' ? 'bg-white text-rose-700 shadow-sm font-bold ring-2 ring-rose-400' : 'text-slate-600 font-medium'"
                                class="py-2.5 rounded-xl text-xs flex items-center justify-center space-x-1.5 transition">
                            <span>🔴</span>
                            <span>Incident / Issue</span>
                        </button>
                        <button type="button" @click="setFormTicketType('service_request')"
                                :class="formTicketType === 'service_request' ? 'bg-white text-blue-700 shadow-sm font-bold ring-2 ring-blue-400' : 'text-slate-600 font-medium'"
                                class="py-2.5 rounded-xl text-xs flex items-center justify-center space-x-1.5 transition">
                            <span>🔵</span>
                            <span>Service Request</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500 italic" x-show="formTicketType === 'incident'">
                        💥 Something is broken, malfunctioning, or offline (e.g. printer error, network down).
                    </p>
                    <p class="text-[11px] text-slate-500 italic" x-show="formTicketType === 'service_request'">
                        📦 Request equipment (laptop purchase), floor/desk move, ERP access, or enhancements. Requires approval.
                    </p>
                </div>
                
                <!-- Branch Location -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Affected Branch / Office</label>
                    <select x-model="formBranchId" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <template x-for="b in formData.branches" :key="b.id">
                            <option :value="b.id" x-text="b.name + ' (' + b.code + ')'"></option>
                        </template>
                    </select>
                </div>

                <!-- Category Cascade: Parent Category (Filtered by Type) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        <span x-text="formTicketType === 'service_request' ? 'Request Category' : 'Issue Category'"></span>
                    </label>
                    <select x-model="formParentCatId" @change="onParentCategoryChange()" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">-- Select Category --</option>
                        <template x-for="cat in filteredParentCategories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name + (cat.requires_approval ? ' 🔒 (Requires Approval)' : '')"></option>
                        </template>
                    </select>
                </div>

                <!-- Subcategory -->
                <div x-show="filteredSubcategories.length > 0">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Specific Problem / Item</label>
                    <select x-model="formSubCatId" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">-- Select Specific Issue --</option>
                        <template x-for="sub in filteredSubcategories" :key="sub.id">
                            <option :value="sub.id" x-text="sub.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Priority / Urgency -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Urgency Level</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" @click="formPriority = 'low'" :class="formPriority === 'low' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-700'" class="py-1.5 rounded-lg text-xs font-semibold transition">Low</button>
                        <button type="button" @click="formPriority = 'medium'" :class="formPriority === 'medium' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="py-1.5 rounded-lg text-xs font-semibold transition">Medium</button>
                        <button type="button" @click="formPriority = 'high'" :class="formPriority === 'high' ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-700'" class="py-1.5 rounded-lg text-xs font-semibold transition">High</button>
                        <button type="button" @click="formPriority = 'critical'" :class="formPriority === 'critical' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700'" class="py-1.5 rounded-lg text-xs font-semibold transition">Critical</button>
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Summary / Subject</label>
                    <input type="text" x-model="formSubject" required placeholder="Brief description of the problem..."
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Detailed Description -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Details & Symptoms</label>
                    <textarea x-model="formDescription" required rows="3" placeholder="Please provide details, error messages, or steps that reproduce the problem..."
                              class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <!-- Multi-file upload -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Photos / PDF Documents (Max 20MB)</label>
                    <input type="file" multiple @change="handleNewTicketFiles" accept="image/*,application/pdf"
                           class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-[10px] text-slate-400 mt-1">Attach photo of error screen, receipt printer, or damaged hardware.</p>
                </div>

                <div x-show="formError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded-lg font-medium" x-text="formError"></div>

                <div class="pt-2">
                    <button type="submit" :disabled="submittingTicket"
                            class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm shadow-md transition disabled:opacity-50">
                        <span x-show="!submittingTicket">Submit Ticket</span>
                        <span x-show="submittingTicket">Submitting Ticket...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FULL-SCREEN IMAGE LIGHTBOX MODAL (TMA) -->
    <div x-show="showLightbox"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="showLightbox = false"
         class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md flex flex-col p-4" x-cloak>
        <div class="flex items-center justify-between text-white pb-3 max-w-md mx-auto w-full">
            <span class="text-xs font-semibold truncate max-w-[200px]" x-text="lightboxFilename"></span>
            <div class="flex items-center space-x-2">
                <a :href="lightboxDownloadUrl" download class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-2.5 py-1 rounded-lg transition">
                    Download
                </a>
                <button type="button" @click="showLightbox = false" class="text-white hover:text-slate-300 text-2xl font-bold p-1">
                    &times;
                </button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center max-w-md mx-auto w-full overflow-hidden">
            <img :src="lightboxUrl" :alt="lightboxFilename" class="max-h-[80vh] max-w-full rounded-xl shadow-2xl object-contain">
        </div>
    </div>

</div>

<!-- Alpine Application Script -->
<script>
function tmaApp() {
    return {
        loading: true,
        authState: 'unlinked', // 'unlinked', 'authenticated', 'web_login_required'
        hasPendingRequest: false,
        pendingEmployeeCode: '',
        telegramUser: {},
        currentUser: {},
        linkEmployeeCode: '',
        linkError: '',
        submittingLink: false,
        isBrowserDevMode: false,

        // Direct Web Login State
        webLoginIdentifier: '',
        webLoginPassword: '',
        webLoginError: '',
        loggingIn: false,

        // Image Lightbox State
        showLightbox: false,
        lightboxUrl: '',
        lightboxFilename: '',
        lightboxDownloadUrl: '',

        // Tabs & Tickets
        currentTab: 'active',
        tickets: [],
        loadingTickets: false,

        // Detail Modal & Chat
        activeTicketModal: false,
        selectedTicket: null,
        messages: [],
        replyText: '',
        replyFiles: [],
        showReopenPrompt: false,
        reopenReason: '',
        confirmingAction: false,
        chatPollTimer: null,
        listPollTimer: null,

        // New Ticket Modal
        newTicketModal: false,
        formTicketType: 'incident', // 'incident' or 'service_request'
        formData: { branches: [], categories: [] },
        formBranchId: '',
        formParentCatId: '',
        filteredSubcategories: [],
        formSubCatId: '',
        formPriority: 'medium',
        formSubject: '',
        formDescription: '',
        formAttachments: [],
        submittingTicket: false,
        formError: '',

        // Approval Actions
        pendingApprovalsCount: 0,
        showApprovePrompt: false,
        showRejectPrompt: false,
        approvalComment: '',
        rejectionReason: '',
        actioningApproval: false,

        get filteredParentCategories() {
            const targetType = this.formTicketType === 'service_request' ? 'service_request' : 'incident_issue';
            return (this.formData.categories || []).filter(c => c.type === targetType);
        },

        setFormTicketType(type) {
            this.formTicketType = type;
            this.formParentCatId = '';
            this.filteredSubcategories = [];
            this.formSubCatId = '';
        },

        initApp() {
            const tg = window.Telegram?.WebApp;
            if (tg) {
                tg.ready();
                tg.expand();
                tg.enableClosingConfirmation();
            }

            // If user navigates away or refreshes while inside chat modal, clear presence immediately
            window.addEventListener('beforeunload', () => {
                if (this.selectedTicket?.id && this.activeTicketModal) {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    const blob = new Blob([JSON.stringify({ _token: token })], { type: 'application/json' });
                    navigator.sendBeacon(`/tma/api/tickets/${this.selectedTicket.id}/leave`, blob);
                }
            });

            this.checkAuth();
            this.loadFormData();
            this.startListPolling();
        },

        startListPolling() {
            if (this.listPollTimer) clearInterval(this.listPollTimer);
            this.listPollTimer = setInterval(() => {
                if (!this.activeTicketModal && this.authState === 'authenticated') {
                    this.loadTickets(true);
                }
            }, 8000);
        },

        openLightbox(url, name, downloadUrl) {
            this.lightboxUrl = url;
            this.lightboxFilename = name;
            this.lightboxDownloadUrl = downloadUrl;
            this.showLightbox = true;
        },

        checkAuth() {
            this.loading = true;
            const initData = window.Telegram?.WebApp?.initData || '';

            // Check if opening in standard browser without Telegram
            if (!initData) {
                this.isBrowserDevMode = true;
            }

            fetch('/tma/api/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ init_data: initData })
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.status === 'authenticated') {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    this.loadTickets();
                } else if (data.status === 'unlinked') {
                    this.authState = 'unlinked';
                    this.hasPendingRequest = data.has_pending_request;
                    this.pendingEmployeeCode = data.pending_request?.employee_code || '';
                    this.telegramUser = data.telegram_user || {};
                } else if (data.status === 'web_login_required') {
                    this.authState = 'web_login_required';
                }
            })
            .catch(() => {
                this.loading = false;
                this.authState = 'web_login_required';
            });
        },

        submitWebLogin() {
            this.loggingIn = true;
            this.webLoginError = '';

            fetch('/tma/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    login: this.webLoginIdentifier,
                    password: this.webLoginPassword,
                    remember: true
                })
            })
            .then(async res => {
                const data = await res.json();
                this.loggingIn = false;
                if (!res.ok) {
                    this.webLoginError = data.error || 'Login failed. Please check credentials.';
                } else {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    this.webLoginPassword = '';
                    this.loadTickets();
                }
            })
            .catch(() => {
                this.loggingIn = false;
                this.webLoginError = 'Network error. Please try again.';
            });
        },

        logout() {
            fetch('/tma/api/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).finally(() => {
                this.authState = 'web_login_required';
                this.currentUser = {};
                this.tickets = [];
            });
        },

        devLogin(empCode) {
            this.loading = true;
            fetch('/tma/api/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ dev_employee_code: empCode })
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.status === 'authenticated') {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    this.loadTickets();
                }
            });
        },

        submitLinkRequest() {
            this.submittingLink = true;
            this.linkError = '';

            const initData = window.Telegram?.WebApp?.initData || '';

            fetch('/tma/api/link-request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    init_data: initData,
                    telegram_id: this.telegramUser.id || 123456789,
                    telegram_username: this.telegramUser.username || null,
                    first_name: this.telegramUser.first_name || '',
                    last_name: this.telegramUser.last_name || '',
                    employee_code: this.linkEmployeeCode
                })
            })
            .then(async res => {
                const data = await res.json();
                this.submittingLink = false;
                if (!res.ok) {
                    this.linkError = data.error || 'Failed to submit link request';
                } else {
                    this.hasPendingRequest = true;
                    this.pendingEmployeeCode = data.employee_code;
                }
            })
            .catch(() => {
                this.submittingLink = false;
                this.linkError = 'Network error. Please try again.';
            });
        },

        switchTab(tab) {
            this.currentTab = tab;
            this.loadTickets();
        },

        loadTickets(silent = false) {
            if (!silent) this.loadingTickets = true;
            fetch(`/tma/api/tickets?tab=${this.currentTab}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                this.loadingTickets = false;
                this.tickets = data.tickets || [];
                if (data.pending_approvals_count !== undefined) {
                    this.pendingApprovalsCount = data.pending_approvals_count;
                }
            })
            .catch(() => {
                this.loadingTickets = false;
            });
        },

        openTicketDetail(id) {
            fetch(`/tma/api/tickets/${id}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                this.selectedTicket = data.ticket;
                this.messages = data.messages || [];
                this.showApprovePrompt = false;
                this.showRejectPrompt = false;
                this.approvalComment = '';
                this.rejectionReason = '';
                this.activeTicketModal = true;
                this.$nextTick(() => {
                    const c = document.getElementById('timelineContainer');
                    if (c) c.scrollTop = c.scrollHeight;
                });
                this.startChatPolling(id);
            });
        },

        startChatPolling(id) {
            this.stopChatPolling();
            this.chatPollTimer = setInterval(() => {
                if (!this.activeTicketModal || !this.selectedTicket || this.selectedTicket.id !== id) {
                    this.stopChatPolling();
                    return;
                }

                fetch(`/tma/api/tickets/${id}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (!this.activeTicketModal || !this.selectedTicket) return;
                    const prevCount = this.messages ? this.messages.length : 0;
                    const newCount = data.messages ? data.messages.length : 0;
                    this.selectedTicket = data.ticket;
                    this.messages = data.messages || [];
                    if (newCount > prevCount) {
                        this.$nextTick(() => {
                            const c = document.getElementById('timelineContainer');
                            if (c) c.scrollTop = c.scrollHeight;
                        });
                    }
                })
                .catch(() => {});
            }, 3500);
        },

        stopChatPolling() {
            if (this.chatPollTimer) {
                clearInterval(this.chatPollTimer);
                this.chatPollTimer = null;
            }
        },

        closeTicketModal() {
            const ticketId = this.selectedTicket ? this.selectedTicket.id : null;
            this.stopChatPolling();
            this.activeTicketModal = false;
            this.selectedTicket = null;
            this.replyText = '';
            this.replyFiles = [];
            this.showReopenPrompt = false;
            this.showApprovePrompt = false;
            this.showRejectPrompt = false;

            // Immediately clear server presence so Telegram alert triggers if IT sends a message
            if (ticketId) {
                fetch(`/tma/api/tickets/${ticketId}/leave`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                }).catch(() => {});
            }

            this.loadTickets(true);
        },

        handleReplyFiles(e) {
            this.replyFiles = Array.from(e.target.files);
        },

        sendReply() {
            if (!this.selectedTicket) return;
            const formData = new FormData();
            if (this.replyText) formData.append('body', this.replyText);
            this.replyFiles.forEach(f => formData.append('attachments[]', f));

            fetch(`/tma/api/tickets/${this.selectedTicket.id}/messages`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(() => {
                this.replyText = '';
                this.replyFiles = [];
                this.openTicketDetail(this.selectedTicket.id);
            });
        },

        submitApprove() {
            if (!this.selectedTicket || !this.selectedTicket.current_approval_step) return;
            this.actioningApproval = true;
            fetch(`/tma/api/approvals/${this.selectedTicket.current_approval_step.id}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comments: this.approvalComment })
            })
            .then(res => res.json())
            .then(() => {
                this.actioningApproval = false;
                this.showApprovePrompt = false;
                this.approvalComment = '';
                this.openTicketDetail(this.selectedTicket.id);
            })
            .catch(() => {
                this.actioningApproval = false;
            });
        },

        submitReject() {
            if (!this.selectedTicket || !this.selectedTicket.current_approval_step || !this.rejectionReason) return;
            this.actioningApproval = true;
            fetch(`/tma/api/approvals/${this.selectedTicket.current_approval_step.id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: this.rejectionReason })
            })
            .then(res => res.json())
            .then(() => {
                this.actioningApproval = false;
                this.showRejectPrompt = false;
                this.rejectionReason = '';
                this.openTicketDetail(this.selectedTicket.id);
            })
            .catch(() => {
                this.actioningApproval = false;
            });
        },

        confirmResolution() {
            if (!this.selectedTicket) return;
            this.confirmingAction = true;
            fetch(`/tma/api/tickets/${this.selectedTicket.id}/confirm`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(() => {
                this.confirmingAction = false;
                this.openTicketDetail(this.selectedTicket.id);
            });
        },

        submitReopen() {
            if (!this.selectedTicket || !this.reopenReason) return;
            fetch(`/tma/api/tickets/${this.selectedTicket.id}/reopen`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: this.reopenReason })
            })
            .then(res => res.json())
            .then(() => {
                this.showReopenPrompt = false;
                this.reopenReason = '';
                this.openTicketDetail(this.selectedTicket.id);
            });
        },

        loadFormData() {
            fetch('/tma/api/form-data', {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                this.formData = data;
                if (data.branches && data.branches.length > 0) {
                    this.formBranchId = data.user?.branch_id || data.branches[0].id;
                }
                if (data.user?.pending_approvals_count !== undefined) {
                    this.pendingApprovalsCount = data.user.pending_approvals_count;
                }
            });
        },

        openNewTicketModal() {
            this.formSubject = '';
            this.formDescription = '';
            this.formAttachments = [];
            this.formError = '';
            this.formParentCatId = '';
            this.filteredSubcategories = [];
            this.formSubCatId = '';
            this.newTicketModal = true;
        },

        onParentCategoryChange() {
            const parent = this.formData.categories.find(c => c.id == this.formParentCatId);
            if (parent && parent.children) {
                this.filteredSubcategories = parent.children;
                if (parent.children.length > 0) {
                    this.formSubCatId = parent.children[0].id;
                }
                this.formPriority = parent.default_priority || 'medium';
            } else {
                this.filteredSubcategories = [];
                this.formSubCatId = '';
            }
        },

        handleNewTicketFiles(e) {
            this.formAttachments = Array.from(e.target.files);
        },

        submitNewTicket() {
            this.submittingTicket = true;
            this.formError = '';

            const formData = new FormData();
            formData.append('ticket_type', this.formTicketType);
            formData.append('branch_id', this.formBranchId);
            formData.append('category_id', this.formSubCatId || this.formParentCatId);
            formData.append('priority', this.formPriority);
            formData.append('subject', this.formSubject);
            formData.append('description', this.formDescription);
            this.formAttachments.forEach(f => formData.append('attachments[]', f));

            fetch('/tma/api/tickets', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async res => {
                const data = await res.json();
                this.submittingTicket = false;
                if (!res.ok) {
                    this.formError = data.message || 'Failed to submit ticket';
                } else {
                    this.newTicketModal = false;
                    this.switchTab('active');
                    this.openTicketDetail(data.ticket_id);
                }
            })
            .catch(() => {
                this.submittingTicket = false;
                this.formError = 'Network error while submitting ticket.';
            });
        },

        getServiceStepClass(t, step) {
            if (!t) return 'bg-slate-200 text-slate-400';
            const currentStepNum = t.current_approval_step?.step_number;
            if (t.status === 'rejected') return 'bg-rose-100 text-rose-700';
            if (!t.is_pending_approval) return 'bg-emerald-500 text-white';
            if (currentStepNum > step) return 'bg-emerald-500 text-white';
            if (currentStepNum === step) return 'bg-blue-600 text-white ring-2 ring-blue-300 animate-pulse';
            return 'bg-slate-200 text-slate-400';
        },

        getServiceStepIcon(t, step) {
            if (!t) return step;
            const currentStepNum = t.current_approval_step?.step_number;
            if (t.status === 'rejected') return '✕';
            if (!t.is_pending_approval || currentStepNum > step) return '✔';
            return step;
        },

        getFulfillmentStepClass(t) {
            if (!t) return 'bg-slate-200 text-slate-400';
            if (['in_progress', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(t.status)) return 'bg-emerald-500 text-white';
            if (t.status === 'submitted' || t.status === 'assigned') return 'bg-blue-600 text-white ring-2 ring-blue-300 animate-pulse';
            return 'bg-slate-200 text-slate-400';
        },

        getFulfillmentStepIcon(t) {
            if (!t) return '4';
            if (['in_progress', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(t.status)) return '✔';
            return '4';
        },

        getStatusBannerClass(status) {
            const map = {
                'pending_approval': 'bg-amber-50 text-amber-900 border border-amber-200',
                'submitted': 'bg-blue-50 text-blue-900 border border-blue-200',
                'assigned': 'bg-purple-50 text-purple-900 border border-purple-200',
                'in_progress': 'bg-indigo-50 text-indigo-900 border border-indigo-200',
                'blocked': 'bg-red-50 text-red-900 border border-red-200',
                'pending_confirmation': 'bg-emerald-50 text-emerald-900 border border-emerald-300 font-bold',
                'resolved_confirmed': 'bg-slate-100 text-slate-800 border border-slate-200',
                'resolved_auto_closed': 'bg-slate-100 text-slate-800 border border-slate-200',
                'rejected': 'bg-rose-50 text-rose-900 border border-rose-200',
                'reopened': 'bg-rose-50 text-rose-900 border border-rose-200',
                'cancelled': 'bg-slate-100 text-slate-600 border border-slate-200'
            };
            return map[status] || 'bg-slate-50 text-slate-700 border border-slate-200';
        },

        getCurrentStageTitle(t) {
            if (!t) return '';
            if (t.status === 'pending_approval') return '⏳ ' + (t.current_approval_step?.step_name || 'Approval Required');
            if (t.status === 'submitted') return '📥 Approved & Received by IT';
            if (t.status === 'assigned') return '🛠 Assigned to IT Technician';
            if (t.status === 'in_progress') return '⚙ Work In Progress / Setup';
            if (t.status === 'blocked') return '🛑 Procurement / Parts Hold';
            if (t.status === 'pending_confirmation') return '✨ Fulfillment Ready - Confirm Signoff';
            if (t.status === 'resolved_confirmed' || t.status === 'resolved_auto_closed') return '✔ Completed & Closed';
            if (t.status === 'rejected') return '❌ Request Rejected';
            return t.status;
        },

        getCurrentStageDetail(t) {
            if (!t) return '';
            if (t.status === 'pending_approval') {
                return 'Waiting for ' + (t.current_approval_step?.step_name || 'HOD') + ' review & signoff.';
            }
            if (t.status === 'submitted') return 'All approvals granted! Waiting for Help Desk triage & technician dispatch.';
            if (t.status === 'assigned') return 'Assigned to ' + t.technician_name + '. Awaiting technician acceptance.';
            if (t.status === 'in_progress') return t.technician_name + ' is actively configuring/procuring your request.';
            if (t.status === 'blocked') return 'Hold Reason: ' + (t.blocked_reason || 'Waiting for supplier/parts.');
            if (t.status === 'pending_confirmation') return 'IT completed the request. Please verify and confirm resolution above.';
            if (t.status === 'rejected') return 'Reason: ' + (t.cancellation_reason || 'Declined during review.');
            return 'Processing normal workflow.';
        },

        getCurrentStageEmoji(t) {
            if (!t) return '📋';
            if (t.status === 'pending_approval') return '⏳';
            if (t.status === 'submitted') return '📥';
            if (t.status === 'assigned') return '👤';
            if (t.status === 'in_progress') return '🛠';
            if (t.status === 'blocked') return '🛑';
            if (t.status === 'pending_confirmation') return '✨';
            if (t.status === 'resolved_confirmed') return '✔';
            if (t.status === 'rejected') return '❌';
            return '📋';
        },

        getIncidentStageTitle(t) {
            if (!t) return '';
            if (t.status === 'submitted') return '📥 Reported - In Triage';
            if (t.status === 'assigned') return '👤 Assigned to Technician';
            if (t.status === 'in_progress') return '🛠 Technician Working';
            if (t.status === 'blocked') return '🛑 Blocked / Waiting on Parts';
            if (t.status === 'clarifying') return '💬 IT Help Desk Needs Information';
            if (t.status === 'pending_confirmation') return '✨ Fix Completed - Verify Resolution';
            if (t.status === 'resolved_confirmed') return '✔ Resolved & Confirmed';
            if (t.status === 'reopened') return '⚠️ Reopened - Returned to Tech';
            return t.status;
        },

        getIncidentStageDetail(t) {
            if (!t) return '';
            if (t.status === 'submitted') return 'Waiting for IT Help Desk specialist to review & dispatch technician.';
            if (t.status === 'assigned') return 'Assigned to ' + t.technician_name + '. Tech will accept shortly.';
            if (t.status === 'in_progress') return t.technician_name + ' is actively troubleshooting the problem.';
            if (t.status === 'blocked') return 'Blocked: ' + (t.blocked_reason || 'Waiting on vendor or replacement.');
            if (t.status === 'clarifying') return 'Please read IT message below and submit your reply.';
            if (t.status === 'pending_confirmation') return 'Technician tested the fix. Please test and confirm above.';
            return 'Issue is being handled.';
        },

        getIncidentStageEmoji(t) {
            if (!t) return '⚡';
            if (t.status === 'submitted') return '📥';
            if (t.status === 'assigned') return '👤';
            if (t.status === 'in_progress') return '🛠';
            if (t.status === 'blocked') return '🛑';
            if (t.status === 'pending_confirmation') return '✨';
            if (t.status === 'resolved_confirmed') return '✔';
            return '⚡';
        },

        getStatusBadgeClass(status) {
            const map = {
                'pending_approval': 'bg-amber-100 text-amber-900 border border-amber-300 font-bold',
                'submitted': 'bg-blue-100 text-blue-800',
                'clarifying': 'bg-amber-100 text-amber-900 border border-amber-300',
                'assigned': 'bg-purple-100 text-purple-800',
                'in_progress': 'bg-indigo-100 text-indigo-800',
                'blocked': 'bg-red-100 text-red-800',
                'pending_confirmation': 'bg-emerald-100 text-emerald-900 border border-emerald-400 font-bold',
                'resolved_confirmed': 'bg-slate-200 text-slate-700',
                'resolved_auto_closed': 'bg-slate-200 text-slate-600',
                'reopened': 'bg-rose-100 text-rose-800 border border-rose-300',
                'rejected': 'bg-rose-100 text-rose-800 border border-rose-300',
                'cancelled': 'bg-slate-100 text-slate-400'
            };
            return map[status] || 'bg-slate-100 text-slate-700';
        },

        formatStatus(status) {
            const map = {
                'pending_approval': 'Pending Approval',
                'submitted': 'Triage',
                'clarifying': 'Needs Info',
                'assigned': 'Assigned',
                'in_progress': 'In Progress',
                'blocked': 'Blocked',
                'pending_confirmation': 'Verify Fix',
                'resolved_confirmed': 'Completed',
                'resolved_auto_closed': 'Auto Closed',
                'reopened': 'Reopened',
                'rejected': 'Rejected',
                'cancelled': 'Cancelled'
            };
            return map[status] || status;
        }
    }
}
</script>

</body>
</html>
