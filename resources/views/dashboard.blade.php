<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ikhtisar Keuangan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Cards Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1: Saldo -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wider">Total Saldo Kas</p>
                            <h3 class="text-3xl font-bold mt-2">Rp {{ number_format($totalKas ?? 0, 0, ',', '.') }}</h3>
                        </div>
                        <div class="p-3 bg-white/20 rounded-xl">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Pemasukan -->
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-emerald-100 text-sm font-medium uppercase tracking-wider">Pemasukan (Bulan Ini)</p>
                            <h3 class="text-3xl font-bold mt-2">Rp {{ number_format($pemasukanBulanIni ?? 0, 0, ',', '.') }}</h3>
                        </div>
                        <div class="p-3 bg-white/20 rounded-xl">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Pengeluaran -->
                <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-2xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-rose-100 text-sm font-medium uppercase tracking-wider">Pengeluaran (Bulan Ini)</p>
                            <h3 class="text-3xl font-bold mt-2">Rp {{ number_format($pengeluaranBulanIni ?? 0, 0, ',', '.') }}</h3>
                        </div>
                        <div class="p-3 bg-white/20 rounded-xl">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Transaksi Pending -->
                <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-amber-100 text-sm font-medium uppercase tracking-wider">Perlu Verifikasi (n8n)</p>
                            <h3 class="text-3xl font-bold mt-2">{{ $pendingCount ?? 0 }} Trx</h3>
                        </div>
                        <div class="p-3 bg-white/20 rounded-xl">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 dark:border-gray-700 mt-8">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Transaksi Terakhir</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Sinkronisasi otomatis dengan Bot Telegram via n8n</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">
                                <th class="px-6 py-4 font-medium">Tanggal</th>
                                <th class="px-6 py-4 font-medium">Deskripsi</th>
                                <th class="px-6 py-4 font-medium">Kategori / Akun</th>
                                <th class="px-6 py-4 font-medium">Sumber</th>
                                <th class="px-6 py-4 font-medium text-right">Nominal</th>
                                <th class="px-6 py-4 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($transactions as $trx)
                                @php
                                    $firstLine = $trx->lines->first();
                                    $expenseLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'expense');
                                    $revenueLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'revenue');
                                    $isExpense = (bool)$expenseLine;
                                    $amount = $isExpense 
                                        ? ($expenseLine->debit ?? 0) 
                                        : ($revenueLine->credit ?? $trx->lines->sum('debit'));
                                    $accountName = $isExpense 
                                        ? ($expenseLine->account->name ?? 'Beban') 
                                        : ($revenueLine->account->name ?? ($firstLine->account->name ?? '-'));
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                                        {{ $trx->date ? \Carbon\Carbon::parse($trx->date)->translatedFormat('d M Y, H:i') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300 font-medium">
                                        {{ $trx->description }}
                                        <div class="text-xs text-gray-400 font-normal">{{ $trx->reference }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $accountName }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $trx->source === 'telegram' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ ucfirst($trx->source ?? 'system') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right {{ $isExpense ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        {{ $isExpense ? '-' : '+' }} Rp {{ number_format($amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($trx->status === 'verified')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Verified</span>
                                        @elseif($trx->status === 'rejected')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada transaksi tercatat. Kirim pesan transaksi melalui Bot Telegram untuk melihatnya di sini!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
