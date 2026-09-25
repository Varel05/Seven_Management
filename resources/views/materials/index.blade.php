<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shadow-xs">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                        {{ __('Manajemen Bahan Baku & Komponen HPP') }}
                    </h1>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        {{ __('Master kain, aksesoris, ongkos jahit, dan overhead pabrik sebagai acuan stok & kartu HPP baju.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cost-sheets.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-950/70 hover:bg-emerald-900 text-emerald-200 border border-emerald-700/60 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    <span>Kartu HPP (BOM)</span>
                </a>
                <a href="{{ route('production.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <span>Produksi Baju</span>
                </a>
                <button type="button" @click="$dispatch('open-modal', 'add-material-modal')" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>Tambah Bahan</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        restockModalOpen: false,
        selectedMaterial: null,
        openRestockModal(mat) {
            this.selectedMaterial = mat;
            this.restockModalOpen = true;
        }
    }">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

            <!-- Flash Alert -->
            @if (session('success'))
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span class="text-sm font-semibold">{{ session('error') }}</span>
                </div>
            @endif

            <!-- 4 Statistik Kartu Ringkasan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Valuasi Persediaan Gudang -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Nilai Stok Bahan</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mt-1">
                            Rp {{ number_format($totalStockValuation, 0, ',', '.') }}
                        </h3>
                        <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 block">Akun COA Persediaan 1004</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <!-- Jenis Bahan Fisik -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Bahan Fisik Terdata</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mt-1">
                            {{ $physicalCount }} <span class="text-sm font-medium text-slate-400">item</span>
                        </h3>
                        <span class="text-[11px] text-slate-500 font-medium mt-0.5 block">Kain, furing, busa, kancing</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                </div>

                <!-- Peringatan Stok Menipis -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Alert Stok Menipis</span>
                        <h3 class="text-xl font-black {{ $lowStockCount > 0 ? 'text-amber-500' : 'text-slate-900 dark:text-white' }} mt-1">
                            {{ $lowStockCount }} <span class="text-sm font-medium text-slate-400">item</span>
                        </h3>
                        <span class="text-[11px] text-slate-500 font-medium mt-0.5 block">Perlu restock segera</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl {{ $lowStockCount > 0 ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-400' }} flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>

                <!-- Total Komponen Biaya -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Katalog Komponen</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mt-1">
                            {{ $totalMaterialKinds }} <span class="text-sm font-medium text-slate-400">komponen</span>
                        </h3>
                        <span class="text-[11px] text-slate-500 font-medium mt-0.5 block">Termasuk upah jahit & overhead</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <!-- Baris 1: Pencarian & Dropdown Status Stok -->
                <form method="GET" action="{{ route('materials.index') }}" class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center justify-between">
                    @if ($selectedCategory)
                        <input type="hidden" name="category" value="{{ $selectedCategory }}">
                    @endif

                    <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center flex-1">
                        <!-- Input Pencarian -->
                        <div class="relative w-full sm:max-w-md">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input 
                                type="text" 
                                name="q" 
                                value="{{ $search }}" 
                                placeholder="Cari kode atau nama bahan baku/komponen..." 
                                class="w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-slate-100 dark:bg-slate-800/80 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                            >
                        </div>

                        <!-- Filter Status Stok Gudang -->
                        <div class="w-full sm:w-auto">
                            <select name="stock_status" onchange="this.form.submit()" class="w-full sm:w-auto text-xs rounded-xl bg-slate-100 dark:bg-slate-800/80 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 py-2.5 px-3 cursor-pointer">
                                <option value="">Semua Kondisi Stok</option>
                                <option value="low" {{ $selectedStock === 'low' ? 'selected' : '' }}>⚠️ Stok Menipis (<= Min)</option>
                                <option value="out" {{ $selectedStock === 'out' ? 'selected' : '' }}>⛔ Stok Habis (0)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cari Bahan</span>
                        </button>

                        @if ($search || $selectedCategory || $selectedStock)
                            <a href="{{ route('materials.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold transition-all flex items-center gap-1 cursor-pointer" title="Hapus semua filter">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Reset</span>
                            </a>
                        @endif
                    </div>
                </form>

                <!-- Baris 2: Filter Kategori Bahan (Auto-Wrap jika Melebihi Lebar Layar) -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mr-1 shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span>Filter Kategori:</span>
                    </span>

                    <a href="{{ route('materials.index', array_filter(['q' => $search, 'stock_status' => $selectedStock])) }}" 
                       class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ empty($selectedCategory) ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                        Semua Kategori
                    </a>

                    @foreach ($categories as $catKey => $catLabel)
                        <a href="{{ route('materials.index', array_filter(['category' => $catKey, 'q' => $search, 'stock_status' => $selectedStock])) }}" 
                           class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ $selectedCategory === $catKey ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            {{ $catLabel }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Tabel Master Bahan Baku -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-4">Kode & Nama Komponen</th>
                                <th class="py-3 px-4">Kategori</th>
                                <th class="py-3 px-4">Satuan</th>
                                <th class="py-3 px-4 text-right">Harga Standar</th>
                                <th class="py-3 px-4 text-center">Stok Gudang</th>
                                <th class="py-3 px-4 text-right">Total Nilai Persediaan</th>
                                <th class="py-3 px-4">Akun Keuangan (COA)</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                            @forelse ($materials as $m)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $m->name }}</div>
                                        <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ $m->code }}</div>
                                        @if ($m->description)
                                            <div class="text-[10px] text-slate-400 truncate max-w-xs">{{ $m->description }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $catBadge = match($m->category) {
                                                'raw_material' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                                'supporting_material' => 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20',
                                                'accessory' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                                                'direct_labor' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                                'overhead' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                                default => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $catBadge }}">
                                            {{ $m->category_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-mono">{{ $m->unit }}</td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900 dark:text-white">
                                        {{ $m->formatted_standard_cost }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if ($m->isPhysical())
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold {{ $m->stock <= $m->min_stock ? ($m->stock <= 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400') : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $m->stock <= $m->min_stock ? ($m->stock <= 0 ? 'bg-rose-500 animate-pulse' : 'bg-amber-500 animate-pulse') : 'bg-emerald-500' }}"></span>
                                                <span>{{ $m->formatted_stock }}</span>
                                            </div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">Min: {{ number_format($m->min_stock, 0) }} {{ $m->unit }}</div>
                                        @else
                                            <span class="text-slate-400 italic text-[11px]">- Jasa/Biaya -</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-700 dark:text-slate-300">
                                        @if ($m->isPhysical())
                                            Rp {{ number_format($m->stock * $m->standard_cost, 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($m->account)
                                            <span class="text-[11px] text-slate-600 dark:text-slate-400 block font-mono font-semibold">{{ $m->account->code }}</span>
                                            <span class="text-[10px] text-slate-400 truncate block max-w-[130px]">{{ $m->account->name }}</span>
                                        @else
                                            <span class="text-slate-400 italic text-[11px]">Belum diatur</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if ($m->isPhysical())
                                                <button type="button" @click="openRestockModal({{ json_encode($m) }})" 
                                                        title="Restock / Tambah Stok Masuk"
                                                        class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 hover:text-white dark:text-emerald-400 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            @endif
                                            <button type="button" @click="$dispatch('open-modal', 'edit-material-modal-{{ $m->id }}')" 
                                                    title="Edit Bahan"
                                                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <form method="POST" action="{{ route('materials.destroy', $m) }}" onsubmit="return confirm('Hapus komponen bahan {{ $m->name }}?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Hapus" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white dark:text-rose-400 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Edit Modal per Item -->
                                        <x-modal name="edit-material-modal-{{ $m->id }}" maxWidth="lg">
                                            <form method="POST" action="{{ route('materials.update', $m) }}" class="p-6 space-y-4 text-left">
                                                @csrf
                                                @method('PUT')
                                                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Edit Bahan: {{ $m->name }}</h3>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kode Komponen *</label>
                                                        <input type="text" name="code" value="{{ $m->code }}" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Nama Bahan *</label>
                                                        <input type="text" name="name" value="{{ $m->name }}" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kategori *</label>
                                                        <select name="category" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                            @foreach ($categories as $ck => $cl)
                                                                <option value="{{ $ck }}" {{ $m->category === $ck ? 'selected' : '' }}>{{ $cl }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Satuan *</label>
                                                        <input type="text" name="unit" value="{{ $m->unit }}" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Harga Standar (Rp) *</label>
                                                        <input type="number" step="0.01" name="standard_cost" value="{{ $m->standard_cost }}" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Batas Min. Stok</label>
                                                        <input type="number" step="0.01" name="min_stock" value="{{ $m->min_stock }}" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Akun Akuntansi (COA)</label>
                                                    <select name="account_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                                        <option value="">-- Pilih Akun --</option>
                                                        @foreach ($accounts as $acc)
                                                            <option value="{{ $acc->id }}" {{ $m->account_id === $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Deskripsi / Catatan</label>
                                                    <textarea name="description" rows="2" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">{{ $m->description }}</textarea>
                                                </div>
                                                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                                                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                                                    <x-primary-button>Simpan Perubahan</x-primary-button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-400">
                                        Tidak ada data komponen bahan ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($materials->hasPages())
                    <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                        {{ $materials->links() }}
                    </div>
                @endif
            </div>

            <!-- Riwayat Mutasi Stok Terakhir (Log Card) -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Riwayat 10 Mutasi Stok Terakhir</h2>
                    </div>
                    <span class="text-[11px] text-slate-400">Kartu Stok Otomatis</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="text-slate-400 text-[10px] uppercase font-bold border-b border-slate-100 dark:border-slate-800">
                                <th class="py-2">Waktu</th>
                                <th class="py-2">Nama Bahan</th>
                                <th class="py-2">Tipe Mutasi</th>
                                <th class="py-2 text-right">Kuantitas</th>
                                <th class="py-2 text-right">Harga Satuan</th>
                                <th class="py-2">Ref / Nomor Batch</th>
                                <th class="py-2">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                            @forelse ($recentMovements as $mov)
                                <tr>
                                    <td class="py-2 font-mono text-slate-400 text-[11px]">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 font-bold text-slate-900 dark:text-white">{{ $mov->material?->name ?? 'Bahan Dihapus' }}</td>
                                    <td class="py-2">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $mov->type === 'in' ? 'bg-emerald-500/10 text-emerald-500' : ($mov->type === 'out' ? 'bg-rose-500/10 text-rose-500' : 'bg-amber-500/10 text-amber-500') }}">
                                            {{ $mov->type_label }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-right font-mono font-bold {{ $mov->type === 'in' ? 'text-emerald-500' : ($mov->type === 'out' ? 'text-rose-500' : 'text-amber-500') }}">
                                        {{ $mov->formatted_quantity }}
                                    </td>
                                    <td class="py-2 text-right font-mono">Rp {{ number_format($mov->unit_cost, 0, ',', '.') }}</td>
                                    <td class="py-2 font-mono text-[11px] text-slate-400">{{ $mov->reference_number ?: '-' }}</td>
                                    <td class="py-2 text-slate-400 text-[11px]">{{ $mov->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-center text-slate-400">Belum ada riwayat mutasi stok bahan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Bahan Baru -->
        <x-modal name="add-material-modal" maxWidth="lg">
            <form method="POST" action="{{ route('materials.store') }}" class="p-6 space-y-4">
                @csrf
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">+</div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Tambah Komponen Bahan / Biaya Baru</h3>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kode Komponen *</label>
                        <input type="text" name="code" required placeholder="Contoh: MAT-JTB" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Nama Bahan / Komponen *</label>
                        <input type="text" name="name" required placeholder="Contoh: Kain Jetblack Super" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kategori *</label>
                        <select name="category" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                            @foreach ($categories as $ck => $cl)
                                <option value="{{ $ck }}">{{ $cl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Satuan Ukur *</label>
                        <input type="text" name="unit" required placeholder="meter / pcs / roll" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Harga Standar (Rp) *</label>
                        <input type="number" step="0.01" name="standard_cost" required placeholder="0" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Stok Awal</label>
                        <input type="number" step="0.01" name="stock" value="0" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Min. Alert Stok</label>
                        <input type="number" step="0.01" name="min_stock" value="5" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Akun Bagan Keuangan (COA)</label>
                    <select name="account_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Akun Akuntansi --</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $acc->code === '1004' ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Keterangan / Spesifikasi</label>
                    <textarea name="description" rows="2" placeholder="Catatan supplier, grade kain, atau karakteristik..." class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-primary-button>Simpan Bahan Baru</x-primary-button>
                </div>
            </form>
        </x-modal>

        <!-- Modal Restock Masuk -->
        <div x-show="restockModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4" @click.away="restockModalOpen = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">📥</div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Restock / Pembelian Bahan</h3>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold" x-text="selectedMaterial ? selectedMaterial.name + ' (' + selectedMaterial.code + ')' : ''"></p>
                        </div>
                    </div>
                </div>

                <form :action="'{{ url('/materials') }}/' + (selectedMaterial ? selectedMaterial.id : '') + '/restock'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">
                            Jumlah Masuk (<span x-text="selectedMaterial ? selectedMaterial.unit : 'unit'"></span>) *
                        </label>
                        <input type="number" step="0.001" name="quantity" required placeholder="Contoh: 25.5" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Harga Beli Satuan Baru (Rp)</label>
                        <input type="number" step="0.01" name="unit_cost" :value="selectedMaterial ? selectedMaterial.standard_cost : ''" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Kosongkan jika harga sama dengan standar.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">No. Faktur / Referensi Pembelian</label>
                        <input type="text" name="reference_number" placeholder="Contoh: INV-PO-2026-09" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Catatan Penerimaan</label>
                        <input type="text" name="notes" placeholder="Nama toko kain / supplier..." class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-emerald-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="restockModalOpen = false" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300">Batal</button>
                        <button type="submit" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950">Simpan Stok Masuk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
