<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('employees.index') }}" class="p-2.5 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors shadow-xs" title="Kembali ke Daftar Karyawan & Payroll">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h2 class="text-xl font-black text-white">Slip Gaji Karyawan</h2>
                    <p class="text-xs text-slate-400">Periode: {{ $payroll->period }} ({{ $payroll->employee->name }})</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <a 
                    href="{{ route('employees.index') }}" 
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800 text-xs font-semibold transition-colors print:hidden"
                >
                    <span>← Daftar Karyawan</span>
                </a>

                @if(!$payroll->isPaid())
                    <form action="{{ route('employees.payroll.post-transaction', $payroll) }}" method="POST" class="inline print:hidden" onsubmit="return confirm('Apakah Anda yakin ingin membukukan pengeluaran gaji {{ $payroll->employee->name }} sebesar Rp {{ number_format($payroll->take_home_pay, 0, ',', '.') }} ke transaksi jurnal keuangan?');">
                        @csrf
                        <button 
                            type="submit" 
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                            title="Catat pengeluaran gaji ke akun Beban Gaji (5002) dan Akun Kas/Bank"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Bukukan Transaksi Keuangan</span>
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-xs font-bold print:hidden">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Telah Dibukukan ({{ $payroll->journalEntry?->reference }})</span>
                    </span>
                @endif

                <button 
                    type="button" 
                    id="btn-download-pdf"
                    onclick="downloadSlipPdf()" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer print:hidden"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span id="btn-download-text">Unduh Slip PDF</span>
                </button>
                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold border border-slate-700 transition-all cursor-pointer print:hidden"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Fisik</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            <!-- Banner Status: Draft vs Terbukukan -->
            @if(!$payroll->isPaid())
                <div class="mb-4 p-4 sm:p-5 rounded-2xl bg-amber-500/15 dark:bg-amber-950/60 border border-amber-500/40 text-amber-900 dark:text-amber-200 print:hidden shadow-xs space-y-3.5 overflow-hidden">
                    <!-- Pesan Peringatan Berada di Atas (Spacious & Mudah Dibaca) -->
                    <div class="flex items-start sm:items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-black shrink-0 shadow-xs mt-0.5 sm:mt-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <strong class="font-extrabold text-sm sm:text-base block text-amber-950 dark:text-amber-100">
                                Slip Gaji Status Draft (Belum Dicatat ke Transaksi)
                            </strong>
                            <p class="text-xs sm:text-[13px] text-amber-800 dark:text-amber-300/90 mt-0.5 leading-relaxed">
                                Slip ini belum dicatat ke buku jurnal (Beban Gaji 5002 belum didebit). Jika rincian di bawah telah sesuai, silakan pilih akun pembayaran dan klik tombol <strong>Bukukan Sekarang</strong>.
                            </p>
                        </div>
                    </div>

                    <!-- Opsi Memilih Akun dan Tombol Bukukan Berada di Bawah Pesan -->
                    <div class="pt-3 border-t border-amber-500/25 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="text-[11px] sm:text-xs text-amber-800 dark:text-amber-400 font-medium flex items-center gap-1.5 shrink min-w-0">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="truncate">Pilih akun pembayaran:</span>
                        </div>
                        <form action="{{ route('employees.payroll.post-transaction', $payroll) }}" method="POST" class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0" onsubmit="return confirm('Bukukan pengeluaran gaji {{ $payroll->employee->name }} sebesar Rp {{ number_format($payroll->take_home_pay, 0, ',', '.') }} ke transaksi jurnal?');">
                            @csrf
                            @if(isset($assetAccounts) && $assetAccounts->isNotEmpty())
                                <div class="w-full sm:w-auto">
                                    <select name="asset_account_id" class="w-full sm:w-44 px-2.5 py-1.5 rounded-xl border border-amber-300 dark:border-amber-800/80 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-xs cursor-pointer truncate">
                                        @foreach($assetAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ ($payroll->asset_account_id ?? $payroll->employee->asset_account_id) == $acc->id ? 'selected' : '' }}>
                                                {{ $acc->name }} ({{ $acc->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <button type="submit" class="w-full sm:w-auto px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-950/20 hover:scale-102 active:scale-98 cursor-pointer flex items-center justify-center gap-1.5 shrink-0 whitespace-nowrap">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                                <span>Bukukan Sekarang</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="mb-4 p-3.5 rounded-2xl bg-emerald-500/15 dark:bg-emerald-950/50 border border-emerald-500/40 text-emerald-900 dark:text-emerald-200 flex items-center justify-between text-xs print:hidden shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>
                            <strong>Telah Dibukukan ke Transaksi Keuangan:</strong> Ref Jurnal <span class="font-mono font-bold">{{ $payroll->journalEntry?->reference }}</span> • Tanggal: <span class="font-mono">{{ $payroll->paid_at?->format('d/m/Y H:i') ?? $payroll->journalEntry?->date?->format('d/m/Y') }}</span>
                        </span>
                    </div>
                </div>
            @endif

            <!-- Banner Pemberitahuan Pembuatan PDF Otomatis Jika Ada -->
            @if(request()->query('auto_pdf'))
                <div id="auto-pdf-status-banner" class="mb-4 p-4 rounded-2xl bg-emerald-500/15 dark:bg-emerald-950/60 border border-emerald-500/40 text-emerald-900 dark:text-emerald-200 flex items-center justify-between text-xs print:hidden shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-black shrink-0 shadow-xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <strong class="font-extrabold text-sm block text-emerald-950 dark:text-emerald-100">Slip PDF Siap Diunduh!</strong>
                            <span class="text-emerald-800 dark:text-emerald-300">
                                Berkas PDF slip gaji sedang diproses. Jika belum terunduh, klik tombol <strong>Unduh Slip PDF</strong> di atas.
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('employees.index') }}" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition-all shrink-0 ml-3 shadow-xs">
                        Selesai
                    </a>
                </div>
            @endif

            <!-- Notifikasi Session Success Jika Ada -->
            @if(session('success') && !request()->query('auto_pdf'))
                <div class="mb-4 p-3.5 rounded-2xl bg-teal-500/10 dark:bg-teal-950/40 border border-teal-500/30 text-teal-800 dark:text-teal-200 text-xs flex items-center gap-2.5 print:hidden">
                    <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-4 p-3.5 rounded-2xl bg-amber-500/10 dark:bg-amber-950/40 border border-amber-500/30 text-amber-800 dark:text-amber-200 text-xs flex items-center gap-2.5 print:hidden">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif
            
            <!-- Slip Sheet (Matches Exact Excel Layout) -->
            <div id="slip-content" class="bg-white text-slate-900 border border-slate-300 shadow-xl rounded-none p-6 font-sans text-xs print:border-none print:shadow-none print:p-0 print:m-0">
                
                <!-- Table Header Metadata -->
                <table class="w-full border-collapse border border-black mb-3">
                    <tbody>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold w-1/3 border-r border-black">Periode</td>
                            <td class="p-1.5 font-bold">: {{ $payroll->period }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">Nama</td>
                            <td class="p-1.5">: {{ $payroll->employee->name }}</td>
                        </tr>
                        @php
                            $creditLine = $payroll->journalEntry?->lines?->firstWhere('credit', '>', 0);
                            $paidAccountName = $creditLine?->account?->name ?? $payroll->assetAccount?->name ?? $payroll->employee?->assetAccount?->name;
                        @endphp
                        <tr class="{{ $payroll->isCs() ? 'border-b border-black' : '' }}">
                            <td class="p-1.5 font-bold border-r border-black">Metode Pembayaran</td>
                            <td class="p-1.5">: {{ $payroll->payment_method }} {{ $paidAccountName ? '('.$paidAccountName.')' : '' }}</td>
                        </tr>
                        @if($payroll->isCs())
                            <tr class="border-b border-black">
                                <td class="p-1.5 font-bold border-r border-black">Jumlah Closing</td>
                                <td class="p-1.5 font-mono">
                                    : {{ $payroll->closing_points }} poin = {{ $payroll->closing_pcs ?: $payroll->closing_points }} pcs (x Rp {{ number_format($payroll->rate_per_point, 0, ',', '.') }})
                                </td>
                            </tr>
                            <tr>
                                <td class="p-1.5 border-r border-black text-[11px] text-slate-600" colspan="2">
                                    @php
                                        $cb = $payroll->closing_breakdown ?: [];
                                    @endphp
                                    (SD = {{ $cb['SD'] ?? 0 }} pcs, FC = {{ $cb['FC'] ?? 0 }} pcs, JK= {{ $cb['JK'] ?? 0 }} pcs, INA = {{ $cb['INA'] ?? 0 }} pcs, BJ = {{ $cb['BJ'] ?? 0 }} pcs, LM = {{ $cb['LM'] ?? 0 }} pcs)
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <!-- Shift & Absensi -->
                <table class="w-full border-collapse border border-black mb-3 text-xs">
                    <tbody>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">
                                Total Shift ({{ $payroll->period_start ? \Carbon\Carbon::parse($payroll->period_start)->translatedFormat('d M') : now()->startOfMonth()->translatedFormat('d M') }} - {{ $payroll->period_end ? \Carbon\Carbon::parse($payroll->period_end)->translatedFormat('d M Y') : now()->endOfMonth()->translatedFormat('d M Y') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-24">{{ $payroll->total_shifts }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">Terlambat</td>
                            <td class="p-1.5 text-right font-mono font-bold w-24 text-rose-600">{{ $payroll->late_count }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">Total Hadir Disiplin</td>
                            <td class="p-1.5 text-right font-mono font-bold w-24">{{ $payroll->discipline_present }}</td>
                        </tr>
                        <tr>
                            <td class="p-1.5 font-bold border-r border-black">Total Kehadiran</td>
                            <td class="p-1.5 text-right font-mono font-bold w-24">{{ $payroll->total_present }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Rincian Honorarium -->
                <table class="w-full border-collapse border border-black mb-3 text-xs">
                    <thead>
                        <tr class="border-b border-black bg-slate-100">
                            <th class="p-1.5 text-left font-bold border-r border-black" colspan="2">Honorarium</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rowNum = 1;
                        @endphp
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                {{ $rowNum++ }}. Honor Utama ({{ $payroll->total_present }} hari x Rp {{ number_format($payroll->daily_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_main_salary }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                {{ $rowNum++ }}. Bonus Disiplin ({{ $payroll->discipline_present }} hari x Rp {{ number_format($payroll->discipline_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_discipline_bonus }}</td>
                        </tr>
                        @if($payroll->isCs())
                            <tr class="border-b border-black">
                                <td class="p-1.5 border-r border-black">
                                    {{ $rowNum++ }}. Bonus Penjualan ({{ $payroll->closing_points }} poin x Rp {{ number_format($payroll->rate_per_point, 0, ',', '.') }})
                                </td>
                                <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_sales_bonus }}</td>
                            </tr>
                        @endif
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                {{ $rowNum++ }}. Bonus Tgl Merah ({{ $payroll->holiday_shifts }} hari x Rp {{ number_format($payroll->holiday_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_holiday_bonus }}</td>
                        </tr>
                        @php
                            $allowancesList = $payroll->allowances_breakdown['allowances'] ?? [];
                            $deductionsList = $payroll->allowances_breakdown['deductions'] ?? [];
                            $baseMainSalary = (float) $payroll->main_salary > 0 
                                ? (float) $payroll->main_salary 
                                : ((float) ($payroll->employee?->base_salary ?? 0) > 0 ? (float) $payroll->employee->base_salary : ((float) ($payroll->daily_rate ?? 100000) * 27));

                            // Auto-heal / fallback jika item persentase masih 0 dari data lama
                            foreach ($allowancesList as &$alwItem) {
                                if (($alwItem['calculation_type'] ?? '') === 'percentage' && (float) ($alwItem['amount'] ?? 0) <= 0) {
                                    $ratePct = (float) ($alwItem['rate_percent'] ?? 0);
                                    $alwItem['amount'] = round(($ratePct / 100) * $baseMainSalary);
                                    $alwItem['formatted_amount'] = 'Rp ' . number_format($alwItem['amount'], 0, ',', '.');
                                }
                            }
                            unset($alwItem);

                            foreach ($deductionsList as &$dedItem) {
                                if (($dedItem['calculation_type'] ?? '') === 'percentage' && (float) ($dedItem['amount'] ?? 0) <= 0) {
                                    $ratePct = (float) ($dedItem['rate_percent'] ?? 0);
                                    $dedItem['amount'] = round(($ratePct / 100) * $baseMainSalary);
                                    $dedItem['formatted_amount'] = 'Rp ' . number_format($dedItem['amount'], 0, ',', '.');
                                }
                            }
                            unset($dedItem);

                            $computedAllowanceTotal = !empty($allowancesList) ? array_sum(array_column($allowancesList, 'amount')) : (float) $payroll->allowance_total;
                            $computedDeductionTotal = !empty($deductionsList) ? array_sum(array_column($deductionsList, 'amount')) : (float) $payroll->deduction_total;

                            $salesBonusAmount = $payroll->isCs() ? (float) $payroll->sales_bonus : 0.0;
                            $grossTotal = (float) $payroll->main_salary + (float) $payroll->discipline_bonus + $salesBonusAmount + (float) $payroll->holiday_bonus + $computedAllowanceTotal;
                            $computedTHP = max(0, $grossTotal - $computedDeductionTotal);
                        @endphp

                        @if(!empty($allowancesList))
                            @foreach($allowancesList as $idx => $alw)
                                <tr class="border-b border-black">
                                    <td class="p-1.5 border-r border-black">
                                        {{ $rowNum++ }}. Tunjangan: {{ $alw['name'] }} {{ !empty($alw['rate_label']) ? $alw['rate_label'] : '' }}
                                    </td>
                                    <td class="p-1.5 text-right font-mono font-bold w-32">+ Rp {{ number_format((float) ($alw['amount'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @elseif($computedAllowanceTotal > 0)
                            <tr class="border-b border-black">
                                <td class="p-1.5 border-r border-black">
                                    {{ $rowNum++ }}. Tunjangan Tambahan
                                </td>
                                <td class="p-1.5 text-right font-mono font-bold w-32">+ Rp {{ number_format($computedAllowanceTotal, 0, ',', '.') }}</td>
                            </tr>
                        @endif

                        <tr class="border-b-2 border-black bg-slate-50 font-bold">
                            <td class="p-1.5 border-r border-black uppercase">SUBTOTAL PENGHASILAN KOTOR</td>
                            <td class="p-1.5 text-right font-mono">Rp {{ number_format($grossTotal, 0, ',', '.') }}</td>
                        </tr>

                        @if(!empty($deductionsList))
                            <tr class="border-b border-black bg-rose-50/70">
                                <th class="p-1.5 text-left font-bold border-r border-black text-rose-900" colspan="2">Potongan Gaji</th>
                            </tr>
                            @foreach($deductionsList as $dIdx => $ded)
                                <tr class="border-b border-black text-rose-950">
                                    <td class="p-1.5 border-r border-black pl-3">
                                        - {{ $ded['name'] }} {{ !empty($ded['rate_label']) ? $ded['rate_label'] : '' }}
                                    </td>
                                    <td class="p-1.5 text-right font-mono font-bold w-32 text-rose-700">- Rp {{ number_format((float) ($ded['amount'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-b-2 border-black bg-rose-100/60 font-bold text-rose-900">
                                <td class="p-1.5 border-r border-black uppercase">TOTAL POTONGAN</td>
                                <td class="p-1.5 text-right font-mono">- Rp {{ number_format($computedDeductionTotal, 0, ',', '.') }}</td>
                            </tr>
                        @elseif($computedDeductionTotal > 0)
                            <tr class="border-b border-black bg-rose-50/70">
                                <th class="p-1.5 text-left font-bold border-r border-black text-rose-900" colspan="2">Potongan Gaji</th>
                            </tr>
                            <tr class="border-b-2 border-black bg-rose-100/60 font-bold text-rose-900">
                                <td class="p-1.5 border-r border-black uppercase">TOTAL POTONGAN</td>
                                <td class="p-1.5 text-right font-mono">- Rp {{ number_format($computedDeductionTotal, 0, ',', '.') }}</td>
                            </tr>
                        @endif

                        <tr class="bg-emerald-300 font-black text-slate-900 border-b border-black">
                            <td class="p-2 border-r border-black uppercase text-sm">Total Take Home Pay (Gaji Bersih)</td>
                            <td class="p-2 text-right font-mono text-sm">Rp {{ number_format($computedTHP, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Footer Note & Dokumen (Tanpa tanda tangan, nama terang, dan cap) -->
                <div class="border border-black p-3">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 text-xs">
                        <div class="flex-1">
                            <span class="font-bold text-[10px] text-slate-700 uppercase">Note / Catatan :</span>
                            <div class="text-[11px] italic text-slate-800 mt-0.5">
                                {{ $payroll->notes ?: '-' }}
                            </div>
                        </div>
                        <div class="text-right text-[10px] text-slate-600 font-mono shrink-0 space-y-0.5">
                            <div>Yogyakarta, {{ $payroll->paid_at ? $payroll->paid_at->format('Y-m-d') : now()->format('Y-m-d') }}</div>
                            <div>Ref: {{ $payroll->journalEntry?->reference ?? '-' }}</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- html2pdf Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <script>
        function downloadSlipPdf(auto = false) {
            const element = document.getElementById('slip-content');
            const btnText = document.getElementById('btn-download-text');
            const originalText = btnText ? btnText.innerText : 'Unduh Slip PDF';
            
            if (btnText) {
                btnText.innerText = 'Membuat PDF...';
            }

            const cleanFileName = 'Slip_Gaji_{{ \Illuminate\Support\Str::slug($payroll->employee->name) }}_{{ \Illuminate\Support\Str::slug($payroll->period) }}.pdf';

            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     cleanFileName,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            if (typeof html2pdf !== 'undefined') {
                html2pdf().set(opt).from(element).save().then(() => {
                    if (btnText) {
                        btnText.innerText = '✅ PDF Terunduh';
                        setTimeout(() => { btnText.innerText = originalText; }, 2500);
                    }
                }).catch(err => {
                    console.error('Gagal generate PDF via html2pdf:', err);
                    if (btnText) btnText.innerText = originalText;
                    if (auto) {
                        window.print();
                    }
                });
            } else {
                // Fallback browser print jika html2pdf gagal dimuat
                window.print();
                if (btnText) btnText.innerText = originalText;
            }
        }

        // Pemicu otomatis begitu halaman dibuka dari proses pembayaran gaji
        document.addEventListener('DOMContentLoaded', function () {
            @if(request()->query('auto_pdf'))
                setTimeout(function () {
                    downloadSlipPdf(true);
                }, 600);
            @endif
        });
    </script>

    <!-- Print CSS -->
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            nav, header, .x-slot, button, a, #auto-pdf-status-banner {
                display: none !important;
            }
            #slip-content {
                border: 1px solid black !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
            }
        }
    </style>
</x-app-layout>
