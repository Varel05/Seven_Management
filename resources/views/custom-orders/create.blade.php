<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('custom-orders.index') }}" class="p-2 rounded-xl bg-emerald-900/80 hover:bg-emerald-800 text-emerald-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                        {{ __('Buat Pesanan Jas Custom Baru') }}
                    </h1>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        {{ __('Input ukuran custom, tipe kain, warna, foto & catatan > Kirim ke AI n8n > Tentukan harga kesepakatan > Simpan pesanan') }}
                    </p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        suitType: 'jas_blazer_pria',
        materials: {{ json_encode($materials) }},
        materialId: '',
        selectedMaterialObj: null,
        materialMeters: 0,
        fabricType: '',
        color: '',
        height: 172,
        chest: 98,
        waist: 84,
        jacketLength: 74,
        sleeveLength: 62,
        trouserLength: 100,
        notes: '',
        fabricPrice: 220000,
        laborCost: 600000,
        targetMargin: 45,
        totalPrice: '',
        downPayment: '',
        estimateLoading: false,
        estimateResult: null,
        hasAnalyzed: false,
        imagePreview: null,
        onMaterialSelected() {
            const found = this.materials.find(m => m.id == this.materialId);
            if (found) {
                this.selectedMaterialObj = found;
                this.fabricType = found.name;
                this.fabricPrice = found.standard_cost;
            } else {
                this.selectedMaterialObj = null;
            }
        },
        onImageSelected(event) {
            const file = event.target.files[0];
            if (file) {
                this.imagePreview = URL.createObjectURL(file);
            } else {
                this.imagePreview = null;
            }
        },
        async sendToAiAnalysis() {
            this.estimateLoading = true;
            try {
                let formData = new FormData();
                formData.append('suit_type', this.suitType);
                formData.append('material_id', this.materialId || '');
                formData.append('fabric_type', this.fabricType || '');
                formData.append('color', this.color || '');
                formData.append('height', this.height || '');
                formData.append('chest', this.chest || '');
                formData.append('waist', this.waist || '');
                formData.append('jacket_length', this.jacketLength || '');
                formData.append('sleeve_length', this.sleeveLength || '');
                formData.append('trouser_length', this.trouserLength || '');
                formData.append('notes', this.notes || '');
                formData.append('fabric_price', this.fabricPrice || '');
                formData.append('labor_cost', this.laborCost || '');
                formData.append('target_margin', this.targetMargin || '');

                if (this.$refs.imageInput && this.$refs.imageInput.files[0]) {
                    formData.append('reference_image', this.$refs.imageInput.files[0]);
                }

                let res = await fetch('{{ route('custom-orders.estimate') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                let json = await res.json();
                if (json.success) {
                    this.estimateResult = json.data;
                    this.hasAnalyzed = true;
                    if (json.data?.materials?.main_fabric_meters) {
                        this.materialMeters = json.data.materials.main_fabric_meters;
                    }

                    // Jika user belum mengisi harga kesepakatan, beri saran awal dari kalkulasi AI
                    if ((!this.totalPrice || this.totalPrice == 0) && json.data.financial?.suggested_price) {
                        this.totalPrice = json.data.financial.suggested_price;
                        this.downPayment = Math.round(this.totalPrice * 0.5); // DP 50%
                    }
                } else {
                    alert('Gagal memproses estimasi AI: ' + (json.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('Error saat menghubungi AI n8n:', e);
                alert('Gagal menghubungi AI n8n: ' + e.message);
            } finally {
                this.estimateLoading = false;
            }
        },
        applyAiRecommendations() {
            if (this.estimateResult && this.estimateResult.financial) {
                this.totalPrice = this.estimateResult.financial.suggested_price;
                this.downPayment = Math.round(this.totalPrice * 0.5);
            }
        },
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }
    }">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12">
            
            <form method="POST" action="{{ route('custom-orders.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                @csrf
                <input type="hidden" name="material_id" :value="materialId">
                <input type="hidden" name="material_meters" :value="materialMeters">

                <!-- Left Column: Form Inputs (7 cols) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Card 1: Data Pelanggan & Jadwal -->
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">1</div>
                            <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Informasi Klien & Tanggal Pesanan</h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Klien / Pelanggan *</label>
                                <input type="text" name="customer_name" required placeholder="Contoh: Bpk. Hendra Gunawan" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">No. Telepon / WhatsApp</label>
                                <input type="text" name="customer_phone" placeholder="0812xxxxxxxx" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tanggal Pesanan Masuk *</label>
                                <input type="date" name="order_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Selesai (Due Date)</label>
                                <input type="date" name="due_date" value="{{ date('Y-m-d', strtotime('+14 days')) }}" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Model, Ukuran Custom, Tipe Kain, Warna, Gambar & Catatan -->
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">2</div>
                                <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Spesifikasi Custom Jas & Bahan</h2>
                            </div>
                            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                Input Analisis AI
                            </span>
                        </div>

                        <!-- Pilih Bahan dari Stok Gudang (Integrasi Master Materials) -->
                        <div class="p-3.5 rounded-xl bg-emerald-500/5 dark:bg-emerald-950/20 border border-emerald-500/20 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-emerald-900 dark:text-emerald-300">
                                    Pilih Kain dari Stok Gudang (Terintegrasi)
                                </label>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400">Master Bahan Baku</span>
                            </div>
                            <select x-model="materialId" @change="onMaterialSelected()" class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-800 border-emerald-300 dark:border-emerald-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-medium">
                                <option value="">-- Kain Kustom / Tidak dari Stok Gudang --</option>
                                <template x-for="mat in materials" :key="mat.id">
                                    <option :value="mat.id" x-text="mat.name + ' (Sisa Stok: ' + mat.stock + ' ' + mat.unit + ') - Rp ' + Number(mat.standard_cost).toLocaleString('id-ID') + '/' + mat.unit"></option>
                                </template>
                            </select>

                            <template x-if="selectedMaterialObj">
                                <div class="flex items-center gap-2 pt-1">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold"
                                          :class="selectedMaterialObj.stock >= 2.5 ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400'">
                                        <span x-text="'Stok Gudang: ' + selectedMaterialObj.stock + ' ' + selectedMaterialObj.unit"></span>
                                        <span x-show="selectedMaterialObj.stock < 2.5"> (Menipis!)</span>
                                    </span>
                                    <span class="text-[11px] text-slate-500">Harga Standar: <strong class="text-slate-700 dark:text-slate-300" x-text="'Rp ' + Number(selectedMaterialObj.standard_cost).toLocaleString('id-ID') + '/m'"></strong></span>
                                </div>
                            </template>
                        </div>

                        <!-- Kategori, Tipe Kain, Warna -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kategori Pakaian *</label>
                                <select name="suit_type" x-model="suitType" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold focus:ring-2 focus:ring-emerald-500">
                                    @foreach ($categories as $catKey => $catLabel)
                                        <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tipe Bahan Kain</label>
                                <input type="text" name="fabric_type" x-model="fabricType" placeholder="Semi-Wool / Pure Wool / Dobby" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Warna Bahan</label>
                                <input type="text" name="color" x-model="color" placeholder="Midnight Blue / Hitam / Charcoal" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <!-- Ukuran Tubuh Custom Grid -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Parameter Ukuran Tubuh Custom (cm)</span>
                                <span class="text-[10px] text-slate-400">Dasar perhitungan pola & meteran bahan</span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Tinggi Badan (cm)</label>
                                    <input type="number" name="height" x-model.number="height" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Lingkar Dada (cm)</label>
                                    <input type="number" name="chest" x-model.number="chest" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Lingkar Pinggang (cm)</label>
                                    <input type="number" name="waist" x-model.number="waist" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Panjang Jas (cm)</label>
                                    <input type="number" name="jacket_length" x-model.number="jacketLength" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Panjang Lengan (cm)</label>
                                    <input type="number" name="sleeve_length" x-model.number="sleeveLength" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-500 mb-0.5">Panjang Celana (cm)</label>
                                    <input type="number" name="trouser_length" x-model.number="trouserLength" class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono focus:ring-1 focus:ring-emerald-500">
                                </div>
                            </div>
                        </div>

                        <!-- Foto Referensi Desain Gambar -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Unggah Foto Referensi Jas / Desain Gambar</label>
                            <input 
                                type="file" 
                                name="reference_image" 
                                accept="image/*" 
                                x-ref="imageInput"
                                @change="onImageSelected($event)"
                                class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-500/10 file:text-emerald-700 dark:file:bg-emerald-950/60 dark:file:text-emerald-300 hover:file:bg-emerald-500/20 cursor-pointer"
                            >
                            
                            <!-- Preview Gambar Jika Ada -->
                            <template x-if="imagePreview">
                                <div class="mt-3 relative w-36 h-44 rounded-xl overflow-hidden border-2 border-emerald-500/40 shadow-md">
                                    <img :src="imagePreview" alt="Preview Jas" class="w-full h-full object-cover">
                                    <div class="absolute bottom-0 inset-x-0 bg-slate-900/80 text-[10px] text-emerald-300 text-center py-0.5 font-bold">
                                        Foto Terpilih
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Catatan Khusus Klien -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Catatan Khusus Klien</label>
                            <textarea name="notes" x-model="notes" rows="2" placeholder="Permintaan khusus kerah, saku serong, kancing monil, belahan belakang double-vent..." class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"></textarea>
                        </div>

                        <!-- Tombol Kirim ke AI n8n untuk Analisis -->
                        <div class="pt-3 border-t border-slate-200/80 dark:border-slate-800">
                            <button 
                                type="button" 
                                @click="sendToAiAnalysis()"
                                :disabled="estimateLoading"
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/20 hover:shadow-emerald-900/40 active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2.5 disabled:opacity-50"
                            >
                                <svg class="w-4 h-4 animate-spin" x-show="estimateLoading" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <svg class="w-4 h-4" x-show="!estimateLoading" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span x-text="estimateLoading ? 'Menganalisis Gambar & Ukuran dengan AI n8n...' : 'Kirim ke AI (n8n) untuk Analisis Desain & Estimasi Bahan'"></span>
                            </button>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 text-center mt-1.5">
                                Klik tombol di atas untuk mengirim ukuran custom, tipe kain, warna, foto & catatan ke AI n8n.
                            </p>
                        </div>
                    </div>

                    <!-- Card 3: Form Harga Kesepakatan & DP -->
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">3</div>
                                <h2 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Kesepakatan Harga & Pembayaran DP</h2>
                            </div>
                            <template x-if="hasAnalyzed">
                                <button 
                                    type="button" 
                                    @click="applyAiRecommendations()"
                                    class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span>Pakai Rekomendasi AI</span>
                                </button>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Total Harga Disepakati (Rp) *</label>
                                <input type="number" name="total_price" x-model.number="totalPrice" required min="0" placeholder="Contoh: 1850000" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold text-sm focus:ring-2 focus:ring-emerald-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Harga final hasil negosiasi dengan klien</span>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Uang Muka (DP) Diterima Sekarang (Rp)</label>
                                <input type="number" name="down_payment" x-model.number="downPayment" min="0" placeholder="0 jika belum bayar DP" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold text-sm focus:ring-2 focus:ring-emerald-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Otomatis dibukukan ke jurnal penerimaan kas/bank</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rekening / Kas Penerima Uang Muka (DP)</label>
                            <select name="account_id" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500">
                                @foreach ($paymentAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex justify-end pt-3">
                            <button type="submit" class="px-6 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/40 hover:scale-105 active:scale-95 transition-all cursor-pointer flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Simpan Pesanan ke Daftar Pesanan Bespoke / Custom</span>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Right Column: AI Material & Cost Estimator Card (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-900 text-white rounded-3xl p-6 border border-emerald-800/60 shadow-xl space-y-5 sticky top-24">
                        
                        <div class="flex items-center justify-between pb-3 border-b border-emerald-800/60">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                                    <svg class="w-5 h-5 animate-spin" x-show="estimateLoading" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <svg class="w-5 h-5" x-show="!estimateLoading" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-white">AI Material & Cost Estimator</h3>
                                    <span class="text-[11px] text-emerald-400 font-medium">Algoritma Master Tailor + n8n AI Agent</span>
                                </div>
                            </div>
                            <span 
                                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition-all"
                                :class="hasAnalyzed ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-slate-700'"
                                x-text="hasAnalyzed ? 'Dianalisis AI n8n' : 'Menunggu Analisis AI'"
                            >
                            </span>
                        </div>

                        <!-- State 1: Belum Melakukan Analisis AI (Menunggu Tombol Kirim) -->
                        <template x-if="!hasAnalyzed && !estimateLoading">
                            <div class="py-8 px-4 text-center space-y-4">
                                <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1.5">Siap untuk Analisis Desain & Bahan</h4>
                                    <p class="text-xs text-slate-300 leading-relaxed max-w-sm mx-auto">
                                        Isi ukuran custom, tipe kain, warna bahan, foto dan catatan di samping, lalu klik tombol <strong class="text-emerald-300">"Kirim ke AI (n8n) untuk Analisis"</strong>.
                                    </p>
                                </div>
                                <div class="pt-3 flex justify-center items-center gap-3 text-[11px] text-emerald-400/90 font-medium">
                                    <span>✓ Kebutuhan Kain</span>
                                    <span>•</span>
                                    <span>✓ Kalkulasi HPP</span>
                                    <span>•</span>
                                    <span>✓ Saran Harga Jual</span>
                                </div>
                            </div>
                        </template>

                        <!-- State 2: Sedang Memproses Analisis AI -->
                        <template x-if="estimateLoading">
                            <div class="py-12 px-4 text-center space-y-3">
                                <div class="w-10 h-10 mx-auto rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center animate-spin">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </div>
                                <h4 class="text-sm font-bold text-white">Menghubungi AI Agent n8n...</h4>
                                <p class="text-xs text-slate-400">Sedang menganalisis proporsi ukuran, estimasi bahan & kalkulasi finansial</p>
                            </div>
                        </template>

                        <!-- State 3: Hasil Analisis AI Selesai -->
                        <template x-if="hasAnalyzed && !estimateLoading">
                            <div class="space-y-4">
                                <!-- Summary Naratif -->
                                <div class="p-3.5 rounded-2xl bg-emerald-900/40 border border-emerald-700/50 text-xs text-emerald-200 leading-relaxed" x-text="estimateResult?.summary">
                                </div>

                                <!-- Rincian Kebutuhan Bahan -->
                                <div class="space-y-2.5">
                                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-300">Estimasi Kebutuhan Kain:</div>
                                    
                                    <div class="p-3 rounded-xl bg-slate-900/60 border border-emerald-900/50 space-y-2 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 font-medium">Kain Utama (Jas/Baju):</span>
                                            <span class="font-mono font-bold text-white text-sm" x-text="(estimateResult?.materials?.main_fabric_meters || 0) + ' meter'"></span>
                                        </div>
                                        <div class="text-[11px] text-emerald-400/80 italic" x-text="estimateResult?.materials?.main_fabric_description"></div>

                                        <template x-if="estimateResult?.materials?.lining_meters > 0">
                                            <div class="flex items-center justify-between pt-1 border-t border-slate-800 text-slate-300">
                                                <span>Kain Furing / Lining:</span>
                                                <span class="font-mono font-bold text-white" x-text="estimateResult.materials.lining_meters + ' meter'"></span>
                                            </div>
                                        </template>

                                        <template x-if="estimateResult?.materials?.interlining_kufner_meters > 0">
                                            <div class="flex items-center justify-between pt-1 border-t border-slate-800 text-slate-300">
                                                <span>Kain Keras / Kufner:</span>
                                                <span class="font-mono font-bold text-white" x-text="estimateResult.materials.interlining_kufner_meters + ' meter'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Rincian Biaya Produksi (HPP) -->
                                <div class="space-y-2.5">
                                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-300">Struktur Pengeluaran (HPP):</div>
                                    
                                    <div class="p-3 rounded-xl bg-slate-900/60 border border-emerald-900/50 space-y-2 text-xs">
                                        <div class="flex justify-between text-slate-300">
                                            <span>Total Biaya Bahan Baku:</span>
                                            <span class="font-mono font-semibold" x-text="formatRupiah(estimateResult?.materials?.total_material_cost)"></span>
                                        </div>
                                        <div class="flex justify-between text-slate-300">
                                            <span>Ongkos Pengerjaan Penjahit:</span>
                                            <span class="font-mono font-semibold" x-text="formatRupiah(estimateResult?.labor?.labor_cost)"></span>
                                        </div>
                                        <div class="flex justify-between font-bold pt-2 border-t border-slate-800 text-white">
                                            <span>TOTAL HPP (MODAL):</span>
                                            <span class="font-mono text-emerald-400 text-sm" x-text="formatRupiah(estimateResult?.financial?.total_cost)"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rekomendasi Harga & Proyeksi Laba -->
                                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-emerald-300 font-semibold">Rekomendasi Harga Jual:</span>
                                        <span class="text-lg font-black font-mono text-white" x-text="formatRupiah(estimateResult?.financial?.suggested_price)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-300">Proyeksi Laba Kotor:</span>
                                        <span class="font-mono font-bold text-purple-400" x-text="'+ ' + formatRupiah(estimateResult?.financial?.projected_profit) + ' (' + (estimateResult?.financial?.profit_margin_percent || 0) + '%)'"></span>
                                    </div>
                                </div>

                                <!-- Tombol Terapkan Rekomendasi ke Form -->
                                <button 
                                    type="button" 
                                    @click="applyAiRecommendations()"
                                    class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition-all cursor-pointer flex items-center justify-center gap-2"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Terapkan Rekomendasi Harga & DP ke Form</span>
                                </button>
                            </div>
                        </template>

                    </div>
                </div>

            </form>

        </div>
    </div>
</x-app-layout>
