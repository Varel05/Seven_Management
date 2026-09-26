<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Employee extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_AKUNTAN = 'akuntan';

    public const ROLE_HRD = 'hrd';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_STAFF = 'staff';

    public const ROLE_CS = 'cs';

    public const ROLES = [
        self::ROLE_OWNER => 'Owner / Pemilik',
        self::ROLE_MANAGER => 'Manager / Pengelola',
        self::ROLE_AKUNTAN => 'Akuntan / Finance',
        self::ROLE_HRD => 'HRD / Personalia',
        self::ROLE_SUPERVISOR => 'Supervisor / Pengawas',
        self::ROLE_STAFF => 'Staff Umum',
        self::ROLE_CS => 'Customer Service (CS)',
    ];

    protected $guarded = [];

    protected $casts = [
        'role' => EmployeeRole::class,
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
        'tier_name',
        'role_label',
        'role_value',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(EmployeePointLog::class)->latest();
    }

    public function getRoleValueAttribute(): string
    {
        if ($this->role instanceof EmployeeRole) {
            return $this->role->value;
        }

        return (string) ($this->role ?? EmployeeRole::Staff->value);
    }

    public function isOwner(): bool
    {
        return $this->role === EmployeeRole::Owner || $this->role_value === EmployeeRole::Owner->value;
    }

    public function isManager(): bool
    {
        return $this->role === EmployeeRole::Manager || $this->role_value === EmployeeRole::Manager->value;
    }

    public function isAkuntan(): bool
    {
        return $this->role === EmployeeRole::Akuntan || $this->role_value === EmployeeRole::Akuntan->value;
    }

    public function isHrd(): bool
    {
        return $this->role === EmployeeRole::Hrd || $this->role_value === EmployeeRole::Hrd->value;
    }

    public function isSupervisor(): bool
    {
        return $this->role === EmployeeRole::Supervisor || $this->role_value === EmployeeRole::Supervisor->value;
    }

    public function isAboveStaff(): bool
    {
        if ($this->role instanceof EmployeeRole) {
            return $this->role->isAboveStaff();
        }

        $enum = EmployeeRole::tryFrom($this->role_value);

        return $enum ? $enum->isAboveStaff() : false;
    }

    public function isStaff(): bool
    {
        return ! $this->isAboveStaff();
    }

    public function isCs(): bool
    {
        return $this->role === EmployeeRole::Cs || $this->role_value === EmployeeRole::Cs->value;
    }

    public function getRoleLabelAttribute(): string
    {
        if ($this->role instanceof EmployeeRole) {
            return $this->role->label();
        }

        $enum = EmployeeRole::tryFrom($this->role_value);

        return $enum ? $enum->label() : (self::ROLES[$this->role_value] ?? ucfirst($this->role_value));
    }

    public function getRoleBadgeClassAttribute(): string
    {
        if ($this->role instanceof EmployeeRole) {
            return $this->role->badgeClass();
        }

        $enum = EmployeeRole::tryFrom($this->role_value);

        return $enum ? $enum->badgeClass() : EmployeeRole::Staff->badgeClass();
    }

    /**
     * Normalisasi nomor telepon / HP ke format angka standar lokal (contoh: 08123456789).
     */
    public static function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $digits = preg_replace('/[^\d]/', '', (string) $phone);
        if (empty($digits)) {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Cari karyawan berdasarkan nomor HP (mendukung format 08..., 628..., +628..., maupun berformat spasi/strip).
     */
    public static function findByPhone(?string $phone): ?self
    {
        if (empty($phone)) {
            return null;
        }

        $rawDigits = preg_replace('/[^\d]/', '', (string) $phone);
        if (empty($rawDigits)) {
            return null;
        }

        $local = str_starts_with($rawDigits, '62') ? '0'.substr($rawDigits, 2) : (str_starts_with($rawDigits, '0') ? $rawDigits : '0'.$rawDigits);
        $intl = str_starts_with($local, '0') ? '62'.substr($local, 1) : $local;

        return static::where(function ($q) use ($phone, $rawDigits, $local, $intl) {
            $q->where('phone', $phone)
                ->orWhere('phone', $rawDigits)
                ->orWhere('phone', $local)
                ->orWhere('phone', $intl)
                ->orWhere('phone', '+'.$intl)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', ''), '.', '') IN (?, ?, ?)", [$local, $intl, $rawDigits]);
        })->first();
    }

    /**
     * Menambahkan atau mengurangi poin karyawan dengan pencatatan log riwayat transaksi poin.
     */
    public function addPoints(
        int $points,
        string $category = EmployeePointLog::CATEGORY_MANUAL,
        ?string $notes = null,
        ?string $refType = null,
        ?int $refId = null,
        ?string $actor = null
    ): EmployeePointLog {
        $oldPoints = (int) $this->current_points;
        $newPoints = max(0, $oldPoints + $points);
        $tierRate = self::getRateForPoints($newPoints);

        $updateData = ['current_points' => $newPoints];
        if ($tierRate > 0) {
            $updateData['rate_per_point'] = $tierRate;
        } elseif ($this->rate_per_point <= 2600) {
            $updateData['rate_per_point'] = 0;
        }

        $this->update($updateData);

        return $this->pointLogs()->create([
            'points' => $points,
            'category' => $category,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'actor' => $actor,
            'notes' => $notes,
        ]);
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
            $points >= 500 => ['tier' => 5, 'rate' => 2600.0, 'min_points' => 500, 'name' => 'Tier 5', 'label' => 'Tier 5 (≥ 500 Poin)'],
            $points >= 445 => ['tier' => 4, 'rate' => 2200.0, 'min_points' => 445, 'name' => 'Tier 4', 'label' => 'Tier 4 (445 - 499 Poin)'],
            $points >= 370 => ['tier' => 3, 'rate' => 1800.0, 'min_points' => 370, 'name' => 'Tier 3', 'label' => 'Tier 3 (370 - 444 Poin)'],
            $points >= 295 => ['tier' => 2, 'rate' => 1400.0, 'min_points' => 295, 'name' => 'Tier 2', 'label' => 'Tier 2 (295 - 369 Poin)'],
            $points >= 200 => ['tier' => 1, 'rate' => 1000.0, 'min_points' => 200, 'name' => 'Tier 1', 'label' => 'Tier 1 (200 - 294 Poin)'],
            default => ['tier' => 0, 'rate' => 0.0, 'min_points' => 0, 'name' => 'Belum Capai Tier', 'label' => '< 200 Poin (Belum Capai Tier)'],
        };
    }

    /**
     * Dapatkan nama tingkatan (tier) saja tanpa rentang poin, misal: 'Tier 1', 'Tier 2', atau 'Belum Capai Tier'.
     */
    public function getTierNameAttribute(): string
    {
        return self::getTierInfoForPoints((int) $this->current_points)['name'];
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
