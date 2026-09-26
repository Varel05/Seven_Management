<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecurringTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'last_notified_at' => 'datetime',
        'last_posted_at' => 'datetime',
    ];

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Memeriksa apakah pengeluaran berulang ini sudah dibukukan/dibayar pada periode berjalan.
     * Melakukan pengecekan ganda: kolom last_posted_at dan keberadaan data di tabel journal_entries.
     */
    public function isPaidForCurrentPeriod(): bool
    {
        $today = now();

        if ($this->frequency === 'monthly') {
            if ($this->last_posted_at && $this->last_posted_at->isCurrentMonth() && $this->last_posted_at->isCurrentYear()) {
                return true;
            }

            return JournalEntry::where(function ($q) {
                $q->where('description', 'LIKE', "Pengeluaran Rutin: {$this->name}%")
                    ->orWhere('description', 'LIKE', "%{$this->name}%");
            })
                ->whereYear('date', $today->year)
                ->whereMonth('date', $today->month)
                ->where('status', '!=', 'rejected')
                ->exists();
        }

        if ($this->frequency === 'yearly') {
            if ($this->last_posted_at && $this->last_posted_at->isCurrentYear()) {
                return true;
            }

            return JournalEntry::where(function ($q) {
                $q->where('description', 'LIKE', "Pengeluaran Rutin: {$this->name}%")
                    ->orWhere('description', 'LIKE', "%{$this->name}%");
            })
                ->whereYear('date', $today->year)
                ->where('status', '!=', 'rejected')
                ->exists();
        }

        if ($this->frequency === 'weekly') {
            if ($this->last_posted_at && $this->last_posted_at->isCurrentWeek() && $this->last_posted_at->isCurrentYear()) {
                return true;
            }

            return JournalEntry::where(function ($q) {
                $q->where('description', 'LIKE', "Pengeluaran Rutin: {$this->name}%")
                    ->orWhere('description', 'LIKE', "%{$this->name}%");
            })
                ->whereBetween('date', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()])
                ->where('status', '!=', 'rejected')
                ->exists();
        }

        return false;
    }

    /**
     * Mencari entri jurnal buku besar yang sudah ada di database untuk periode berjalan.
     */
    public function findExistingCurrentPeriodJournal(): ?JournalEntry
    {
        $today = now();

        $query = JournalEntry::where(function ($q) {
            $q->where('description', 'LIKE', "Pengeluaran Rutin: {$this->name}%")
                ->orWhere('description', 'LIKE', "%{$this->name}%");
        })->where('status', '!=', 'rejected');

        if ($this->frequency === 'monthly') {
            $query->whereYear('date', $today->year)
                ->whereMonth('date', $today->month);
        } elseif ($this->frequency === 'yearly') {
            $query->whereYear('date', $today->year);
        } elseif ($this->frequency === 'weekly') {
            $query->whereBetween('date', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()]);
        }

        return $query->latest('date')->first();
    }

    /**
     * Memeriksa apakah transaksi berulang ini jatuh tempo hari ini dan belum dibukukan pada periode berjalan.
     */
    public function isDueToday(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = now();
        $targetDay = (int) $this->day_of_month;
        $currentDay = (int) $today->day;
        $daysInMonth = (int) $today->daysInMonth;

        // Tangani kasus jika target tanggal 31 tapi bulan ini hanya sampai tanggal 28/30
        $effectiveDay = min($targetDay, $daysInMonth);

        if ($this->frequency === 'monthly') {
            $isMatchingDay = ($currentDay === $effectiveDay);

            return $isMatchingDay && ! $this->isPaidForCurrentPeriod();
        }

        if ($this->frequency === 'yearly') {
            $targetMonth = (int) ($this->month_of_year ?? 1);
            $isMatchingDate = ($today->month === $targetMonth && $currentDay === $effectiveDay);

            return $isMatchingDate && ! $this->isPaidForCurrentPeriod();
        }

        if ($this->frequency === 'weekly') {
            // day_of_month 1-7 merepresentasikan Monday-Sunday
            $isMatchingDayOfWeek = ((int) $today->dayOfWeekIso === ($targetDay % 7 ?: 7));

            return $isMatchingDayOfWeek && ! $this->isPaidForCurrentPeriod();
        }

        return false;
    }

    /**
     * Eksekusi pembukuan akuntansi (double-entry) untuk pengeluaran berulang ini.
     */
    public function executePosting(?float $customAmount = null, string $source = 'telegram'): JournalEntry
    {
        return DB::transaction(function () use ($customAmount, $source) {
            $finalAmount = $customAmount !== null && $customAmount > 0
                ? $customAmount
                : (float) $this->amount;

            $reference = 'RC-'.strtoupper(Str::random(8));

            $lowerSource = strtolower($source);
            if (str_starts_with($lowerSource, 'telegram')) {
                $normalizedSource = 'telegram';
            } elseif (str_starts_with($lowerSource, 'web')) {
                $normalizedSource = 'website';
            } else {
                $normalizedSource = $source;
            }

            $journalEntry = JournalEntry::create([
                'reference' => $reference,
                'description' => "Pengeluaran Rutin: {$this->name} (".now()->translatedFormat('F Y').')',
                'date' => now(),
                'source' => $normalizedSource,
                'status' => 'verified',
            ]);

            // Baris 1: Debit Akun Beban
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $this->expense_account_id,
                'description' => "Beban: {$this->name}",
                'debit' => $finalAmount,
                'credit' => 0,
            ]);

            // Baris 2: Kredit Akun Kas / Bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $this->asset_account_id,
                'description' => "Pembayaran via {$this->assetAccount->name}",
                'debit' => 0,
                'credit' => $finalAmount,
            ]);

            $this->update([
                'last_posted_at' => now(),
            ]);

            return $journalEntry->load('lines.account');
        });
    }
}
