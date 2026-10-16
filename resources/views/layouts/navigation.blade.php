<div>
    <!-- ========================================================================= -->
    <!-- TOP HEADER BAR (Navbar Utama: Logo, Navigasi Menu, Tema, Profil & Logout) -->
    <!-- ========================================================================= -->
    <header class="fixed top-0 inset-x-0 h-16 z-40 bg-emerald-950 border-b border-emerald-800/60 dark:border-slate-800 shadow-md backdrop-blur-md px-3 sm:px-6 flex items-center justify-between">
        
        <!-- Sisi Kiri: Tombol Menu Mobile (< 768px) + Brand Logo & Nama Aplikasi -->
        <div class="flex items-center gap-3">
            <!-- Tombol Toggle Menu Mobile (Hanya Tampil di Layar HP) -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" 
                    type="button" 
                    class="md:hidden p-2 rounded-xl text-emerald-200 hover:text-white hover:bg-emerald-800/50 dark:hover:bg-slate-800 transition-colors cursor-pointer border border-emerald-700/30 dark:border-slate-700/50 shadow-xs"
                    title="Menu Navigasi Mobile">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path :class="mobileMenuOpen ? 'hidden' : 'block'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="mobileMenuOpen ? 'block' : 'hidden'" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Brand Logo & Nama Aplikasi -->
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 sm:gap-3 group">
                <x-application-logo />
                <div class="flex flex-col">
                    <span class="font-black text-base sm:text-lg tracking-tight text-white leading-tight">
                        Seven<span class="text-emerald-400">Accounting</span>
                    </span>
                    <span class="text-[9px] sm:text-[10px] font-bold text-emerald-200/70 dark:text-slate-400 uppercase tracking-widest leading-none">
                        Management System
                    </span>
                </div>
            </a>
        </div>

        <!-- Bagian Tengah: Navigasi Menu Utama di Header (Desktop & Tablet) -->
        <nav class="hidden md:flex items-center gap-1 lg:gap-2 mx-2 lg:mx-4 h-full">
            <!-- 1. Ikhtisar Keuangan -->
            <a href="{{ route('dashboard') }}" 
               class="h-full flex items-center gap-2 px-3 text-xs tracking-wide transition-colors border-b-2 {{ request()->routeIs('dashboard') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-200/70 hover:text-white hover:border-emerald-400/40 font-medium' }}"
               title="Ikhtisar Keuangan & Transaksi">
                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-400' : 'text-emerald-400/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Ikhtisar Keuangan</span>
            </a>

            <!-- 2. Pajak PPh Final -->
            <a href="{{ route('taxes.index') }}" 
               class="h-full flex items-center gap-2 px-3 text-xs tracking-wide transition-colors border-b-2 {{ request()->routeIs('taxes.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-200/70 hover:text-white hover:border-emerald-400/40 font-medium' }}"
               title="Perhitungan & Pelaporan Pajak PPh Final">
                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('taxes.*') ? 'text-emerald-400' : 'text-emerald-400/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Pajak PPh Final</span>
            </a>

            <!-- 3. Gaji Karyawan -->
            <a href="{{ route('employees.index') }}" 
               class="h-full flex items-center gap-2 px-3 text-xs tracking-wide transition-colors border-b-2 {{ request()->routeIs('employees.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-200/70 hover:text-white hover:border-emerald-400/40 font-medium' }}"
               title="Manajemen Gaji Karyawan & Payroll">
                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('employees.*') ? 'text-emerald-400' : 'text-emerald-400/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Gaji Karyawan</span>
            </a>

            <!-- 4. Upah Penjahit -->
            <a href="{{ route('tailor-payrolls.index') }}" 
               class="h-full flex items-center gap-2 px-3 text-xs tracking-wide transition-colors border-b-2 {{ request()->routeIs('tailor-payrolls.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-200/70 hover:text-white hover:border-emerald-400/40 font-medium' }}"
               title="Sistem Upah Buruh Borongan & Slip">
                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('tailor-payrolls.*') ? 'text-emerald-400' : 'text-emerald-400/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>Upah Penjahit</span>
            </a>
        </nav>

        <!-- Sisi Kanan: Profil Pengguna & Logout -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Profil User & Pengaturan -->
            <a href="{{ route('profile.edit') }}" 
               class="flex items-center gap-2 px-2 py-1.5 rounded-xl hover:bg-emerald-800/40 dark:hover:bg-slate-800/60 transition-colors group border border-transparent hover:border-emerald-700/30"
               title="Pengaturan Profil ({{ Auth::user()->name }})">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold text-xs shrink-0 border border-emerald-500/30 group-hover:scale-105 transition-transform">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="hidden lg:flex flex-col text-left">
                    <span class="text-xs font-bold text-white leading-tight group-hover:text-emerald-300 transition-colors">{{ Auth::user()->name }}</span>
                    <span class="text-[10px] text-emerald-300/70 dark:text-slate-400 max-w-[120px] truncate">{{ Auth::user()->email }}</span>
                </div>
            </a>

            <!-- Tombol Keluar (Logout) -->
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" 
                        class="p-2 rounded-xl text-rose-300 hover:text-rose-100 hover:bg-rose-950/40 transition-colors cursor-pointer border border-rose-900/40" 
                        title="Keluar / Logout">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </button>
            </form>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- MENU NAVIGASI MOBILE (Drop-down Menu Layar Kecil / Smartphone) -->
    <!-- ========================================================================= -->
    <div x-show="mobileMenuOpen" 
         x-cloak 
         @click.away="mobileMenuOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="fixed top-16 inset-x-0 z-30 md:hidden bg-emerald-950/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-emerald-800/60 dark:border-slate-800 p-3 space-y-1 shadow-2xl">
        
        <!-- 1. Ikhtisar Keuangan -->
        <a href="{{ route('dashboard') }}" 
           @click="mobileMenuOpen = false"
           class="flex items-center gap-3 px-3 py-2.5 text-xs transition-colors border-b-2 {{ request()->routeIs('dashboard') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-100 hover:text-white font-medium' }}">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Ikhtisar Keuangan</span>
        </a>

        <!-- 2. Pajak PPh Final -->
        <a href="{{ route('taxes.index') }}" 
           @click="mobileMenuOpen = false"
           class="flex items-center gap-3 px-3 py-2.5 text-xs transition-colors border-b-2 {{ request()->routeIs('taxes.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-100 hover:text-white font-medium' }}">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Pajak PPh Final</span>
        </a>

        <!-- 3. Gaji Karyawan -->
        <a href="{{ route('employees.index') }}" 
           @click="mobileMenuOpen = false"
           class="flex items-center gap-3 px-3 py-2.5 text-xs transition-colors border-b-2 {{ request()->routeIs('employees.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-100 hover:text-white font-medium' }}">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Gaji Karyawan</span>
        </a>

        <!-- 4. Upah Penjahit -->
        <a href="{{ route('tailor-payrolls.index') }}" 
           @click="mobileMenuOpen = false"
           class="flex items-center gap-3 px-3 py-2.5 text-xs transition-colors border-b-2 {{ request()->routeIs('tailor-payrolls.*') ? 'border-emerald-400 text-white font-bold' : 'border-transparent text-emerald-100 hover:text-white font-medium' }}">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span>Upah Penjahit</span>
        </a>
    </div>
</div>
