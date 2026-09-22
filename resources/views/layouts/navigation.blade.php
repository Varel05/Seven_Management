<nav x-data="{ 
    open: false, 
    darkMode: localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) 
}" class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-800 dark:from-slate-900 dark:via-emerald-950/80 dark:to-slate-900 border-b border-emerald-900/50 dark:border-slate-800 shadow-sm transition-colors sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-6">
                <!-- Logo & Brand -->
                <div class="shrink-0 flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-emerald-900/60 dark:bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-extrabold text-base tracking-tight text-white leading-tight">
                                Seven<span class="text-emerald-200 dark:text-emerald-400">Ledger</span>
                            </span>
                            <span class="text-[10px] font-bold text-emerald-100/80 dark:text-slate-400 uppercase tracking-wider">Accounting AI</span>
                        </div>
                    </a>
                </div>

                <!-- AI Status Pill -->
                <div class="hidden md:flex items-center">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-900/50 text-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-600/40 dark:border-emerald-800 shadow-2xs backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                        AI Agent (n8n & Telegram) Aktif
                    </span>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-4 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <svg class="w-4 h-4 me-1.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                        {{ __('Ikhtisar Keuangan') }}
                    </x-nav-link>
                    <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                        <svg class="w-4 h-4 me-1.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        {{ __('Karyawan & Payroll') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Header Right: Dark Mode Toggle & Settings -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 space-x-3">
                <!-- Dark / Light Mode Switcher Button -->
                <button @click="
                    darkMode = !darkMode;
                    if (darkMode) {
                        document.documentElement.classList.add('dark');
                        localStorage.theme = 'dark';
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.theme = 'light';
                    }
                " type="button" class="p-2.5 rounded-xl text-emerald-100 hover:text-white dark:text-slate-400 dark:hover:text-slate-100 hover:bg-emerald-900/50 dark:hover:bg-slate-800 focus:outline-none transition-all duration-150 border border-emerald-600/40 dark:border-slate-700/80 bg-emerald-900/35 dark:bg-slate-800 shadow-2xs" title="Beralih Tema Terang / Gelap" aria-label="Toggle Dark Mode">
                    <!-- Sun Icon (in dark mode) -->
                    <svg x-show="darkMode" x-cloak class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <!-- Moon Icon (in light mode) -->
                    <svg x-show="!darkMode" class="w-5 h-5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                <!-- Profile Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3.5 py-2 border border-emerald-600/40 dark:border-slate-700/80 text-sm font-semibold rounded-xl text-white dark:text-slate-200 bg-emerald-900/35 hover:bg-emerald-900/50 dark:bg-slate-800 dark:hover:bg-slate-700/50 shadow-2xs focus:outline-none transition ease-in-out duration-150">
                            <div class="w-6 h-6 rounded-lg bg-emerald-950/80 text-white flex items-center justify-center font-bold text-xs me-2 shadow-xs border border-emerald-500/40">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1.5 text-emerald-200 dark:text-slate-400">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700/80">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Login sebagai</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ Auth::user()->email }}</p>
                        </div>
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profil Pengguna') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Keluar (Log Out)') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden space-x-2">
                <!-- Mobile Theme Toggle -->
                <button @click="
                    darkMode = !darkMode;
                    if (darkMode) {
                        document.documentElement.classList.add('dark');
                        localStorage.theme = 'dark';
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.theme = 'light';
                    }
                " type="button" class="p-2 rounded-lg text-emerald-100 hover:text-white dark:text-slate-400 dark:hover:text-slate-200 hover:bg-emerald-900/50 dark:hover:bg-slate-800">
                    <svg x-show="darkMode" x-cloak class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg x-show="!darkMode" class="w-5 h-5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>

                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-lg text-emerald-100 hover:text-white dark:text-slate-400 dark:hover:text-slate-300 hover:bg-emerald-900/50 dark:hover:bg-slate-800 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-emerald-900 dark:border-slate-800 bg-emerald-800 dark:bg-slate-900">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard & Jurnal') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                {{ __('Karyawan & Payroll') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-3 border-t border-emerald-900/80 dark:border-slate-800">
            <div class="px-4">
                <div class="font-medium text-base text-white dark:text-slate-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-emerald-200/80 dark:text-slate-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profil') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Keluar (Log Out)') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
