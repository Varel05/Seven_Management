<div>
    <!-- ========================================================================= -->
    <!-- TOP HEADER BAR (Navbar Atas: Nama Aplikasi, Trigger, Status AI, Tema, Profil & Logout) -->
    <!-- ========================================================================= -->
    <header class="fixed top-0 inset-x-0 h-16 z-40 bg-gradient-to-r from-emerald-950 via-emerald-900 to-slate-950 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 border-b border-emerald-800/60 dark:border-slate-800 shadow-md backdrop-blur-md px-3 sm:px-6 flex items-center justify-between">
        
        <!-- Sisi Kiri: Tombol Trigger + Brand Logo & Nama Aplikasi -->
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Tombol Trigger Buka / Tutup / Ciutkan Sidebar -->
            <button @click="if (window.innerWidth < 768) { mobileSidebarOpen = !mobileSidebarOpen } else { toggleSidebar() }" 
                    type="button" 
                    class="p-2 rounded-xl text-emerald-200 hover:text-white hover:bg-emerald-800/50 dark:hover:bg-slate-800 transition-colors cursor-pointer border border-emerald-700/30 dark:border-slate-700/50 shadow-xs"
                    title="Buka / Ciutkan Sidebar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Brand Logo & Nama Aplikasi -->
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 sm:gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-slate-950 flex items-center justify-center font-black shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-base sm:text-lg tracking-tight text-white leading-tight">
                        Seven<span class="text-emerald-400">Ledger</span>
                    </span>
                    <span class="text-[9px] sm:text-[10px] font-bold text-emerald-200/70 dark:text-slate-400 uppercase tracking-widest leading-none">
                        Management System
                    </span>
                </div>
            </a>
        </div>

        <!-- Sisi Kanan: Status AI Agent, Mode Gelap, Profil Pengguna & Logout -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Status AI Agent -->
            <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-900/40 dark:bg-slate-800/80 border border-emerald-700/30 dark:border-slate-700/60 text-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-semibold text-emerald-200 dark:text-emerald-400 text-xs">AI Agent</span>
                <span class="text-[9px] font-mono text-emerald-300/80 bg-emerald-950 px-1.5 py-0.5 rounded border border-emerald-800/50">n8n</span>
            </div>

            <!-- Tombol Toggle Tema Gelap / Terang -->
            <button @click="toggleDarkMode()" 
                    type="button" 
                    class="p-2 rounded-xl text-emerald-200 hover:text-white dark:text-slate-300 hover:bg-emerald-800/50 dark:hover:bg-slate-800 transition-colors cursor-pointer border border-emerald-700/30 dark:border-slate-700/50"
                    title="Beralih Mode Gelap / Terang">
                <svg x-show="darkMode" x-cloak class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                <svg x-show="!darkMode" class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
            </button>

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

    <!-- Backdrop Layar untuk Sidebar Mobile -->
    <div x-show="mobileSidebarOpen" 
         x-cloak 
         @click="mobileSidebarOpen = false" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-40 md:hidden">
    </div>

    <!-- ========================================================================= -->
    <!-- SIDEBAR NAVIGASI (Fixed di Samping Bawah Top Header) -->
    <!-- Mode Lebar: w-64 (Icon + Teks) | Mode Ciut: w-16 (Hanya Icon) -->
    <!-- ========================================================================= -->
    <aside :class="[
               mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
               sidebarOpen ? 'md:w-64' : 'md:w-16'
           ]"
           class="fixed top-16 bottom-0 left-0 z-40 w-64 bg-gradient-to-b from-emerald-950 via-emerald-950 to-slate-950 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 border-r border-emerald-800/50 dark:border-slate-800 shadow-2xl flex flex-col justify-between transition-all duration-300 ease-in-out">
        
        <!-- Bagian Navigasi Menu Terkategori -->
        <div class="flex-1 flex flex-col min-h-0 overflow-y-auto overflow-x-hidden custom-scrollbar py-3">
            
            <!-- Mobile Close Button Header -->
            <div class="px-4 pb-2 flex items-center justify-between md:hidden border-b border-emerald-800/40 mb-2">
                <span class="text-xs font-bold text-emerald-300">Menu Navigasi</span>
                <button @click="mobileSidebarOpen = false" type="button" class="p-1 rounded-lg text-emerald-300 hover:text-white hover:bg-emerald-800/50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="px-2 space-y-5">

                <!-- ============================================================= -->
                <!-- KELOMPOK 1: MANAJEMEN -->
                <!-- ============================================================= -->
                <div class="space-y-1">
                    <!-- Heading Kategori Manajemen -->
                    <div class="px-2 py-1 flex items-center" :class="sidebarOpen ? 'justify-between' : 'md:justify-center justify-between'">
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block text-[10px] font-black uppercase tracking-wider text-emerald-400 dark:text-emerald-500 truncate">
                            Manajemen
                        </span>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <!-- Mini Divider ketika sidebar ciut -->
                        <div :class="sidebarOpen ? 'md:hidden' : 'md:block'" class="hidden w-6 h-0.5 bg-emerald-700/50 rounded-full my-1.5" title="Manajemen"></div>
                    </div>

                    <!-- 1. Ikhtisar Keuangan -->
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" title="Ikhtisar Keuangan">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Ikhtisar Keuangan</span>
                    </x-sidebar-link>

                    <!-- 2. Retail & Stok Baju -->
                    <x-sidebar-link :href="route('retail.index')" :active="request()->routeIs('retail.*')" title="Retail & Stok Baju">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Retail & Stok Baju</span>
                    </x-sidebar-link>

                    <!-- 3. Karyawan & Payroll -->
                    <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" title="Karyawan & Payroll">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Karyawan & Payroll</span>
                    </x-sidebar-link>
                </div>

                <!-- ============================================================= -->
                <!-- KELOMPOK 2: PRODUKSI & HPP -->
                <!-- ============================================================= -->
                <div class="space-y-1">
                    <!-- Heading Kategori Produksi -->
                    <div class="px-2 py-1 flex items-center" :class="sidebarOpen ? 'justify-between' : 'md:justify-center justify-between'">
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block text-[10px] font-black uppercase tracking-wider text-amber-400 dark:text-amber-500 truncate">
                            Produksi & HPP
                        </span>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                        <!-- Mini Divider ketika sidebar ciut -->
                        <div :class="sidebarOpen ? 'md:hidden' : 'md:block'" class="hidden w-6 h-0.5 bg-amber-500/50 rounded-full my-1.5" title="Produksi & HPP"></div>
                    </div>

                    <!-- 4. Jas Custom & AI -->
                    <x-sidebar-link :href="route('custom-orders.index')" :active="request()->routeIs('custom-orders.*')" title="Jas Custom & AI">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Jas Custom & AI</span>
                    </x-sidebar-link>

                    <!-- 5. Stok Bahan Baku -->
                    <x-sidebar-link :href="route('materials.index')" :active="request()->routeIs('materials.*')" title="Stok Bahan Baku">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Stok Bahan Baku</span>
                    </x-sidebar-link>

                    <!-- 6. Kartu HPP & BOM -->
                    <x-sidebar-link :href="route('cost-sheets.index')" :active="request()->routeIs('cost-sheets.*') && !request()->routeIs('production.*')" title="Kartu HPP (BOM)">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Kartu HPP (BOM)</span>
                    </x-sidebar-link>

                    <!-- 7. Produksi Pakaian (Batch & Potong Bahan) -->
                    <x-sidebar-link :href="route('production.create')" :active="request()->routeIs('production.*')" title="Produksi Baju">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block truncate">Produksi Baju</span>
                    </x-sidebar-link>
                </div>

            </nav>
        </div>

        <!-- Bagian Bawah: Tombol Perkecil / Perluas Sidebar di Desktop -->
        <div class="p-2 border-t border-emerald-800/40 dark:border-slate-800/60 bg-emerald-950/40 dark:bg-slate-950/40 hidden md:block">
            <button @click="toggleSidebar()" 
                    type="button" 
                    class="w-full flex items-center rounded-xl text-xs font-semibold text-emerald-200 hover:text-white hover:bg-emerald-800/40 dark:hover:bg-slate-800 transition-colors p-2.5 cursor-pointer"
                    :class="sidebarOpen ? 'justify-start gap-2.5' : 'justify-center'"
                    :title="sidebarOpen ? 'Ciutkan Sidebar' : 'Perluas Sidebar'">
                <svg :class="sidebarOpen ? '' : 'rotate-180'" class="w-4 h-4 transition-transform duration-300 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
                <span :class="sidebarOpen ? 'md:block' : 'md:hidden'" class="block text-[11px] font-bold tracking-tight truncate">Ciutkan Menu</span>
            </button>
        </div>

    </aside>
</div>
