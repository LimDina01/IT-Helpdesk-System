<!DOCTYPE html>
<html lang="en" class="min-h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Operations Portal') - CBVH IT Help Desk</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col antialiased text-slate-800">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl 2xl:max-w-screen-2xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            
            <!-- Brand & Navigation Links -->
            <div class="flex items-center space-x-8">
                <a href="{{ route('portal.overview') }}" wire:navigate class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-xl flex items-center justify-center font-black text-white text-lg shadow-sm">
                        IT
                    </div>
                    <div>
                        <div class="font-bold text-base leading-tight tracking-tight">CBVH IT Operations</div>
                        <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Help Desk & Technical Portal</div>
                    </div>
                </a>

                <nav class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('portal.overview') }}" 
                       wire:navigate
                       class="{{ request()->routeIs('portal.overview') ? 'bg-slate-800 text-white shadow-xs ring-1 ring-white/10' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <span>Central Overview</span>
                    </a>

                    <a href="{{ route('portal.dashboard') }}" 
                       wire:navigate
                       class="{{ request()->routeIs('portal.dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Ticket Operations</span>
                    </a>

                    <a href="{{ route('portal.link-requests.index') }}" 
                       wire:navigate
                       class="{{ request()->routeIs('portal.link-requests.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Telegram Link Requests</span>
                        @php
                            $pendingLinksBadge = \App\Models\TelegramLinkRequest::where('status', 'pending')->count();
                        @endphp
                        @if($pendingLinksBadge > 0)
                            <span class="bg-amber-500 text-slate-950 text-[10px] font-bold px-1.5 py-0.2 rounded-full">
                                {{ $pendingLinksBadge }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('portal.assets.index') }}" 
                       wire:navigate
                       class="{{ request()->routeIs('portal.assets.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                        <span>🖥️</span>
                        <span>IT Assets</span>
                    </a>

                    @if(Auth::user()->hasRole(['hod', 'system_admin']))
                        <a href="{{ route('portal.cockpit') }}" 
                           wire:navigate
                           class="{{ request()->routeIs('portal.cockpit*') ? 'bg-purple-900/60 text-purple-200 border border-purple-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                            <span>⚡</span>
                            <span>HOD Cockpit</span>
                            @php
                                $cockpitUrgentCount = \App\Models\Ticket::where(function($q) {
                                    $q->whereIn('status', ['blocked', 'reopened', 'pending_approval'])
                                      ->orWhereNotNull('hold_requested_at');
                                })->count();
                                $pendingHoldNavbarCount = \App\Models\Ticket::whereNotNull('hold_requested_at')->count();
                            @endphp
                            @if($cockpitUrgentCount > 0)
                                <span class="bg-purple-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full animate-pulse inline-flex items-center gap-1 cursor-help"
                                      title="⚡ {{ $cockpitUrgentCount }} Actionable Bottlenecks:&#10;• Approvals&#10;• Hold Requests ({{ $pendingHoldNavbarCount }})&#10;• Blocked&#10;• Reopened">
                                    @if($pendingHoldNavbarCount > 0)
                                        <span>⏸</span>
                                    @endif
                                    <span>{{ $cockpitUrgentCount }}</span>
                                </span>
                            @endif
                        </a>

                        <a href="{{ route('portal.admin.index') }}" 
                           wire:navigate
                           class="{{ request()->routeIs('portal.admin.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                            <span>⚙️</span>
                            <span>Admin Settings</span>
                        </a>
                    @endif
                </nav>
            </div>

            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-bold text-white">{{ Auth::user()->name }}</div>
                    <div class="text-[10px] text-blue-400 font-semibold">
                        {{ Auth::user()->roles->pluck('label')->implode(' • ') }}
                    </div>
                </div>

                <div x-data="{ showLogoutModal: false }" x-init="$watch('showLogoutModal', val => document.body.classList.toggle('overflow-hidden', val))">
                    <button type="button" 
                            @click="showLogoutModal = true"
                            class="bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-slate-700 flex items-center space-x-1.5 shadow-2xs">
                        <span>Sign Out</span>
                    </button>

                    <!-- Logout Confirmation Modal -->
                    <template x-teleport="body">
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
                             class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl shadow-slate-950/40 ring-1 ring-slate-900/5 space-y-4 border border-slate-100 text-slate-800 animate-fadeIn">
                            <div class="flex items-center space-x-3.5">
                                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xl shrink-0 border border-rose-100 shadow-2xs">
                                    🚪
                                </div>
                                <div>
                                    <h3 class="text-sm font-extrabold text-slate-900">Sign Out Confirmation</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Are you sure you want to end your current session?</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                                <button type="button" 
                                        @click="showLogoutModal = false" 
                                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                                    Cancel
                                </button>
                                <form method="POST" action="{{ route('portal.logout') }}">
                                    @csrf
                                    <button type="submit" 
                                            class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 active:scale-95 rounded-xl shadow-xs transition">
                                        Yes, Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    </template>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    @if(session('status'))
        <div class="max-w-7xl 2xl:max-w-screen-2xl mx-auto px-4 sm:px-6 mt-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-semibold flex items-center justify-between shadow-xs">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="max-w-7xl 2xl:max-w-screen-2xl mx-auto px-4 sm:px-6 mt-4">
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-xs font-semibold shadow-xs space-y-1">
                @foreach($errors->all() as $error)
                    <div class="flex items-center space-x-2">
                        <span>⚠️</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Main Workspace Content -->
    <main class="flex-1 max-w-7xl 2xl:max-w-screen-2xl w-full mx-auto px-4 sm:px-6 py-6">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        CBVH IT Operations Management Portal • Internal Intranet System
    </footer>

    @livewireScripts
</body>
</html>
