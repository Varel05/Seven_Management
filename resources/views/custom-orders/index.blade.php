<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-emerald-900/80 dark:bg-slate-800 border border-emerald-700/80 dark:border-slate-700 text-emerald-200 dark:text-emerald-300 flex items-center justify-center font-bold shadow-xs backdrop-blur-md shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                            {{ __('Jasa Pembuatan Jas Custom & AI') }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/80 dark:bg-slate-800 text-emerald-200 dark:text-emerald-300 border border-emerald-700/80 dark:border-slate-700 backdrop-blur-md shadow-2xs">
                            Pendapatan Jasa Tailor (4003)
                        </span>
                    </div>
                    <p class="text-xs text-emerald-200/80 dark:text-slate-400 mt-1 font-medium">
                        {{ __('Kelola pesanan jas bespoke, pipeline produksi, kalkulasi estimasi bahan AI, dan penerimaan pembayaran DP') }}
                    </p>
                </div>
            </div>

            <!-- Header Quick Action -->
            <div class="flex items-center gap-2.5">
                <a 
                    href="{{ route('custom-orders.create') }}" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Buat Pesanan Custom (+ AI Estimasi)</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        paymentModal: false,
        activeOrder: {},
        paymentAmount: 0,
        selectedAccount: '{{ $paymentAccounts->first()?->id }}',
        openPaymentModal(ord) {
            this.activeOrder = ord;
            this.paymentAmount = ord.remaining_payment || (ord.total_price - ord.down_payment);
            this.paymentModal = true;
        },
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

            @if (session('error'))
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-sm shadow-xs backdrop-blur-md">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Pesanan Dalam Produksi -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Jas Sedang Dikerjakan</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-800 dark:text-white flex items-baseline gap-2">
                        <span>{{ $activeOrdersCount }}</span>
                        <span class="text-xs font-medium text-slate-500">pesanan aktif</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ $completedOrdersCount }} pesanan telah selesai & diserahkan
                    </div>
                </div>

                <!-- Total Omset Tailoring -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Omset Tailoring</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-800 dark:text-white">
                        Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        Akun Pendapatan Jasa Tailor <span class="font-bold text-emerald-600">4003</span>
                    </div>
                </div>

                <!-- Laba Kotor Tailoring -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Laba Kotor Tailor</span>
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-purple-600 dark:text-purple-400">
                        Rp {{ number_format($totalProfit, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        Setelah dipotong biaya bahan & ongkos jahit
                    </div>
                </div>

                <!-- Piutang Belum Dilunasi -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden group hover:border-emerald-500/40 transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sisa Tagihan / Piutang</span>
                        <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-2xl font-black text-rose-600 dark:text-rose-400">
                        Rp {{ number_format($uncollectedReceivables, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        Pelunasan saat fitting atau pengambilan jas
                    </div>
                </div>
            </div>

            <!-- Filter & Status Pipeline Bar -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <!-- Baris 1: Pencarian Cepat -->
                <form method="GET" action="{{ route('custom-orders.index') }}" class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center justify-between">
                    @if ($selectedStatus)
                        <input type="hidden" name="status" value="{{ $selectedStatus }}">
                    @endif
                    <div class="relative w-full sm:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text" 
                            name="q" 
                            value="{{ $search }}" 
                            placeholder="Cari nama klien, no. pesanan, telepon..." 
                            class="w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-slate-100 dark:bg-slate-800/80 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                        >
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cari Pesanan</span>
                        </button>

                        @if ($search || $selectedStatus)
                            <a href="{{ route('custom-orders.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold transition-all flex items-center gap-1 cursor-pointer" title="Hapus semua filter pencarian & status">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Reset</span>
                            </a>
                        @endif
                    </div>
                </form>

                <!-- Baris 2: Filter Status / Kategori Tahap Pengerjaan (Auto-Wrap jika Melebihi Lebar Layar) -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mr-1 shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span>Filter Status:</span>
                    </span>

                    <a 
                        href="{{ route('custom-orders.index', array_filter(['q' => $search])) }}" 
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ empty($selectedStatus) ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                    >
                        Semua Pesanan
                    </a>

                    @foreach ($statuses as $stKey => $stLabel)
                        <a 
                            href="{{ route('custom-orders.index', array_filter(['status' => $stKey, 'q' => $search])) }}" 
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ $selectedStatus === $stKey ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                        >
                            {{ $stLabel }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Daftar Pesanan Jas Bespoke / Custom</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pemantauan tahap pengerjaan, kalkulasi bahan AI, serta status pembayaran pelanggan.</p>
                    </div>
                    <span class="text-xs font-medium text-slate-500">
                        Menampilkan {{ $orders->count() }} dari {{ $orders->total() }} pesanan
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4">No. Pesanan / Tanggal</th>
                                <th class="py-3.5 px-4">Klien & Sumber</th>
                                <th class="py-3.5 px-4">Kategori Jas & Bahan</th>
                                <th class="py-3.5 px-4 text-right">Biaya Modal (HPP)</th>
                                <th class="py-3.5 px-4 text-right">Harga Pesanan</th>
                                <th class="py-3.5 px-4 text-right">Laba (% Margin)</th>
                                <th class="py-3.5 px-4 text-center">Pembayaran</th>
                                <th class="py-3.5 px-4 text-center">Tahap Produksi</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800">
                            @forelse ($orders as $order)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3.5 px-4 font-mono">
                                        <div class="font-bold text-slate-800 dark:text-white text-sm">
                                            <a href="{{ route('custom-orders.show', $order) }}" class="hover:text-emerald-500 underline decoration-dotted">
                                                {{ $order->order_number }}
                                            </a>
                                        </div>
                                        <div class="text-[11px] text-slate-400">Order: {{ $order->order_date->format('d M Y') }}</div>
                                        @if ($order->due_date)
                                            <div class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">Target: {{ $order->due_date->format('d M Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-800 dark:text-slate-200">{{ $order->customer_name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $order->customer_phone ?? '-' }}</div>
                                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $order->source === 'telegram' ? 'bg-sky-500/10 text-sky-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                            @if ($order->source === 'telegram')
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.52 2.77-1.18 3.35-1.38 3.73-1.39.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                                Telegram AI
                                            @else
                                                Web Form
                                            @endif
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $order->suit_type_label }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $order->fabric_type ?? 'Kain Standar' }} • {{ $order->color ?? 'Warna Custom' }}</div>
                                        @if (!empty($order->ai_estimation['materials']['main_fabric_meters']))
                                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">
                                                Estimasi: {{ $order->ai_estimation['materials']['main_fabric_meters'] }}m kain
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono text-slate-600 dark:text-slate-400">
                                        Rp {{ number_format($order->total_cost, 0, ',', '.') }}
                                        <div class="text-[10px] text-slate-400">Bahan + Jahit</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-800 dark:text-white text-sm">
                                        {{ $order->formatted_total_price }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="font-bold text-purple-600 dark:text-purple-400 font-mono">
                                            +Rp {{ number_format($order->gross_profit, 0, ',', '.') }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-semibold">
                                            Margin: {{ $order->profit_margin_percentage }}%
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if ($order->payment_status === 'paid')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                ✓ Lunas
                                            </span>
                                        @elseif ($order->payment_status === 'partial_dp')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                                DP Rp {{ number_format($order->down_payment, 0, ',', '.') }}
                                            </span>
                                            <div class="text-[10px] text-rose-500 font-mono mt-0.5">Sisa: Rp {{ number_format($order->remaining_payment, 0, ',', '.') }}</div>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                                Belum Bayar
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <form method="POST" action="{{ route('custom-orders.status', $order) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select 
                                                name="production_status" 
                                                onchange="this.form.submit()" 
                                                class="text-[11px] font-semibold py-1 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer"
                                            >
                                                @foreach ($statuses as $k => $label)
                                                    <option value="{{ $k }}" {{ $order->production_status === $k ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a 
                                                href="{{ route('custom-orders.show', $order) }}" 
                                                class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all" 
                                                title="Lihat Detail & Ukuran"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                            @if ($order->payment_status !== 'paid')
                                                <button 
                                                    type="button" 
                                                    @click="openPaymentModal({{ Js::from($order) }})" 
                                                    class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all" 
                                                    title="Catat Pembayaran / Pelunasan"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-10 text-slate-400">
                                        Belum ada data pesanan jas custom. Klik tombol <strong>+ Buat Pesanan Custom (+ AI Estimasi)</strong> untuk memulai.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>

        </div>

        <!-- MODAL CATAT PEMBAYARAN / PELUNASAN -->
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
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Catat Pembayaran Jas Custom</h3>
                    <button @click="paymentModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form :action="'/custom-orders/' + activeOrder.id + '/payment'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <div class="text-xs text-slate-500">Nomor Pesanan:</div>
                        <div class="font-mono font-bold text-slate-800 dark:text-white" x-text="activeOrder.order_number"></div>
                        <div class="text-xs text-slate-400" x-text="'Klien: ' + activeOrder.customer_name"></div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Total Harga:</span>
                            <span class="font-mono font-bold" x-text="formatRupiah(activeOrder.total_price)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Sudah Dibayar (DP):</span>
                            <span class="font-mono text-emerald-600" x-text="formatRupiah(activeOrder.down_payment)"></span>
                        </div>
                        <div class="flex justify-between font-bold pt-1 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-rose-500">Sisa Tagihan:</span>
                            <span class="font-mono text-rose-500" x-text="formatRupiah(activeOrder.total_price - activeOrder.down_payment)"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jumlah Pembayaran Diterima (Rp) *</label>
                        <input type="number" name="amount" x-model="paymentAmount" required min="1" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono font-bold">
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
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-xs font-bold shadow-md cursor-pointer">Simpan & Bukukan ke Jurnal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
