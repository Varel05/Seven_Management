<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <meta name="color-scheme" content="dark">

        <!-- Permanent Dark Mode Initializer -->
        <script>
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Chart.js for Financial Charts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body 
        x-data="{ 
            mobileMenuOpen: false
        }"
        class="font-sans antialiased bg-slate-950 text-slate-100 transition-colors duration-150 relative selection:bg-emerald-500 selection:text-white"
    >
        <!-- Ambient Color Lighting for Dark Mode -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        </div>

        <div class="min-h-screen bg-transparent">
            @include('layouts.navigation')

            <div id="app-main-content" class="flex flex-col min-h-screen pt-16 w-full">
                <!-- Page Heading (Deep Emerald Banner) -->
                @isset($header)
                    <header class="relative bg-emerald-950 dark:bg-slate-900 border-b border-emerald-900/80 dark:border-slate-800 shadow-md text-white overflow-hidden transition-colors">
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
