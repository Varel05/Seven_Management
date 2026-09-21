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

        <!-- Zero-FOUC Theme Initializer -->
        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-150 relative selection:bg-emerald-500 selection:text-white">
        <!-- Ambient Color Lighting for Light & Dark Mode -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 left-1/4 w-[600px] h-[350px] bg-gradient-to-br from-emerald-200/35 via-teal-100/25 to-transparent dark:from-emerald-500/10 dark:via-transparent blur-3xl rounded-full"></div>
            <div class="absolute top-60 -right-20 w-[500px] h-[400px] bg-gradient-to-bl from-blue-200/30 via-indigo-100/20 to-transparent dark:from-blue-500/10 dark:via-transparent blur-3xl rounded-full"></div>
            <div class="absolute bottom-10 left-1/3 w-[450px] h-[300px] bg-gradient-to-tr from-rose-100/25 to-transparent dark:from-transparent blur-3xl rounded-full"></div>
        </div>

        <div class="min-h-screen bg-transparent">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white/80 dark:bg-slate-900/85 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800 shadow-xs transition-colors">
                    <div class="max-w-[1800px] w-full mx-auto py-5 px-4 sm:px-6 lg:px-8 xl:px-12">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
