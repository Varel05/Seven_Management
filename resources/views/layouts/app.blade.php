<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <meta name="color-scheme" content="light dark">

        <!-- Zero-FOUC Theme & Sidebar Initializer (Default Sidebar: Closed) -->
        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            if (localStorage.getItem('sidebar_open') === 'true') {
                document.documentElement.classList.add('sidebar-expanded');
            } else {
                document.documentElement.classList.remove('sidebar-expanded');
            }
        </script>

        <style>
            /* Instant zero-FOUC styles when user explicitly has sidebar opened */
            @media (min-width: 768px) {
                html.sidebar-expanded aside#app-sidebar { width: 16rem !important; }
                html.sidebar-expanded #app-main-content { padding-left: 16rem !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-text-label { display: block !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-mini-divider { display: none !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-category-header { justify-content: space-between !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-link-btn { justify-content: flex-start !important; padding-left: 0.875rem !important; padding-right: 0.875rem !important; gap: 0.75rem !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-toggle-btn { justify-content: flex-start !important; gap: 0.625rem !important; }
                html.sidebar-expanded aside#app-sidebar .sidebar-toggle-icon { transform: rotate(0deg) !important; }
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Chart.js for Financial Charts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body 
        x-data="{ 
            sidebarOpen: localStorage.getItem('sidebar_open') === 'true',
            mobileSidebarOpen: false, 
            darkMode: localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
            toggleSidebar() {
                this.sidebarOpen = !this.sidebarOpen;
                localStorage.setItem('sidebar_open', this.sidebarOpen);
                if (this.sidebarOpen) {
                    document.documentElement.classList.add('sidebar-expanded');
                } else {
                    document.documentElement.classList.remove('sidebar-expanded');
                }
            },
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                if (this.darkMode) {
                    document.documentElement.classList.add('dark');
                    localStorage.theme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.theme = 'light';
                }
            }
        }"
        class="font-sans antialiased bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-150 relative selection:bg-emerald-500 selection:text-white"
    >
        <!-- Ambient Color Lighting for Light & Dark Mode -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 left-1/4 w-[600px] h-[350px] bg-gradient-to-br from-emerald-300/20 via-teal-200/15 to-transparent dark:from-emerald-500/10 dark:via-transparent blur-3xl rounded-full"></div>
            <div class="absolute top-60 -right-20 w-[500px] h-[400px] bg-gradient-to-bl from-blue-300/15 via-indigo-200/10 to-transparent dark:from-blue-500/10 dark:via-transparent blur-3xl rounded-full"></div>
            <div class="absolute bottom-10 left-1/3 w-[450px] h-[300px] bg-gradient-to-tr from-slate-200/40 to-transparent dark:from-transparent blur-3xl rounded-full"></div>
        </div>

        <div class="min-h-screen bg-transparent">
            @include('layouts.navigation')

            <div id="app-main-content" :class="sidebarOpen ? 'md:pl-64' : 'md:pl-16'" class="flex flex-col min-h-screen pt-16 md:pl-16 transition-all duration-300">
                <!-- Page Heading (Deep Emerald Banner) -->
                @isset($header)
                    <header class="relative bg-emerald-950 dark:bg-slate-900 border-b border-emerald-900/80 dark:border-slate-800 shadow-md text-white overflow-hidden transition-colors">
                        <!-- Ambient subtle glowing accents -->
                        <div class="absolute -top-24 -right-24 w-96 h-96 bg-emerald-500/15 dark:bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-teal-400/10 dark:bg-teal-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="max-w-[1800px] w-full mx-auto py-5 px-4 sm:px-6 lg:px-8 xl:px-12 relative z-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
