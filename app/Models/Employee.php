<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'daily_rate' => 'decimal:2',
        'discipline_rate' => 'decimal:2',
        'holiday_rate' => 'decimal:2',
        'rate_per_point' => 'decimal:2',
        'current_points' => 'integer',
        'claim_bonus' => 'boolean',
        'pay_day' => 'integer',
        'last_paid_at' => 'datetime',
    ];

    protected $appends = [
        'bonus_salary',
        'total_allowance',
        'formatted_total_allowance',
        'total_salary',
        'tier_label',
        'tier_name',
        'tier_points_to_deduct',
        'role_label',
        'role_value',
        'effective_daily_rate',
        'formatted_daily_rate',
        'effective_discipline_rate',
        'effective_holiday_rate',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(EmployeePointLog::class)->latest();
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(Allowance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(EmployeePayroll::class)->latest();
    }

    public function latestPayroll(): HasOne
    {
        return $this->hasOne(EmployeePayroll::class)->latestOfMany();
    }

    /**
     * Tarif harian efektif (jika belum diset manual, estimasi dari gaji pokok / 27 shift).
     */
    public function getEffectiveDailyRateAttribute(): float
    {
        if ((float) ($this->daily_rate ?? 0) > 0) {
            return (float) $this->daily_rate;
        }

        $base = (float) ($this->base_salary ?? 0);
        if ($base > 0) {
            return (float) (round(($base / 27) / 1000) * 1000);
        }

        return 100000.0;
    }

    /**
     * Format Rupiah untuk Upah per Shift / Hari.
     */
    public function getFormattedDailyRateAttribute(): string
    {
        return 'Rp '.number_format($this->effective_daily_rate, 0, ',', '.').' / shift';
    }

    /**
     * Tarif bonus disiplin per hari efektif (default Rp 10.000 / hari jika hadir tanpa terlambat).
     */
    public function getEffectiveDisciplineRateAttribute(): float
    {
        if ((float) ($this->discipline_rate ?? 0) > 0) {
            return (float) $this->discipline_rate;
        }

        return 10000.0;
    }

    /**
     * Tarif bonus tanggal merah efektif (default Rp 50.000 / hari jika masuk saat libur nasional).
     */
    public function getEffectiveHolidayRateAttribute(): float
    {
        if ((float) ($this->holiday_rate ?? 0) > 0) {
            return (float) $this->holiday_rate;
        }

        return 50000.0;
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
     * Hitung besar poin yang akan dikurangi dari tier jika karyawan memilih mengambil bonus.
     */
    public function getTierPointsToDeductAttribute(): int
    {
        if (! ($this->claim_bonus ?? true)) {
            return 0;
        }

        $tierInfo = self::getTierInfoForPoints((int) $this->current_points);

        return $tierInfo['tier'] > 0 ? (int) $tierInfo['min_points'] : 0;
    }

    /**
     * Hitung bonus gaji dari total poin kinerja dikalikan tarif per poin bertingkat.
     * Jika karyawan memilih untuk tidak mengambil bonus pada bulan ini, bonus bernilai 0.
     */
    public function getBonusSalaryAttribute(): float
    {
        if (! ($this->claim_bonus ?? true)) {
            return 0.0;
        }

        return (float) ($this->current_points * $this->rate_per_point);
    }

    /**
     * Dapatkan semua tunjangan aktif yang berlaku untuk karyawan ini
     * (berlaku untuk semua, sesuai divisi/role, atau khusus untuk karyawan ini).
     */
    public function getApplicableAllowancesAttribute()
    {
        return Allowance::active()->get()->filter(fn (Allowance $allowance) => $allowance->appliesTo($this))->values();
    }

    /**
     * Total nominal tunjangan karyawan.
     */
    public function getTotalAllowanceAttribute(): float
    {
        return (float) $this->applicable_allowances->sum('amount');
    }

    /**
     * Format Rupiah untuk Total Tunjangan.
     */
    public function getFormattedTotalAllowanceAttribute(): string
    {
        return 'Rp '.number_format($this->total_allowance, 0, ',', '.');
    }

    /**
     * Total gaji = Gaji Pokok + Total Tunjangan + Bonus Poin.
     */
    public function getTotalSalaryAttribute(): float
    {
        return (float) ($this->base_salary + $this->total_allowance + $this->bonus_salary);
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
     * Secara otomatis membuat riwayat slip gaji resmi (EmployeePayroll).
     */
    public function executePayrollPosting(?float $customAmount = null, string $source = 'website', ?array $payrollData = null): JournalEntry
    {
        return DB::transaction(function () use ($customAmount, $source, $payrollData) {
            $period = $payrollData['period'] ?? now()->translatedFormat('F Y');
            
            // Cari draft terbaru yang belum dibayar, abaikan perbedaan penulisan periode dari AI
            $draftPayroll = EmployeePayroll::where('employee_id', $this->id)
                ->whereNull('journal_entry_id')
                ->latest()
                ->first();

            $finalAmount = $customAmount !== null && $customAmount > 0
                ? $customAmount
                : ($draftPayroll ? (float) $draftPayroll->take_home_pay : (float) $this->total_salary);

            if ($draftPayroll) {
                $period = $draftPayroll->period;
            }

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

            $allowanceNames = $this->applicable_allowances->pluck('name')->implode(', ');
            $allowanceText = $this->total_allowance > 0
                ? ', Tunjangan: '.$this->formatted_total_allowance.($allowanceNames ? " ({$allowanceNames})" : '')
                : '';

            $bonusText = $this->bonus_salary > 0
                ? ', Bonus: '.$this->formatted_bonus_salary." ({$this->tier_points_to_deduct} poin)"
                : '';
                
            $mainSalaryBase = $draftPayroll ? $draftPayroll->main_salary : ($payrollData['main_salary'] ?? $this->base_salary);

            // 1. Debit Akun Beban Gaji (5002)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'description' => "Gaji {$this->name} (Pokok/Honor: Rp ".number_format($mainSalaryBase, 0, ',', '.')."{$allowanceText}{$bonusText})",
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



            if ($draftPayroll) {
                // Gunakan data dari draft yang sudah diisi oleh webhook generatePayrollSlip
                $totalPresent = $draftPayroll->total_present;
                $totalShifts = $draftPayroll->total_shifts;
                $lateCount = $draftPayroll->late_count;
                $disciplinePresent = $draftPayroll->discipline_present;
                $holidayShifts = $draftPayroll->holiday_shifts;
                $dailyRate = $draftPayroll->daily_rate;
                $disciplineRate = $draftPayroll->discipline_rate;
                $holidayRate = $draftPayroll->holiday_rate;
                $mainSalary = $draftPayroll->main_salary;
                $disciplineBonus = $draftPayroll->discipline_bonus;
                $salesBonus = $draftPayroll->sales_bonus;
                $holidayBonus = $draftPayroll->holiday_bonus;
                $allowanceTotal = $draftPayroll->allowance_total;

                $draftPayroll->update([
                    'journal_entry_id' => $journalEntry->id,
                    'take_home_pay' => $finalAmount,
                    'notes' => 'Telah Dibayar',
                    'paid_at' => now(), // Assume this column exists or will just be ignored if not in fillable
                ]);
            } else {
                $totalPresent = (int) ($payrollData['total_present'] ?? 27);
                $totalShifts = (int) ($payrollData['total_shifts'] ?? 27);
                $lateCount = (int) ($payrollData['late_count'] ?? 0);
                $disciplinePresent = (int) ($payrollData['discipline_present'] ?? max(0, $totalPresent - $lateCount));
                $holidayShifts = (int) ($payrollData['holiday_shifts'] ?? 0);
    
                $dailyRate = (float) ($payrollData['daily_rate'] ?? $this->effective_daily_rate);
                $disciplineRate = (float) ($payrollData['discipline_rate'] ?? $this->effective_discipline_rate);
                $holidayRate = (float) ($payrollData['holiday_rate'] ?? $this->effective_holiday_rate);
    
                $mainSalary = (float) ($payrollData['main_salary'] ?? ($totalPresent * $dailyRate));
                $disciplineBonus = (float) ($payrollData['discipline_bonus'] ?? ($disciplinePresent * $disciplineRate));
                $salesBonus = (float) ($payrollData['sales_bonus'] ?? $this->bonus_salary);
                $holidayBonus = (float) ($payrollData['holiday_bonus'] ?? ($holidayShifts * $holidayRate));
                $allowanceTotal = (float) ($payrollData['allowance_total'] ?? $this->total_allowance);

                EmployeePayroll::create([
                'employee_id' => $this->id,
                'journal_entry_id' => $journalEntry->id,
                'period' => $period,
                'period_start' => $payrollData['period_start'] ?? now()->subMonth()->setDay(26)->toDateString(),
                'period_end' => $payrollData['period_end'] ?? now()->setDay(25)->toDateString(),
                'payment_method' => $payrollData['payment_method'] ?? 'Transfer',
                'total_shifts' => $totalShifts,
                'total_present' => $totalPresent,
                'late_count' => $lateCount,
                'discipline_present' => $disciplinePresent,
                'holiday_shifts' => $holidayShifts,
                'daily_rate' => $dailyRate,
                'discipline_rate' => $disciplineRate,
                'holiday_rate' => $holidayRate,
                'closing_points' => (int) ($payrollData['closing_points'] ?? $this->current_points),
                'closing_pcs' => (int) ($payrollData['closing_pcs'] ?? 0),
                'rate_per_point' => (float) ($payrollData['rate_per_point'] ?? $this->rate_per_point),
                'closing_breakdown' => $payrollData['closing_breakdown'] ?? null,
                'main_salary' => $mainSalary,
                'discipline_bonus' => $disciplineBonus,
                'sales_bonus' => $salesBonus,
                'holiday_bonus' => $holidayBonus,
                'allowance_total' => $allowanceTotal,
                'take_home_pay' => $finalAmount,
                'notes' => $payrollData['notes'] ?? null,
                'hrd_name' => $payrollData['hrd_name'] ?? 'Ari Husbana',
                'paid_at' => now(),
            ]);
            }

            // 4. Kurangi poin terakumulasi sebesar besar poin tier jika mengambil bonus gaji
            $pointsToDeduct = $this->tier_points_to_deduct;
            if ($pointsToDeduct > 0) {
                $oldPoints = (int) $this->current_points;
                $newPoints = max(0, $oldPoints - $pointsToDeduct);
                $tierRate = self::getRateForPoints($newPoints);
                $tierInfo = self::getTierInfoForPoints($oldPoints);

                $this->update([
                    'current_points' => $newPoints,
                    'rate_per_point' => $tierRate,
                ]);

                $this->pointLogs()->create([
                    'points' => -$pointsToDeduct,
                    'category' => EmployeePointLog::CATEGORY_MANUAL,
                    'actor' => $source === 'telegram' ? 'Payroll Telegram' : 'Payroll System',
                    'notes' => "Pencairan bonus gaji {$tierInfo['name']} ({$period})",
                ]);
            }

            // Tandai sudah dibayarkan untuk periode ini
            $this->update(['last_paid_at' => now()]);

            return $journalEntry;
        });
    }
}
