<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Employee extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'rate_per_point' => 'decimal:2',
        'current_points' => 'integer',
        'pay_day' => 'integer',
        'last_paid_at' => 'datetime',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Hitung bonus gaji dari total poin kinerja dikalikan tarif per poin.
     */
    public function getBonusSalaryAttribute(): float
    {
        return (float) ($this->current_points * $this->rate_per_point);
    }

    /**
     * Total gaji = Gaji Pokok + Bonus Poin.
     */
    public function getTotalSalaryAttribute(): float
    {
        return (float) ($this->base_salary + $this->bonus_salary);
    }

    /**
     * Format Rupiah untuk Gaji Pokok.
     */
    public function getFormattedBaseSalaryAttribute(): string
    {
        return 'Rp '.number_format($this->base_salary, 0, ',', '.');
    }

    /**
     * Format Rupiah untuk Bonus Poin.
     */
    public function getFormattedBonusSalaryAttribute(): string
    {
        return 'Rp '.number_format($this->bonus_salary, 0, ',', '.');
    }

    /**
     * Format Rupiah untuk Total Gaji.
     */
    public function getFormattedTotalSalaryAttribute(): string
    {
        return 'Rp '.number_format($this->total_salary, 0, ',', '.');
    }

    /**
     * Memeriksa apakah gaji karyawan jatuh tempo hari ini dan belum dibayar bulan ini.
     */
    public function isDueToday(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = now();
        $targetDay = min((int) $this->pay_day, (int) $today->daysInMonth);
        $isMatchingDay = ((int) $today->day === $targetDay);
        $alreadyPaidThisMonth = $this->last_paid_at && $this->last_paid_at->isCurrentMonth() && $this->last_paid_at->isCurrentYear();

        return $isMatchingDay && ! $alreadyPaidThisMonth;
    }

    /**
     * Eksekusi pembukuan akuntansi penggajian karyawan ke buku besar.
     * Beban dicatat ke akun kode 5002 (Beban Gaji) dan kredit ke akun Kas/Bank.
     */
    public function executePayrollPosting(?float $customAmount = null, string $source = 'website'): JournalEntry
    {
        return DB::transaction(function () use ($customAmount, $source) {
            $finalAmount = $customAmount !== null && $customAmount > 0
                ? $customAmount
                : (float) $this->total_salary;

            // Pastikan akun 5002 (Beban Gaji) ada
            $expenseAccount = Account::firstOrCreate(
                ['code' => '5002'],
                ['name' => 'Beban Gaji', 'type' => 'expense']
            );

            // Akun kas/bank pembayaran (default ke Kas Operasional 1001 jika belum diisi)
            $assetAccount = $this->assetAccount
                ?? Account::where('code', '1001')->first()
                ?? Account::where('type', 'asset')->first();

            $reference = 'PAY-'.strtoupper(Str::random(8));
            $period = now()->translatedFormat('F Y');

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
                'description' => "Penggajian Karyawan: {$this->name} ({$period})",
                'date' => now(),
                'source' => $normalizedSource,
                'status' => 'verified',
            ]);

            $bonusText = $this->bonus_salary > 0
                ? ', Bonus: '.$this->formatted_bonus_salary." ({$this->current_points} poin)"
                : '';

            // 1. Debit Akun Beban Gaji (5002)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'description' => "Gaji {$this->name} (Pokok: {$this->formatted_base_salary}{$bonusText})",
                'debit' => $finalAmount,
                'credit' => 0,
            ]);

            // 2. Kredit Akun Kas/Bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $assetAccount->id,
                'description' => "Pembayaran Gaji {$this->name} via {$assetAccount->name}",
                'debit' => 0,
                'credit' => $finalAmount,
            ]);

            // Tandai sudah dibayarkan untuk periode ini
            $this->update(['last_paid_at' => now()]);

            return $journalEntry;
        });
    }
}
