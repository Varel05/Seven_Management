<x-app-layout>
    <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="{
             activeTab: '{{ $activeTab }}',
             showTailorModal: false,
             isEditTailor: false,
             tailorModalTitle: 'Tambah Data Penjahit Baru',
             tailorFormAction: '{{ route('tailors.store') }}',
             tailorFormMethod: 'POST',
             tailorForm: {
                 id: null,
                 name: '',
                 phone: '',
                 payment_period: 'weekly',
                 payment_day: 'Sabtu',
                 bank_name: 'BCA',
                 bank_account_number: '',
                 bank_account_holder: '',
                 status: 'active',
                 notes: ''
             },

             openCreateTailor() {
                 this.isEditTailor = false;
                 this.tailorModalTitle = 'Tambah Data Penjahit Baru';
                 this.tailorFormAction = '{{ route('tailors.store') }}';
                 this.tailorFormMethod = 'POST';
                 this.tailorForm = {
                     id: null,
                     name: '',
                     phone: '',
                     payment_period: 'weekly',
                     payment_day: 'Sabtu',
                     bank_name: 'BCA',
                     bank_account_number: '',
                     bank_account_holder: '',
                     status: 'active',
                     notes: ''
                 };
                 this.showTailorModal = true;
             },

             openEditTailor(tailor) {
                 this.isEditTailor = true;
                 this.tailorModalTitle = 'Edit Data Penjahit: ' + tailor.name;
                 this.tailorFormAction = '{{ url('tailors') }}/' + tailor.id;
                 this.tailorFormMethod = 'POST';
                 this.tailorForm = {
                     id: tailor.id,
                     name: tailor.name || '',
                     phone: tailor.phone || '',
                     payment_period: tailor.payment_period || 'weekly',
                     payment_day: tailor.payment_day || (tailor.payment_period === 'monthly' ? '25' : 'Sabtu'),
                     bank_name: tailor.bank_name || 'BCA',
                     bank_account_number: tailor.bank_account_number || '',
                     bank_account_holder: tailor.bank_account_holder || '',
                     status: tailor.status || 'active',
                     notes: tailor.notes || ''
                 };
                 this.showTailorModal = true;
             },

             showRatesModal: false,
             rates: @js($laborMaterials->map(fn($lm) => [
                 'id' => $lm->id ?? null,
                 'code' => $lm->code,
                 'name' => $lm->name,
                 'unit' => $lm->unit ?? 'pcs',
                 'standard_cost' => (float) $lm->standard_cost,
             ])->values()),

             addCustomRate() {
                 const rnd = Math.floor(100 + Math.random() * 900);
                 this.rates.push({
                     id: null,
                     code: 'LAB-JHT-ITEM' + rnd,
                     name: 'Upah Penjahit ',
                     unit: 'pcs',
                     standard_cost: 0
                 });
             },

             removeRate(index) {
                 if (this.rates.length > 1) {
                     this.rates.splice(index, 1);
                 }
             },

             showProductRateModal: false,
             isEditProductRate: false,
             productRateModalTitle: 'Tambah Tarif Produk Baru',
             productRateFormAction: '{{ route('tailor-labor-rates.store') }}',
             productRateForm: {
                 id: null,
                 code: '',
                 name: '',
                 unit: 'pcs',
                 standard_cost: 0
             },

             openCreateProductRate() {
                 this.isEditProductRate = false;
                 this.productRateModalTitle = 'Tambah Tarif Produk Baru';
                 this.productRateFormAction = '{{ route('tailor-labor-rates.store') }}';
                 this.productRateForm = {
                     id: null,
                     code: '',
                     name: '',
                     unit: 'pcs',
                     standard_cost: 0
                 };
                 this.showProductRateModal = true;
             },

             openEditProductRate(rate) {
                 this.isEditProductRate = true;
                 this.productRateModalTitle = 'Edit Tarif Produk: ' + rate.name;
                 this.productRateFormAction = '{{ url('tailor-labor-rates') }}/' + rate.id;
                 this.productRateForm = {
                     id: rate.id,
                     code: rate.code,
                     name: rate.name,
                     unit: rate.unit || 'pcs',
                     standard_cost: parseFloat(rate.standard_cost) || 0
                 };
                 this.showProductRateModal = true;
             }
         }">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-emerald-400">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-200 font-medium">Beban Gaji & Upah (5002)</span>
                    <span>/</span>
                    <span class="text-emerald-400 font-semibold">Upah Penjahit</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L9.75 9.75M12 12l-7 7m7-7l2.879-2.879a3 3 0 114.242 4.242L14.121 14.121z" />
                        </svg>
                    </span>
                    <span>Slip Upah Penjahit Borongan</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Kelola data penjahit, jadwal pembayaran (mingguan/bulanan), dan slip upah borongan per pcs (piece-rate) terintegrasi jurnal akuntansi
                </p>
            </div>

            <!-- Action Buttons: Tambah Penjahit & Buat Slip Baru -->
            <div class="flex items-center gap-2.5">
                <button 
                    type="button" 
                    @click="openCreateTailor()" 
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold text-xs shadow-xs transition-all hover:scale-105 active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    <span>+ Tambah Penjahit</span>
                </button>

                <a href="{{ route('tailor-payrolls.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Buat Slip Upah</span>
                </a>
            </div>
        </div>

        <!-- Alert Notifications -->
        @if (session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Summary Statistics Cards (Bulan Ini) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Total Penjahit Aktif -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Penjahit Aktif</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">Tim Jahit</span>
                </div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">
                    {{ $totalActiveTailors }} <span class="text-sm font-normal text-slate-400">Orang</span>
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Dari {{ $totalRegisteredTailors }} total penjahit terdaftar
                </div>
            </div>

            <!-- 2. Total Upah Kotor Bulan Ini -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Total Upah Kotor</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">Bulan Ini</span>
                </div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">
                    Rp {{ number_format($totalWagesThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Beban upah borongan pakaian
                </div>
            </div>

            <!-- 3. Total Kasbon Dipotong -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Total Kasbon Dipotong</span>
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">Potongan</span>
                </div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-2">
                    Rp {{ number_format($totalBonThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Pelunasan kasbon / piutang bon
                </div>
            </div>

            <!-- 4. Take Home Pay Bersih -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Take Home Pay</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">Kas Keluar</span>
                </div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2">
                    Rp {{ number_format($totalTakeHomePayThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Output: {{ number_format($totalPiecesThisMonth, 0, ',', '.') }} pcs pakaian
                </div>
            </div>
        </div>

        <!-- Master Tarif HPP Upah Penjahit (Direct Labor) Widget -->
        <div class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/60 rounded-2xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Tarif Standar Upah Penjahit Borongan per Produk (Acuan HPP)
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        type="button" 
                        @click="openCreateProductRate()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm shadow-emerald-950/30 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Tambah Produk</span>
                    </button>
                    <button 
                        type="button" 
                        @click="showRatesModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-sm transition-all hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Kelola Massal</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                @foreach ($laborMaterials as $lm)
                    <div class="group relative p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-amber-200/60 dark:border-slate-800 shadow-2xs hover:border-amber-400 dark:hover:border-amber-500/50 transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="text-[10px] font-mono text-slate-400 truncate">{{ $lm->code }}</span>
                                <div class="flex items-center gap-0.5 opacity-90 group-hover:opacity-100 transition-opacity">
                                    <button 
                                        type="button" 
                                        @click='openEditProductRate(@json($lm))'
                                        class="p-1 rounded text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors cursor-pointer"
                                        title="Edit Tarif Produk Ini"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if ($lm->id)
                                        <form method="POST" action="{{ route('tailor-labor-rates.destroy', $lm->id) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus / menonaktifkan tarif {{ $lm->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="submit" 
                                                class="p-1 rounded text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                                                title="Hapus Produk"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-100 block truncate" title="{{ $lm->name }}">
                                {{ $lm->clean_name ?? str_replace(['Upah Penjahit ', 'Biaya '], '', $lm->name) }}
                            </span>
                        </div>
                        <div class="mt-2 pt-1 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-extrabold text-amber-600 dark:text-amber-400">
                                {{ $lm->formatted_standard_cost }}
                            </span>
                            <span class="text-[9px] font-semibold text-slate-400">/{{ $lm->unit }}</span>
                        </div>
                    </div>
                @endforeach

                <!-- Tombol Shortcut Tambah Produk di Grid -->
                <button 
                    type="button" 
                    @click="openCreateProductRate()"
                    class="p-2.5 rounded-xl border border-dashed border-amber-300/80 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/10 hover:bg-amber-100/50 dark:hover:bg-amber-950/30 text-amber-700 dark:text-amber-400 font-bold text-xs flex flex-col items-center justify-center gap-1.5 transition-all hover:scale-[1.02] cursor-pointer min-h-[75px]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-[11px]">+ Tambah Produk</span>
                </button>
            </div>
        </div>

        <!-- NAVIGATION TABS: DAFTAR PENJAHIT (UTAMA) & RIWAYAT SLIP -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-1">
            <button 
                type="button" 
                @click="activeTab = 'tailors'"
                :class="activeTab === 'tailors' 
                    ? 'border-emerald-500 text-emerald-400 font-black' 
                    : 'border-transparent text-slate-400 hover:text-slate-200 font-semibold'"
                class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 text-xs sm:text-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Daftar Penjahit</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-950/80 text-emerald-300 border border-emerald-800/80 font-bold">
                    {{ $tailors->count() }}
                </span>
            </button>

            <button 
                type="button" 
                @click="activeTab = 'payrolls'"
                :class="activeTab === 'payrolls' 
                    ? 'border-emerald-500 text-emerald-400 font-black' 
                    : 'border-transparent text-slate-400 hover:text-slate-200 font-semibold'"
                class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 text-xs sm:text-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Riwayat Slip Upah</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-300 font-bold">
                    {{ $payrolls->total() }}
                </span>
            </button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: DAFTAR PENJAHIT (TAMPILAN UTAMA)    -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'tailors'" class="space-y-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Master Data Penjahit & Jadwal Pembayaran
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Pilih penjahit untuk langsung menghitung slip upah borongan mingguan atau bulanan
                        </p>
                    </div>

                    <!-- Search Filter Penjahit -->
                    <form method="GET" action="{{ route('tailor-payrolls.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="tailors">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input 
                                type="text" 
                                name="tailor_search" 
                                value="{{ request('tailor_search') }}" 
                                placeholder="Cari nama, no hp, bank..." 
                                class="pl-8 pr-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 w-52"
                            />
                        </div>
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors">
                            Cari
                        </button>
                        @if (request()->filled('tailor_search'))
                            <a href="{{ route('tailor-payrolls.index', ['tab' => 'tailors']) }}" class="text-xs text-rose-500 hover:underline">Reset</a>
                        @endif
                    </form>
                </div>

                <!-- Table Penjahit -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-black tracking-wider text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4">Nama Penjahit</th>
                                <th class="py-3.5 px-4">Waktu Pembayaran</th>
                                <th class="py-3.5 px-4">Rekening / Transfer</th>
                                <th class="py-3.5 px-4 text-center">Output Bulan Ini</th>
                                <th class="py-3.5 px-4 text-right">Upah Bersih Bulan Ini</th>
                                <th class="py-3.5 px-4 text-center">Status</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($tailors as $t)
                                <tr class="hover:bg-amber-50/30 dark:hover:bg-slate-800/40 transition-colors">
                                    <!-- Nama Penjahit & Kontak -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-amber-500/10 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-extrabold flex items-center justify-center text-xs border border-amber-500/20">
                                                {{ strtoupper(substr($t->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                                                    {{ $t->name }}
                                                </div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                                    @if ($t->phone)
                                                        <span>{{ $t->phone }}</span>
                                                    @else
                                                        <span class="text-slate-400 italic">Tanpa nomor kontak</span>
                                                    @endif
                                                    @if ($t->notes)
                                                        <span class="text-slate-300">•</span>
                                                        <span class="truncate max-w-[140px] text-[10px]" title="{{ $t->notes }}">{{ $t->notes }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Waktu Pembayaran (Mingguan / Bulanan) -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if ($t->payment_period === 'weekly')
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold text-xs">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span>Mingguan</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                                                Pencairan: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $t->payment_day ?: 'Setiap Sabtu' }}</span>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 font-bold text-xs">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span>Bulanan</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                                                Pencairan: <span class="font-semibold text-slate-700 dark:text-slate-300">Tgl {{ $t->payment_day ?: '25' }}</span>
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Rekening Pembayaran -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if ($t->bank_account_number)
                                            <div class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                                {{ $t->bank_name ?: 'Bank' }} • {{ $t->bank_account_number }}
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                a.n. {{ $t->bank_account_holder ?: $t->name }}
                                            </div>
                                        @else
                                            <span class="text-slate-400 text-xs italic">Tunai / Belum diatur</span>
                                        @endif
                                    </td>

                                    <!-- Output Bulan Ini -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if ($t->month_pieces > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                {{ number_format($t->month_pieces) }} pcs
                                            </span>
                                            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                                {{ $t->month_slips_count }} kali slip
                                            </div>
                                        @else
                                            <span class="text-slate-400 text-xs">-</span>
                                        @endif
                                    </td>

                                    <!-- Upah Bersih Bulan Ini -->
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        @if ($t->month_take_home_pay > 0)
                                            <div class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400">
                                                Rp {{ number_format($t->month_take_home_pay, 0, ',', '.') }}
                                            </div>
                                            @if ($t->month_latest_date)
                                                <div class="text-[10px] text-slate-400 font-mono">
                                                    Slip: {{ \Carbon\Carbon::parse($t->month_latest_date)->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-slate-400 text-xs">Rp 0</span>
                                        @endif
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if ($t->status === 'active')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                Non-aktif
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Tombol Aksi -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Tombol Buat / Hitung Slip Borongan -->
                                            <a href="{{ route('tailor-payrolls.create', ['tailor_id' => $t->id]) }}" 
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition-all hover:scale-105 active:scale-95 cursor-pointer"
                                               title="Hitung & Buat Slip Upah Borongan untuk Penjahit Ini">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span>Hitung Upah</span>
                                            </a>

                                            <!-- Tombol Edit Data Penjahit -->
                                            <button 
                                                type="button" 
                                                @click="openEditTailor({{ Js::from($t) }})" 
                                                class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                                title="Edit Data Penjahit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>

                                            <!-- Tombol Hapus Penjahit (Jika Belum Ada Slip) -->
                                            @if ($t->payrolls_count === 0)
                                                <form method="POST" action="{{ route('tailors.destroy', $t) }}" 
                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus data penjahit {{ $t->name }}?');" 
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors cursor-pointer"
                                                            title="Hapus Penjahit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-12 text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-500 flex items-center justify-center">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            </div>
                                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Belum ada data penjahit yang terdaftar</p>
                                            <p class="text-[11px] text-slate-400 max-w-sm">Daftarkan data penjahit dan atur waktu pembayaran (mingguan atau bulanan) untuk mempermudah perhitungan upah borongan.</p>
                                            <button type="button" @click="openCreateTailor()" class="mt-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-950/40 transition-all cursor-pointer">
                                                + Tambah Penjahit Pertama
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: RIWAYAT SLIP UPAH PENJAHIT BORONGAN -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'payrolls'" class="space-y-4" style="display: none;">
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            Riwayat Slip Upah Penjahit
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Daftar slip upah borongan yang telah diterbitkan dan dibukukan ke jurnal akuntansi
                        </p>
                    </div>

                    <!-- Filter / Search Form -->
                    <form method="GET" action="{{ route('tailor-payrolls.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="payrolls">
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Cari nama penjahit..." 
                            class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 w-48"
                        />
                        <input 
                            type="month" 
                            name="month" 
                            value="{{ request('month') }}" 
                            class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors">
                            Filter
                        </button>
                        @if (request()->hasAny(['search', 'month']))
                            <a href="{{ route('tailor-payrolls.index', ['tab' => 'payrolls']) }}" class="text-xs text-rose-500 hover:underline">Reset</a>
                        @endif
                    </form>
                </div>

                <!-- Table Riwayat Slip -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-black tracking-wider text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4">Tanggal / Hari</th>
                                <th class="py-3.5 px-4">Nama Penjahit</th>
                                <th class="py-3.5 px-4 text-center">Output (Pcs)</th>
                                <th class="py-3.5 px-4 text-right">Total Gaji</th>
                                <th class="py-3.5 px-4 text-right">Potongan Bon</th>
                                <th class="py-3.5 px-4 text-right">Take Home Pay</th>
                                <th class="py-3.5 px-4 text-center">Metode / Jurnal</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($payrolls as $p)
                                <tr class="hover:bg-amber-50/30 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-900 dark:text-white">
                                            {{ $p->period_label ?: $p->payroll_date->translatedFormat('l, j F Y') }}
                                        </div>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $p->payroll_date->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                                            {{ $p->tailor_name }}
                                        </div>
                                        @if ($p->tailor)
                                            <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">Penjahit Terdaftar</span>
                                        @elseif ($p->employee)
                                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">Karyawan</span>
                                        @else
                                            <span class="text-[10px] text-slate-400 font-medium">Penjahit Borongan Luar</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $p->total_pieces }} pcs
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                        {{ $p->formatted_total_wage }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        @if ($p->total_bon > 0)
                                            <span class="font-bold text-rose-600 dark:text-rose-400">
                                                -{{ $p->formatted_total_bon }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <span class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-1 rounded-lg border border-emerald-200/80 dark:border-emerald-800">
                                            {{ $p->formatted_take_home_pay }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 block">
                                            {{ $p->payment_method }}
                                        </span>
                                        @if ($p->journalEntry)
                                            <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 block" title="Ref Jurnal">
                                                {{ $p->journalEntry->reference }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('tailor-payrolls.show', $p) }}" 
                                               class="p-1.5 rounded-lg bg-emerald-950/80 hover:bg-emerald-900 text-emerald-300 border border-emerald-800/80 transition-colors"
                                               title="Lihat & Cetak Slip">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                            </a>

                                            <form method="POST" action="{{ route('tailor-payrolls.destroy', $p) }}" 
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus slip upah penjahit ini beserta jurnalnya?');" 
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="p-1.5 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 transition-colors cursor-pointer"
                                                        title="Hapus Slip">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-10 h-10 text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-xs">Belum ada slip upah penjahit yang dibuat.</p>
                                            <a href="{{ route('tailor-payrolls.create') }}" class="text-xs text-emerald-400 font-bold hover:underline">
                                                + Buat Slip Upah Pertama
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if ($payrolls->hasPages())
                    <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                        {{ $payrolls->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: TAMBAH / EDIT DATA PENJAHIT        -->
        <!-- ========================================== -->
        <div x-show="showTailorModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="min-h-screen px-4 text-center flex items-center justify-center">
                <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showTailorModal = false"></div>
                <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10">
                    
                    <!-- Header Modal -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L9.75 9.75M12 12l-7 7m7-7l2.879-2.879a3 3 0 114.242 4.242L14.121 14.121z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" x-text="tailorModalTitle"></h3>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500">Kelola informasi penjahit dan periode waktu pembayaran</p>
                            </div>
                        </div>
                        <button @click="showTailorModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Form Body -->
                    <form :action="tailorFormAction" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <template x-if="isEditTailor">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <!-- Nama Penjahit -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                Nama Penjahit <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                x-model="tailorForm.name" 
                                required 
                                placeholder="Contoh: Penjahit Adriana" 
                                class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            >
                        </div>

                        <!-- No Telepon / WA -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                Nomor Telepon / WhatsApp
                            </label>
                            <input 
                                type="text" 
                                name="phone" 
                                x-model="tailorForm.phone" 
                                placeholder="081234567890" 
                                class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            >
                        </div>

                        <!-- Waktu Pembayaran (Mingguan / Bulanan) -->
                        <div class="p-3.5 rounded-2xl bg-slate-50/50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-3">
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase">
                                Jadwal Waktu Pembayaran Upah
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
                                       :class="tailorForm.payment_period === 'weekly' ? 'bg-white dark:bg-slate-800 border-emerald-500 shadow-xs' : 'border-slate-200 dark:border-slate-700 opacity-60'">
                                    <input type="radio" name="payment_period" value="weekly" x-model="tailorForm.payment_period" class="text-emerald-500 focus:ring-emerald-400">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white">Mingguan</div>
                                        <div class="text-[10px] text-slate-400">Pencairan per minggu</div>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
                                       :class="tailorForm.payment_period === 'monthly' ? 'bg-white dark:bg-slate-800 border-emerald-500 shadow-xs' : 'border-slate-200 dark:border-slate-700 opacity-60'">
                                    <input type="radio" name="payment_period" value="monthly" x-model="tailorForm.payment_period" class="text-emerald-500 focus:ring-emerald-400">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white">Bulanan</div>
                                        <div class="text-[10px] text-slate-400">Pencairan per bulan</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Detail Hari / Tanggal Pencairan -->
                            <div>
                                <template x-if="tailorForm.payment_period === 'weekly'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                            Hari Pencairan Mingguan:
                                        </label>
                                        <select name="payment_day" x-model="tailorForm.payment_day" class="w-full text-xs rounded-xl bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500">
                                            <option value="Sabtu">Setiap Hari Sabtu</option>
                                            <option value="Jumat">Setiap Hari Jumat</option>
                                            <option value="Kamis">Setiap Hari Kamis</option>
                                            <option value="Rabu">Setiap Hari Rabu</option>
                                            <option value="Selasa">Setiap Hari Selasa</option>
                                            <option value="Senin">Setiap Hari Senin</option>
                                            <option value="Minggu">Setiap Hari Minggu</option>
                                        </select>
                                    </div>
                                </template>

                                <template x-if="tailorForm.payment_period === 'monthly'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                            Tanggal Pencairan Bulanan:
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-slate-500 font-medium">Setiap tanggal</span>
                                            <input 
                                                type="number" 
                                                name="payment_day" 
                                                x-model="tailorForm.payment_day" 
                                                min="1" 
                                                max="31" 
                                                class="w-24 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-1.5 focus:ring-emerald-500 text-center"
                                                placeholder="25"
                                            >
                                            <span class="text-xs text-slate-500 font-medium">tiap bulan</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Data Pembayaran Transfer (Bank & Rekening) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Nama Bank</label>
                                <input 
                                    type="text" 
                                    name="bank_name" 
                                    x-model="tailorForm.bank_name" 
                                    placeholder="BCA / Mandiri / Kas" 
                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                                >
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Nomor Rekening</label>
                                <input 
                                    type="text" 
                                    name="bank_account_number" 
                                    x-model="tailorForm.bank_account_number" 
                                    placeholder="1234567890" 
                                    class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                                >
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Atas Nama</label>
                                <input 
                                    type="text" 
                                    name="bank_account_holder" 
                                    x-model="tailorForm.bank_account_holder" 
                                    placeholder="Nama di buku tabungan" 
                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                                >
                            </div>
                        </div>

                        <!-- Status & Catatan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Status Keaktifan</label>
                                <select name="status" x-model="tailorForm.status" class="w-full text-xs rounded-xl bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500">
                                    <option value="active">Aktif (Menerima Order Jahit)</option>
                                    <option value="inactive">Non-aktif (Sedang Libur/Off)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Catatan / Spesialisasi</label>
                                <input 
                                    type="text" 
                                    name="notes" 
                                    x-model="tailorForm.notes" 
                                    placeholder="Spesialis jas pria / vest" 
                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                                >
                            </div>
                        </div>

                        <!-- Footer Modal Buttons -->
                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" @click="showTailorModal = false" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:bg-slate-800 cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer">
                                <span x-text="isEditTailor ? 'Simpan Perubahan' : 'Daftarkan Penjahit'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL PENGATURAN TARIF STANDAR UPAH PENJAHIT (HARGA PER PCS) -->
        <div 
            x-show="showRatesModal" 
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto" 
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div 
                    x-show="showRatesModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs transition-opacity" 
                    @click="showRatesModal = false"
                ></div>

                <div 
                    x-show="showRatesModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 border border-amber-200 dark:border-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-2xl sm:w-full p-6 space-y-5"
                >
                    <!-- Header Modal -->
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 rounded-2xl bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                                    Atur Tarif Standar Upah Penjahit (Harga per Pcs)
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Nilai ini akan otomatis terinput saat membuat lembar slip gaji baru (dapat tetap diedit per slip).
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="showRatesModal = false" 
                            class="p-1 rounded-xl text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form Pengaturan Tarif -->
                    <form method="POST" action="{{ route('tailor-payrolls.labor-rates.update') }}" class="space-y-4">
                        @csrf

                        <div class="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
                            <template x-for="(rate, index) in rates" :key="index">
                                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                    <input type="hidden" :name="'rates[' + index + '][id]'" :value="rate.id">
                                    
                                    <!-- Kode Item -->
                                    <div class="sm:w-36 shrink-0">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kode Item</label>
                                        <input 
                                            type="text" 
                                            :name="'rates[' + index + '][code]'" 
                                            x-model="rate.code" 
                                            required 
                                            class="w-full text-xs font-mono font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2.5 py-1.5 focus:ring-amber-500"
                                            placeholder="LAB-JHT-..."
                                        >
                                    </div>

                                    <!-- Nama Item -->
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Pekerjaan / Jenis Pakaian</label>
                                        <input 
                                            type="text" 
                                            :name="'rates[' + index + '][name]'" 
                                            x-model="rate.name" 
                                            required 
                                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2.5 py-1.5 focus:ring-amber-500"
                                            placeholder="Upah Penjahit ..."
                                        >
                                    </div>

                                    <!-- Satuan -->
                                    <div class="sm:w-16 shrink-0">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Satuan</label>
                                        <input 
                                            type="text" 
                                            :name="'rates[' + index + '][unit]'" 
                                            x-model="rate.unit" 
                                            required 
                                            class="w-full text-xs text-center font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-amber-500"
                                            placeholder="pcs"
                                        >
                                    </div>

                                    <!-- Biaya / Tarif per Pcs -->
                                    <div class="sm:w-44 shrink-0">
                                        <label class="block text-[10px] font-bold text-amber-500 uppercase mb-1">Harga per Pcs (Rp)</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-xs text-amber-500 font-bold">Rp</span>
                                            <input 
                                                type="number" 
                                                min="0" 
                                                step="500" 
                                                :name="'rates[' + index + '][standard_cost]'" 
                                                x-model.number="rate.standard_cost" 
                                                required 
                                                class="w-full text-xs font-mono font-bold text-right pl-8 pr-2.5 py-1.5 rounded-xl border border-amber-300 dark:border-amber-600/50 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-amber-500"
                                                placeholder="0"
                                            >
                                        </div>
                                    </div>

                                    <!-- Hapus Row -->
                                    <div class="sm:pt-4 flex items-center justify-end">
                                        <button 
                                            type="button" 
                                            @click="removeRate(index)" 
                                            x-show="rates.length > 1"
                                            class="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors cursor-pointer"
                                            title="Hapus Jenis Tarif"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Tombol Tambah Item Baru -->
                        <div class="flex items-center justify-between pt-1">
                            <button 
                                type="button" 
                                @click="addCustomRate()" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-300 dark:border-amber-700/80 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 hover:bg-amber-100 text-xs font-bold transition-colors cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Tambah Jenis Jahitan Baru</span>
                            </button>
                        </div>

                        <!-- Footer Modal Buttons -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button 
                                type="button" 
                                @click="showRatesModal = false" 
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-extrabold shadow-md shadow-amber-900/30 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Simpan Perubahan Tarif</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL TAMBAH & EDIT TARIF STANDAR PRODUK -->
        <div 
            x-show="showProductRateModal" 
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto" 
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div 
                    x-show="showProductRateModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs transition-opacity" 
                    @click="showProductRateModal = false"
                ></div>

                <div 
                    x-show="showProductRateModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-md sm:w-full p-6 space-y-5"
                >
                    <!-- Header Modal -->
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 rounded-2xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="productRateModalTitle"></h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Standar upah borongan jahit per potong (pcs).
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="showProductRateModal = false" 
                            class="p-1 rounded-xl text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form Tambah/Edit Produk -->
                    <form method="POST" :action="productRateFormAction" class="space-y-4">
                        @csrf
                        <template x-if="isEditProductRate">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <!-- Kode Produk -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                Kode Item / Produk
                            </label>
                            <input 
                                type="text" 
                                name="code" 
                                x-model="productRateForm.code" 
                                placeholder="Misal: LAB-JHT-GMS (Otomatis jika dikosongkan)" 
                                class="w-full text-xs font-mono font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                            >
                        </div>

                        <!-- Nama Produk -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                Nama Produk / Jenis Jahitan <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                x-model="productRateForm.name" 
                                required
                                placeholder="Misal: Upah Penjahit Gamis / Celana Kulot" 
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                            >
                        </div>

                        <!-- Grid: Satuan & Tarif per Pcs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Satuan <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    name="unit" 
                                    x-model="productRateForm.unit" 
                                    required
                                    placeholder="pcs" 
                                    class="w-full text-xs text-center font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 focus:ring-emerald-500"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-amber-500 uppercase mb-1">
                                    Tarif per Pcs (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs text-amber-500 font-bold">Rp</span>
                                    <input 
                                        type="number" 
                                        name="standard_cost" 
                                        x-model.number="productRateForm.standard_cost" 
                                        min="0"
                                        step="500"
                                        required
                                        placeholder="0" 
                                        class="w-full text-xs font-mono font-bold text-right pl-9 pr-3 py-2 rounded-xl border border-amber-300 dark:border-amber-600/50 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-amber-500"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Footer Modal Buttons -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button 
                                type="button" 
                                @click="showProductRateModal = false" 
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span x-text="isEditProductRate ? 'Simpan Perubahan' : 'Tambahkan Produk'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
