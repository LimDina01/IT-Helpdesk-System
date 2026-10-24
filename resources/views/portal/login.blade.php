<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - CBVH IT Operations</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center p-4">

<div class="max-w-md w-full space-y-6">

    <!-- Header Logo & Title -->
    <div class="text-center space-y-2">
        <div class="w-14 h-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-2xl flex items-center justify-center font-black text-white text-2xl mx-auto shadow-lg shadow-blue-500/20">
            IT
        </div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">CBVH IT Operations</h1>
        <p class="text-xs text-slate-400 font-medium">Help Desk & Technician Management Portal</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-3xl p-8 shadow-2xl border border-slate-100 space-y-5">
        
        <!-- Auth Backend Status Indicator -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 text-xs">
            <span class="text-slate-500 font-medium">Auth System:</span>
            @if(config('services.ldap.enabled'))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" title="Attempts CBVH Active Directory first, falls back to local accounts">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Active Directory (LDAP) + Local
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200" title="Active Directory is toggled off in .env. Using local database accounts.">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Local / Sample Accounts Mode
                </span>
            @endif
        </div>

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-xl text-xs font-semibold">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-xl text-xs font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.login.submit') }}" class="space-y-4" id="loginForm">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Staff Email, Username, or Employee Code</label>
                <input type="text" name="email" id="emailInput" value="{{ old('email') }}" required autofocus
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-600 text-sm font-medium transition"
                       placeholder="e.g. tech1, admin@cbvh.com.kh, or EMP-1005">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password</label>
                </div>
                <input type="password" name="password" id="passwordInput" required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-600 text-sm font-medium transition"
                       placeholder="••••••••••••">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center space-x-2 text-slate-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>Remember my session</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm shadow-md shadow-blue-600/30 transition hover:scale-[1.01] active:scale-[0.99]">
                Sign In to Portal
            </button>
        </form>

        <!-- Quick 1-Click Demo Accounts -->
        <div class="pt-4 border-t border-slate-100 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Quick Sample Login (1-Click)</span>
                <span class="text-[10px] text-slate-400 font-mono">Pass: Password123!</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <button type="button" onclick="fillCreds('admin@cbvh.com.kh')" class="px-2.5 py-2 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 rounded-xl border border-slate-200 text-left font-medium transition flex flex-col">
                    <span class="font-bold text-slate-800">👑 HOD / Admin</span>
                    <span class="text-[10px] text-slate-500">admin@cbvh.com.kh</span>
                </button>
                <button type="button" onclick="fillCreds('helpdesk@cbvh.com.kh')" class="px-2.5 py-2 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 rounded-xl border border-slate-200 text-left font-medium transition flex flex-col">
                    <span class="font-bold text-slate-800">🎧 Help Desk</span>
                    <span class="text-[10px] text-slate-500">helpdesk@cbvh.com.kh</span>
                </button>
                <button type="button" onclick="fillCreds('tech1@cbvh.com.kh')" class="px-2.5 py-2 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 rounded-xl border border-slate-200 text-left font-medium transition flex flex-col">
                    <span class="font-bold text-slate-800">🛠 Tech 1 (Dara)</span>
                    <span class="text-[10px] text-slate-500">tech1@cbvh.com.kh</span>
                </button>
                <button type="button" onclick="fillCreds('tech2@cbvh.com.kh')" class="px-2.5 py-2 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 rounded-xl border border-slate-200 text-left font-medium transition flex flex-col">
                    <span class="font-bold text-slate-800">⚡ Tech 2 (Multi-role)</span>
                    <span class="text-[10px] text-slate-500">tech2@cbvh.com.kh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Switcher to TMA -->
    <div class="text-center">
        <a href="{{ route('tma.index') }}" class="text-xs text-slate-400 hover:text-white font-medium underline">
            &larr; Switch to Telegram Mini App (Requester Portal)
        </a>
    </div>

</div>

<script>
function fillCreds(email) {
    document.getElementById('emailInput').value = email;
    document.getElementById('passwordInput').value = 'Password123!';
    document.getElementById('loginForm').submit();
}
</script>

</body>
</html>
