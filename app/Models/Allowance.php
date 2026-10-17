<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allowance extends Model
{
    public const TARGET_ALL = 'all';

    public const TARGET_ROLE = 'role';

    public const TARGET_EMPLOYEE = 'employee';

    public const TYPE_ALLOWANCE = 'allowance';

    public const TYPE_DEDUCTION = 'deduction';

    public const CALC_FIXED = 'fixed';

    public const CALC_PERCENTAGE = 'percentage';

    protected $fillable = [
        'name',
        'type',
        'target_type',
        'target_role',
        'employee_id',
        'target_employee_ids',
        'amount',
        'calculation_type',
        'rate_percent',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'float',
        'rate_percent' => 'float',
        'is_active' => 'boolean',
        'target_employee_ids' => 'array',
    ];

    protected $appends = [
        'target_employee_ids_list',
        'formatted_amount',
        'target_label',
        'type_label',
        'calculation_label',
    ];

    /**
     * Pegawai spesifik yang menerima tunjangan ini (jika target_type = employee).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope untuk tunjangan yang sedang aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAllowances(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('type', self::TYPE_ALLOWANCE)->orWhereNull('type');
        });
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    public function isDeduction(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }

    public function isAllowance(): bool
    {
        return ! $this->isDeduction();
    }

    public function isPercentage(): bool
    {
        return $this->calculation_type === self::CALC_PERCENTAGE;
    }

    /**
     * Hitung nominal efektif untuk karyawan tertentu.
     */
    public function calculateAmountFor(Employee $employee, ?float $baseAmount = null): float
    {
        if ($this->isPercentage()) {
            $percent = (float) ($this->rate_percent ?? 0);
            if ($percent <= 0) {
                return 0.0;
            }

            if ($baseAmount !== null && $baseAmount > 0) {
                $basis = $baseAmount;
            } elseif ((float) ($employee->base_salary ?? 0) > 0) {
                $basis = (float) $employee->base_salary;
            } else {
                // Estimasi standar per bulan (27 hari kerja x upah harian efektif)
                $basis = (float) ($employee->effective_daily_rate * 27);
            }

            return (float) round(($percent / 100) * $basis);
        }

        return (float) ($this->amount ?? 0);
    }

    /**
     * Periksa apakah tunjangan ini berlaku untuk seorang pegawai tertentu.
     */
    public function appliesTo(Employee $employee): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->target_type === self::TARGET_ALL) {
            return true;
        }

        if ($this->target_type === self::TARGET_ROLE) {
            $empRoleValue = $employee->role_value;

            return ! empty($this->target_role) && $this->target_role === $empRoleValue;
        }

        if ($this->target_type === self::TARGET_EMPLOYEE) {
            $targetIds = $this->target_employee_ids ?: ($this->employee_id ? [(int) $this->employee_id] : []);

            return in_array((int) $employee->id, array_map('intval', $targetIds), true);
        }

        return false;
    }

    /**
     * Format Rupiah atau persentase untuk nominal tunjangan/potongan.
     */
    public function getFormattedAmountAttribute(): string
    {
        if ($this->isPercentage()) {
            return ($this->rate_percent ?? 0).'%';
        }

        return 'Rp '.number_format((float) ($this->amount ?? 0), 0, ',', '.');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->isDeduction() ? 'Potongan (-)' : 'Tunjangan (+)';
    }

    public function getCalculationLabelAttribute(): string
    {
        return $this->isPercentage() ? "Persentase ({$this->rate_percent}%)" : 'Nominal Tetap';
    }

    /**
     * Daftar ID pegawai target sebagai array integer.
     *
     * @return array<int>
     */
    public function getTargetEmployeeIdsListAttribute(): array
    {
        $ids = $this->target_employee_ids ?: ($this->employee_id ? [(int) $this->employee_id] : []);

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }

    /**
     * Label keterangan target alokasi penerima tunjangan.
     */
    public function getTargetLabelAttribute(): string
    {
        if ($this->target_type === self::TARGET_ALL) {
            return 'Semua Karyawan';
        }

        if ($this->target_type === self::TARGET_ROLE) {
            $roleLabel = Employee::ROLES[$this->target_role] ?? ucfirst($this->target_role);

            return "Divisi: {$roleLabel}";
        }

        if ($this->target_type === self::TARGET_EMPLOYEE) {
            $ids = $this->target_employee_ids_list;

            if (empty($ids)) {
                return 'Khusus: Belum dipilih';
            }

            $names = Employee::whereIn('id', $ids)->pluck('name')->toArray();
            if (empty($names)) {
                return 'Khusus: '.count($ids).' Pegawai';
            }

            if (count($names) <= 2) {
                return 'Khusus: '.implode(', ', $names);
            }

            return 'Khusus ('.count($names).' orang): '.$names[0].', '.$names[1].' +'.(count($names) - 2).' lainnya';
        }

        return ucfirst($this->target_type);
    }
}
