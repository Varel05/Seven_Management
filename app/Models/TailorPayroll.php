<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TailorPayroll extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payroll_date' => 'date',
        'paid_at' => 'datetime',
        'total_pieces' => 'integer',
        'total_wage' => 'decimal:2',
        'total_bon' => 'decimal:2',
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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TailorPayrollItem::class)->orderBy('order');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(TailorPayrollAdvance::class)->orderBy('advance_date');
    }

    public function getFormattedTotalWageAttribute(): string
    {
        return 'Rp '.number_format($this->total_wage, 0, ',', '.');
    }

    public function getFormattedTotalBonAttribute(): string
    {
        return 'Rp '.number_format($this->total_bon, 0, ',', '.');
    }

    public function getFormattedTakeHomePayAttribute(): string
    {
        return 'Rp '.number_format($this->take_home_pay, 0, ',', '.');
    }
}
