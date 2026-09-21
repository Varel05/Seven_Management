<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                        {{ __('Ikhtisar Keuangan & Buku Jurnal') }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/80 dark:bg-slate-800 text-emerald-200 dark:text-emerald-300 border border-emerald-700/80 dark:border-slate-700 backdrop-blur-md shadow-2xs">
                        Periode {{ now()->translatedFormat('F Y') }}
                    </span>
                </div>
                <p class="text-xs text-emerald-200/80 dark:text-slate-400 mt-1 font-medium">
                    Pencatatan akuntansi double-entry otomatis via Bot Telegram & AI Agent (n8n)
                </p>
            </div>

            <!-- Header Quick Stats / Sync Status -->
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-900/80 dark:bg-emerald-950/60 border border-emerald-700/80 dark:border-emerald-800 text-emerald-200 dark:text-emerald-300 text-xs font-semibold shadow-xs backdrop-blur-md">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-300"></span>
                    </span>
                    Webhook Telegram Terhubung
                </div>
                <span class="text-xs text-emerald-600 dark:text-slate-700">|</span>
                <span class="text-xs text-emerald-200/90 dark:text-slate-400 font-medium">
                    <strong class="font-extrabold text-white dark:text-slate-200 text-sm">{{ $verifiedCount ?? 0 }}</strong> Terverifikasi
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        filterStatus: 'all',
        searchQuery: '',
        perPage: 10,
        currentPage: 1,
        selectedTrx: null,
        showDetailModal: false,
        items: {{ Js::from($transactions->map(function($t) {
            $firstLine = $t->lines->first();
            $expenseLine = $t->lines->first(fn($l) => $l->account && $l->account->type === 'expense');
            $revenueLine = $t->lines->first(fn($l) => $l->account && $l->account->type === 'revenue');
            $accountName = $expenseLine ? ($expenseLine->account->name ?? '') : ($revenueLine ? ($revenueLine->account->name ?? '') : ($firstLine->account->name ?? ''));
            return [
                'id'     => $t->id,
                'status' => $t->status,
                'search' => strtolower($t->description . ' ' . $t->reference . ' ' . $accountName),
            ];
        })) }},
        get filteredItems() {
            return this.items.filter(item => {
                const matchStatus = this.filterStatus === 'all' || this.filterStatus === item.status;
                const matchSearch = !this.searchQuery || item.search.includes(this.searchQuery.toLowerCase().trim());
                return matchStatus && matchSearch;
            });
        },
        get visibleCount() {
            return this.filteredItems.length;
        },
        get totalPages() {
            if (this.perPage === 'all') return 1;
            const limit = Number(this.perPage);
            return Math.max(1, Math.ceil(this.filteredItems.length / limit));
        },
        get paginatedItemIds() {
            if (this.perPage === 'all') {
                return this.filteredItems.map(i => i.id);
            }
            const limit = Number(this.perPage);
            const start = (this.currentPage - 1) * limit;
            return this.filteredItems.slice(start, start + limit).map(i => i.id);
        },
        isRowVisible(id) {
            return this.paginatedItemIds.includes(id);
        },
        get displayStart() {
            if (this.filteredItems.length === 0) return 0;
            if (this.perPage === 'all') return 1;
            return (this.currentPage - 1) * Number(this.perPage) + 1;
        },
        get displayEnd() {
            if (this.filteredItems.length === 0) return 0;
            if (this.perPage === 'all') return this.filteredItems.length;
            return Math.min(this.currentPage * Number(this.perPage), this.filteredItems.length);
        },
        goToPage(p) {
            if (p >= 1 && p <= this.totalPages) {
                this.currentPage = p;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        get pageNumbers() {
            const total = this.totalPages;
            const current = this.currentPage;
            const delta = 2;
            const range = [];
            for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                range.push(i);
            }
            if (current - delta > 2) {
                range.unshift('...');
            }
            if (current + delta < total - 1) {
                range.push('...');
            }
            range.unshift(1);
            if (total > 1) {
                range.push(total);
            }
            return range;
        },
        init() {
            this.$watch('filterStatus', () => { this.currentPage = 1; });
            this.$watch('searchQuery', () => { this.currentPage = 1; });
            this.$watch('perPage', () => { this.currentPage = 1; });
        },
        openModal(trx) {
            this.selectedTrx = trx;
            this.showDetailModal = true;
        },
        closeModal() {
            this.showDetailModal = false;
            this.selectedTrx = null;
        }
    }">
    <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-8">

            <!-- Flash Notification Alerts -->
            @if(session('success'))
                <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 text-sm flex items-center gap-3 shadow-sm transition-all">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-sm flex items-center gap-3 shadow-sm transition-all">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            <!-- 1. VIBRANT FINANCIAL KPI CARDS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                
                <!-- Card 1: Saldo Kas & Bank (Liquid Assets) -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-blue-300 dark:hover:border-slate-700 transition-all group">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-blue-300 uppercase tracking-wider">Kas & Bank</span>
                        <div class="p-2.5 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-slate-900 dark:text-white">
                            Rp {{ number_format($totalKasDanBank ?? $totalKas ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            <span>Kas Ops: Rp {{ number_format($totalKas ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Pemasukan Bulan Ini (Revenue) -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-emerald-300 dark:hover:border-slate-700 transition-all group">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-emerald-300 uppercase tracking-wider">Pendapatan</span>
                        <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format($pemasukanBulanIni ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Total Akun Revenue</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Pengeluaran Bulan Ini (Expenses) -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-rose-300 dark:hover:border-slate-700 transition-all group">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-rose-500 to-pink-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-rose-300 uppercase tracking-wider">Beban Usaha</span>
                        <div class="p-2.5 rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" /></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-rose-700 dark:text-rose-400">
                            Rp {{ number_format($pengeluaranBulanIni ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span>Total Akun Beban</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Laba / Rugi Bersih (Net Profit/Loss) -->
                @php
                    $isProfit = ($labaBersihBulanIni ?? 0) >= 0;
                @endphp
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-teal-300 dark:hover:border-slate-700 transition-all group">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-teal-500 to-cyan-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-teal-300 uppercase tracking-wider">Laba / Rugi Bersih</span>
                        <div class="p-2.5 rounded-xl {{ $isProfit ? 'bg-teal-50 text-teal-600 dark:bg-teal-600 dark:text-white' : 'bg-rose-50 text-rose-600 dark:bg-rose-600 dark:text-white' }} shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight {{ $isProfit ? 'text-teal-700 dark:text-teal-400' : 'text-rose-700 dark:text-rose-400' }}">
                            {{ $isProfit ? '+' : '-' }} Rp {{ number_format(abs($labaBersihBulanIni ?? 0), 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs {{ $isProfit ? 'text-teal-700/80 dark:text-teal-400' : 'text-rose-700/80 dark:text-rose-400' }} font-medium">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isProfit ? 'bg-teal-500' : 'bg-rose-500' }}"></span>
                            <span>{{ $isProfit ? 'Surplus (Untung)' : 'Defisit (Rugi)' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Perlu Audit / Verifikasi Telegram -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-amber-300 dark:hover:border-slate-700 transition-all group">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-amber-300 uppercase tracking-wider">Perlu Audit AI</span>
                        <div class="p-2.5 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-amber-700 dark:text-amber-400">
                            {{ $pendingCount ?? 0 }} <span class="text-sm font-sans font-normal text-slate-500 dark:text-slate-400">Trx</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            <span>Menunggu Review</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 2. COLOR-CODED CHART OF ACCOUNTS (COA) SUMMARY BAR -->
            @if(isset($accounts) && $accounts->count() > 0)
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-2">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Bagan Akun Finansial (Chart of Accounts)
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Saldo kumulatif per akun buku besar utama</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            {{ $accounts->count() }} akun aktif
                        </span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3.5 mt-5">
                        @foreach($accounts as $acc)
                            @php
                                $typeConfigs = [
                                    'asset' => [
                                        'box' => 'border-sky-200/90 dark:border-sky-900/50 bg-gradient-to-br from-sky-50 via-sky-50/40 to-white dark:from-slate-800 dark:to-slate-900 text-sky-950 dark:text-sky-200',
                                        'badge' => 'bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
                                        'code' => 'text-sky-700 dark:text-sky-400',
                                    ],
                                    'revenue' => [
                                        'box' => 'border-emerald-200/90 dark:border-emerald-900/50 bg-gradient-to-br from-emerald-50 via-emerald-50/40 to-white dark:from-slate-800 dark:to-slate-900 text-emerald-950 dark:text-emerald-200',
                                        'badge' => 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                                        'code' => 'text-emerald-700 dark:text-emerald-400',
                                    ],
                                    'expense' => [
                                        'box' => 'border-rose-200/90 dark:border-rose-900/50 bg-gradient-to-br from-rose-50 via-rose-50/40 to-white dark:from-slate-800 dark:to-slate-900 text-rose-950 dark:text-rose-200',
                                        'badge' => 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                                        'code' => 'text-rose-700 dark:text-rose-400',
                                    ],
                                    'liability' => [
                                        'box' => 'border-purple-200/90 dark:border-purple-900/50 bg-gradient-to-br from-purple-50 via-purple-50/40 to-white dark:from-slate-800 dark:to-slate-900 text-purple-950 dark:text-purple-200',
                                        'badge' => 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
                                        'code' => 'text-purple-700 dark:text-purple-400',
                                    ],
                                    'equity' => [
                                        'box' => 'border-amber-200/90 dark:border-amber-900/50 bg-gradient-to-br from-amber-50 via-amber-50/40 to-white dark:from-slate-800 dark:to-slate-900 text-amber-950 dark:text-amber-200',
                                        'badge' => 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                                        'code' => 'text-amber-700 dark:text-amber-400',
                                    ],
                                ];
                                $cfg = $typeConfigs[$acc['type']] ?? [
                                    'box' => 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200',
                                    'badge' => 'bg-slate-100 text-slate-700',
                                    'code' => 'text-slate-600',
                                ];
                            @endphp
                            <div class="p-3.5 rounded-2xl border {{ $cfg['box'] }} shadow-xs hover:shadow-sm transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold {{ $cfg['code'] }}">{{ $acc['code'] }}</span>
                                    <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-md {{ $cfg['badge'] }}">
                                        {{ $acc['type'] }}
                                    </span>
                                </div>
                                <div class="text-xs font-semibold mt-2 text-slate-800 dark:text-slate-200 truncate" title="{{ $acc['name'] }}">
                                    {{ $acc['name'] }}
                                </div>
                                <div class="font-mono text-xs font-extrabold mt-1.5 text-slate-900 dark:text-white">
                                    Rp {{ number_format($acc['balance'], 0, ',', '.') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 3. BUKU JURNAL UMUM & LEDGER TABLE -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                
                <!-- Table Controls Header (Search & Status Tabs) -->
                <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                Buku Jurnal Transaksi
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Data mutasi debit dan kredit terlacak real-time
                            </p>
                        </div>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Per-Page Limit Selector -->
                        <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                            <span class="hidden xl:inline font-medium">Batas Data:</span>
                            <select 
                                x-model="perPage" 
                                class="py-1.5 pl-2.5 pr-7 text-xs font-semibold rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 shadow-2xs cursor-pointer transition-all"
                                title="Batas maksimal data per halaman"
                            >
                                <option value="5">5 baris</option>
                                <option value="10">10 baris</option>
                                <option value="25">25 baris</option>
                                <option value="50">50 baris</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>

                        <!-- Live Search Input -->
                        <div class="relative">
                            <input 
                                x-model="searchQuery" 
                                type="text" 
                                placeholder="Cari keterangan / voucher..." 
                                class="w-52 sm:w-60 pl-9 pr-3 py-1.5 text-xs rounded-xl bg-white dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 shadow-xs transition-all"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>

                        <!-- Status Filter Tabs with Vivid Color Coding -->
                        <div class="inline-flex rounded-xl bg-slate-200/70 dark:bg-slate-800 p-1 border border-slate-300/60 dark:border-slate-700 text-xs font-medium">
                            <button 
                                @click="filterStatus = 'all'" 
                                :class="filterStatus === 'all' ? 'bg-slate-900 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg transition-all"
                            >
                                Semua
                            </button>
                            <button 
                                @click="filterStatus = 'verified'" 
                                :class="filterStatus === 'verified' ? 'bg-emerald-600 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-emerald-700 dark:hover:text-emerald-400'"
                                class="px-3 py-1 rounded-lg transition-all"
                            >
                                Verified
                            </button>
                            <button 
                                @click="filterStatus = 'pending'" 
                                :class="filterStatus === 'pending' ? 'bg-amber-500 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-amber-700 dark:hover:text-amber-400'"
                                class="px-3 py-1 rounded-lg transition-all"
                            >
                                Pending
                            </button>
                            <button 
                                @click="filterStatus = 'rejected'" 
                                :class="filterStatus === 'rejected' ? 'bg-rose-600 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-rose-700 dark:hover:text-rose-400'"
                                class="px-3 py-1 rounded-lg transition-all"
                            >
                                Rejected
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="w-full overflow-x-auto lg:overflow-x-visible">
                    <table class="w-full text-left border-collapse table-auto lg:table-fixed">
                        <thead>
                            <tr class="bg-gradient-to-r from-slate-100 via-slate-50 to-slate-100 dark:from-slate-800/80 dark:to-slate-800/60 border-b border-slate-200/90 dark:border-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                <th class="px-4 py-3.5 w-32 lg:w-36">Tanggal</th>
                                <th class="px-4 py-3.5 w-32 lg:w-36">No. Referensi</th>
                                <th class="px-4 py-3.5 w-auto">Keterangan / Transaksi</th>
                                <th class="px-4 py-3.5 w-44 lg:w-48">Akun Terkait</th>
                                <th class="px-4 py-3.5 w-28 lg:w-32">Sumber</th>
                                <th class="px-4 py-3.5 text-right w-36 lg:w-44">Nominal</th>
                                <th class="px-4 py-3.5 text-center w-28 lg:w-32">Status</th>
                                <th class="px-4 py-3.5 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/80 text-sm">
                            @forelse($transactions as $trx)
                                @php
                                    $firstLine = $trx->lines->first();
                                    $expenseLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'expense');
                                    $revenueLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'revenue');
                                    $isExpense = (bool)$expenseLine;
                                    $amount = $isExpense 
                                        ? ($expenseLine->debit ?? 0) 
                                        : ($revenueLine->credit ?? $trx->lines->sum('debit'));
                                    $primaryAccount = $isExpense 
                                        ? ($expenseLine->account->name ?? 'Beban Operasional') 
                                        : ($revenueLine->account->name ?? ($firstLine->account->name ?? '-'));
                                    $primaryAccountCode = $isExpense 
                                        ? ($expenseLine->account->code ?? '5001') 
                                        : ($revenueLine->account->code ?? ($firstLine->account->code ?? '1001'));
                                    
                                    // Json data for modal
                                    $trxJson = json_encode([
                                        'id' => $trx->id,
                                        'reference' => $trx->reference,
                                        'description' => $trx->description,
                                        'date' => $trx->date ? \Carbon\Carbon::parse($trx->date)->translatedFormat('d F Y, H:i') : '-',
                                        'source' => $trx->source,
                                        'status' => $trx->status,
                                        'amount' => $amount,
                                        'isExpense' => $isExpense,
                                        'lines' => $trx->lines->map(function($line) {
                                            return [
                                                'account_code' => $line->account->code ?? '-',
                                                'account_name' => $line->account->name ?? 'Akun',
                                                'account_type' => $line->account->type ?? '-',
                                                'description'  => $line->description,
                                                'debit'        => (float)$line->debit,
                                                'credit'       => (float)$line->credit,
                                            ];
                                        })
                                    ]);
                                @endphp
                                <tr 
                                    x-show="isRowVisible({{ $trx->id }})"
                                    class="hover:bg-emerald-50/40 dark:hover:bg-slate-800/40 transition-colors group"
                                >
                                    <!-- Tanggal -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                        <div class="font-semibold text-slate-900 dark:text-slate-200">
                                            {{ $trx->date ? \Carbon\Carbon::parse($trx->date)->translatedFormat('d M Y') : '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ $trx->date ? \Carbon\Carbon::parse($trx->date)->format('H:i') : '' }} WIB
                                        </div>
                                    </td>

                                    <!-- Referensi -->
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                            {{ $trx->reference ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- Keterangan Transaksi -->
                                    <td class="px-4 py-3.5 text-slate-900 dark:text-slate-100 font-medium">
                                        <div class="text-sm font-semibold truncate" title="{{ $trx->description }}">
                                            {{ $trx->description }}
                                        </div>
                                    </td>

                                    <!-- Akun Terkait -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-xs">
                                        <div class="inline-flex items-center gap-1.5 max-w-full">
                                            <span class="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                                                {{ $primaryAccountCode }}
                                            </span>
                                            <span class="truncate text-slate-700 dark:text-slate-300 font-medium" title="{{ $primaryAccount }}">
                                                {{ $primaryAccount }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Sumber -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-xs">
                                        @if(strtolower($trx->source) === 'telegram')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-300/80 dark:border-sky-800 shadow-2xs">
                                                <svg class="w-3.5 h-3.5 text-sky-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                                Telegram
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                {{ ucfirst($trx->source ?? 'Manual') }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Nominal -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-extrabold text-sm">
                                        @if($isExpense)
                                            <span class="inline-block px-2 py-0.5 rounded-lg text-rose-700 dark:text-rose-400 bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-900/50 shadow-2xs">
                                                - Rp {{ number_format($amount, 0, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded-lg text-emerald-700 dark:text-emerald-400 bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-900/50 shadow-2xs">
                                                + Rp {{ number_format($amount, 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center text-xs">
                                        @if($trx->status === 'verified')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/80 dark:border-emerald-800">
                                                ✓ Verified
                                            </span>
                                        @elseif($trx->status === 'rejected')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300/80 dark:border-rose-800">
                                                ✗ Rejected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/80 dark:border-amber-800 animate-pulse">
                                                ⏳ Pending
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Aksi / Audit Button -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center text-xs">
                                        <button 
                                            @click='openModal({!! $trxJson !!})' 
                                            type="button" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 hover:text-emerald-700 bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 hover:border-emerald-300 dark:border-slate-700 shadow-xs transition-all"
                                            title="Buka rincian debit-kredit jurnal"
                                        >
                                            <svg class="w-3.5 h-3.5 text-slate-500 group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Audit
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <!-- Kasus 1: Database Kosong (Belum ada data di database) -->
                                <tr>
                                    <td colspan="8" class="px-6 py-14 text-center">
                                        <div class="max-w-md mx-auto flex flex-col items-center">
                                            <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-3.5 border border-amber-200/80 dark:border-amber-800/60 shadow-xs">
                                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                                </svg>
                                            </div>
                                            <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Data Tidak Ditemukan di Database</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                                                Belum ada mutasi transaksi yang tersimpan di dalam database buku besar. Silakan kirim transaksi melalui Bot Telegram atau webhook n8n untuk mulai mencatat.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                            <!-- Kasus 2: Data Ada di Database, tetapi Tidak Ditemukan pada Filter / Pencarian Tertentu -->
                            @if($transactions->isNotEmpty())
                                <tr x-show="visibleCount === 0" x-cloak>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mb-3 border border-slate-200 dark:border-slate-700 shadow-xs">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Data Tidak Ditemukan</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                Tidak ditemukan transaksi yang cocok dengan kata kunci atau filter status yang dipilih.
                                            </p>
                                            <button 
                                                type="button" 
                                                @click="filterStatus = 'all'; searchQuery = ''" 
                                                class="mt-3.5 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition-colors shadow-2xs"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                Reset Filter & Pencarian
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Table Navigation & Pagination Footer -->
                @if($transactions->isNotEmpty())
                    <div 
                        x-show="filteredItems.length > 0" 
                        class="px-5 py-3.5 border-t border-slate-200/80 dark:border-slate-800 bg-gradient-to-r from-slate-50/80 via-white to-slate-50/80 dark:from-slate-900 dark:to-slate-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                    >
                        <!-- Rentang & Total Data Transaksi -->
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="inline-flex w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>
                                Menampilkan 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="displayStart"></strong> 
                                - 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="displayEnd"></strong> 
                                dari 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="filteredItems.length"></strong> 
                                data transaksi
                            </span>
                            <span class="hidden md:inline text-slate-300 dark:text-slate-700">|</span>
                            <span class="hidden md:inline font-medium" x-text="'Halaman ' + currentPage + ' dari ' + totalPages"></span>
                        </div>

                        <!-- Kontrol Navigasi Tombol & Halaman -->
                        <div class="flex items-center gap-1.5 self-center sm:self-auto" x-show="totalPages > 1 || perPage !== 'all'">
                            <!-- Tombol Sebelumnya -->
                            <button 
                                type="button" 
                                @click="prevPage()" 
                                :disabled="currentPage === 1"
                                :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 bg-white dark:bg-slate-800 shadow-2xs'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all"
                                title="Halaman sebelumnya"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                <span>Sebelumnya</span>
                            </button>

                            <!-- Nomor Halaman Dinamis -->
                            <div class="flex items-center gap-1">
                                <template x-for="(p, index) in pageNumbers" :key="index">
                                    <div class="flex items-center">
                                        <template x-if="p === '...'">
                                            <span class="px-1.5 py-1 text-xs text-slate-400 dark:text-slate-500 font-mono">...</span>
                                        </template>
                                        <template x-if="p !== '...'">
                                            <button 
                                                type="button" 
                                                @click="goToPage(p)" 
                                                :class="currentPage === p 
                                                    ? 'bg-emerald-600 text-white font-extrabold shadow-sm shadow-emerald-600/30 border-emerald-600' 
                                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 font-semibold'"
                                                class="w-8 h-8 flex items-center justify-center text-xs rounded-xl border transition-all"
                                                x-text="p"
                                            ></button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Tombol Selanjutnya -->
                            <button 
                                type="button" 
                                @click="nextPage()" 
                                :disabled="currentPage >= totalPages"
                                :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 bg-white dark:bg-slate-800 shadow-2xs'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all"
                                title="Halaman berikutnya"
                            >
                                <span>Selanjutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

        </div>

        <!-- 4. MODAL RINCIAN JURNAL AUDIT (DOUBLE-ENTRY DETAIL) -->
        <div 
            x-show="showDetailModal" 
            x-cloak 
            class="fixed inset-0 z-50 overflow-y-auto" 
            style="display: none;"
        >
            <!-- Backdrop -->
            <div 
                x-show="showDetailModal" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeModal()" 
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div class="min-h-full flex items-center justify-center p-4">
                <div 
                    x-show="showDetailModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 rounded-3xl max-w-2xl w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 transition-all text-slate-800 dark:text-slate-100"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-md shadow-emerald-600/20">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>Posting Jurnal Akuntansi</span>
                                    <span class="font-mono text-xs px-2.5 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700" x-text="selectedTrx?.reference"></span>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="'Waktu: ' + selectedTrx?.date"></p>
                            </div>
                        </div>

                        <button @click="closeModal()" type="button" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Meta Details in Vibrant Tiles -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-5 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 text-xs">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block font-medium">Keterangan:</span>
                            <span class="font-bold text-slate-900 dark:text-slate-200 block truncate" x-text="selectedTrx?.description"></span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block font-medium">Sumber Data:</span>
                            <span class="font-bold text-slate-900 dark:text-slate-200 uppercase" x-text="selectedTrx?.source"></span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block font-medium">Status:</span>
                            <span class="font-bold capitalize" :class="{
                                'text-emerald-700 dark:text-emerald-400': selectedTrx?.status === 'verified',
                                'text-rose-700 dark:text-rose-400': selectedTrx?.status === 'rejected',
                                'text-amber-700 dark:text-amber-400': selectedTrx?.status === 'pending',
                            }" x-text="selectedTrx?.status"></span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block font-medium">Total Nilai:</span>
                            <span class="font-mono font-extrabold text-slate-900 dark:text-white" x-text="'Rp ' + (selectedTrx ? new Intl.NumberFormat('id-ID').format(selectedTrx.amount) : '0')"></span>
                        </div>
                    </div>

                    <!-- Double-Entry Breakdown Table -->
                    <div class="mt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-400 mb-2.5 flex items-center justify-between">
                            <span>Rincian Baris Debit & Kredit (Double-Entry Ledger)</span>
                            <span class="text-[10px] text-emerald-600 font-bold">Debit = Kredit</span>
                        </h4>
                        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gradient-to-r from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-800 text-slate-700 dark:text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="px-4 py-3">Kode Akun</th>
                                        <th class="px-4 py-3">Nama Akun & Posisi</th>
                                        <th class="px-4 py-3 text-right font-mono">Debit (Rp)</th>
                                        <th class="px-4 py-3 text-right font-mono">Kredit (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <template x-for="(line, idx) in selectedTrx?.lines" :key="idx">
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                            <td class="px-4 py-3 font-mono font-bold text-slate-800 dark:text-slate-300" x-text="line.account_code"></td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-slate-900 dark:text-slate-200" x-text="line.account_name"></div>
                                                <div class="text-[10px] text-slate-500" x-text="line.description || selectedTrx?.description"></div>
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono font-bold" :class="line.debit > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-300 dark:text-slate-600'" x-text="new Intl.NumberFormat('id-ID').format(line.debit)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold" :class="line.credit > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-300 dark:text-slate-600'" x-text="new Intl.NumberFormat('id-ID').format(line.credit)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-emerald-50/80 dark:bg-slate-800/80 border-t border-emerald-200 dark:border-slate-800 font-mono font-extrabold text-xs">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-emerald-900 dark:text-slate-300 uppercase tracking-wider text-[11px]">
                                            Total Keseimbangan (Balance Check)
                                        </td>
                                        <td class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400" x-text="new Intl.NumberFormat('id-ID').format(selectedTrx?.lines?.reduce((acc, l) => acc + Number(l.debit), 0) || 0)"></td>
                                        <td class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400" x-text="new Intl.NumberFormat('id-ID').format(selectedTrx?.lines?.reduce((acc, l) => acc + Number(l.credit), 0) || 0)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Actions (Verification & Reject) -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <div class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Jurnal Seimbang & Siap Audit
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Tombol Verifikasi/Tolak jika status pending -->
                            <template x-if="selectedTrx && selectedTrx.status === 'pending'">
                                <div class="flex items-center gap-2">
                                    <form :action="'/journal-entries/' + selectedTrx.id + '/reject'" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 shadow-xs transition-colors">
                                            Tolak (Reject)
                                        </button>
                                    </form>
                                    <form :action="'/journal-entries/' + selectedTrx.id + '/verify'" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-md shadow-emerald-600/20 transition-all">
                                            Verifikasi Jurnal
                                        </button>
                                    </form>
                                </div>
                            </template>

                            <button @click="closeModal()" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
