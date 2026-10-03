<x-app-layout>
    <div class="py-6 sm:py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Top Navigation & Action Buttons (Hidden when printing) -->
        <div class="flex items-center justify-between print:hidden">
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <a href="{{ route('tailor-payrolls.index') }}" class="hover:text-amber-600">Upah Penjahit</a>
                <span>/</span>
                <span class="text-amber-600 font-semibold">Slip Upah #{{ $tailorPayroll->id }}</span>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('tailor-payrolls.index') }}" 
                   class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    ← Daftar Slip
                </a>
                <a href="{{ route('tailor-payrolls.create') }}" 
                   class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 transition-colors">
                    + Buat Baru
                </a>
                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black text-xs shadow-md shadow-amber-500/20 transition-all flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Cetak Slip Upah</span>
                </button>
            </div>
        </div>

        <!-- LEMBAR SLIP UPAH RESMI (FORMAT PERSIS EXCEL ASLI) -->
        <div class="bg-white text-slate-900 border-2 border-slate-800 rounded-2xl shadow-xl p-6 sm:p-8 font-sans print:border-none print:shadow-none print:p-0 print:m-0" id="print-area">
            
            <!-- Tanggal di Atas Kiri -->
            <div class="text-sm font-bold text-slate-900 mb-2">
                {{ $tailorPayroll->period_label ?: $tailorPayroll->payroll_date->translatedFormat('l, j F Y') }}
            </div>

            <!-- Banner Kuning Header Penjahit -->
            <div class="bg-amber-400 border-2 border-slate-800 text-center py-3 px-4 mb-0 text-slate-950 font-black text-2xl tracking-wide">
                {{ $tailorPayroll->tailor_name }}
            </div>

            <!-- Tabel Utama: Rincian Upah Borongan Pcs -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-2 border-t-0 border-slate-800 border-collapse">
                    <thead>
                        <tr class="border-b-2 border-slate-800 bg-white font-bold text-slate-900 text-center">
                            <th class="py-2.5 px-3 border-r-2 border-slate-800 text-left w-6/12">Keterangan</th>
                            <th class="py-2.5 px-3 border-r-2 border-slate-800 text-center w-2/12">Jumlah</th>
                            <th class="py-2.5 px-3 border-r-2 border-slate-800 text-right w-2/12">Biaya per pcs</th>
                            <th class="py-2.5 px-3 text-right w-2/12">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y border-b-2 border-slate-800 divide-slate-800">
                        @foreach ($tailorPayroll->items as $item)
                            <tr class="text-slate-900 font-semibold">
                                <td class="py-2 px-3 border-r-2 border-slate-800">
                                    {{ $item->item_name }}
                                </td>
                                <td class="py-2 px-3 border-r-2 border-slate-800 text-center font-bold">
                                    {{ $item->quantity }}
                                </td>
                                <td class="py-2 px-3 border-r-2 border-slate-800 text-right font-mono">
                                    {{ number_format($item->rate_per_piece, 0, ',', '.') }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold">
                                    {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach

                        <!-- TOTAL GAJI ROW -->
                        <tr class="border-t-2 border-slate-800 font-black bg-white">
                            <td class="py-2.5 px-3 border-r-2 border-slate-800 text-center text-sm">
                                Total Gaji
                            </td>
                            <td class="py-2.5 px-3 border-r-2 border-slate-800 text-center text-sm">
                                {{ $tailorPayroll->total_pieces }}
                            </td>
                            <td class="py-2.5 px-3 border-r-2 border-slate-800"></td>
                            <td class="py-2.5 px-3 text-right font-mono text-sm">
                                {{ number_format($tailorPayroll->total_wage, 0, ',', '.') }}
                            </td>
                        </tr>

                        <!-- JUMLAH BON ROW -->
                        <tr class="border-t-2 border-slate-800 font-bold">
                            <td class="py-2 px-3 border-r-2 border-slate-800">
                                Jumlah bon
                            </td>
                            <td class="py-2 px-3 border-r-2 border-slate-800"></td>
                            <td class="py-2 px-3 border-r-2 border-slate-800"></td>
                            <td class="py-2 px-3 text-right font-mono font-bold text-rose-700">
                                {{ number_format($tailorPayroll->total_bon, 0, ',', '.') }}
                            </td>
                        </tr>

                        <!-- TAKE HOMEPAY ROW (HIGHLIGHT KUNING EMAS) -->
                        <tr class="border-t-2 border-slate-800 bg-amber-400 text-slate-950 font-black text-sm">
                            <td class="py-2.5 px-4 border-r-2 border-slate-800 text-center uppercase tracking-wider">
                                Take homepay
                            </td>
                            <td class="py-2.5 px-3 border-r-2 border-slate-800"></td>
                            <td class="py-2.5 px-3 border-r-2 border-slate-800"></td>
                            <td class="py-2.5 px-3 text-right font-mono text-base">
                                {{ number_format($tailorPayroll->take_home_pay, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tabel Pendukung: Rincian Kasbon / Bon di Bawah Kanan -->
            <div class="mt-6 flex justify-end">
                <div class="w-full sm:w-7/12">
                    <table class="w-full text-left text-xs border-2 border-slate-800 border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-800 bg-slate-100 font-bold text-slate-900 text-center">
                                <th class="py-1.5 px-3 border-r-2 border-slate-800 text-center w-4/12">Tanggal</th>
                                <th class="py-1.5 px-3 border-r-2 border-slate-800 text-left w-5/12">Keterangan</th>
                                <th class="py-1.5 px-3 text-right w-3/12">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 border-b-2 border-slate-800">
                            @forelse ($tailorPayroll->advances as $adv)
                                <tr class="text-slate-900">
                                    <td class="py-1.5 px-3 border-r-2 border-slate-800 text-center font-mono">
                                        {{ $adv->advance_date->format('d/m/Y') }}
                                    </td>
                                    <td class="py-1.5 px-3 border-r-2 border-slate-800">
                                        {{ $adv->description }}
                                    </td>
                                    <td class="py-1.5 px-3 text-right font-mono font-semibold">
                                        {{ number_format($adv->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-1.5 px-3 border-r-2 border-slate-800 text-center font-mono">&nbsp;</td>
                                    <td class="py-1.5 px-3 border-r-2 border-slate-800 text-center text-slate-400 italic">Tidak ada kasbon</td>
                                    <td class="py-1.5 px-3 text-right font-mono">-</td>
                                </tr>
                            @endforelse
                            <!-- Total Baris Bon -->
                            <tr class="font-bold bg-slate-50 border-t-2 border-slate-800">
                                <td colspan="2" class="py-1.5 px-3 border-r-2 border-slate-800 text-right">Total</td>
                                <td class="py-1.5 px-3 text-right font-mono font-bold">
                                    {{ $tailorPayroll->total_bon > 0 ? number_format($tailorPayroll->total_bon, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tanda Tangan & Keterangan Pengesahan -->
            <div class="mt-12 pt-6 border-t border-slate-300 grid grid-cols-3 gap-4 text-center text-xs text-slate-800">
                <div>
                    <p class="font-bold text-slate-600 mb-14">Penerima (Penjahit)</p>
                    <p class="font-extrabold text-slate-900 border-t border-slate-400 inline-block px-6 pt-1">
                        {{ $tailorPayroll->tailor_name }}
                    </p>
                </div>
                <div>
                    <p class="font-bold text-slate-600 mb-14">Kasir / Keuangan</p>
                    <p class="font-extrabold text-slate-900 border-t border-slate-400 inline-block px-6 pt-1">
                        Maya Anggraini
                    </p>
                </div>
                <div>
                    <p class="font-bold text-slate-600 mb-14">Mengetahui (Owner)</p>
                    <p class="font-extrabold text-slate-900 border-t border-slate-400 inline-block px-6 pt-1">
                        Owner Seven Management
                    </p>
                </div>
            </div>

            <!-- Catatan Jurnal & Info Sistem Bawah -->
            <div class="mt-8 pt-3 border-t border-dashed border-slate-200 text-[10px] text-slate-400 flex items-center justify-between">
                <div>
                    Metode: <strong class="text-slate-600">{{ $tailorPayroll->payment_method }}</strong>
                    @if ($tailorPayroll->journalEntry)
                        • Ref Jurnal: <strong class="text-emerald-700 font-mono">{{ $tailorPayroll->journalEntry->reference }}</strong>
                    @endif
                </div>
                <div>
                    SevenLedger System • Dicetak pada {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>

    </div>

    <!-- Print Specific Styling -->
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            header, aside, nav, footer, button, a {
                display: none !important;
            }
            #print-area {
                border: 2px solid black !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10px !important;
                width: 100% !important;
            }
        }
    </style>
</x-app-layout>
