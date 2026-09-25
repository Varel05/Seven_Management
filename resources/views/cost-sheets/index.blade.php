<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shadow-xs">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                        {{ __('Kartu HPP & Resep Produksi (BOM)') }}
                    </h1>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        {{ __('Acuan perhitungan modal dasar pembuatan pakaian per ukuran (S, M, L) sebelum margin laba.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('materials.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-950/70 hover:bg-emerald-900 text-emerald-200 border border-emerald-700/60 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <span>Stok Bahan</span>
                </a>
                <a href="{{ route('production.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <span>Produksi Baju</span>
                </a>
                <button type="button" @click="$dispatch('open-modal', 'add-costsheet-modal')" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>Buat Kartu HPP</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

            <!-- Flash Alert -->
            @if (session('success'))
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            @endif

            <!-- Statistik KPI Kartu HPP -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Model / Template Kartu</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalSheets }} Model</h3>
                        <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 block">Formula BOM Baku</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Varian Ukuran Dihitung</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalVariants }} Varian</h3>
                        <span class="text-[11px] text-slate-500 font-medium mt-0.5 block">Kalkulasi spesifik per size</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tersambung ke Produk Retail</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $linkedProductsCount }} Produk</h3>
                        <span class="text-[11px] text-slate-500 font-medium mt-0.5 block">Modal ter-update otomatis</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                </div>
            </div>

            <!-- List Kartu HPP Card Grid -->
            <div class="space-y-4">
                @forelse ($costSheets as $cs)
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-500/40 transition-all">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        {{ $cs->code }}
                                    </span>
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white">
                                        {{ $cs->name }}
                                    </h2>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-500 mt-1">
                                    <span>Kategori: <strong class="text-slate-700 dark:text-slate-300">{{ ucfirst(str_replace('_', ' ', $cs->category)) }}</strong></span>
                                    @if ($cs->fabric_type)
                                        <span>• Bahan Kain: <strong class="text-slate-700 dark:text-slate-300">{{ $cs->fabric_type }}</strong></span>
                                    @endif
                                    @if ($cs->product)
                                        <span>• Terhubung ke: <strong class="text-emerald-600 dark:text-emerald-400">{{ $cs->product->name }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('cost-sheets.show', $cs) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Rincian Resep BOM</span>
                                </a>
                            </div>
                        </div>

                        <!-- Varian Ukuran Breakdown Pills -->
                        <div class="pt-4">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Varian Ukuran & Rekapitulasi HPP:</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($cs->variants as $var)
                                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 flex flex-col justify-between space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded-md text-xs font-black bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-mono">
                                                Size {{ $var->size }}
                                            </span>
                                            <a href="{{ route('production.create', ['variant_id' => $var->id]) }}" 
                                               class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                                                <span>Produksi Size Ini</span>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        </div>

                                        <div class="space-y-1 text-xs">
                                            <div class="flex justify-between text-slate-500">
                                                <span>Bahan Baku Fisik:</span>
                                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $var->formatted_material_cost }}</span>
                                            </div>
                                            <div class="flex justify-between text-slate-500">
                                                <span>Upah Jahit (Labor):</span>
                                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $var->formatted_labor_cost }}</span>
                                            </div>
                                            <div class="flex justify-between text-slate-500">
                                                <span>Overhead Pabrik (BOP):</span>
                                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $var->formatted_overhead_cost }}</span>
                                            </div>
                                            <div class="pt-1 border-t border-slate-200 dark:border-slate-700 flex justify-between font-bold">
                                                <span class="text-slate-900 dark:text-white">TOTAL HPP DASAR:</span>
                                                <span class="font-mono text-emerald-600 dark:text-emerald-400 text-sm">{{ $var->formatted_total_cost_price }}</span>
                                            </div>
                                        </div>

                                        @if ($var->suggested_selling_price > 0)
                                            <div class="pt-1.5 text-[11px] bg-emerald-500/5 -mx-1 -mb-1 p-1.5 rounded-lg flex items-center justify-between text-emerald-700 dark:text-emerald-300 font-semibold">
                                                <span>Saran Jual:</span>
                                                <span class="font-mono font-bold">{{ $var->formatted_suggested_selling_price }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white/80 dark:bg-slate-900/80 rounded-2xl p-12 text-center text-slate-400 border border-slate-200/80 dark:border-slate-800">
                        Belum ada Kartu HPP yang dibuat. Klik tombol "Buat Kartu HPP" untuk mulai membuat resep BOM pakaian.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Modal Tambah Kartu HPP Baru -->
        <x-modal name="add-costsheet-modal" maxWidth="lg">
            <form method="POST" action="{{ route('cost-sheets.store') }}" class="p-6 space-y-4 text-left">
                @csrf
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Buat Kartu HPP Baru</h3>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kode Kartu HPP *</label>
                        <input type="text" name="code" required placeholder="Contoh: HPP-JAS-SLIM-NAVY" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Nama Pakaian / Model *</label>
                        <input type="text" name="name" required placeholder="Contoh: Jas Slim Fit Navy Semiwool" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Kategori *</label>
                        <input type="text" name="category" value="jas_reguler" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Tipe Kain Utama</label>
                        <input type="text" name="fabric_type" placeholder="Contoh: Jetblack / Semiwool" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Tautkan ke Produk Retail (Opsional)</label>
                    <select name="product_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        <option value="">-- Tanpa Tautan (Template Mandiri) --</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}">{{ $p->code }} - {{ $p->name }} (Size: {{ $p->size }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Keterangan / Standar Mutu</label>
                    <textarea name="description" rows="2" placeholder="Catatan spesifikasi penjahitan..." class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-primary-button>Buat Kartu HPP</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
