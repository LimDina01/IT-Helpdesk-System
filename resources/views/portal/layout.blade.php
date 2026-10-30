<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Operations Portal') - CBVH IT Help Desk</title>

    <!-- Tailwind CSS CDN & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full flex flex-col antialiased text-slate-800">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl 2xl:max-w-screen-2xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            
            <!-- Brand & Navigation Links -->
            <div class="flex items-center space-x-8">
                <a href="{{ route('portal.dashboard') }}" class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-xl flex items-center justify-center font-black text-white text-lg shadow-sm">
                        IT
                    </div>
                    <div>
                        <div class="font-bold text-base leading-tight tracking-tight">CBVH IT Operations</div>
                        <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Help Desk & Technical Portal</div>
                    </div>
                </a>

                <nav class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('portal.dashboard') }}" 
                       class="{{ request()->routeIs('portal.dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }} px-3 py-2 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Ticket Operations</span>
                    </a>

                    <a href="{{ route('portal.link-requests.index') }}" 
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

                    @if(Auth::user()->hasRole(['hod', 'system_admin']))
                        <a href="{{ route('portal.cockpit') }}" 
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
                                <span class="bg-purple-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full animate-pulse inline-flex items-center gap-1" title="{{ $cockpitUrgentCount }} urgent items ({{ $pendingHoldNavbarCount }} hold requests)">
                                    @if($pendingHoldNavbarCount > 0)
                                        <span>⏸</span>
                                    @endif
                                    <span>{{ $cockpitUrgentCount }}</span>
                                </span>
                            @endif
                        </a>

                        <a href="{{ route('portal.admin.index') }}" 
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

                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-slate-700">
                        Sign Out
                    </button>
                </form>
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

</body>
</html>
