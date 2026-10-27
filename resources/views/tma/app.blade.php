<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>CBVH IT Helpdesk - Telegram Mini App</title>

    <!-- Telegram WebApp SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Tailwind CSS CDN & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- Google Fonts: Inter & Kantumruy Pro (Khmer UI) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root, html, body {
            color-scheme: light only !important;
            --tg-theme-bg-color: #f8fafc !important;
            --tg-theme-text-color: #0f172a !important;
            --tg-theme-hint-color: #64748b !important;
            --tg-theme-link-color: #2563eb !important;
            --tg-theme-button-color: #2563eb !important;
            --tg-theme-button-text-color: #ffffff !important;
            --tg-theme-secondary-bg-color: #ffffff !important;
        }

        html, body {
            font-family: 'Inter', 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc !important;
            color: #0f172a !important;
            color-scheme: light only !important;
            -webkit-tap-highlight-color: transparent;
            -webkit-text-size-adjust: 100%;
        }

        /* 100% Solid Light Mode Override - Neutralizes iPhone / WebKit Dark Mode */
        @media (prefers-color-scheme: dark) {
            :root, html, body {
                color-scheme: light !important;
                background-color: #f8fafc !important;
                color: #0f172a !important;
            }
            input, textarea, select, .tma-input, .tma-textarea, .tma-select {
                color-scheme: light !important;
                color: #0f172a !important;
                background-color: #ffffff !important;
                -webkit-text-fill-color: #0f172a !important;
                caret-color: #2563eb !important;
            }
            select option, .tma-select option {
                color-scheme: light !important;
                color: #0f172a !important;
                background-color: #ffffff !important;
                -webkit-text-fill-color: #0f172a !important;
            }
        }

        /* Universal Form Controls Fix for iOS WebKit */
        input, textarea, select, .tma-input, .tma-textarea, .tma-select {
            color: #0f172a !important;
            background-color: #ffffff !important;
            -webkit-text-fill-color: #0f172a !important;
            opacity: 1 !important;
            color-scheme: light only !important;
            caret-color: #2563eb !important;
            -webkit-tap-highlight-color: transparent;
        }

        input:focus, textarea:focus, select:focus, .tma-input:focus, .tma-textarea:focus, .tma-select:focus {
            color: #0f172a !important;
            background-color: #ffffff !important;
            -webkit-text-fill-color: #0f172a !important;
            outline: none;
        }

        input::placeholder, textarea::placeholder, .tma-input::placeholder, .tma-textarea::placeholder {
            color: #94a3b8 !important;
            -webkit-text-fill-color: #94a3b8 !important;
            opacity: 1 !important;
        }

        /* Select Dropdowns: Custom iOS-safe Chevron arrow & Solid Light Options */
        select, .tma-select {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
            background-position: right 0.75rem center !important;
            background-repeat: no-repeat !important;
            background-size: 1rem 1rem !important;
            padding-right: 2.25rem !important;
        }

        select option, .tma-select option {
            color: #0f172a !important;
            background-color: #ffffff !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        .tg-theme-card {
            background-color: #ffffff !important;
            color: #0f172a !important;
        }

        .tg-theme-button {
            background-color: #2563eb !important;
            color: #ffffff !important;
        }

        /* Smooth transitions & touch optimization */
        * {
            -webkit-tap-highlight-color: transparent;
        }
        button, a, [role="button"] {
            touch-action: manipulation;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen pb-20 select-none bg-slate-50 text-slate-900">

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

    <!-- AUTH & ONBOARDING SCREENS -->
    @include('tma.partials.auth-screens')

    <!-- MAIN REQUESTER PORTAL (When Authenticated) -->
    <div x-show="!loading && authState === 'authenticated'" class="flex-1 flex flex-col">
        @include('tma.partials.home')
        @include('tma.partials.category-select')
        @include('tma.partials.new-ticket-form')
        @include('tma.partials.tickets-list')
    </div>

    <!-- FULL-SCREEN MODALS & OVERLAYS -->
    @include('tma.partials.chat-page')
    @include('tma.partials.ticket-detail-page')
    @include('tma.partials.lightbox')

</div>

<!-- I18n Translation Dictionary -->
@include('tma.partials.i18n')

<!-- Alpine Application Script -->
@include('tma.partials.scripts')

</body>
</html>
