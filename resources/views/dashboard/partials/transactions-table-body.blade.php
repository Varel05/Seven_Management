@forelse($transactions as $trx)
    @php
        $firstLine = $trx->lines->first();
        $expenseLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'expense');
        $revenueLine = $trx->lines->first(fn($l) => $l->account && $l->account->type === 'revenue');
        $isExpense = (bool)$expenseLine;
        $amount = $isExpense 
            ? ($expenseLine->debit ?? 0) 
            : ($revenueLine->credit ?? $trx->lines->sum('debit'));
        $primaryAccount = $isExpense 
            ? ($expenseLine->account->name ?? 'Beban Operasional') 
            : ($revenueLine->account->name ?? ($firstLine->account->name ?? '-'));
        $primaryAccountCode = $isExpense 
            ? ($expenseLine->account->code ?? '5001') 
            : ($revenueLine->account->code ?? ($firstLine->account->code ?? '1001'));
        
        // Json data for modal
        $trxJson = json_encode([
            'id' => $trx->id,
            'reference' => $trx->reference,
            'description' => $trx->description,
            'date' => $trx->date ? \Carbon\Carbon::parse($trx->date)->translatedFormat('d F Y, H:i') : '-',
            'source' => $trx->source,
            'status' => $trx->status,
            'amount' => $amount,
            'isExpense' => $isExpense,
            'lines' => $trx->lines->map(function($line) {
                return [
                    'account_code' => $line->account->code ?? '-',
                    'account_name' => $line->account->name ?? 'Akun',
                    'account_type' => $line->account->type ?? '-',
                    'description'  => $line->description,
                    'debit'        => (float)$line->debit,
                    'credit'       => (float)$line->credit,
                ];
            })
        ]);
    @endphp
    <tr 
        x-show="isRowVisible({{ $trx->id }})"
        class="hover:bg-emerald-50/40 dark:hover:bg-slate-800/40 transition-colors group"
    >
        <!-- Tanggal -->
        <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
            <div class="font-semibold text-slate-900 dark:text-slate-200">
                {{ $trx->date ? \Carbon\Carbon::parse($trx->date)->translatedFormat('d M Y') : '-' }}
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ $trx->date ? \Carbon\Carbon::parse($trx->date)->format('H:i') : '' }} WIB
            </div>
        </td>

        <!-- Referensi -->
        <td class="px-4 py-3.5 whitespace-nowrap overflow-hidden max-w-[150px] lg:max-w-[180px]">
            <span 
                class="inline-block max-w-full truncate font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 align-middle"
                title="{{ $trx->reference ?? '-' }}"
            >
                {{ $trx->reference ?? '-' }}
            </span>
        </td>

        <!-- Keterangan Transaksi -->
        <td class="px-4 py-3.5 text-slate-900 dark:text-slate-100 font-medium min-w-0">
            <div class="text-sm font-semibold truncate" title="{{ $trx->description }}">
                {{ $trx->description }}
            </div>
        </td>

        <!-- Akun Terkait -->
        <td class="px-4 py-3.5 whitespace-nowrap text-xs">
            <div class="inline-flex items-center gap-1.5 max-w-full">
                <span class="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                    {{ $primaryAccountCode }}
                </span>
                <span class="truncate text-slate-700 dark:text-slate-300 font-medium" title="{{ $primaryAccount }}">
                    {{ $primaryAccount }}
                </span>
            </div>
        </td>

        <!-- Sumber -->
        <td class="px-4 py-3.5 whitespace-nowrap text-xs">
            @if(str_starts_with(strtolower($trx->source ?? ''), 'telegram'))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-300/80 dark:border-sky-800 shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-sky-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                    Telegram
                </span>
            @elseif(str_starts_with(strtolower($trx->source ?? ''), 'web'))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/15 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-300/80 dark:border-indigo-800 shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                    Website
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    {{ ucfirst($trx->source ?? 'Manual') }}
                </span>
            @endif
        </td>

        <!-- Nominal -->
        <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-extrabold text-sm">
            @if($isExpense)
                <span class="inline-block px-2 py-0.5 rounded-lg text-rose-700 dark:text-rose-400 bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-900/50 shadow-2xs">
                    - Rp {{ number_format($amount, 0, ',', '.') }}
                </span>
            @else
                <span class="inline-block px-2 py-0.5 rounded-lg text-emerald-700 dark:text-emerald-400 bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-900/50 shadow-2xs">
                    + Rp {{ number_format($amount, 0, ',', '.') }}
                </span>
            @endif
        </td>

        <!-- Status -->
        <td class="px-4 py-3.5 whitespace-nowrap text-center text-xs">
            @if($trx->status === 'verified')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/80 dark:border-emerald-800">
                    ✓ Verified
                </span>
            @elseif($trx->status === 'rejected')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300/80 dark:border-rose-800">
                    ✗ Rejected
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/80 dark:border-amber-800 animate-pulse">
                    ⏳ Pending
                </span>
            @endif
        </td>

        <!-- Aksi / Audit Button -->
        <td class="px-4 py-3.5 whitespace-nowrap text-center text-xs">
            <button 
                @click='openModal({!! $trxJson !!})' 
                type="button" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 hover:text-emerald-700 bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 hover:border-emerald-300 dark:border-slate-700 shadow-xs transition-all"
                title="Buka rincian debit-kredit jurnal"
            >
                <svg class="w-3.5 h-3.5 text-slate-500 group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                Audit
            </button>
        </td>
    </tr>
@empty
    <!-- Kasus 1: Database Kosong (Belum ada data di database) -->
    <tr>
        <td colspan="8" class="px-6 py-14 text-center">
            <div class="max-w-md mx-auto flex flex-col items-center">
                <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-3.5 border border-amber-200/80 dark:border-amber-800/60 shadow-xs">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Data Tidak Ditemukan di Database</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                    Belum ada mutasi transaksi yang tersimpan di dalam database buku besar. Silakan kirim transaksi melalui Bot Telegram atau webhook n8n untuk mulai mencatat.
                </p>
            </div>
        </td>
    </tr>
@endforelse

<!-- Kasus 2: Data Ada di Database, tetapi Tidak Ditemukan pada Filter / Pencarian Tertentu -->
@if(count($transactions) > 0)
    <tr x-show="visibleCount === 0" x-cloak>
        <td colspan="8" class="px-6 py-12 text-center">
            <div class="max-w-md mx-auto flex flex-col items-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mb-3 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Data Tidak Ditemukan</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Tidak ditemukan transaksi yang cocok dengan kata kunci atau filter status yang dipilih.
                </p>
                <button 
                    type="button" 
                    @click="filterStatus = 'all'; searchQuery = ''" 
                    class="mt-3.5 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition-colors shadow-2xs"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reset Filter & Pencarian
                </button>
            </div>
        </td>
    </tr>
@endif
