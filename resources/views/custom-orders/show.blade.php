<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('custom-orders.index') }}" class="p-2 rounded-xl bg-emerald-900/80 hover:bg-emerald-800 text-emerald-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs font-mono">
                            {{ $order->order_number }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            {{ $statuses[$order->production_status] ?? $order->production_status }}
                        </span>
                    </div>
                    <p class="text-xs text-emerald-200/80 mt-0.5">
                        Klien: <strong class="text-white">{{ $order->customer_name }}</strong> • Dipesan: {{ $order->order_date->format('d M Y') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold shadow-xs flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Lembar Kerja Penjahit</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        paymentModal: false,
        paymentAmount: {{ $order->remaining_payment }},
        selectedAccount: '{{ $paymentAccounts->first()?->id }}',
        formatRupiah(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }
    }">
        <div class="max-w-[1800px] w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

            <!-- Flash Alerts -->
            @if (session('success'))
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-sm shadow-xs backdrop-blur-md">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- Production Pipeline Status Card -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Tahap Pengerjaan Jas (Pipeline Produksi)</h2>
                        <p class="text-xs text-slate-500">Perbarui tahapan saat jas sedang dijahit, fitting, atau siap diambil.</p>
                    </div>

                    <form method="POST" action="{{ route('custom-orders.status', $order) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="production_status" onchange="this.form.submit()" class="text-xs font-semibold py-1.5 px-3 rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer">
                            @foreach ($statuses as $k => $label)
                                <option value="{{ $k }}" {{ $order->production_status === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <!-- Step Tracker Pills -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
                    @php
                        $stageList = ['consultation', 'cutting_sewing', 'fitting', 'finishing', 'ready', 'completed'];
                        $currentIdx = array_search($order->production_status, $stageList);
                        if ($currentIdx === false) $currentIdx = -1;
                    @endphp
                    @foreach ($stageList as $idx => $stKey)
                        @php
                            $isPassed = $idx <= $currentIdx;
                            $isCurrent = $order->production_status === $stKey;
                        @endphp
                        <div class="p-3 rounded-xl border text-center transition-all {{ $isCurrent ? 'bg-emerald-500/10 border-emerald-500 text-emerald-700 dark:text-emerald-300 font-bold' : ($isPassed ? 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-semibold' : 'bg-transparent border-slate-200/50 dark:border-slate-800 text-slate-400') }}">
                            <div class="text-[10px] uppercase tracking-wider mb-0.5">Tahap {{ $idx + 1 }}</div>
                            <div class="text-xs">{{ $statuses[$stKey] ?? $stKey }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Main Specs & Financial Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column: Specs & Measurements (7 cols) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Spesifikasi Model & Kain -->
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 pb-2 border-b border-slate-100 dark:border-slate-800">
                            Spesifikasi Model & Desain
                        </h2>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span class="text-slate-400 block mb-0.5">Kategori Pakaian</span>
                                <span class="font-bold text-slate-800 dark:text-white text-sm">{{ $order->suit_type_label }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Bahan Kain</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $order->fabric_type ?? 'Standar Tailor' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Warna</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $order->color ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- Integrasi Master Bahan Gudang & Status Pemotongan Kain -->
                        @if ($order->material_id && $order->material)
                            <div class="p-3.5 rounded-xl bg-emerald-500/5 dark:bg-emerald-950/20 border border-emerald-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-bold text-emerald-900 dark:text-emerald-200 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        <span>Bahan Gudang: {{ $order->material->name }} ({{ $order->material_meters }} {{ $order->material->unit }})</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        Sisa stok saat ini di gudang: <strong class="text-slate-700 dark:text-slate-300">{{ $order->material->formatted_stock }}</strong>
                                    </div>
                                </div>
                                <div>
                                    @if ($order->is_material_cut)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>Bahan Sudah Dipotong</span>
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('custom-orders.cut-material', $order) }}" onsubmit="return confirm('Potong {{ $order->material_meters }} {{ $order->material->unit }} bahan {{ $order->material->name }} dari stok gudang?')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition-colors shadow-xs">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"/></svg>
                                                <span>Potong Bahan dari Stok</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Ukuran Tubuh Pelanggan -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Ukuran Tubuh Klien (cm)</span>
                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-center">
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Tinggi</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['height'] ?? '-' }} cm</div>
                                </div>
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Lingkar Dada</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['chest'] ?? '-' }} cm</div>
                                </div>
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Pinggang</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['waist'] ?? '-' }} cm</div>
                                </div>
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Panjang Jas</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['jacket_length'] ?? '-' }} cm</div>
                                </div>
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Lengan</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['sleeve_length'] ?? '-' }} cm</div>
                                </div>
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <div class="text-[10px] text-slate-400">Celana</div>
                                    <div class="text-xs font-mono font-bold">{{ $order->body_measurements['trouser_length'] ?? '-' }} cm</div>
                                </div>
                            </div>
                        </div>

                        <!-- Foto Referensi -->
                        @if ($order->reference_image)
                            <div class="space-y-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Foto Referensi Desain / Jas:</span>
                                <div class="max-w-xs rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                                    <img src="{{ Str::startsWith($order->reference_image, 'http') ? $order->reference_image : asset('storage/' . $order->reference_image) }}" alt="Desain Jas" class="w-full h-auto object-cover">
                                </div>
                            </div>
                        @endif

                        @if ($order->notes)
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300">
                                <span class="font-bold">Catatan Klien:</span> {{ $order->notes }}
                            </div>
                        @endif
                    </div>

                </div>

                <!-- Right Column: Financial & Accounting Breakdown (5 cols) -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- Kartu Status Keuangan & Laba Rugi -->
                    <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Kalkulasi Keuangan & Laba
                            </h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $order->payment_status === 'paid' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600' }}">
                                {{ $paymentStatuses[$order->payment_status] ?? $order->payment_status }}
                            </span>
                        </div>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Biaya Bahan Baku Kain:</span>
                                <span class="font-mono">Rp {{ number_format($order->material_cost, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Ongkos Pengerjaan Penjahit:</span>
                                <span class="font-mono">Rp {{ number_format($order->labor_cost, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-bold pt-2 border-t border-slate-200 dark:border-slate-800 text-slate-800 dark:text-white">
                                <span>TOTAL HPP (MODAL):</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ $order->formatted_total_cost }}</span>
                            </div>
                            <div class="flex justify-between font-black text-sm pt-1 border-t border-slate-200 dark:border-slate-800 text-slate-800 dark:text-white">
                                <span>TOTAL HARGA PESANAN:</span>
                                <span class="font-mono text-base text-slate-900 dark:text-white">{{ $order->formatted_total_price }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-700 dark:text-purple-300 font-bold">
                                <span>Laba Kotor Tailoring:</span>
                                <span class="font-mono text-sm">+Rp {{ number_format($order->gross_profit, 0, ',', '.') }} ({{ $order->profit_margin_percentage }}%)</span>
                            </div>
                        </div>

                        <!-- Pembayaran & Pelunasan -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Uang Muka (DP) Masuk:</span>
                                <span class="font-mono font-bold text-emerald-600">Rp {{ number_format($order->down_payment, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span class="text-rose-500">Sisa Tagihan / Pelunasan:</span>
                                <span class="font-mono text-rose-500 text-sm">Rp {{ number_format($order->remaining_payment, 0, ',', '.') }}</span>
                            </div>

                            @if ($order->remaining_payment > 0)
                                <button 
                                    type="button" 
                                    @click="paymentModal = true" 
                                    class="w-full mt-3 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white text-xs font-bold shadow-md cursor-pointer flex items-center justify-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <span>Catat Pelunasan Tagihan</span>
                                </button>
                            @else
                                <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 text-center font-bold text-xs mt-2">
                                    ✓ Tagihan Pembuatan Jas Ini Telah Lunas
                                </div>
                            @endif
                        </div>

                        <!-- Info Akuntansi Buku Besar -->
                        @if ($order->journalEntry)
                            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-400 space-y-1">
                                <div class="font-semibold text-slate-600 dark:text-slate-300">Pencatatan Buku Besar:</div>
                                <div>No. Ref Jurnal: <strong class="font-mono text-slate-700 dark:text-slate-200">{{ $order->journalEntry->reference }}</strong></div>
                                <div>Status: <span class="text-emerald-500 font-bold uppercase">{{ $order->journalEntry->status }}</span></div>
                            </div>
                        @endif
                    </div>

                </div>

            </div>

        </div>

        <!-- MODAL PELUNASAN -->
        <div 
            x-show="paymentModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
        >
            <div 
                @click.away="paymentModal = false" 
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Pelunasan Pesanan Jas</h3>
                    <button @click="paymentModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('custom-orders.payment', $order) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jumlah Pelunasan (Rp) *</label>
                        <input type="number" name="amount" x-model="paymentAmount" required min="1" max="{{ $order->remaining_payment }}" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Masuk ke Rekening / Kas *</label>
                        <select name="account_id" x-model="selectedAccount" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                            @foreach ($paymentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="paymentModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md cursor-pointer">Simpan & Bukukan ke Jurnal</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
