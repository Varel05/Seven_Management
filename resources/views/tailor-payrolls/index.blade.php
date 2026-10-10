<x-app-layout>
    <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-200 font-medium">Produksi & HPP</span>
                    <span>/</span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Upah Penjahit</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </span>
                    <span>Slip Upah Penjahit Borongan</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Perhitungan upah borongan per pcs (piece-rate) terintegrasi tarif HPP, rincian kasbon, dan jurnal otomatis
                </p>
            </div>

            <!-- Action Button: Buat Slip Upah Baru -->
            <div class="flex items-center gap-2">
                <a href="{{ route('tailor-payrolls.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:brightness-110 text-white font-bold text-xs shadow-md shadow-amber-500/20 transition-all hover:scale-102">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Buat Slip Upah Baru</span>
                </a>
            </div>
        </div>

        <!-- Alert Notification -->
        @if (session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Summary Statistics Cards (Bulan Ini) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Total Upah Kotor Bulan Ini -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Total Upah Kotor</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">Bulan Ini</span>
                </div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">
                    Rp {{ number_format($totalWagesThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Beban tenaga kerja jahit langsung
                </div>
            </div>

            <!-- 2. Total Kasbon Dipotong -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Total Kasbon Dipotong</span>
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">Potongan</span>
                </div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-2">
                    Rp {{ number_format($totalBonThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Pengembalian pinjaman bon
                </div>
            </div>

            <!-- 3. Take Home Pay Bersih -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Take Home Pay</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">Kas Keluar</span>
                </div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2">
                    Rp {{ number_format($totalTakeHomePayThisMonth, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Total bersih dibayarkan ke penjahit
                </div>
            </div>

            <!-- 4. Total Output Jahit (Pcs) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-bold uppercase tracking-wider">Output Pakaian</span>
                    <span class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">Produksi</span>
                </div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">
                    {{ number_format($totalPiecesThisMonth, 0, ',', '.') }} <span class="text-sm font-normal text-slate-400">pcs</span>
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Total jas, vest, celana & revisi
                </div>
            </div>
        </div>

        <!-- Master Tarif HPP Upah Penjahit (Direct Labor) Widget -->
        <div class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/60 rounded-2xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Tarif Standar Upah Penjahit di HPP (Direct Labor)
                    </h2>
                </div>
                <a href="{{ route('materials.index') }}" class="text-xs font-semibold text-amber-700 dark:text-amber-400 hover:underline flex items-center gap-1">
                    <span>Kelola di Bahan Baku & HPP</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                @foreach ($laborMaterials as $lm)
                    <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-amber-200/60 dark:border-slate-800 shadow-2xs">
                        <span class="text-[10px] font-mono text-slate-400 block">{{ $lm->code }}</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100 block truncate" title="{{ $lm->name }}">
                            {{ str_replace(['Upah Penjahit ', 'Biaya '], '', $lm->name) }}
                        </span>
                        <span class="text-xs font-extrabold text-amber-600 dark:text-amber-400 mt-1 block">
                            {{ $lm->formatted_standard_cost }} <span class="text-[9px] font-normal text-slate-400">/{{ $lm->unit }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Daftar Slip Upah Penjahit -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    Riwayat Slip Upah Penjahit
                </h2>

                <!-- Filter / Search Form -->
                <form method="GET" action="{{ route('tailor-payrolls.index') }}" class="flex items-center gap-2">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Cari nama penjahit..." 
                        class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 w-48"
                    />
                    <input 
                        type="month" 
                        name="month" 
                        value="{{ request('month') }}" 
                        class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    />
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors">
                        Filter
                    </button>
                    @if (request()->hasAny(['search', 'month']))
                        <a href="{{ route('tailor-payrolls.index') }}" class="text-xs text-rose-500 hover:underline">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-black tracking-wider text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4">Tanggal / Hari</th>
                            <th class="py-3.5 px-4">Nama Penjahit</th>
                            <th class="py-3.5 px-4 text-center">Output (Pcs)</th>
                            <th class="py-3.5 px-4 text-right">Total Gaji</th>
                            <th class="py-3.5 px-4 text-right">Potongan Bon</th>
                            <th class="py-3.5 px-4 text-right">Take Home Pay</th>
                            <th class="py-3.5 px-4 text-center">Metode / Jurnal</th>
                            <th class="py-3.5 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($payrolls as $p)
                            <tr class="hover:bg-amber-50/30 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $p->period_label ?: $p->payroll_date->translatedFormat('l, j F Y') }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $p->payroll_date->format('d/m/Y') }}</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                                        {{ $p->tailor_name }}
                                    </div>
                                    @if ($p->employee)
                                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">Karyawan Terdaftar</span>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-medium">Penjahit Borongan</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $p->total_pieces }} pcs
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                    {{ $p->formatted_total_wage }}
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    @if ($p->total_bon > 0)
                                        <span class="font-bold text-rose-600 dark:text-rose-400">
                                            -{{ $p->formatted_total_bon }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <span class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-1 rounded-lg border border-emerald-200/80 dark:border-emerald-800">
                                        {{ $p->formatted_take_home_pay }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 block">
                                        {{ $p->payment_method }}
                                    </span>
                                    @if ($p->journalEntry)
                                        <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 block" title="Ref Jurnal">
                                            {{ $p->journalEntry->reference }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="{{ route('tailor-payrolls.show', $p) }}" 
                                           class="p-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 transition-colors"
                                           title="Lihat & Cetak Slip">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </a>

                                        <form method="POST" action="{{ route('tailor-payrolls.destroy', $p) }}" 
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus slip upah penjahit ini beserta jurnalnya?');" 
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="p-1.5 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 transition-colors"
                                                    title="Hapus Slip">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-10 text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="text-xs">Belum ada slip upah penjahit yang dibuat.</p>
                                        <a href="{{ route('tailor-payrolls.create') }}" class="text-xs text-amber-600 dark:text-amber-400 font-bold hover:underline">
                                            + Buat Slip Upah Pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($payrolls->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                    {{ $payrolls->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
