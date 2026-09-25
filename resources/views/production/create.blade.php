<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('cost-sheets.index') }}" class="p-2 rounded-xl bg-emerald-900/80 hover:bg-emerald-800 text-emerald-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                        {{ __('Formulir Produksi Pakaian & Potong Bahan') }}
                    </h1>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        {{ __('Hitung pemakaian bahan standar BOM, sesuaikan kuantitas riil kain secara manual, lalu eksekusi potong stok.') }}
                    </p>
                </div>
            </div>
        </div>
    </x-slot>

    @php
        $variantsData = $variants->map(function ($v) {
            return [
                'id' => $v->id,
                'cost_sheet_id' => $v->cost_sheet_id,
                'name' => $v->costSheet->name . ' (Size ' . $v->size . ')',
                'size' => $v->size,
                'suggested_product_id' => $v->costSheet->product_id,
                'standard_total_hpp' => (float) $v->total_cost_price,
                'items' => $v->items->map(function ($it) {
                    return [
                        'id' => $it->id,
                        'material_id' => $it->material_id,
                        'name' => $it->material?->name ?? 'Bahan',
                        'code' => $it->material?->code ?? '',
                        'category' => $it->material?->category ?? 'raw_material',
                        'category_label' => $it->material?->category_label ?? 'Bahan Baku',
                        'is_physical' => $it->material ? $it->material->isPhysical() : true,
                        'unit' => $it->material?->unit ?? 'pcs',
                        'available_stock' => (float) ($it->material?->stock ?? 0),
                        'standard_qty_per_pc' => (float) $it->quantity,
                        'unit_cost' => (float) $it->unit_price,
                    ];
                })->values(),
            ];
        })->values();
    @endphp

    <div class="py-8" x-data="{
        allVariants: {{ json_encode($variantsData) }},
        selectedVariantId: '{{ $activeVariant ? $activeVariant->id : '' }}',
        batchQuantity: 5,
        selectedProductId: '{{ $selectedProductId ?? ($activeVariant?->costSheet?->product_id ?? '') }}',
        updateProductCost: true,
        productionNotes: '',
        activeItems: [],

        init() {
            this.loadVariantItems();
        },

        loadVariantItems() {
            const v = this.allVariants.find(item => item.id == this.selectedVariantId);
            if (v) {
                if (v.suggested_product_id && !this.selectedProductId) {
                    this.selectedProductId = v.suggested_product_id;
                }
                this.activeItems = v.items.map(it => {
                    const stdTotal = parseFloat((it.standard_qty_per_pc * this.batchQuantity).toFixed(3));
                    return {
                        ...it,
                        standard_total: stdTotal,
                        actual_quantity: stdTotal, // Default sama dengan standar, tapi BISA DIUBAH MANUAL!
                        unit_cost: it.unit_cost,
                    };
                });
            } else {
                this.activeItems = [];
            }
        },

        onQuantityChange() {
            if (this.batchQuantity < 1) this.batchQuantity = 1;
            this.activeItems.forEach(it => {
                const stdTotal = parseFloat((it.standard_qty_per_pc * this.batchQuantity).toFixed(3));
                it.standard_total = stdTotal;
                // Update default aktual jika user belum mengubah secara ekstrem
                it.actual_quantity = stdTotal;
            });
        },

        getSubtotal(it) {
            return (parseFloat(it.actual_quantity || 0) * parseFloat(it.unit_cost || 0));
        },

        getTotalBatchCost() {
            return this.activeItems.reduce((acc, it) => acc + this.getSubtotal(it), 0);
        },

        getActualHppPerPc() {
            if (this.batchQuantity <= 0) return 0;
            return this.getTotalBatchCost() / this.batchQuantity;
        },

        getStandardTotalBatch() {
            const v = this.allVariants.find(item => item.id == this.selectedVariantId);
            return v ? (v.standard_total_hpp * this.batchQuantity) : 0;
        },

        getCostDiff() {
            return this.getTotalBatchCost() - this.getStandardTotalBatch();
        },

        formatRupiah(num) {
            return 'Rp ' + Number(Math.round(num || 0)).toLocaleString('id-ID');
        }
    }">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

            <form method="POST" action="{{ route('production.store') }}" class="space-y-6">
                @csrf

                <!-- Card 1: Pengaturan Batch Produksi & Target Produk -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">1</div>
                            <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                Parameter Produksi & Pilihan Varian Ukuran
                            </h2>
                        </div>
                        <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20">
                            Batch Work Order
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Pilih Model & Varian HPP (BOM) *</label>
                            <select name="cost_sheet_variant_id" x-model="selectedVariantId" @change="loadVariantItems()" required 
                                    class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold focus:ring-amber-500">
                                <option value="">-- Pilih Model & Ukuran --</option>
                                @foreach ($variants as $v)
                                    <option value="{{ $v->id }}">
                                        {{ $v->costSheet->name }} - Size {{ $v->size }} (HPP Standar: Rp {{ number_format($v->total_cost_price, 0) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Jumlah Potong (Pcs) *</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" name="batch_quantity" x-model.number="batchQuantity" @input="onQuantityChange()" required 
                                       class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold font-mono focus:ring-amber-500">
                                <span class="text-xs font-bold text-slate-500">Pcs</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Tambah ke Stok Produk Retail</label>
                            <select name="product_id" x-model="selectedProductId" 
                                    class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500">
                                <option value="">-- Tidak Ada (Hanya Potong Bahan) --</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->code }} - {{ $p->name }} (Size {{ $p->size }}) - Stok: {{ $p->stock }} pcs</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" id="update_cost" name="update_product_cost" value="1" x-model="updateProductCost" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <label for="update_cost" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Perbarui nilai HPP produk (cost_price) di master produk sesuai hasil kalkulasi riil batch produksi ini
                        </label>
                    </div>
                </div>

                <!-- Card 2: Form Manual Kebutuhan Bahan Baku (Bisa Diedit Manual!) -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div class="p-6 pb-3 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">2</div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                    Penyesuaian Manual Pemakaian Bahan Riil
                                </h2>
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold mt-0.5">
                                    💡 Jika kain yang terpakai lebih banyak/sedikit karena susut atau pola potong, ubah kolom "Pemakaian Riil".
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/50 dark:bg-slate-800/40 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 dark:border-slate-800">
                                    <th class="py-3 px-4">Nama Bahan / Komponen</th>
                                    <th class="py-3 px-4 text-center">Stok Gudang</th>
                                    <th class="py-3 px-4 text-right">Standar per Pc</th>
                                    <th class="py-3 px-4 text-right">Estimasi Standar Total</th>
                                    <th class="py-3 px-4 text-center bg-amber-500/5 dark:bg-amber-500/10 border-x border-amber-500/20">
                                        Pemakaian Riil (Editable) *
                                    </th>
                                    <th class="py-3 px-4 text-right">Harga Satuan (Rp)</th>
                                    <th class="py-3 px-4 text-right">Subtotal Riil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                                <template x-for="(it, idx) in activeItems" :key="it.id">
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3 px-4">
                                            <input type="hidden" :name="'items[' + idx + '][material_id]'" :value="it.material_id">
                                            <div class="font-bold text-slate-900 dark:text-white" x-text="it.name"></div>
                                            <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400" x-text="it.code + ' (' + it.category_label + ')'"></div>
                                        </td>

                                        <!-- Stok Gudang Saat Ini -->
                                        <td class="py-3 px-4 text-center font-mono">
                                            <template x-if="it.is_physical">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold"
                                                      :class="it.available_stock < it.actual_quantity ? 'bg-rose-500/15 text-rose-500' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'">
                                                    <span x-text="Number(it.available_stock).toLocaleString('id-ID') + ' ' + it.unit"></span>
                                                    <span x-show="it.available_stock < it.actual_quantity" title="Stok Kurang!" class="text-rose-500 font-bold">⚠️</span>
                                                </span>
                                            </template>
                                            <template x-if="!it.is_physical">
                                                <span class="text-slate-400 italic text-[10px]">- Biaya -</span>
                                            </template>
                                        </td>

                                        <!-- Standar per Pc -->
                                        <td class="py-3 px-4 text-right font-mono text-slate-500">
                                            <span x-text="Number(it.standard_qty_per_pc).toLocaleString('id-ID') + ' ' + it.unit"></span>
                                        </td>

                                        <!-- Estimasi Standar Total -->
                                        <td class="py-3 px-4 text-right font-mono text-slate-500">
                                            <span x-text="Number(it.standard_total).toLocaleString('id-ID') + ' ' + it.unit"></span>
                                        </td>

                                        <!-- INPUT PEMAKAIAN RIIL (EDITABLE MANUAL) -->
                                        <td class="py-2 px-4 bg-amber-500/5 dark:bg-amber-500/10 border-x border-amber-500/20 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <input type="number" step="0.001" min="0" 
                                                       :name="'items[' + idx + '][actual_quantity]'" 
                                                       x-model.number="it.actual_quantity"
                                                       required
                                                       class="w-24 px-2 py-1.5 text-xs text-center font-mono font-bold rounded-lg bg-white dark:bg-slate-900 border-amber-400 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500">
                                                <span class="text-[10px] text-slate-500 font-semibold" x-text="it.unit"></span>
                                            </div>
                                            <template x-if="it.actual_quantity != it.standard_total">
                                                <span class="text-[9px] font-bold block mt-0.5" 
                                                      :class="it.actual_quantity > it.standard_total ? 'text-amber-500' : 'text-emerald-500'">
                                                    <span x-text="(it.actual_quantity > it.standard_total ? '+' : '') + (it.actual_quantity - it.standard_total).toFixed(3) + ' vs standar'"></span>
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Harga Satuan Riil -->
                                        <td class="py-3 px-4 text-right font-mono">
                                            <input type="number" step="0.01" min="0" 
                                                   :name="'items[' + idx + '][unit_cost]'" 
                                                   x-model.number="it.unit_cost"
                                                   class="w-24 px-2 py-1 text-xs text-right font-mono rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                        </td>

                                        <!-- Subtotal Riil -->
                                        <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                            <span x-text="formatRupiah(getSubtotal(it))"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Card 3: Rekapitulasi Biaya & Tombol Eksekusi -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-7 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Catatan Pengerjaan / Batch Produksi</label>
                        <textarea name="notes" rows="3" x-model="productionNotes" placeholder="Catatan penjahit, nomor lot kain, sisa perca, atau kendala pemotongan..." 
                                  class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"></textarea>
                    </div>

                    <div class="lg:col-span-5 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Perbandingan HPP Riil Batch</h3>
                            <span class="text-[10px] text-slate-400" x-text="batchQuantity + ' Pcs Diproduksi'"></span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between text-slate-500">
                                <span>Estimasi HPP Standar BOM:</span>
                                <span class="font-mono" x-text="formatRupiah(getStandardTotalBatch())"></span>
                            </div>
                            <div class="flex justify-between text-slate-900 dark:text-white font-bold text-sm">
                                <span>Total Biaya Riil Batch Ini:</span>
                                <span class="font-mono text-amber-500" x-text="formatRupiah(getTotalBatchCost())"></span>
                            </div>
                            <div class="flex justify-between text-xs" :class="getCostDiff() > 0 ? 'text-rose-500' : 'text-emerald-500'">
                                <span>Selisih Biaya:</span>
                                <span class="font-mono font-bold" x-text="(getCostDiff() > 0 ? '+ ' : '') + formatRupiah(getCostDiff())"></span>
                            </div>

                            <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                                <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex justify-between items-center">
                                    <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300">HPP Riil per Pc:</span>
                                    <span class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400" x-text="formatRupiah(getActualHppPerPc())"></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 px-4 text-xs font-black rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-md transition-all flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Simpan & Eksekusi Potong Stok</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
