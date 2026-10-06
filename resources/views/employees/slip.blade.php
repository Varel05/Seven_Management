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
                <button 
                    type="button" 
                    id="btn-download-pdf"
                    onclick="downloadSlipPdf()" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 text-xs font-black shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer print:hidden"
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

            <!-- Banner Pemberitahuan Pembuatan PDF Otomatis -->
            @if(request()->query('auto_pdf'))
                <div id="auto-pdf-status-banner" class="mb-4 p-4 rounded-2xl bg-emerald-500/15 dark:bg-emerald-950/60 border border-emerald-500/40 text-emerald-900 dark:text-emerald-200 flex items-center justify-between text-xs print:hidden shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-black shrink-0 shadow-xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <strong class="font-extrabold text-sm block text-emerald-950 dark:text-emerald-100">Slip PDF Otomatis Dibuat!</strong>
                            <span class="text-emerald-800 dark:text-emerald-300">
                                Berkas PDF slip gaji sedang di-generate & diunduh otomatis. Jika belum terunduh, klik tombol <strong>Unduh Slip PDF</strong> di atas.
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
