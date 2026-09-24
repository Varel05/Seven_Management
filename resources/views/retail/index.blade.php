<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-emerald-900/80 dark:bg-slate-800 border border-emerald-700/80 dark:border-slate-700 text-emerald-200 dark:text-emerald-300 flex items-center justify-center font-bold shadow-xs backdrop-blur-md shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                            {{ __('Manajemen Retail & Sewa Pakaian') }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/80 dark:bg-slate-800 text-emerald-200 dark:text-emerald-300 border border-emerald-700/80 dark:border-slate-700 backdrop-blur-md shadow-2xs">
                            Beli & Sewa Jas / Pakaian
                        </span>
                    </div>
                    <p class="text-xs text-emerald-200/80 dark:text-slate-400 mt-1 font-medium">
                        {{ __('Kelola stok pakaian jadi, penjualan beli langsung, rental sewa jas & pencatatan pengembalian unit') }}
                    </p>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-2.5 flex-wrap" x-data>
                <button 
                    type="button" 
                    @click="window.dispatchEvent(new CustomEvent('open-pos-modal'))"
                    onclick="window.dispatchEvent(new CustomEvent('open-pos-modal'))" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 via-teal-600 to-indigo-600 hover:from-emerald-400 hover:to-indigo-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span>Kasir</span>
                </button>
                <button 
                    type="button" 
                    @click="window.dispatchEvent(new CustomEvent('open-product-modal'))"
                    onclick="window.dispatchEvent(new CustomEvent('open-product-modal'))" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-900/80 hover:bg-emerald-800/80 dark:bg-slate-800 dark:hover:bg-slate-700 border border-emerald-600/60 dark:border-slate-700 text-emerald-100 dark:text-slate-200 text-xs font-semibold shadow-xs transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Produk Baru</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ 
        posModal: false, 
        productModal: false,
        editModal: false,
        activeTab: 'catalog', // 'catalog', 'rentals', 'sales'
        editingProduct: {},
        transactionType: 'sale', // 'sale' atau 'rental'
        rentalStartDate: '{{ date('Y-m-d') }}',
        rentalEndDate: '{{ date('Y-m-d', strtotime('+3 days')) }}',
        depositAmount: 100000,
        cartItems: [],
        paymentMethod: 'cash',
        selectedAccount: '{{ $paymentAccounts->first()?->id }}',
        customerName: '',
        customerPhone: '',
        notes: '',
        addItemToCart(prod, mode = null) {
            let type = mode || this.transactionType;
            let unitPrice = type === 'rental' 
                ? (prod.effective_rental_price || prod.rental_price || Math.round(prod.selling_price * 0.3)) 
                : prod.selling_price;

            let found = this.cartItems.find(i => i.product_id === prod.id);
            if (found) {
                if (found.quantity < prod.stock) {
                    found.quantity++;
                }
            } else {
                this.cartItems.push({
                    product_id: prod.id,
                    name: prod.name,
                    code: prod.code,
                    price: unitPrice,
                    selling_price: prod.selling_price,
                    rental_price: prod.effective_rental_price || prod.rental_price || Math.round(prod.selling_price * 0.3),
                    cost: prod.cost_price,
                    stock: prod.stock,
                    quantity: 1
                });
            }
        },
        setTransactionType(type) {
            this.transactionType = type;
            this.cartItems.forEach(item => {
                item.price = type === 'rental' ? item.rental_price : item.selling_price;
            });
        },
        removeItemFromCart(idx) {
            this.cartItems.splice(idx, 1);
        },
        get cartTotal() {
            return this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
        },
        get grandTotalWithDeposit() {
            let total = this.cartTotal;
            if (this.transactionType === 'rental') {
                total += Number(this.depositAmount || 0);
            }
            return total;
        },
        get cartTotalCost() {
            if (this.transactionType === 'rental') return 0;
            return this.cartItems.reduce((acc, item) => acc + (item.cost * item.quantity), 0);
        },
        get cartProfit() {
            return this.cartTotal - this.cartTotalCost;
        },
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }
    }" 
    @open-pos-modal.window="posModal = true; if ($event.detail?.type) setTransactionType($event.detail.type);"
    @open-product-modal.window="productModal = true">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

            <!-- Flash Alert Messages -->
            @if (session('success'))
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-sm shadow-xs backdrop-blur-md">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-sm shadow-xs backdrop-blur-md">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-sm space-y-1">
                    <div class="font-bold">Periksa kembali data yang dimasukkan:</div>
                    <ul class="list-disc list-inside text-xs">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Top Metric Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Nilai Persediaan (HPP) -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nilai Persediaan (HPP)</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-800 dark:text-white">
                        Rp {{ number_format($totalCostValue, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Potensi Nilai Jual: <span class="font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalRetailValue, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Total Unit Stok Baju & Status Sewa -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Stok Tersedia</span>
                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-800 dark:text-white flex items-baseline gap-2">
                        <span>{{ number_format($totalUnits, 0, ',', '.') }}</span>
                        <span class="text-xs font-medium text-slate-500">pcs di rak</span>
                    </div>
                    <div class="mt-1 text-xs flex items-center gap-1.5 flex-wrap">
                        @if ($activeRentalsCount > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                👔 {{ $activeRentalsCount }} transaksi sewa aktif
                            </span>
                        @endif
                        @if ($lowStockCount > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                ⚠️ {{ $lowStockCount }} stok menipis
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Penjualan & Sewa Bulan Ini -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Omzet Retail (Bulan Ini)</span>
                        <div class="w-9 h-9 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-800 dark:text-white">
                        Rp {{ number_format($monthRevenue, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Penjualan Beli (<span class="font-bold text-teal-600">4002</span>) & Sewa (<span class="font-bold text-indigo-600">4004</span>)
                    </div>
                </div>

                <!-- Laba Kotor Retail Bulan Ini -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Laba Kotor Retail</span>
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-purple-600 dark:text-purple-400">
                        Rp {{ number_format($monthProfit, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Setelah HPP penjualan (Sewa memiliki margin 100%)
                    </div>
                </div>
            </div>

            <!-- Tab Navigasi Halaman -->
            <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                <button 
                    type="button" 
                    @click="activeTab = 'catalog'" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2"
                    :class="activeTab === 'catalog' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Katalog Pakaian (Beli & Sewa)</span>
                </button>
                <button 
                    type="button" 
                    @click="activeTab = 'rentals'" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 relative"
                    :class="activeTab === 'rentals' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Penyewaan Aktif (Rental)</span>
                    @if ($activeRentalsCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white">
                            {{ $activeRentalsCount }}
                        </span>
                    @endif
                </button>
                <button 
                    type="button" 
                    @click="activeTab = 'sales'" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2"
                    :class="activeTab === 'sales' ? 'bg-slate-800 dark:bg-slate-700 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Semua Riwayat Transaksi</span>
                </button>
            </div>

            <!-- TAB 1: KATALOG PAKAIAN -->
            <div x-show="activeTab === 'catalog'" class="space-y-6">
                <!-- Filter & Search Bar (Bebas Rolling Kiri Kanan) -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <form method="GET" action="{{ route('retail.index') }}" class="space-y-3">
                        <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
                            <!-- Search Input -->
                            <div class="relative w-full sm:max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input 
                                    type="text" 
                                    name="q" 
                                    value="{{ $search }}" 
                                    placeholder="Cari nama pakaian, kode, warna..." 
                                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl bg-slate-100 dark:bg-slate-800/80 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                            </div>

                            <!-- Reset Filter Button if Active -->
                            @if (!empty($search) || !empty($selectedCategory))
                                <div class="w-full sm:w-auto flex justify-end">
                                    <a 
                                        href="{{ route('retail.index') }}" 
                                        class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/20 transition-all flex items-center gap-1.5"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Reset Filter</span>
                                    </a>
                                </div>
                            @endif
                        </div>

                        <!-- Category Pills (Wrapped, Bebas Rolling Kiri-Kanan) -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/80">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                Kategori:
                            </span>
                            <a 
                                href="{{ route('retail.index', array_filter(['q' => $search])) }}" 
                                class="px-2.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ empty($selectedCategory) ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                            >
                                Semua ({{ \App\Models\Product::count() }})
                            </a>
                            @foreach ($categories as $catKey => $catLabel)
                                <a 
                                    href="{{ route('retail.index', array_filter(['category' => $catKey, 'q' => $search])) }}" 
                                    class="px-2.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $selectedCategory === $catKey ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                >
                                    {{ $catLabel }}
                                </a>
                            @endforeach
                        </div>
                    </form>
                </div>

                <!-- Product Catalog Table -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-white">Daftar Stok Pakaian (Beli & Sewa)</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Monitoring kuantitas stok di rak, harga jual beli, serta tarif sewa jas/pakaian.</p>
                        </div>
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Menampilkan {{ $products->count() }} dari {{ $products->total() }} produk
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="py-3.5 px-4">Produk Pakaian</th>
                                    <th class="py-3.5 px-4">Kategori</th>
                                    <th class="py-3.5 px-4 text-center">Ukuran / Warna</th>
                                    <th class="py-3.5 px-4 text-right">Harga Modal (HPP)</th>
                                    <th class="py-3.5 px-4 text-right">Harga Beli (Jual)</th>
                                    <th class="py-3.5 px-4 text-right">Tarif Sewa (Rental)</th>
                                    <th class="py-3.5 px-4 text-center">Stok Rak</th>
                                    <th class="py-3.5 px-4 text-center">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800">
                                @forelse ($products as $product)
                                    @php
                                        $unitProfit = $product->selling_price - $product->cost_price;
                                        $marginPercent = $product->selling_price > 0 ? round(($unitProfit / $product->selling_price) * 100, 1) : 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-800 dark:text-white text-sm">{{ $product->name }}</div>
                                            <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ $product->code }}</div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                {{ $product->category_label }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $product->size ?? '-' }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $product->color ?? '-' }}</div>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono text-slate-600 dark:text-slate-400">
                                            Rp {{ number_format($product->cost_price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                            Rp {{ number_format($product->selling_price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm">
                                            {{ $product->formatted_rental_price }}
                                            <span class="block text-[10px] text-slate-400 font-normal">/ 3 hari</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            @if ($product->stock <= 0)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                                    Habis (0)
                                                </span>
                                            @elseif ($product->isLowStock())
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                    Sisa {{ $product->stock }} (Menipis)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                    {{ $product->stock }} pcs
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                @if ($product->stock > 0)
                                                    <button 
                                                        type="button" 
                                                        @click="setTransactionType('sale'); addItemToCart({{ Js::from($product) }}, 'sale'); posModal = true;" 
                                                        class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all cursor-pointer" 
                                                        title="Jual Produk Ini (Beli Langsung)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                                    </button>
                                                    <button 
                                                        type="button" 
                                                        @click="setTransactionType('rental'); addItemToCart({{ Js::from($product) }}, 'rental'); posModal = true;" 
                                                        class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all cursor-pointer" 
                                                        title="Sewakan Produk Ini (Rental)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    </button>
                                                @endif
                                                <button 
                                                    type="button" 
                                                    @click="editingProduct = {{ Js::from($product) }}; editModal = true;" 
                                                    class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer" 
                                                    title="Edit Produk"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                <form method="POST" action="{{ route('retail.products.destroy', $product) }}" onsubmit="return confirm('Yakin ingin menghapus produk ini?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10 transition-all cursor-pointer" title="Hapus Produk">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-10 text-slate-400">
                                            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            </div>
                                            <span>Tidak ada produk pakaian yang cocok. Klik tombol <strong>+ Produk Baru</strong> di atas untuk menambah stok.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($products->hasPages())
                        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- TAB 2: PENYEWAAN AKTIF (RENTAL TRACKING & PENGEMBALIAN) -->
            <div x-show="activeTab === 'rentals'" class="space-y-6">
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></span>
                                Daftar Pakaian yang Sedang Disewa (Aktif)
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Monitoring barang yang sedang dibawa pelanggan. Klik <strong>Terima Kembali</strong> saat jas/pakaian telah dikembalikan untuk memulihkan stok.</p>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                            {{ $activeRentalsCount }} Jas Sedang Disewa
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="py-3 px-4">Invoice / Tgl Sewa</th>
                                    <th class="py-3 px-4">Penyewa (Klien)</th>
                                    <th class="py-3 px-4">Pakaian yang Disewa</th>
                                    <th class="py-3 px-4 text-center">Batas Pengembalian</th>
                                    <th class="py-3 px-4 text-right">Biaya Sewa</th>
                                    <th class="py-3 px-4 text-right">Uang Jaminan (DP)</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi Pengembalian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800">
                                @forelse ($activeRentals as $rental)
                                    @php
                                        $isOverdue = $rental->isOverdue();
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                        <td class="py-3.5 px-4 font-mono">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $rental->invoice_number }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $rental->sale_date->format('d M Y') }}</div>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $rental->customer_name }}
                                            @if ($rental->customer_phone)
                                                <div class="text-[10px] text-slate-400">{{ $rental->customer_phone }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="space-y-1">
                                                @foreach ($rental->items as $item)
                                                    <div>
                                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $item->product?->name ?? 'Pakaian' }}</span>
                                                        <span class="text-slate-400 text-[11px] font-mono">({{ $item->quantity }} pcs)</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="font-bold font-mono {{ $isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200' }}">
                                                {{ $rental->rental_end_date ? $rental->rental_end_date->format('d M Y') : '-' }}
                                            </div>
                                            @if ($isOverdue)
                                                <span class="text-[10px] font-bold text-rose-500 uppercase">Terlambat Kembali</span>
                                            @else
                                                <span class="text-[10px] text-slate-400">Tepat Waktu</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ $rental->formatted_total_amount }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-600 dark:text-slate-300">
                                            Rp {{ number_format($rental->deposit_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            @if ($isOverdue)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 border border-rose-500/20">
                                                    ⚠️ Overdue
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-600 border border-indigo-500/20">
                                                    Sedang Disewa
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <form method="POST" action="{{ route('retail.rentals.return', $rental) }}" onsubmit="return confirm('Konfirmasi penerimaan pakaian sewa untuk invoice #{{ $rental->invoice_number }}? Stok produk akan otomatis dikembalikan ke rak.');">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition-all cursor-pointer"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    <span>Terima Kembali</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-10 text-slate-400">
                                            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <span>Tidak ada pakaian yang sedang disewa saat ini. Semua inventaris aman di rak.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: RIWAYAT TRANSAKSI (BELI & SEWA) -->
            <div x-show="activeTab === 'sales'" class="space-y-6">
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-slate-200/80 dark:border-slate-800">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Riwayat Transaksi Retail (Penjualan Beli & Sewa)</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Semua transaksi di bawah ini telah otomatis dibukukan secara double-entry ke Jurnal Akuntansi Seven Management.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="py-3 px-4">Invoice / Tanggal</th>
                                    <th class="py-3 px-4">Tipe</th>
                                    <th class="py-3 px-4">Pelanggan</th>
                                    <th class="py-3 px-4">Rincian Pakaian</th>
                                    <th class="py-3 px-4">Metode Bayar</th>
                                    <th class="py-3 px-4 text-right">Total Transaksi</th>
                                    <th class="py-3 px-4 text-right">Laba / Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800">
                                @forelse ($recentSales as $sale)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                        <td class="py-3.5 px-4 font-mono">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $sale->invoice_number }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $sale->sale_date->format('d M Y H:i') }}</div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            @if ($sale->isRental())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-500/10 text-indigo-600 border border-indigo-500/20">
                                                    Sewa (Rental)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                                    Beli (Sale)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $sale->customer_name }}
                                            @if ($sale->customer_phone)
                                                <div class="text-[10px] text-slate-400">{{ $sale->customer_phone }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="space-y-1">
                                                @foreach ($sale->items as $item)
                                                    <div class="text-xs">
                                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $item->product?->name ?? 'Produk' }}</span>
                                                        <span class="text-slate-400 text-[11px]">x{{ $item->quantity }} (@ Rp {{ number_format($item->subtotal / $item->quantity, 0, ',', '.') }})</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $sale->payment_method === 'cash' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-blue-500/10 text-blue-600' }}">
                                                {{ $sale->payment_method }}
                                            </span>
                                            @if ($sale->account)
                                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $sale->account->name }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                            {{ $sale->formatted_total_amount }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono font-bold text-purple-600 dark:text-purple-400">
                                            +Rp {{ number_format($sale->gross_profit, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-8 text-slate-400">
                                            Belum ada transaksi tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL 1: KASIR CEPAT (Beli Langsung vs Sewa Jas) -->
        <div 
            x-show="posModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div 
                @click.away="posModal = false" 
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 max-h-[90vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold"
                            :class="transactionType === 'rental' ? 'bg-indigo-500/10 text-indigo-600' : 'bg-emerald-500/10 text-emerald-600'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-white" x-text="transactionType === 'rental' ? 'Kasir Penyewaan Jas (Rental)' : 'Kasir Penjualan Retail Cepat (Beli)'"></h3>
                            <p class="text-xs text-slate-500">Pilih mode transaksi beli atau sewa pakaian, otomatis dicatat ke buku besar</p>
                        </div>
                    </div>
                    <button @click="posModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form method="POST" action="{{ route('retail.sales.store') }}" class="space-y-4">
                    @csrf

                    <!-- Mode Pilihan: Beli vs Sewa -->
                    <div class="p-1 rounded-xl bg-slate-100 dark:bg-slate-800 grid grid-cols-2 gap-1 border border-slate-200 dark:border-slate-700">
                        <button 
                            type="button" 
                            @click="setTransactionType('sale')" 
                            class="py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                            :class="transactionType === 'sale' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span>Beli Langsung (Sale)</span>
                        </button>
                        <button 
                            type="button" 
                            @click="setTransactionType('rental')" 
                            class="py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                            :class="transactionType === 'rental' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Sewa Pakaian (Rental)</span>
                        </button>
                    </div>

                    <input type="hidden" name="transaction_type" :value="transactionType">

                    <!-- Tanggal Sewa & Deposit (Hanya Jika Rental) -->
                    <template x-if="transactionType === 'rental'">
                        <div class="p-3.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/60 space-y-3">
                            <div class="text-xs font-bold text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Parameter Periode Sewa & Uang Jaminan:</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Mulai Sewa</label>
                                    <input type="date" name="rental_start_date" x-model="rentalStartDate" required class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Batas Pengembalian *</label>
                                    <input type="date" name="rental_end_date" x-model="rentalEndDate" required class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Uang Jaminan / Deposit</label>
                                    <input type="number" name="deposit_amount" x-model.number="depositAmount" min="0" placeholder="Rp 100.000" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Pelanggan & Pembayaran -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Pelanggan / Klien *</label>
                            <input type="text" name="customer_name" x-model="customerName" required placeholder="Contoh: Bpk. Rudi" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">No. WhatsApp / HP</label>
                            <input type="text" name="customer_phone" x-model="customerPhone" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Metode Bayar</label>
                            <select name="payment_method" x-model="paymentMethod" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                <option value="cash">Tunai (Cash)</option>
                                <option value="transfer">Transfer Bank</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Masuk ke Rekening / Kas</label>
                            <select name="account_id" x-model="selectedAccount" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                @foreach ($paymentAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Pilih Produk Dropdown -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Pilih Pakaian dari Stok</span>
                            <span class="text-[11px] text-slate-400">Harga otomatis menyesuaikan mode (Beli / Sewa)</span>
                        </div>
                        <div class="flex gap-2">
                            <select id="quickProductSelect" class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                <option value="">-- Pilih Produk Pakaian --</option>
                                @foreach ($availableProducts as $avail)
                                    <option value="{{ $avail->id }}" data-product="{{ json_encode($avail) }}">
                                        {{ $avail->name }} ({{ $avail->code }}) - Jual: Rp {{ number_format($avail->selling_price, 0, ',', '.') }} | Sewa: Rp {{ number_format($avail->effective_rental_price, 0, ',', '.') }} [Stok: {{ $avail->stock }}]
                                    </option>
                                @endforeach
                            </select>
                            <button 
                                type="button" 
                                @click="
                                    let sel = document.getElementById('quickProductSelect');
                                    let opt = sel.options[sel.selectedIndex];
                                    if (opt && opt.dataset.product) {
                                        addItemToCart(JSON.parse(opt.dataset.product));
                                    }
                                "
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shrink-0 cursor-pointer"
                            >
                                + Tambah
                            </button>
                        </div>

                        <!-- Daftar Item Dalam Keranjang -->
                        <div class="space-y-2 mt-2">
                            <template x-for="(item, idx) in cartItems" :key="idx">
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="flex-1 pr-2">
                                        <div class="font-bold text-xs text-slate-800 dark:text-white" x-text="item.name"></div>
                                        <div class="text-[11px] text-slate-400">
                                            <span x-text="formatRupiah(item.price) + ' / pcs'"></span>
                                            <span class="text-[10px] px-1.5 py-0.2 rounded font-semibold ml-1"
                                                :class="transactionType === 'rental' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300'"
                                                x-text="transactionType === 'rental' ? 'Tarif Sewa' : 'Harga Beli'"
                                            ></span>
                                        </div>
                                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center border border-slate-300 dark:border-slate-700 rounded-lg overflow-hidden">
                                            <button 
                                                type="button" 
                                                @click="if (item.quantity > 1) item.quantity--" 
                                                class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-xs font-bold"
                                            >-</button>
                                            <input 
                                                type="number" 
                                                :name="'items['+idx+'][quantity]'" 
                                                x-model.number="item.quantity" 
                                                :max="item.stock" 
                                                min="1" 
                                                class="w-12 text-center text-xs py-1 px-1 bg-transparent border-none focus:ring-0 font-bold"
                                            >
                                            <button 
                                                type="button" 
                                                @click="if (item.quantity < item.stock) item.quantity++" 
                                                class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-xs font-bold"
                                            >+</button>
                                        </div>
                                        <div class="w-24 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-xs" x-text="formatRupiah(item.price * item.quantity)"></div>
                                        <button type="button" @click="removeItemFromCart(idx)" class="text-rose-500 hover:text-rose-700 p-1 text-xs cursor-pointer">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="cartItems.length === 0">
                                <div class="text-center py-4 text-xs text-slate-400 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl">
                                    Keranjang masih kosong. Pilih produk di atas lalu klik Tambah.
                                </div>
                            </template>
                        </div>

                        <!-- Ringkasan Total & Profit -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-1.5" x-show="cartItems.length > 0">
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span x-text="transactionType === 'rental' ? 'Subtotal Biaya Sewa:' : 'Estimasi HPP (Modal):'"></span>
                                <span class="font-mono" x-text="transactionType === 'rental' ? formatRupiah(cartTotal) : formatRupiah(cartTotalCost)"></span>
                            </div>
                            <template x-if="transactionType === 'rental' && depositAmount > 0">
                                <div class="flex items-center justify-between text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                    <span>Uang Jaminan (Titipan Deposit):</span>
                                    <span class="font-mono" x-text="formatRupiah(depositAmount)"></span>
                                </div>
                            </template>
                            <template x-if="transactionType === 'sale'">
                                <div class="flex items-center justify-between text-xs text-purple-600 dark:text-purple-400 font-semibold">
                                    <span>Estimasi Laba Kotor:</span>
                                    <span class="font-mono" x-text="'+ ' + formatRupiah(cartProfit)"></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between text-sm font-black text-slate-800 dark:text-white pt-1 border-t border-slate-200 dark:border-slate-700">
                                <span>TOTAL DITERIMA KASIR:</span>
                                <span class="text-emerald-600 dark:text-emerald-400 text-base font-mono" x-text="formatRupiah(grandTotalWithDeposit)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="posModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">Batal</button>
                        <button 
                            type="submit" 
                            :disabled="cartItems.length === 0" 
                            class="px-5 py-2.5 rounded-xl disabled:opacity-50 text-white text-xs font-bold shadow-md cursor-pointer transition-all"
                            :class="transactionType === 'rental' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500' : 'bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500'"
                        >
                            <span x-text="transactionType === 'rental' ? 'Proses Penyewaan & Bukukan Jurnal' : 'Proses Penjualan & Bukukan Jurnal'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: TAMBAH PRODUK BARU (Beli & Sewa) -->
        <div 
            x-show="productModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div 
                @click.away="productModal = false" 
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 max-h-[90vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Tambah Produk Pakaian (Bisa Beli & Sewa)</h3>
                    <button @click="productModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form method="POST" action="{{ route('retail.products.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kode Produk SKU *</label>
                            <input type="text" name="code" required placeholder="Misal: JAS-002" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kategori Pakaian *</label>
                            <select name="category" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold">
                                @foreach ($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Pakaian *</label>
                        <input type="text" name="name" required placeholder="Misal: Tuxedo Peak Lapel Midnight Black" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ukuran (Size)</label>
                            <input type="text" name="size" placeholder="S, M, L, XL, 32..." class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Warna</label>
                            <input type="text" name="color" placeholder="Navy, Hitam, Deep Blue..." class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Harga Modal (HPP) *</label>
                            <input type="number" name="cost_price" required min="0" placeholder="Rp" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Harga Beli (Jual) *</label>
                            <input type="number" name="selling_price" required min="0" placeholder="Rp" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tarif Sewa (Rental)</label>
                            <input type="number" name="rental_price" min="0" placeholder="Otomatis jika kosong" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jumlah Stok Awal *</label>
                            <input type="number" name="stock" required min="0" value="5" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Batas Minimal Stok (Alert)</label>
                            <input type="number" name="min_stock" min="0" value="2" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Deskripsi Pakaian</label>
                        <textarea name="description" rows="2" placeholder="Keterangan bahan, potongan, atau keunggulan jas/baju..." class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="productModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md cursor-pointer">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 3: EDIT PRODUK (Beli & Sewa) -->
        <div 
            x-show="editModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
        >
            <div 
                @click.away="editModal = false" 
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 max-h-[90vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Edit Informasi Produk Pakaian</h3>
                    <button @click="editModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form :action="'/retail/products/' + editingProduct.id" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kode Produk SKU *</label>
                            <input type="text" name="code" x-model="editingProduct.code" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kategori Pakaian *</label>
                            <select name="category" x-model="editingProduct.category" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold">
                                @foreach ($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Pakaian *</label>
                        <input type="text" name="name" x-model="editingProduct.name" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ukuran (Size)</label>
                            <input type="text" name="size" x-model="editingProduct.size" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Warna</label>
                            <input type="text" name="color" x-model="editingProduct.color" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Harga Modal (HPP) *</label>
                            <input type="number" name="cost_price" x-model="editingProduct.cost_price" required min="0" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Harga Jual (Beli) *</label>
                            <input type="number" name="selling_price" x-model="editingProduct.selling_price" required min="0" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tarif Sewa (Rental)</label>
                            <input type="number" name="rental_price" x-model="editingProduct.rental_price" min="0" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Stok Baju Sekarang *</label>
                            <input type="number" name="stock" x-model="editingProduct.stock" required min="0" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Batas Minimal Stok</label>
                            <input type="number" name="min_stock" x-model="editingProduct.min_stock" min="0" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md cursor-pointer">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
