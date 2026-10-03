<x-app-layout>
    <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="{
             tailorName: 'Penjahit Adriana',
             selectedEmployeeId: '',
             payrollDate: '{{ $defaultDate }}',
             periodLabel: '{{ $defaultPeriodLabel }}',
             paymentMethod: 'Tunai',
             accountId: '{{ $paymentAccounts->first()?->id }}',
             notes: '',

             // Rincian Item Jahitan (Default dari HPP)
             items: [
                 {
                     material_id: '{{ $laborMaterials->firstWhere('code', 'LAB-JHT-REG')?->id }}',
                     item_name: 'Jas Reguler',
                     quantity: 0,
                     rate_per_piece: {{ $laborMaterials->firstWhere('code', 'LAB-JHT-REG')?->standard_cost ?? 50000 }}
                 },
                 {
                     material_id: '{{ $laborMaterials->firstWhere('code', 'LAB-JHT-VST')?->id }}',
                     item_name: 'Vest',
                     quantity: 0,
                     rate_per_piece: {{ $laborMaterials->firstWhere('code', 'LAB-JHT-VST')?->standard_cost ?? 37000 }}
                 },
                 {
                     material_id: '{{ $laborMaterials->firstWhere('code', 'LAB-JHT-PRM')?->id }}',
                     item_name: 'Jas Premium',
                     quantity: 0,
                     rate_per_piece: {{ $laborMaterials->firstWhere('code', 'LAB-JHT-PRM')?->standard_cost ?? 150000 }}
                 },
                 {
                     material_id: '{{ $laborMaterials->firstWhere('code', 'LAB-JHT-REV')?->id }}',
                     item_name: 'Revisi',
                     quantity: 0,
                     rate_per_piece: {{ $laborMaterials->firstWhere('code', 'LAB-JHT-REV')?->standard_cost ?? 0 }}
                 }
             ],

             // Rincian Bon / Kasbon
             advances: [
                 { advance_date: '{{ $defaultDate }}', description: '', amount: 0 }
             ],

             // Tambah Baris Jahitan Kustom
             addItemFromHpp(event) {
                 const select = event.target;
                 const opt = select.selectedOptions[0];
                 if (!opt.value) return;

                 this.items.push({
                     material_id: opt.value,
                     item_name: opt.dataset.name,
                     quantity: 1,
                     rate_per_piece: parseFloat(opt.dataset.cost || 0)
                 });
                 select.value = '';
             },

             addCustomItem() {
                 this.items.push({
                     material_id: null,
                     item_name: '',
                     quantity: 1,
                     rate_per_piece: 0
                 });
             },

             removeItem(index) {
                 if (this.items.length > 1) {
                     this.items.splice(index, 1);
                 }
             },

             // Rincian Kasbon
             addAdvance() {
                 this.advances.push({
                     advance_date: this.payrollDate,
                     description: '',
                     amount: 0
                 });
             },

             removeAdvance(index) {
                 this.advances.splice(index, 1);
             },

             // Kalkulasi Realtime
             get totalPieces() {
                 return this.items.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
             },

             get totalWage() {
                 return this.items.reduce((sum, item) => {
                     const qty = parseInt(item.quantity) || 0;
                     const rate = parseFloat(item.rate_per_piece) || 0;
                     return sum + (qty * rate);
                 }, 0);
             },

             get totalBon() {
                 return this.advances.reduce((sum, adv) => sum + (parseFloat(adv.amount) || 0), 0);
             },

             get takeHomePay() {
                 return Math.max(0, this.totalWage - this.totalBon);
             },

             formatRupiah(val) {
                 return new Intl.NumberFormat('id-ID', {
                     style: 'currency',
                     currency: 'IDR',
                     minimumFractionDigits: 0
                 }).format(val || 0);
             },

             formatNumber(val) {
                 return new Intl.NumberFormat('id-ID').format(val || 0);
             },

             onEmployeeSelect(event) {
                 const opt = event.target.selectedOptions[0];
                 if (opt && opt.value) {
                     this.tailorName = opt.dataset.name;
                     this.selectedEmployeeId = opt.value;
                 }
             }
         }">

        <!-- Top Header & Breadcrumb -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('tailor-payrolls.index') }}" class="hover:text-amber-600">Upah Penjahit</a>
                    <span>/</span>
                    <span class="text-amber-600 font-semibold">Buat Slip Upah Baru</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    Kalkulator & Form Slip Upah Penjahit
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Perhitungan otomatis berbasis output per pcs dengan harga jasa HPP & potongan bon
                </p>
            </div>
            <a href="{{ route('tailor-payrolls.index') }}" 
               class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                ← Kembali
            </a>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs shadow-2xs">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tailor-payrolls.store') }}" class="space-y-6">
            @csrf

            <!-- Form Kontrol Atas (Pilih Karyawan / Nama Penjahit & Tanggal) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-5 shadow-xs">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                    Informasi Penjahit & Tanggal
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- 1. Pilih Karyawan Terdaftar (Opsional) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Pilih Karyawan (Jika Ada)
                        </label>
                        <select 
                            x-model="selectedEmployeeId"
                            @change="onEmployeeSelect($event)"
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"
                        >
                            <option value="">-- Input Bebas (Borongan Luar) --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}" data-name="{{ $emp->name }}">
                                    {{ $emp->name }} ({{ $emp->position }})
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="employee_id" :value="selectedEmployeeId || ''">
                    </div>

                    <!-- 2. Nama Penjahit (Muncul di Header Slip) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Nama Penjahit <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="tailor_name" 
                            x-model="tailorName"
                            required
                            placeholder="Contoh: Penjahit Adriana" 
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold focus:ring-amber-500"
                        />
                    </div>

                    <!-- 3. Tanggal Slip -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Tanggal Slip <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="date" 
                            name="payroll_date" 
                            x-model="payrollDate"
                            required
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500 font-mono"
                        />
                    </div>

                    <!-- 4. Label Periode / Hari -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Header Hari & Tanggal
                        </label>
                        <input 
                            type="text" 
                            name="period_label" 
                            x-model="periodLabel"
                            placeholder="Kamis, 1 Oktober 2026" 
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"
                        />
                    </div>
                </div>
            </div>

            <!-- TAMPILAN SHEET EXCEL (DUA TABEL: UPAH JAHIT & RINCIAN BON) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- TABEL UTAMA: UPAH BORONGAN PER PCS (7 atau 8 Kolom) -->
                <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                    <div>
                        <!-- Header Tanggal Atas Kiri -->
                        <div class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-300" x-text="periodLabel || 'Tanggal Belum Diisi'"></span>
                            <span class="text-[11px] font-mono text-slate-400">Lembar Slip Upah Jahit</span>
                        </div>

                        <!-- Header Kuning Emas Penjahit -->
                        <div class="bg-amber-400 dark:bg-amber-500 text-slate-950 font-black text-xl text-center py-3.5 tracking-wide border-b border-amber-500 shadow-inner">
                            <span x-text="tailorName || 'Nama Penjahit'"></span>
                        </div>

                        <!-- Tabel Item Jahitan -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b-2 border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/60 font-bold text-slate-700 dark:text-slate-300">
                                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 w-5/12">Keterangan</th>
                                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-center w-2/12">Jumlah (pcs)</th>
                                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-right w-3/12">Biaya per pcs</th>
                                        <th class="py-2.5 px-3 text-right w-2/12">Total</th>
                                        <th class="py-2.5 px-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                            <!-- Keterangan Item -->
                                            <td class="p-2 border-r border-slate-200 dark:border-slate-700">
                                                <input 
                                                    type="text" 
                                                    :name="'items[' + index + '][item_name]'"
                                                    x-model="item.item_name" 
                                                    required
                                                    class="w-full text-xs font-semibold py-1 px-2 rounded-lg bg-transparent border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-800"
                                                />
                                                <input type="hidden" :name="'items[' + index + '][material_id]'" :value="item.material_id || ''">
                                            </td>

                                            <!-- Jumlah Pcs -->
                                            <td class="p-2 border-r border-slate-200 dark:border-slate-700">
                                                <input 
                                                    type="number" 
                                                    min="0"
                                                    :name="'items[' + index + '][quantity]'"
                                                    x-model.number="item.quantity" 
                                                    class="w-full text-xs font-bold text-center py-1 px-2 rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 focus:ring-amber-500"
                                                />
                                            </td>

                                            <!-- Biaya per pcs -->
                                            <td class="p-2 border-r border-slate-200 dark:border-slate-700">
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none text-[10px] text-slate-400">Rp</span>
                                                    <input 
                                                        type="number" 
                                                        min="0"
                                                        step="100"
                                                        :name="'items[' + index + '][rate_per_piece]'"
                                                        x-model.number="item.rate_per_piece" 
                                                        class="w-full text-xs font-mono font-bold text-right py-1 pl-6 pr-2 rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 focus:ring-amber-500"
                                                    />
                                                </div>
                                            </td>

                                            <!-- Total Subtotal -->
                                            <td class="p-2 text-right font-mono font-bold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                                <span x-text="formatNumber((item.quantity || 0) * (item.rate_per_piece || 0))"></span>
                                            </td>

                                            <!-- Hapus Baris -->
                                            <td class="p-2 text-center">
                                                <button 
                                                    type="button" 
                                                    @click="removeItem(index)" 
                                                    x-show="items.length > 1"
                                                    class="text-rose-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950/60"
                                                    title="Hapus Baris"
                                                >
                                                    ✕
                                                </button>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- TOTAL GAJI ROW -->
                                    <tr class="border-t-2 border-slate-300 dark:border-slate-700 font-bold bg-slate-50/90 dark:bg-slate-800/80">
                                        <td class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-right uppercase tracking-wider text-xs">
                                            Total Gaji
                                        </td>
                                        <td class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-center font-extrabold text-slate-900 dark:text-white">
                                            <span x-text="totalPieces"></span>
                                        </td>
                                        <td class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-extrabold text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                            <span x-text="formatNumber(totalWage)"></span>
                                        </td>
                                        <td></td>
                                    </tr>

                                    <!-- JUMLAH BON ROW -->
                                    <tr class="border-t border-slate-200 dark:border-slate-700 font-bold">
                                        <td colspan="3" class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-right text-rose-600 dark:text-rose-400 text-xs">
                                            Jumlah bon
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                            <span x-text="formatNumber(totalBon)"></span>
                                        </td>
                                        <td></td>
                                    </tr>

                                    <!-- TAKE HOMEPAY ROW (WARNA KUNING EMAS) -->
                                    <tr class="border-t-2 border-amber-400 bg-amber-400 dark:bg-amber-500 text-slate-950 font-black text-sm">
                                        <td colspan="3" class="py-3 px-4 border-r border-amber-500 text-center uppercase tracking-wide">
                                            Take homepay
                                        </td>
                                        <td class="py-3 px-3 text-right font-mono text-base whitespace-nowrap">
                                            <span x-text="formatNumber(takeHomePay)"></span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Dropdown Tambah Item dari HPP -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">+ Tambah dari HPP:</span>
                                <select 
                                    @change="addItemFromHpp($event)"
                                    class="text-xs rounded-xl bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"
                                >
                                    <option value="">-- Pilih Item Tenaga Kerja HPP --</option>
                                    @foreach ($laborMaterials as $lm)
                                        <option value="{{ $lm->id }}" data-name="{{ str_replace(['Upah Penjahit ', 'Biaya '], '', $lm->name) }}" data-cost="{{ $lm->standard_cost }}">
                                            {{ $lm->name }} (Rp {{ number_format($lm->standard_cost, 0, ',', '.') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button 
                                type="button" 
                                @click="addCustomItem()"
                                class="text-xs font-bold text-amber-700 dark:text-amber-400 hover:underline"
                            >
                                + Baris Jahitan Bebas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABEL KANAN / BAWAH: RINCIAN BON / KASBON (5 Kolom) -->
                <div class="lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                    <div>
                        <!-- Header Rincian Bon -->
                        <div class="p-3 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                    Tabel Rincian Bon (Kasbon)
                                </h3>
                            </div>
                            <button 
                                type="button" 
                                @click="addAdvance()"
                                class="px-2.5 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 text-xs font-bold transition-colors"
                            >
                                + Tambah Bon
                            </button>
                        </div>

                        <!-- Tabel Bon -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b-2 border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/60 font-bold text-slate-700 dark:text-slate-300">
                                        <th class="py-2 px-3 border-r border-slate-200 dark:border-slate-700 w-4/12">Tanggal</th>
                                        <th class="py-2 px-3 border-r border-slate-200 dark:border-slate-700 w-5/12">Keterangan</th>
                                        <th class="py-2 px-3 text-right w-3/12">Jumlah</th>
                                        <th class="py-2 px-2 text-center w-6"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(adv, idx) in advances" :key="idx">
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <!-- Tanggal Bon -->
                                            <td class="p-1.5 border-r border-slate-200 dark:border-slate-700">
                                                <input 
                                                    type="date" 
                                                    :name="'advances[' + idx + '][advance_date]'"
                                                    x-model="adv.advance_date" 
                                                    class="w-full text-xs font-mono py-1 px-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
                                                />
                                            </td>

                                            <!-- Keterangan Bon -->
                                            <td class="p-1.5 border-r border-slate-200 dark:border-slate-700">
                                                <input 
                                                    type="text" 
                                                    :name="'advances[' + idx + '][description]'"
                                                    x-model="adv.description" 
                                                    placeholder="Contoh: Kasbon makan"
                                                    class="w-full text-xs py-1 px-2 rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
                                                />
                                            </td>

                                            <!-- Jumlah Bon -->
                                            <td class="p-1.5">
                                                <input 
                                                    type="number" 
                                                    min="0"
                                                    step="1000"
                                                    :name="'advances[' + idx + '][amount]'"
                                                    x-model.number="adv.amount" 
                                                    class="w-full text-xs font-mono font-bold text-right py-1 px-2 rounded-lg bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
                                                />
                                            </td>

                                            <!-- Tombol Hapus Bon -->
                                            <td class="p-1.5 text-center">
                                                <button 
                                                    type="button" 
                                                    @click="removeAdvance(idx)"
                                                    class="text-rose-400 hover:text-rose-600 p-0.5 rounded"
                                                >
                                                    ✕
                                                </button>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- TOTAL BARIS BON -->
                                    <tr class="border-t-2 border-slate-300 dark:border-slate-700 font-bold bg-slate-50 dark:bg-slate-800">
                                        <td colspan="2" class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700 text-right uppercase tracking-wider text-xs">
                                            Total Bon
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-extrabold text-sm text-rose-600 dark:text-rose-400">
                                            <span x-text="totalBon > 0 ? formatNumber(totalBon) : '-'"></span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Petunjuk & Catatan Bon -->
                    <div class="p-3 bg-slate-50/80 dark:bg-slate-800/40 border-t border-slate-200 dark:border-slate-700 text-[11px] text-slate-500">
                        Total rincian bon akan otomatis memotong total gaji kotor penjahit menjadi <strong>Take Home Pay</strong>.
                    </div>
                </div>
            </div>

            <!-- Bagian Pembayaran & Tombol Simpan -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Penyelesaian & Pembukuan Akuntansi
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Metode Pembayaran -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Metode Pembayaran
                        </label>
                        <select 
                            name="payment_method" 
                            x-model="paymentMethod"
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold focus:ring-amber-500"
                        >
                            <option value="Tunai">Tunai / Cash</option>
                            <option value="Transfer (Bank BCA)">Transfer (Bank BCA)</option>
                            <option value="Transfer Bank Lain">Transfer Bank Lain</option>
                        </select>
                    </div>

                    <!-- Akun Kas / Bank -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Sumber Kas / Rekening Pengeluaran
                        </label>
                        <select 
                            name="account_id" 
                            x-model="accountId"
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"
                        >
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Catatan Tambahan (Opsional)
                        </label>
                        <input 
                            type="text" 
                            name="notes" 
                            x-model="notes"
                            placeholder="Catatan batch jahit, kondisi kain..." 
                            class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-amber-500"
                        />
                    </div>
                </div>

                <!-- Submit Button Bar -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="text-xs text-slate-500">
                        Otomatis membukukan: <span class="font-semibold text-slate-700 dark:text-slate-300">Debit Beban Gaji (5002)</span> dan <span class="font-semibold text-slate-700 dark:text-slate-300">Kredit Kas/Bank</span> sebesar Take Home Pay.
                    </div>
                    <button 
                        type="submit" 
                        class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black text-sm shadow-lg shadow-amber-500/25 transition-all flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Simpan & Cetak Slip Upah</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
