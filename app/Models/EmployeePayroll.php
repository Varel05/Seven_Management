<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayroll extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'total_shifts' => 'integer',
        'total_present' => 'integer',
        'late_count' => 'integer',
        'discipline_present' => 'integer',
        'holiday_shifts' => 'integer',
        'daily_rate' => 'decimal:2',
        'discipline_rate' => 'decimal:2',
        'holiday_rate' => 'decimal:2',
        'closing_points' => 'integer',
        'closing_pcs' => 'integer',
        'rate_per_point' => 'decimal:2',
        'closing_breakdown' => 'array',
        'main_salary' => 'decimal:2',
        'discipline_bonus' => 'decimal:2',
        'sales_bonus' => 'decimal:2',
        'holiday_bonus' => 'decimal:2',
        'allowance_total' => 'decimal:2',
        'take_home_pay' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function getFormattedMainSalaryAttribute(): string
    {
        return 'Rp '.number_format($this->main_salary, 0, ',', '.');
    }

    public function getFormattedDisciplineBonusAttribute(): string
    {
        return 'Rp '.number_format($this->discipline_bonus, 0, ',', '.');
    }

    public function getFormattedSalesBonusAttribute(): string
    {
        return 'Rp '.number_format($this->sales_bonus, 0, ',', '.');
    }

    public function getFormattedHolidayBonusAttribute(): string
    {
        return 'Rp '.number_format($this->holiday_bonus, 0, ',', '.');
    }

    public function getFormattedAllowanceTotalAttribute(): string
    {
        return 'Rp '.number_format($this->allowance_total, 0, ',', '.');
    }

    public function getFormattedTakeHomePayAttribute(): string
    {
        return 'Rp '.number_format($this->take_home_pay, 0, ',', '.');
    }

    /**
     * Hitung ulang take home pay berdasarkan komponen-komponennya.
     */
    public function recalculateTakeHomePay(): float
    {
        $main = (float) ($this->total_present * $this->daily_rate);
        $discipline = (float) ($this->discipline_present * $this->discipline_rate);
        $sales = (float) ($this->closing_points > 0 && $this->rate_per_point > 0
            ? $this->closing_points * $this->rate_per_point
            : $this->sales_bonus);
        $holiday = (float) ($this->holiday_shifts * $this->holiday_rate);
        $allowances = (float) $this->allowance_total;

        $thp = $main + $discipline + $sales + $holiday + $allowances;

        $this->main_salary = $main;
        $this->discipline_bonus = $discipline;
        $this->sales_bonus = $sales;
        $this->holiday_bonus = $holiday;
        $this->take_home_pay = $thp;

        return $thp;
    }
}
