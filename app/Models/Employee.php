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

    protected $appends = [
        'bonus_salary',
        'total_salary',
        'tier_label',
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
     * Dapatkan tarif nominal uang per poin berdasarkan tingkatan (tier) poin kinerja:
     * - 200 poin  => Rp 1.000 / poin
     * - 295 poin  => Rp 1.400 / poin
     * - 370 poin  => Rp 1.800 / poin
     * - 445 poin  => Rp 2.200 / poin
     * - ≥ 500 poin => Rp 2.600 / poin
     * - < 200 poin => Rp 0 / poin (belum memenuhi batas minimum bonus)
     */
    public static function getRateForPoints(int $points): float
    {
        return match (true) {
            $points >= 500 => 2600.0,
            $points >= 445 => 2200.0,
            $points >= 370 => 1800.0,
            $points >= 295 => 1400.0,
            $points >= 200 => 1000.0,
            default => 0.0,
        };
    }

    /**
     * Dapatkan informasi detail tingkatan (tier) untuk jumlah poin tertentu.
     */
    public static function getTierInfoForPoints(int $points): array
    {
        return match (true) {
            $points >= 500 => ['tier' => 5, 'rate' => 2600.0, 'min_points' => 500, 'label' => 'Tier 5 (≥ 500 Poin)'],
            $points >= 445 => ['tier' => 4, 'rate' => 2200.0, 'min_points' => 445, 'label' => 'Tier 4 (445 - 499 Poin)'],
            $points >= 370 => ['tier' => 3, 'rate' => 1800.0, 'min_points' => 370, 'label' => 'Tier 3 (370 - 444 Poin)'],
            $points >= 295 => ['tier' => 2, 'rate' => 1400.0, 'min_points' => 295, 'label' => 'Tier 2 (295 - 369 Poin)'],
            $points >= 200 => ['tier' => 1, 'rate' => 1000.0, 'min_points' => 200, 'label' => 'Tier 1 (200 - 294 Poin)'],
            default => ['tier' => 0, 'rate' => 0.0, 'min_points' => 0, 'label' => '< 200 Poin (Belum Capai Tier)'],
        };
    }

    /**
     * Dapatkan label tingkatan (tier) untuk karyawan saat ini.
     */
    public function getTierLabelAttribute(): string
    {
        return self::getTierInfoForPoints((int) $this->current_points)['label'];
    }

    /**
     * Dapatkan tarif per poin.
     * Mengutamakan tarif skema bertingkat jika poin >= 200.
     * Jika poin < 200, mengembalikan tarif khusus manual jika ada, atau 0.
     */
    public function getRatePerPointAttribute($value): float
    {
        $points = (int) ($this->attributes['current_points'] ?? 0);
        $tierRate = self::getRateForPoints($points);

        if ($tierRate > 0) {
            return $tierRate;
        }

        return (float) ($value ?? 0);
    }

    /**
     * Hitung bonus gaji dari total poin kinerja dikalikan tarif per poin bertingkat.
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
     * Memeriksa apakah gaji karyawan sudah dibayar pada bulan berjalan.
     */
    public function isPaidThisMonth(): bool
    {
        $today = now();
        if ($this->last_paid_at && $this->last_paid_at->isCurrentMonth() && $this->last_paid_at->isCurrentYear()) {
            return true;
        }

        return JournalEntry::where(function ($q) {
            $q->where('description', 'LIKE', "Gaji Karyawan: {$this->name}%")
                ->orWhere('description', 'LIKE', "Penggajian Karyawan: {$this->name}%")
                ->orWhere('description', 'LIKE', "%{$this->name}%");
        })
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('status', '!=', 'rejected')
            ->exists();
    }

    /**
     * Mencari entri jurnal penggajian karyawan pada bulan berjalan.
     */
    public function findExistingCurrentMonthPayrollJournal(): ?JournalEntry
    {
        $today = now();

        return JournalEntry::where(function ($q) {
            $q->where('description', 'LIKE', "Gaji Karyawan: {$this->name}%")
                ->orWhere('description', 'LIKE', "Penggajian Karyawan: {$this->name}%")
                ->orWhere('description', 'LIKE', "%{$this->name}%");
        })
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('status', '!=', 'rejected')
            ->latest('date')
            ->first();
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

        return $isMatchingDay && ! $this->isPaidThisMonth();
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
