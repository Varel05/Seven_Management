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
        exportMonth: '{{ now()->format('Y-m') }}',
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
        },
        showAccountModal: false,
        isEditAccount: false,
        accountForm: {
            id: null,
            code: '',
            name: '',
            type: 'expense'
        },
        showDeleteAccountModal: false,
        accountToDelete: null,
        openAddAccountModal() {
            this.isEditAccount = false;
            this.accountForm = { id: null, code: '', name: '', type: 'expense' };
            this.showAccountModal = true;
        },
        openEditAccountModal(acc) {
            this.isEditAccount = true;
            this.accountForm = { id: acc.id, code: acc.code, name: acc.name, type: acc.type };
            this.showAccountModal = true;
        },
        closeAccountModal() {
            this.showAccountModal = false;
        },
        openDeleteAccountModal(acc) {
            this.accountToDelete = acc;
            this.showDeleteAccountModal = true;
        },
        closeDeleteAccountModal() {
            this.showDeleteAccountModal = false;
            this.accountToDelete = null;
        },
        showRecurringModal: false,
        isEditRecurring: false,
        recurringForm: {
            id: null,
            name: '',
            amount: '',
            frequency: 'monthly',
            day_of_month: {{ now()->day }},
            month_of_year: {{ now()->month }},
            expense_account_id: '{{ $expenseAccounts->first()->id ?? '' }}',
            asset_account_id: '{{ $assetAccounts->first()->id ?? '' }}',
            status: 'active',
            notes: ''
        },
        showDeleteRecurringModal: false,
        recurringToDelete: null,
        openAddRecurringModal() {
            this.isEditRecurring = false;
            this.recurringForm = {
                id: null,
                name: '',
                amount: '',
                frequency: 'monthly',
                day_of_month: {{ now()->day }},
                month_of_year: {{ now()->month }},
                expense_account_id: '{{ $expenseAccounts->first()->id ?? '' }}',
                asset_account_id: '{{ $assetAccounts->first()->id ?? '' }}',
                status: 'active',
                notes: ''
            };
            this.showRecurringModal = true;
        },
        openEditRecurringModal(item) {
            this.isEditRecurring = true;
            this.recurringForm = {
                id: item.id,
                name: item.name,
                amount: Number(item.amount),
                frequency: item.frequency,
                day_of_month: item.day_of_month,
                month_of_year: item.month_of_year || 1,
                expense_account_id: item.expense_account_id,
                asset_account_id: item.asset_account_id,
                status: item.status,
                notes: item.notes || ''
            };
            this.showRecurringModal = true;
        },
        closeRecurringModal() {
            this.showRecurringModal = false;
        },
        openDeleteRecurringModal(item) {
            this.recurringToDelete = item;
            this.showDeleteRecurringModal = true;
        },
        closeDeleteRecurringModal() {
            this.showDeleteRecurringModal = false;
            this.recurringToDelete = null;
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

            @if($errors->any())
                <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200 text-sm flex items-start gap-3 shadow-sm transition-all">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold mb-1">Terjadi Kesalahan Validasi:</h4>
                        <ul class="list-disc list-inside text-xs space-y-0.5 text-rose-800 dark:text-rose-300">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
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

            <!-- 2. DIAGRAM KOMBINASI ARUS KEUANGAN (COMBO CHART: BATANG PEMASUKAN & GARIS PENGELUARAN) -->
            <div 
                x-data="{
                    chartMode: 'daily', // 'daily' atau 'monthly'
                    chartInstance: null,
                    dataPayload: {{ Js::from($chartData) }},
                    formatRupiah(num) {
                        return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
                    },
                    get currentMeta() {
                        return this.dataPayload[this.chartMode] || {};
                    },
                    renderChart() {
                        const canvas = document.getElementById('financialTrendChart');
                        if (!canvas || typeof Chart === 'undefined') return;

                        // Pastikan instance grafik sebelumnya dihancurkan (destroy) agar tidak terjadi konflik metaset ukuran array
                        const existingChart = Chart.getChart(canvas) || Chart.getChart('financialTrendChart');
                        if (existingChart) {
                            existingChart.destroy();
                        }

                        const isDark = document.documentElement.classList.contains('dark');
                        const gridColor = isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.7)';
                        const textColor = isDark ? '#94a3b8' : '#64748b';

                        const activeData = this.dataPayload[this.chartMode];
                        if (!activeData) return;

                        const ctx = canvas.getContext('2d');
                        // Gradient halus untuk isian area di bawah garis pengeluaran (Rose)
                        const expGradient = ctx.createLinearGradient(0, 0, 0, 300);
                        expGradient.addColorStop(0, 'rgba(244, 63, 94, 0.18)');
                        expGradient.addColorStop(1, 'rgba(244, 63, 94, 0.01)');

                        const newChart = new Chart(canvas, {
                            type: 'bar',
                            data: {
                                labels: [...activeData.labels],
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: 'Total Pemasukan',
                                        data: [...activeData.revenue],
                                        backgroundColor: isDark ? 'rgba(16, 185, 129, 0.75)' : 'rgba(16, 185, 129, 0.85)',
                                        borderColor: '#10b981',
                                        borderWidth: 1.5,
                                        borderRadius: 6,
                                        borderSkipped: 'bottom',
                                        hoverBackgroundColor: '#059669',
                                        hoverBorderColor: '#047857',
                                        barPercentage: this.chartMode === 'daily' ? 0.65 : 0.45,
                                        categoryPercentage: 0.72,
                                        order: 2,
                                    },
                                    {
                                        type: 'line',
                                        label: 'Laju Pengeluaran',
                                        data: [...activeData.expense],
                                        borderColor: '#f43f5e',
                                        backgroundColor: expGradient,
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.35,
                                        pointBackgroundColor: '#f43f5e',
                                        pointBorderColor: isDark ? '#0f172a' : '#ffffff',
                                        pointBorderWidth: 2,
                                        pointRadius: this.chartMode === 'daily' ? 3 : 5,
                                        pointHoverRadius: 7,
                                        pointHoverBackgroundColor: '#e11d48',
                                        pointHoverBorderColor: '#ffffff',
                                        order: 1,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: {
                                    duration: 500,
                                    easing: 'easeOutQuart'
                                },
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                    tooltip: {
                                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.98)',
                                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                                        bodyColor: isDark ? '#cbd5e1' : '#334155',
                                        borderColor: isDark ? '#334155' : '#e2e8f0',
                                        borderWidth: 1,
                                        padding: 12,
                                        boxPadding: 6,
                                        usePointStyle: true,
                                        titleFont: {
                                            weight: 'bold',
                                            size: 12
                                        },
                                        bodyFont: {
                                            size: 12
                                        },
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.dataset.label || '';
                                                const val = context.parsed.y !== null ? context.parsed.y : 0;
                                                const formatted = new Intl.NumberFormat('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    minimumFractionDigits: 0
                                                }).format(val);
                                                return `  ${label}: ${formatted}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            display: false,
                                        },
                                        ticks: {
                                            color: textColor,
                                            font: {
                                                size: 11,
                                                weight: 500
                                            },
                                            maxRotation: 45,
                                            autoSkip: true,
                                            maxTicksLimit: this.chartMode === 'daily' ? 16 : 12,
                                        }
                                    },
                                    y: {
                                        grid: {
                                            color: gridColor,
                                            borderDash: [4, 4],
                                        },
                                        ticks: {
                                            color: textColor,
                                            font: {
                                                size: 11,
                                            },
                                            callback: function(value) {
                                                if (value >= 1000000) {
                                                    return 'Rp ' + (value / 1000000).toLocaleString('id-ID') + ' jt';
                                                } else if (value >= 1000) {
                                                    return 'Rp ' + (value / 1000).toLocaleString('id-ID') + ' rb';
                                                }
                                                return 'Rp ' + value.toLocaleString('id-ID');
                                            }
                                        },
                                        beginAtZero: true
                                    }
                                }
                            }
                        });

                        window.financialTrendChart = newChart;
                    },
                    switchMode(newMode) {
                        if (this.chartMode === newMode) return;
                        this.chartMode = newMode;
                        this.$nextTick(() => {
                            this.renderChart();
                        });
                    },
                    updateTheme() {
                        this.renderChart();
                    },
                    init() {
                        this.$nextTick(() => {
                            this.renderChart();
                        });

                        const observer = new MutationObserver(() => {
                            this.updateTheme();
                        });
                        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    }
                }"
                class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
            >
                <!-- Chart Header: Judul & Tombol Switch Mode -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 shrink-0">
                            <!-- Icon Combo Chart (Bars & Line) -->
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" stroke="#f43f5e" d="M3 13l5-5 4 4 9-9" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                    Grafik Kombinasi Arus Keuangan
                                </h2>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" x-text="currentMeta.period"></span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5" x-text="chartMode === 'daily' ? 'Kolom vertikal: Total Pemasukan harian | Garis penghubung: Laju Pengeluaran harian (' + currentMeta.period + ')' : 'Kolom vertikal: Total Pemasukan bulanan | Garis penghubung: Laju Pengeluaran bulanan (' + currentMeta.period + ')'"></p>
                        </div>
                    </div>

                    <!-- Trigger Switch: Harian (Bulan Ini) vs Bulanan (Tahun Ini) -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400 hidden md:inline font-medium">Rentang Waktu:</span>
                        <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-1 border border-slate-200/80 dark:border-slate-700/80 text-xs font-semibold">
                            <button 
                                type="button"
                                @click="switchMode('daily')" 
                                :class="chartMode === 'daily' ? 'bg-emerald-600 text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Harian (Bulan Ini)</span>
                            </button>
                            <button 
                                type="button"
                                @click="switchMode('monthly')" 
                                :class="chartMode === 'monthly' ? 'bg-emerald-600 text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <span>Bulanan (Tahun Ini)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Baris 1: Metric Chips Finansial (Total Pemasukan, Total Pengeluaran, Surplus Bersih) -->
                <div class="pt-3.5 pb-2">
                    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 text-xs">
                        <!-- Chip Pemasukan -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60 shadow-2xs">
                            <span class="w-2.5 h-2.5 rounded-xs bg-emerald-500 shadow-2xs"></span>
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Total Pemasukan:</span>
                            <strong class="font-mono font-bold text-emerald-700 dark:text-emerald-400" x-text="formatRupiah(currentMeta.totalRevenue)"></strong>
                        </div>

                        <!-- Chip Pengeluaran -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/60 shadow-2xs">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-2xs"></span>
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Total Pengeluaran:</span>
                            <strong class="font-mono font-bold text-rose-700 dark:text-rose-400" x-text="formatRupiah(currentMeta.totalExpense)"></strong>
                        </div>

                        <!-- Chip Laba / Surplus Bersih -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border shadow-2xs" :class="currentMeta.netProfit >= 0 ? 'bg-teal-50 dark:bg-teal-950/40 border-teal-200/80 dark:border-teal-800/60 text-teal-800 dark:text-teal-300' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200/80 dark:border-rose-800/60 text-rose-800 dark:text-rose-300'">
                            <span class="w-2.5 h-2.5 rounded-full" :class="currentMeta.netProfit >= 0 ? 'bg-teal-500' : 'bg-rose-500'"></span>
                            <span class="font-medium" x-text="currentMeta.netProfit >= 0 ? 'Surplus Bersih:' : 'Defisit Bersih:'"></span>
                            <strong class="font-mono font-bold" x-text="formatRupiah(Math.abs(currentMeta.netProfit))"></strong>
                        </div>
                    </div>
                </div>

                <!-- Baris 2: Legenda Visual Grafik Kombinasi (Kolom Batang & Garis Penghubung) -->
                <div class="pt-1.5 pb-2.5 border-b border-slate-100/90 dark:border-slate-800/80">
                    <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <div class="inline-flex items-center gap-2">
                            <span class="w-3.5 h-3.5 bg-emerald-500 rounded-xs shadow-2xs border border-emerald-600/30"></span>
                            <span>Total Pemasukan (Kolom Batang)</span>
                        </div>
                        <div class="inline-flex items-center gap-2">
                            <span class="relative flex items-center justify-center w-6 h-3">
                                <span class="w-full h-[2.5px] bg-rose-500 rounded-full"></span>
                                <span class="absolute w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white dark:border-slate-900 shadow-xs"></span>
                            </span>
                            <span>Laju Pengeluaran (Garis Penghubung)</span>
                        </div>
                    </div>
                </div>

                <!-- Canvas Area -->
                <div class="relative w-full h-72 sm:h-80 mt-2">
                    <canvas id="financialTrendChart"></canvas>
                </div>
            </div>

            <!-- 3. COLOR-CODED CHART OF ACCOUNTS (COA) SUMMARY BAR & MANAGEMENT -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Bagan Akun Finansial (Chart of Accounts)
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Saldo kumulatif dan pengelolaan akun buku besar utama</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-semibold px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700/60">
                            {{ $accounts->count() }} akun aktif
                        </span>
                        <button 
                            type="button" 
                            @click="openAddAccountModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold shadow-sm shadow-emerald-600/25 transition-all hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Akun</span>
                        </button>
                    </div>
                </div>

                @if(isset($accounts) && $accounts->count() > 0)
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
                            <div class="group relative p-3.5 rounded-2xl border {{ $cfg['box'] }} shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                                <div>
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

                                <!-- Action Bar & Transaction Count -->
                                <div class="mt-3 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-[10px]">
                                    <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1 font-medium" title="{{ $acc['trx_count'] }} transaksi tercatat">
                                        <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        {{ $acc['trx_count'] }} trx
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button 
                                            type="button"
                                            @click="openEditAccountModal({{ Js::from($acc) }})"
                                            class="p-1 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 dark:hover:text-blue-400 transition-colors" 
                                            title="Edit Akun {{ $acc['code'] }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button 
                                            type="button"
                                            @click="openDeleteAccountModal({{ Js::from($acc) }})"
                                            class="p-1 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 dark:hover:text-rose-400 transition-colors" 
                                            title="Hapus Akun {{ $acc['code'] }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Belum Ada Akun Finansial</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">Tambahkan akun buku besar pertama Anda untuk mulai mengelola keuangan.</p>
                        <button 
                            type="button" 
                            @click="openAddAccountModal()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Akun Baru</span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- RECURRING EXPENSES & SUBSCRIPTIONS SECTION -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold shadow-md shadow-purple-600/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                Jadwal Pengeluaran Rutin & Langganan
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pengeluaran berkala yang memerlukan konfirmasi sebelum dibukukan</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-semibold px-2.5 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800/60">
                            {{ $recurringTransactions->where('status', 'active')->count() }} jadwal aktif
                        </span>
                        <button 
                            type="button" 
                            @click="openAddRecurringModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold shadow-sm shadow-purple-600/25 transition-all hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Tagihan Rutin</span>
                        </button>
                    </div>
                </div>

                @if(isset($recurringTransactions) && $recurringTransactions->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-5">
                        @foreach($recurringTransactions as $item)
                            @php
                                $isDue = $item->isDueToday();
                                $alreadyPosted = $item->last_posted_at && $item->last_posted_at->isCurrentMonth();
                            @endphp
                            <div class="relative p-4 rounded-2xl border transition-all flex flex-col justify-between {{ $isDue ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-300 dark:border-amber-700/80 shadow-md ring-1 ring-amber-400/40' : 'bg-white dark:bg-slate-800/60 border-slate-200 dark:border-slate-800 shadow-xs hover:shadow-sm' }}">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full {{ $item->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-lg {{ $item->status === 'active' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                                {{ ucfirst($item->frequency) }} (Tgl {{ $item->day_of_month }})
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button 
                                                type="button" 
                                                @click="openEditRecurringModal({{ Js::from($item) }})" 
                                                class="p-1 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 dark:hover:text-blue-400 transition-colors"
                                                title="Edit Tagihan"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button 
                                                type="button" 
                                                @click="openDeleteRecurringModal({{ Js::from($item) }})" 
                                                class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 dark:hover:text-rose-400 transition-colors"
                                                title="Hapus Tagihan"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white truncate" title="{{ $item->name }}">
                                            {{ $item->name }}
                                        </h3>
                                        <div class="text-lg font-mono font-black text-slate-900 dark:text-white mt-1">
                                            Rp {{ number_format($item->amount, 0, ',', '.') }}
                                        </div>
                                    </div>

                                    <div class="mt-2.5 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs space-y-1">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-500 dark:text-slate-400">Akun Beban:</span>
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $item->expenseAccount->name ?? '-' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-500 dark:text-slate-400">Kas / Bank:</span>
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $item->assetAccount->name ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status / Approval Action Area -->
                                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    @if($isDue)
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1 animate-pulse">
                                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                Jatuh Tempo Hari Ini!
                                            </span>
                                            <form action="{{ route('recurring-transactions.approve', $item) }}" method="POST">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold shadow-xs transition-all hover:scale-105 active:scale-95"
                                                    title="Bukukan ke Jurnal Sekarang"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    <span>Bukukan</span>
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($alreadyPosted)
                                        <div class="flex items-center justify-between text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                Sudah dibukukan bulan ini
                                            </span>
                                            <span class="text-[10px] text-slate-400">{{ $item->last_posted_at->translatedFormat('d M') }}</span>
                                        </div>
                                    @else
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                                            <span>Jatuh tempo berikutnya:</span>
                                            <span class="font-bold text-slate-700 dark:text-slate-300">Tgl {{ $item->day_of_month }} {{ now()->translatedFormat('F') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-7">
                        <div class="w-11 h-11 rounded-2xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center mx-auto mb-2.5">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Belum Ada Pengeluaran Rutin</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 mb-3">Jadwalkan tagihan berkala seperti gaji, langganan server, atau sewa untuk mendapatkan pengingat otomatis.</p>
                        <button 
                            type="button" 
                            @click="openAddRecurringModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-sm shadow-purple-600/20 transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Tagihan Pertama</span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- 3. BUKU JURNAL UMUM & LEDGER TABLE -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                
                <!-- Table Controls Header (Search & Status Tabs & Download Excel) -->
                <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900 space-y-4">
                    <!-- Baris Pertama: Judul & Keterangan (Kiri) dan Fitur Download Excel (Pojok Kanan) -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 shrink-0">
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

                        <!-- Fitur Unduh Excel Jurnal Transaksi Bulanan (Pojok Kanan Baris Pertama) -->
                        <div class="inline-flex items-center gap-1.5 p-1 rounded-xl bg-slate-200/70 dark:bg-slate-800 border border-slate-300/60 dark:border-slate-700 text-xs shadow-2xs self-start sm:self-auto">
                            <input 
                                type="month" 
                                x-model="exportMonth" 
                                class="py-1 px-2.5 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-300/60 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer shadow-2xs transition-all"
                                title="Pilih bulan untuk ekspor buku jurnal transaksi"
                            />
                            <a 
                                :href="'{{ route('journal-entries.export-monthly') }}?month=' + (exportMonth || 'all') + '&status=' + filterStatus + (searchQuery ? '&search=' + encodeURIComponent(searchQuery) : '')" 
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-bold shadow-xs hover:shadow-md hover:shadow-emerald-600/25 transition-all active:scale-95"
                                title="Unduh Buku Jurnal Transaksi Bulanan ke file Excel (.xlsx)"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span>Unduh Excel</span>
                            </a>
                        </div>
                    </div>

                    <!-- Baris Kedua: Fitur Pengaturan Tabel (Menjorok ke Kanan) -->
                    <div class="flex flex-wrap items-center justify-end gap-3 pt-3 border-t border-slate-200/70 dark:border-slate-800/80">
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
                                class="w-52 sm:w-64 pl-9 pr-3 py-1.5 text-xs rounded-xl bg-white dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 shadow-xs transition-all"
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
                                        @if(str_starts_with(strtolower($trx->source ?? ''), 'telegram'))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-300/80 dark:border-sky-800 shadow-2xs">
                                                <svg class="w-3.5 h-3.5 text-sky-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                                Telegram
                                            </span>
                                        @elseif(str_starts_with(strtolower($trx->source ?? ''), 'web'))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/15 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-300/80 dark:border-indigo-800 shadow-2xs">
                                                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                                </svg>
                                                Website
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

        <!-- 5. MODAL TAMBAH & EDIT AKUN KEUANGAN -->
        <div 
            x-show="showAccountModal" 
            x-cloak 
            class="fixed inset-0 z-50 overflow-y-auto" 
            style="display: none;"
        >
            <!-- Backdrop -->
            <div 
                x-show="showAccountModal" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeAccountModal()" 
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div class="min-h-full flex items-center justify-center p-4">
                <div 
                    x-show="showAccountModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 transition-all text-slate-800 dark:text-slate-100"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center font-bold shadow-md shadow-emerald-600/20">
                                <template x-if="!isEditAccount">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </template>
                                <template x-if="isEditAccount">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </template>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="isEditAccount ? 'Edit Akun Keuangan' : 'Tambah Akun Baru'"></h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="isEditAccount ? 'Perbarui informasi kode, nama, atau tipe akun' : 'Daftarkan akun buku besar baru ke sistem'"></p>
                            </div>
                        </div>

                        <button @click="closeAccountModal()" type="button" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form :action="isEditAccount ? ('/accounts/' + accountForm.id) : '{{ route('accounts.store') }}'" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <template x-if="isEditAccount">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <!-- Kode Akun -->
                        <div>
                            <label for="acc_code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Kode Akun (Chart of Account Code) <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="acc_code" 
                                name="code" 
                                x-model="accountForm.code" 
                                required 
                                placeholder="Misal: 1002, 2001, 5002"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 font-mono text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder:text-slate-400 placeholder:font-sans"
                            >
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Gunakan kode angka unik, misal: 1xxx (Aset), 2xxx (Kewajiban), 3xxx (Ekuitas), 4xxx (Pendapatan), 5xxx (Beban).</p>
                        </div>

                        <!-- Nama Akun -->
                        <div>
                            <label for="acc_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Akun <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="acc_name" 
                                name="name" 
                                x-model="accountForm.name" 
                                required 
                                placeholder="Misal: Kas Kecil, Bank BCA, Beban Listrik & Internet"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder:text-slate-400"
                            >
                        </div>

                        <!-- Tipe Akun -->
                        <div>
                            <label for="acc_type" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Tipe Akun (Klasifikasi Keuangan) <span class="text-rose-500">*</span>
                            </label>
                            <select 
                                id="acc_type" 
                                name="type" 
                                x-model="accountForm.type" 
                                required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all"
                            >
                                <option value="asset">Aset (Kas, Bank, Piutang, Perlengkapan)</option>
                                <option value="expense">Beban (Biaya Operasional, Gaji, Sewa, Server)</option>
                                <option value="revenue">Pendapatan (Penjualan, Pendapatan Layanan / Jasa)</option>
                                <option value="liability">Kewajiban (Hutang Usaha, Pinjaman Bank)</option>
                                <option value="equity">Ekuitas (Modal Pemilik, Laba Ditahan, Prive)</option>
                            </select>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 mt-6 border-t border-slate-100 dark:border-slate-800">
                            <button 
                                @click="closeAccountModal()" 
                                type="button" 
                                class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-md shadow-emerald-600/25 transition-all hover:scale-[1.01] active:scale-[0.99]"
                            >
                                <template x-if="!isEditAccount">
                                    <span>Simpan Akun Baru</span>
                                </template>
                                <template x-if="isEditAccount">
                                    <span>Simpan Perubahan</span>
                                </template>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 6. MODAL KONFIRMASI HAPUS AKUN -->
        <div 
            x-show="showDeleteAccountModal" 
            x-cloak 
            class="fixed inset-0 z-50 overflow-y-auto" 
            style="display: none;"
        >
            <!-- Backdrop -->
            <div 
                x-show="showDeleteAccountModal" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDeleteAccountModal()" 
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div class="min-h-full flex items-center justify-center p-4">
                <div 
                    x-show="showDeleteAccountModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 transition-all text-slate-800 dark:text-slate-100"
                >
                    <!-- Header -->
                    <div class="flex items-center gap-3.5 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="w-11 h-11 rounded-2xl bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Konfirmasi Hapus Akun</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono" x-text="accountToDelete ? accountToDelete.code + ' - ' + accountToDelete.name : ''"></p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="mt-4">
                        <template x-if="accountToDelete && accountToDelete.trx_count > 0">
                            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 space-y-2">
                                <div class="flex items-center gap-2 font-bold text-xs">
                                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>Akun Terkunci (Memiliki Transaksi)</span>
                                </div>
                                <p class="text-xs text-amber-800 dark:text-amber-300/90 leading-relaxed">
                                    Akun ini telah memiliki <strong class="font-bold" x-text="accountToDelete.trx_count"></strong> riwayat transaksi jurnal. Demi kepatuhan akuntansi dan integritas buku besar, akun yang sudah mencatat transaksi <strong>tidak dapat dihapus</strong>.
                                </p>
                            </div>
                        </template>

                        <template x-if="accountToDelete && accountToDelete.trx_count === 0">
                            <div class="space-y-3">
                                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                    Apakah Anda yakin ingin menghapus akun <strong class="font-bold text-slate-900 dark:text-white" x-text="accountToDelete.code + ' (' + accountToDelete.name + ')'"></strong>?
                                </p>
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
                                    Akun ini belum memiliki transaksi dan akan dihapus secara permanen dari basis data.
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 mt-6 border-t border-slate-100 dark:border-slate-800">
                        <button 
                            @click="closeDeleteAccountModal()" 
                            type="button" 
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
                        >
                            <span x-text="accountToDelete && accountToDelete.trx_count > 0 ? 'Mengerti & Tutup' : 'Batal'"></span>
                        </button>
                        <template x-if="accountToDelete && accountToDelete.trx_count === 0">
                            <form :action="'/accounts/' + accountToDelete.id" method="POST">
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit" 
                                    class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white shadow-md shadow-rose-600/25 transition-all hover:scale-[1.01] active:scale-[0.99]"
                                >
                                    Ya, Hapus Akun
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. MODAL TAMBAH & EDIT PENGELUARAN RUTIN -->
        <div 
            x-show="showRecurringModal" 
            x-cloak 
            class="fixed inset-0 z-50 overflow-y-auto" 
            style="display: none;"
        >
            <!-- Backdrop -->
            <div 
                x-show="showRecurringModal" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeRecurringModal()" 
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div class="min-h-full flex items-center justify-center p-4">
                <div 
                    x-show="showRecurringModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 rounded-3xl max-w-xl w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 transition-all text-slate-800 dark:text-slate-100"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white flex items-center justify-center font-bold shadow-md shadow-purple-600/20">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="isEditRecurring ? 'Edit Pengeluaran Rutin' : 'Tambah Pengeluaran Rutin'"></h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Jadwalkan tagihan berkala untuk reminder & approval otomatis</p>
                            </div>
                        </div>

                        <button @click="closeRecurringModal()" type="button" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form :action="isEditRecurring ? ('/recurring-transactions/' + recurringForm.id) : '{{ route('recurring-transactions.store') }}'" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <template x-if="isEditRecurring">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <!-- Nama Tagihan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Tagihan / Pengeluaran <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                x-model="recurringForm.name" 
                                required 
                                placeholder="Misal: Gaji Karyawan, Langganan Server AWS, Sewa Kantor"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all placeholder:text-slate-400"
                            >
                        </div>

                        <!-- Nominal & Siklus Frekuensi -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Nominal Estimasi (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    name="amount" 
                                    x-model="recurringForm.amount" 
                                    min="1" 
                                    required 
                                    placeholder="Contoh: 15000000"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 font-mono text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all placeholder:text-slate-400 placeholder:font-sans"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Siklus / Frekuensi <span class="text-rose-500">*</span>
                                </label>
                                <select 
                                    name="frequency" 
                                    x-model="recurringForm.frequency" 
                                    required
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                                >
                                    <option value="monthly">Bulanan (Setiap Bulan)</option>
                                    <option value="yearly">Tahunan (Setiap Tahun)</option>
                                    <option value="weekly">Mingguan</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tanggal Jatuh Tempo & Status -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Tanggal Jatuh Tempo (Hari ke- 1–31) <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    name="day_of_month" 
                                    x-model="recurringForm.day_of_month" 
                                    min="1" 
                                    max="31" 
                                    required 
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 font-mono text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Status Jadwal <span class="text-rose-500">*</span>
                                </label>
                                <select 
                                    name="status" 
                                    x-model="recurringForm.status" 
                                    required
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                                >
                                    <option value="active">Aktif (Kirim Reminder & Approval)</option>
                                    <option value="paused">Dijeda (Nonaktifkan Sementara)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Akun Beban & Akun Kas / Bank -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Akun Beban (Kategori) <span class="text-rose-500">*</span>
                                </label>
                                <select 
                                    name="expense_account_id" 
                                    x-model="recurringForm.expense_account_id" 
                                    required
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                                >
                                    @foreach($expenseAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Sumber Kas / Bank <span class="text-rose-500">*</span>
                                </label>
                                <select 
                                    name="asset_account_id" 
                                    x-model="recurringForm.asset_account_id" 
                                    required
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                                >
                                    @foreach($assetAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Catatan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Catatan Tambahan (Opsional)
                            </label>
                            <input 
                                type="text" 
                                name="notes" 
                                x-model="recurringForm.notes" 
                                placeholder="Misal: Tagihan debit otomatis kartu kredit / transfer manual"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all placeholder:text-slate-400"
                            >
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 mt-6 border-t border-slate-100 dark:border-slate-800">
                            <button 
                                @click="closeRecurringModal()" 
                                type="button" 
                                class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-md shadow-purple-600/25 transition-all hover:scale-[1.01] active:scale-[0.99]"
                            >
                                <template x-if="!isEditRecurring">
                                    <span>Jadwalkan Pengeluaran</span>
                                </template>
                                <template x-if="isEditRecurring">
                                    <span>Simpan Perubahan</span>
                                </template>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 8. MODAL KONFIRMASI HAPUS PENGELUARAN RUTIN -->
        <div 
            x-show="showDeleteRecurringModal" 
            x-cloak 
            class="fixed inset-0 z-50 overflow-y-auto" 
            style="display: none;"
        >
            <!-- Backdrop -->
            <div 
                x-show="showDeleteRecurringModal" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDeleteRecurringModal()" 
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div class="min-h-full flex items-center justify-center p-4">
                <div 
                    x-show="showDeleteRecurringModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 transition-all text-slate-800 dark:text-slate-100"
                >
                    <!-- Header -->
                    <div class="flex items-center gap-3.5 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="w-11 h-11 rounded-2xl bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Hapus Jadwal Pengeluaran</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="recurringToDelete ? recurringToDelete.name : ''"></p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="mt-4 space-y-3">
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Apakah Anda yakin ingin menghapus jadwal pengeluaran rutin <strong class="font-bold text-slate-900 dark:text-white" x-text="recurringToDelete ? recurringToDelete.name : ''"></strong>?
                        </p>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
                            Pengingat bot dan notifikasi untuk tagihan ini tidak akan dikirimkan lagi. Riwayat transaksi jurnal yang sudah dibukukan sebelumnya tidak akan terhapus.
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 mt-6 border-t border-slate-100 dark:border-slate-800">
                        <button 
                            @click="closeDeleteRecurringModal()" 
                            type="button" 
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
                        >
                            Batal
                        </button>
                        <template x-if="recurringToDelete">
                            <form :action="'/recurring-transactions/' + recurringToDelete.id" method="POST">
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit" 
                                    class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white shadow-md shadow-rose-600/25 transition-all hover:scale-[1.01] active:scale-[0.99]"
                                >
                                    Ya, Hapus Jadwal
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
