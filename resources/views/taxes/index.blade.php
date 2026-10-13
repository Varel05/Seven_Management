<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-emerald-900/80 dark:bg-slate-800 border border-emerald-700/80 dark:border-slate-700 text-emerald-200 dark:text-emerald-300 flex items-center justify-center font-bold shadow-xs backdrop-blur-md shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                            Pajak PPh Final UMKM
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-900/80 dark:bg-blue-950 text-blue-200 dark:text-blue-300 border border-blue-700/80 dark:border-blue-800">
                            Badan Usaha (0,5% PP 55/2022)
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/80 dark:bg-slate-800 text-emerald-200 dark:text-emerald-300 border border-emerald-700/80 dark:border-slate-700">
                            Akun Beban Pajak (5005)
                        </span>
                    </div>
                    <p class="text-xs text-emerald-200/80 dark:text-slate-400 mt-1 font-medium">
                        Perhitungan, pelaporan, dan pencatatan penyetoran PPh Final 0,5% atas peredaran bruto pendapatan usaha
                    </p>
                </div>
            </div>

            <!-- Periode Filter & Action -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <form method="GET" action="{{ route('taxes.index') }}" class="flex items-center gap-2">
                    <select name="month" onchange="this.form.submit()" class="text-xs font-semibold rounded-xl bg-emerald-900/80 dark:bg-slate-800 border-emerald-700/80 dark:border-slate-700 text-white dark:text-slate-200 py-2 pl-3 pr-8 focus:ring-emerald-500 focus:border-emerald-500">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>

                    <select name="year" onchange="this.form.submit()" class="text-xs font-semibold rounded-xl bg-emerald-900/80 dark:bg-slate-800 border-emerald-700/80 dark:border-slate-700 text-white dark:text-slate-200 py-2 pl-3 pr-8 focus:ring-emerald-500 focus:border-emerald-500">
                        @for($y = now()->year + 1; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>
                                Tahun {{ $y }}
                            </option>
                        @endfor
                    </select>
                </form>

                @if(!($monthlyTax['is_paid'] ?? false))
                    <button 
                        type="button" 
                        @click="$dispatch('open-tax-pay-modal', { month: {{ $month }}, year: {{ $year }}, amount: {{ $monthlyTax['tax_amount'] }} })"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-900/30 transition-all cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Catat Setor Pajak</span>
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div x-data="{
        payModalOpen: false,
        payMonth: {{ $month }},
        payYear: {{ $year }},
        payAmount: {{ $monthlyTax['tax_amount'] }},
        payAccountCode: '1001',
        payNtpn: '',
        payNotes: ''
    }" 
    @open-tax-pay-modal.window="
        payMonth = $event.detail.month || {{ $month }};
        payYear = $event.detail.year || {{ $year }};
        payAmount = $event.detail.amount || 0;
        payModalOpen = true;
    "
    class="max-w-[1800px] mx-auto py-6 px-4 sm:px-6 lg:px-8 xl:px-12 space-y-6">

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-center justify-between text-xs font-medium">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-center justify-between text-xs font-medium">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- 4 KPI Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Omzet Bruto Masa Terpilih -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span>Omzet Bruto (Masa {{ $monthlyTax['period'] }})</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                </div>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-slate-100 mt-2">
                    {{ $monthlyTax['formatted_gross_revenue'] }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center justify-between">
                    <span>Akun Pendapatan (4001)</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">Kredit</span>
                </div>
            </div>

            <!-- 2. PPh Terutang 0,5% -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-900/60 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-blue-700 dark:text-blue-300">
                    <span>PPh Terutang (Tarif 0,5%)</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                </div>
                <div class="text-2xl font-black font-mono text-blue-700 dark:text-blue-300 mt-2">
                    {{ $monthlyTax['formatted_tax_amount'] }}
                </div>
                <div class="text-xs text-blue-700/80 dark:text-blue-400/80 mt-1 flex items-center justify-between">
                    <span>Entitas: {{ $monthlyTax['entity_type'] }}</span>
                    <span class="font-bold">PPh Final</span>
                </div>
            </div>

            <!-- 3. Batas Waktu Setor -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900/60 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-amber-800 dark:text-amber-300">
                    <span>Batas Akhir Penyetoran</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                </div>
                <div class="text-2xl font-black text-amber-800 dark:text-amber-300 mt-2">
                    {{ $monthlyTax['formatted_due_date'] }}
                </div>
                <div class="text-xs text-amber-700/80 dark:text-amber-400/80 mt-1">
                    Paling lambat tgl 15 bulan berikutnya
                </div>
            </div>

            <!-- 4. Kode Billing DJP -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span>Kode Akun & Jenis Setoran DJP</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                </div>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-slate-100 mt-2">
                    {{ $monthlyTax['kap'] }} / {{ $monthlyTax['kjs'] }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    KAP PPh Final (411128) - KJS Badan (420)
                </div>
            </div>
        </div>

        <!-- Detail Masa Pajak Terpilih (Status & Rincian Pendapatan) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Status Pajak Masa {{ $monthlyTax['period'] }}</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Rincian mutasi akun pendapatan usaha yang menjadi dasar pengenaan PPh Final 0,5%
                    </p>
                </div>

                <div>
                    @if($monthlyTax['is_paid'])
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>LUNAS DISETOR (Ref: {{ $monthlyTax['payment_info']['reference'] ?? '-' }})</span>
                        </div>
                    @else
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>BELUM DISETOR</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Breakdown Akun Pendapatan -->
            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                        Rincian Akun Pendapatan (Omzet)
                    </h3>
                    <div class="space-y-2">
                        @forelse($monthlyTax['revenue_breakdown'] as $rev)
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-xs">
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-slate-100">[{{ $rev['code'] }}] {{ $rev['name'] }}</span>
                                </div>
                                <div class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ $rev['formatted_amount'] }}
                                </div>
                            </div>
                        @empty
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center text-xs text-slate-400 italic">
                                Belum ada transaksi pendapatan pada masa pajak ini
                            </div>
                        @endforelse

                        <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs font-bold text-emerald-900 dark:text-emerald-200">
                            <span>Total Peredaran Bruto:</span>
                            <span class="font-mono text-sm">{{ $monthlyTax['formatted_gross_revenue'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Informasi Pembukuan & Bukti Setor -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                        Informasi Pembukuan & Bukti Setor
                    </h3>

                    @if($monthlyTax['is_paid'])
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-2.5 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-slate-400">Nomor Referensi / NTPN:</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $monthlyTax['payment_info']['reference'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-slate-400">Tanggal Setor:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $monthlyTax['payment_info']['paid_date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-slate-400">Nominal Disetor:</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($monthlyTax['payment_info']['amount'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-slate-400">Jurnal Akuntansi:</span>
                                <span class="text-[11px] font-mono bg-slate-200 dark:bg-slate-700 px-2 py-0.5 rounded">Debit 5005 / Kredit Kas/Bank</span>
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 space-y-3 text-xs">
                            <p class="text-slate-700 dark:text-slate-300 leading-relaxed">
                                PPh Final masa ini sebesar <strong class="font-mono text-amber-800 dark:text-amber-300">{{ $monthlyTax['formatted_tax_amount'] }}</strong> belum dibukukan pembayarannya.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    type="button" 
                                    @click="$dispatch('open-tax-pay-modal', { month: {{ $month }}, year: {{ $year }}, amount: {{ $monthlyTax['tax_amount'] }} })"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    <span>Catat Setor Sekarang</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Panduan e-Billing -->
                    <div class="mt-4 p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 space-y-1">
                        <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Panduan Kode e-Billing DJP Online</span>
                        </div>
                        <p class="text-[11px] leading-relaxed">
                            Buat kode billing di <span class="font-semibold text-blue-600 dark:text-blue-400">djponline.pajak.go.id</span> menggunakan <strong>KAP 411128</strong> dan <strong>KJS 420</strong> (PPh Final UMKM Badan). Setelah disetor ke Bank/Pos, catat nomor NTPN di sini.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rekapitulasi Tahunan (12 Bulan) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        Rekapitulasi PPh Final Tahun {{ $year }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Daftar perhitungan peredaran bruto dan status pelaporan tiap bulan
                    </p>
                </div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                    Total Disetor: <strong class="text-emerald-600 dark:text-emerald-400 font-mono">{{ $annualSummary['formatted_total_paid_tax'] }}</strong> dari <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $annualSummary['formatted_total_annual_tax'] }}</strong>
                </div>
            </div>

            <!-- Tabel 12 Bulan -->
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-3">Masa Pajak</th>
                            <th class="py-3 px-3 text-right">Omzet Bruto</th>
                            <th class="py-3 px-3 text-center">Tarif</th>
                            <th class="py-3 px-3 text-right">PPh Terutang</th>
                            <th class="py-3 px-3 text-center">Batas Setor</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($annualSummary['months'] as $mNum => $mDetail)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors {{ $month == $mNum ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : '' }}">
                                <td class="py-3.5 px-3 font-semibold text-slate-900 dark:text-slate-100">
                                    <div class="flex items-center gap-2">
                                        @if($month == $mNum)
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        @endif
                                        <span>{{ $mDetail['period'] }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3 text-right font-mono text-slate-800 dark:text-slate-200">
                                    {{ $mDetail['formatted_gross_revenue'] }}
                                </td>
                                <td class="py-3.5 px-3 text-center text-slate-600 dark:text-slate-400 font-semibold">
                                    0,5%
                                </td>
                                <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                    {{ $mDetail['formatted_tax_amount'] }}
                                </td>
                                <td class="py-3.5 px-3 text-center text-slate-500 dark:text-slate-400">
                                    {{ $mDetail['formatted_due_date'] }}
                                </td>
                                <td class="py-3.5 px-3 text-center">
                                    @if($mDetail['is_paid'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            Lunas
                                        </span>
                                    @else
                                        @if($mDetail['tax_amount'] > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                Belum Setor
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                Nihil
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-3.5 px-3 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('taxes.index', ['year' => $year, 'month' => $mNum]) }}" 
                                           class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800"
                                           title="Pilih Masa Ini">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>

                                        @if(!$mDetail['is_paid'] && $mDetail['tax_amount'] > 0)
                                            <button 
                                                type="button" 
                                                @click="$dispatch('open-tax-pay-modal', { month: {{ $mNum }}, year: {{ $year }}, amount: {{ $mDetail['tax_amount'] }} })"
                                                class="px-2 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold"
                                            >
                                                Setor
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100/80 dark:bg-slate-800/80 font-bold border-t-2 border-slate-300 dark:border-slate-700">
                            <td class="py-3.5 px-3 text-slate-900 dark:text-white uppercase tracking-wider">
                                Total Tahun {{ $year }}
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ $annualSummary['formatted_total_annual_revenue'] }}
                            </td>
                            <td class="py-3.5 px-3 text-center text-slate-600 dark:text-slate-400">
                                0,5%
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono text-blue-700 dark:text-blue-300">
                                {{ $annualSummary['formatted_total_annual_tax'] }}
                            </td>
                            <td colspan="3" class="py-3.5 px-3 text-right text-emerald-700 dark:text-emerald-300">
                                Sudah Disetor: {{ $annualSummary['formatted_total_paid_tax'] }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL CATAT PENYETORAN PAJAK PPH FINAL -->
        <!-- ========================================================================= -->
        <div x-show="payModalOpen" 
             x-cloak 
             class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                
                <!-- Backdrop -->
                <div x-show="payModalOpen" 
                     @click="payModalOpen = false" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity" 
                     aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal Dialog -->
                <div x-show="payModalOpen" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200 dark:border-slate-800">
                    
                    <form method="POST" action="{{ route('taxes.pay') }}">
                        @csrf
                        <input type="hidden" name="year" :value="payYear">
                        <input type="hidden" name="month" :value="payMonth">

                        <div class="p-6">
                            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white" id="modal-title">
                                            Catat Penyetoran PPh Final
                                        </h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Masa Pajak Bulan ke-<span x-text="payMonth"></span> Tahun <span x-text="payYear"></span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="payModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="mt-4 space-y-4">
                                <!-- Nominal Setoran -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Nominal Pembayaran Pajak (Rp)
                                    </label>
                                    <input 
                                        type="number" 
                                        name="amount" 
                                        x-model="payAmount" 
                                        required 
                                        min="1" 
                                        class="w-full text-sm font-mono font-bold rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        Tarif 0,5% dari omzet bruto bulan tersebut.
                                    </p>
                                </div>

                                <!-- Sumber Dana Pembayaran -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Rekening Sumber Pembayaran (Kredit)
                                    </label>
                                    <select 
                                        name="account_code" 
                                        x-model="payAccountCode" 
                                        class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        @foreach($paymentAccounts as $acc)
                                            <option value="{{ $acc->code }}">[{{ $acc->code }}] {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        Jurnal akan mendebit Akun 5005 (Beban Pajak) dan mengkredit akun kas/bank yang dipilih.
                                    </p>
                                </div>

                                <!-- NTPN -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Nomor Transaksi Penerimaan Negara (NTPN)
                                    </label>
                                    <input 
                                        type="text" 
                                        name="ntpn" 
                                        x-model="payNtpn" 
                                        placeholder="Contoh: 8E79BC31A9F42B10" 
                                        class="w-full text-xs font-mono uppercase tracking-wider rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        16 karakter NTPN yang tertera pada Bukti Penerimaan Negara (BPN). Boleh dikosongkan jika belum ada.
                                    </p>
                                </div>

                                <!-- Catatan -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Catatan / Keterangan Tambahan
                                    </label>
                                    <input 
                                        type="text" 
                                        name="notes" 
                                        x-model="payNotes" 
                                        placeholder="Penyetoran via Bank BCA / Teller / Kantor Pos" 
                                        class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Footer Buttons -->
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/60 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                            <button 
                                type="button" 
                                @click="payModalOpen = false" 
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-900/30 transition-all cursor-pointer"
                            >
                                Simpan & Bukukan ke Jurnal (5005)
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

    </div>
</x-app-layout>
