<?php

namespace App\Models;

use Database\Factories\TailorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tailor extends Model
{
    /** @use HasFactory<TailorFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_payment_schedule',
        'is_weekly',
        'is_monthly',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(TailorPayroll::class)->latest('payroll_date');
    }

    public function latestPayroll(): HasOne
    {
        return $this->hasOne(TailorPayroll::class)->latestOfMany('payroll_date');
    }

    public function getFormattedPaymentScheduleAttribute(): string
    {
        if ($this->payment_period === 'monthly') {
            return 'Bulanan'.($this->payment_day ? ' (Tgl '.$this->payment_day.')' : '');
        }

        return 'Mingguan'.($this->payment_day ? ' ('.$this->payment_day.')' : '');
    }

    public function getIsWeeklyAttribute(): bool
    {
        return $this->payment_period === 'weekly';
    }

    public function getIsMonthlyAttribute(): bool
    {
        return $this->payment_period === 'monthly';
    }
}
