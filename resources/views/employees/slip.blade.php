<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('employees.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h2 class="text-xl font-black text-white">Slip Gaji Karyawan</h2>
                    <p class="text-xs text-slate-400">Periode: {{ $payroll->period }} ({{ $payroll->employee->name }})</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak / Simpan PDF</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">
            
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
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">Metode Pembayaran</td>
                            <td class="p-1.5">: {{ $payroll->payment_method }} {{ $payroll->employee->assetAccount ? '('.$payroll->employee->assetAccount->name.')' : '' }}</td>
                        </tr>
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
                    </tbody>
                </table>

                <!-- Shift & Absensi -->
                <table class="w-full border-collapse border border-black mb-3 text-xs">
                    <tbody>
                        <tr class="border-b border-black">
                            <td class="p-1.5 font-bold border-r border-black">
                                Total Shift ({{ $payroll->period_start ? \Carbon\Carbon::parse($payroll->period_start)->translatedFormat('d M') : '26' }} - {{ $payroll->period_end ? \Carbon\Carbon::parse($payroll->period_end)->translatedFormat('d M Y') : '25' }})
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
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                1. Honor Utama ({{ $payroll->total_present }} hari x Rp {{ number_format($payroll->daily_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_main_salary }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                2. Bonus Disiplin ({{ $payroll->discipline_present }} hari x Rp {{ number_format($payroll->discipline_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_discipline_bonus }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                3. Bonus Penjualan ({{ $payroll->closing_points }} poin x Rp {{ number_format($payroll->rate_per_point, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_sales_bonus }}</td>
                        </tr>
                        <tr class="border-b border-black">
                            <td class="p-1.5 border-r border-black">
                                4. Bonus Tgl Merah ({{ $payroll->holiday_shifts }} hari x Rp {{ number_format($payroll->holiday_rate, 0, ',', '.') }})
                            </td>
                            <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_holiday_bonus }}</td>
                        </tr>
                        @if($payroll->allowance_total > 0)
                            <tr class="border-b border-black">
                                <td class="p-1.5 border-r border-black">
                                    5. Tunjangan Tambahan
                                </td>
                                <td class="p-1.5 text-right font-mono font-bold w-32">{{ $payroll->formatted_allowance_total }}</td>
                            </tr>
                        @endif
                        <tr class="border-b-2 border-black bg-slate-50 font-bold">
                            <td class="p-1.5 border-r border-black uppercase">TOTAL HONOR</td>
                            <td class="p-1.5 text-right font-mono">{{ $payroll->formatted_take_home_pay }}</td>
                        </tr>
                        <tr class="bg-emerald-300 font-black text-slate-900 border-b border-black">
                            <td class="p-2 border-r border-black uppercase text-sm">Total Take Home Pay</td>
                            <td class="p-2 text-right font-mono text-sm">{{ $payroll->formatted_take_home_pay }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Footer Tanda Tangan & Note -->
                <div class="grid grid-cols-2 gap-4 pt-2 border border-black p-3">
                    <div>
                        <div class="text-[11px] text-slate-600">
                            Yogyakarta, {{ $payroll->paid_at ? $payroll->paid_at->format('Y-m-d') : now()->format('Y-m-d') }}
                        </div>
                        <div class="h-14"></div>
                        <div class="font-bold underline text-xs">{{ $payroll->hrd_name ?: 'Ari Husbana' }}</div>
                        <div class="text-[10px] text-slate-600 font-semibold">HRD Seven Management</div>
                    </div>
                    <div class="border border-black p-2 min-h-[90px] flex flex-col justify-between">
                        <span class="font-bold text-[10px] text-slate-700">Note :</span>
                        <div class="text-[11px] italic text-slate-800">
                            {{ $payroll->notes ?: '-' }}
                        </div>
                        <div class="text-[9px] text-slate-500 font-mono text-right">
                            Ref: {{ $payroll->journalEntry?->reference ?? '-' }}
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Print CSS -->
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            nav, header, .x-slot, button, a {
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
