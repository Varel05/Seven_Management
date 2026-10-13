<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SevenLedger') }} - Portal Akuntansi</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <meta name="color-scheme" content="dark">

        <!-- Permanent Dark Mode Initializer -->
        <script>
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body 
        class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150 selection:bg-emerald-500 selection:text-white relative overflow-x-hidden"
    >
        <!-- Top Header / Bar -->
        <header class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex items-center justify-between relative z-10">
            <a href="/" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-base tracking-tight text-white leading-tight">
                        Seven<span class="text-emerald-400">Ledger</span>
                    </span>
                    <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider">Accounting AI</span>
                </div>
            </a>
        </header>

        <!-- Main Content (Center) -->
        <main class="w-full flex items-center justify-center p-4 sm:p-6 relative z-10 flex-1">
            <div class="w-full sm:max-w-lg">
                {{ $slot }}
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full text-center py-6 text-xs text-slate-500 relative z-10 flex flex-col items-center gap-1.5">
            <div class="flex items-center gap-2">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Double-Entry Ledger Security System</span>
                <span>•</span>
                <span>AI Accounting Agent</span>
            </div>
            <p>&copy; {{ date('Y') }} SevenLedger. All rights reserved.</p>
        </footer>
    </body>
</html>
