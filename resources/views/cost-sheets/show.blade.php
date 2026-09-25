<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('cost-sheets.index') }}" class="p-2 rounded-xl bg-emerald-900/80 hover:bg-emerald-800 text-emerald-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300">
                            {{ $costSheet->code }}
                        </span>
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                            {{ $costSheet->name }}
                        </h1>
                    </div>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        {{ $costSheet->description ?: 'Kartu HPP resep Bill of Materials pakaian.' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($activeVariant)
                    <a href="{{ route('production.create', ['variant_id' => $activeVariant->id]) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-xs transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        <span>Produksi Size {{ $activeVariant->size }}</span>
                    </a>
                @endif
                <button type="button" @click="$dispatch('open-modal', 'add-item-modal')" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>+ Tambah Komponen Resep</span>
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

            @if (session('error'))
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span class="text-sm font-semibold">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Tabs Varian Ukuran (Size S, M, L, XL, etc.) -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-3 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 overflow-x-auto">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider px-2">Ukuran:</span>
                    @foreach ($costSheet->variants as $var)
                        <a href="{{ route('cost-sheets.show', ['costSheet' => $costSheet->id, 'variant_id' => $var->id]) }}" 
                           class="px-4 py-2 rounded-xl text-xs font-mono font-bold transition-all flex items-center gap-2 {{ $activeVariant && $activeVariant->id === $var->id ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            <span>Size {{ $var->size }}</span>
                            <span class="text-[10px] font-normal opacity-80">(Rp {{ number_format($var->total_cost_price, 0, ',', '.') }})</span>
                        </a>
                    @endforeach
                </div>
                <button type="button" @click="$dispatch('open-modal', 'add-variant-modal')" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Varian Ukuran Baru</span>
                </button>
            </div>

            @if ($activeVariant)
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Kolom Kiri: Tabel Resep Komponen BOM (8 cols) -->
                    <div class="lg:col-span-8 space-y-4">
                        <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                            <div class="p-5 pb-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                        Daftar Komponen Biaya (Resep BOM Size {{ $activeVariant->size }})
                                    </h2>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Kebutuhan per 1 (satu) potong pakaian</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    {{ $activeVariant->items->count() }} Komponen
                                </span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/50 dark:bg-slate-800/40 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 dark:border-slate-800">
                                            <th class="py-3 px-4">Nama Komponen</th>
                                            <th class="py-3 px-4">Kategori</th>
                                            <th class="py-3 px-4 text-right">Kebutuhan per Pc</th>
                                            <th class="py-3 px-4 text-right">Harga Satuan</th>
                                            <th class="py-3 px-4 text-right">Subtotal</th>
                                            <th class="py-3 px-4 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                                        @forelse ($activeVariant->items as $item)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                                <td class="py-3 px-4">
                                                    <div class="font-bold text-slate-900 dark:text-white">{{ $item->material?->name ?? 'Bahan Dihapus' }}</div>
                                                    <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">{{ $item->material?->code }}</div>
                                                    @if ($item->notes)
                                                        <div class="text-[10px] text-slate-400 italic">{{ $item->notes }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                        {{ $item->material?->category_label }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                                    {{ $item->formatted_quantity }}
                                                </td>
                                                <td class="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-400">
                                                    {{ $item->formatted_unit_price }}
                                                </td>
                                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                                    {{ $item->formatted_subtotal }}
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <form method="POST" action="{{ route('cost-sheets.items.destroy', $item) }}" onsubmit="return confirm('Hapus komponen ini dari resep?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="p-1 rounded text-rose-500 hover:bg-rose-500/10 transition-colors" title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-8 text-center text-slate-400">
                                                    Belum ada komponen pada varian ini. Klik tombol "+ Tambah Komponen Resep".
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Rekapitulasi HPP & Sinkronisasi Produk (4 cols) -->
                    <div class="lg:col-span-4 space-y-4">
                        <!-- Card Rekapitulasi HPP -->
                        <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Rekapitulasi HPP (Size {{ $activeVariant->size }})</h3>
                                <span class="text-[10px] text-slate-400">Modal Dasar / Pc</span>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                                    <span>1. Biaya Bahan Baku (Material):</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $activeVariant->formatted_material_cost }}</span>
                                </div>
                                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                                    <span>2. Upah Tenaga Kerja (Labor):</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $activeVariant->formatted_labor_cost }}</span>
                                </div>
                                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                                    <span>3. Biaya Overhead Pabrik (BOP):</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $activeVariant->formatted_overhead_cost }}</span>
                                </div>

                                <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex flex-col justify-between">
                                        <span class="text-[11px] font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">TOTAL HPP (BELUM TERMASUK LABA):</span>
                                        <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                                            {{ $activeVariant->formatted_total_cost_price }}
                                        </div>
                                    </div>
                                </div>

                                @if ($activeVariant->suggested_selling_price > 0)
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-1">
                                        <div class="flex justify-between text-slate-500 text-[11px]">
                                            <span>Rekomendasi Harga Jual:</span>
                                            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $activeVariant->formatted_suggested_selling_price }}</span>
                                        </div>
                                        @php
                                            $profit = $activeVariant->suggested_selling_price - $activeVariant->total_cost_price;
                                            $margin = $activeVariant->suggested_selling_price > 0 ? round(($profit / $activeVariant->suggested_selling_price) * 100, 1) : 0;
                                        @endphp
                                        <div class="flex justify-between text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            <span>Proyeksi Laba Bersih:</span>
                                            <span>Rp {{ number_format($profit, 0, ',', '.') }} ({{ $margin }}%)</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Card Terapkan ke Produk Retail -->
                        <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                            <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Sinkronisasi HPP ke Produk</h3>
                            </div>

                            <form method="POST" action="{{ route('cost-sheets.sync-product', $activeVariant) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Pilih Produk Pakaian Jadi</label>
                                    <select name="product_id" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                        <option value="">-- Pilih Produk Retail --</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}" {{ $costSheet->product_id === $p->id ? 'selected' : '' }}>
                                                {{ $p->code }} - {{ $p->name }} (Size: {{ $p->size }}) - HPP Saat Ini: Rp {{ number_format($p->cost_price, 0) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="w-full py-2 px-3 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition-colors shadow-xs">
                                    Terapkan HPP {{ $activeVariant->formatted_total_cost_price }} ke Produk
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Modal Tambah Komponen Resep -->
        @if ($activeVariant)
            <x-modal name="add-item-modal" maxWidth="md">
                <form method="POST" action="{{ route('cost-sheets.items.store', $activeVariant) }}" class="p-6 space-y-4 text-left"
                      x-data="{
                          selectedMaterialId: '',
                          materials: {{ json_encode($materials) }},
                          unitPrice: 0,
                          unit: '',
                          quantity: 1,
                          onMaterialChange() {
                              const found = this.materials.find(m => m.id == this.selectedMaterialId);
                              if (found) {
                                  this.unitPrice = found.standard_cost;
                                  this.unit = found.unit;
                              }
                          }
                      }">
                    @csrf
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            Tambah Bahan ke Resep Size {{ $activeVariant->size }}
                        </h3>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Pilih Komponen Bahan / Jasa *</label>
                        <select name="material_id" x-model="selectedMaterialId" @change="onMaterialChange()" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                            <option value="">-- Pilih dari Master Bahan --</option>
                            @foreach ($materials as $mat)
                                <option value="{{ $mat->id }}">
                                    [{{ $mat->code }}] {{ $mat->name }} ({{ $mat->unit }}) - Rp {{ number_format($mat->standard_cost, 0) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">
                                Kebutuhan (<span x-text="unit || 'satuan'"></span>) *
                            </label>
                            <input type="number" step="0.001" name="quantity" x-model="quantity" required placeholder="Contoh: 1.6" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Harga Satuan (Rp) *</label>
                            <input type="number" step="0.01" name="unit_price" x-model="unitPrice" required class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold">
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center text-xs">
                        <span class="text-slate-500 font-medium">Estimasi Subtotal:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white" x-text="'Rp ' + Number(quantity * unitPrice).toLocaleString('id-ID')"></span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Catatan Pemakaian (Opsional)</label>
                        <input type="text" name="notes" placeholder="Misal: untuk badan luar & kerah..." class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                        <x-primary-button>Tambahkan ke Resep</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endif

        <!-- Modal Tambah Varian Ukuran Baru -->
        <x-modal name="add-variant-modal" maxWidth="md">
            <form method="POST" action="{{ route('cost-sheets.variants.store', $costSheet) }}" class="p-6 space-y-4 text-left">
                @csrf
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Tambah Varian Ukuran Baru</h3>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Nama Ukuran (Size) *</label>
                    <input type="text" name="size" required placeholder="Contoh: L / XL / Custom" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Salin Resep dari Varian Lain (Opsional)</label>
                    <select name="copy_from_variant_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                        <option value="">-- Mulai dengan Resep Kosong --</option>
                        @foreach ($costSheet->variants as $var)
                            <option value="{{ $var->id }}">Salin dari Size {{ $var->size }} ({{ $var->items->count() }} komponen)</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] text-slate-400 mt-1 block">Anda bisa mengedit kuantitas bahan setelah disalin.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Catatan Ukuran</label>
                    <input type="text" name="notes" placeholder="Misal: untuk tinggi badan > 180cm" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-primary-button>Simpan Varian Ukuran</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
