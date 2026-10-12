<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    public const STANDARD_RATES = [
        'LAB-JHT-REG' => [
            'code' => 'LAB-JHT-REG',
            'name' => 'Upah Penjahit Jas Reguler',
            'unit' => 'pcs',
            'standard_cost' => 50000,
        ],
        'LAB-JHT-VST' => [
            'code' => 'LAB-JHT-VST',
            'name' => 'Upah Penjahit Rompi / Vest',
            'unit' => 'pcs',
            'standard_cost' => 37000,
        ],
        'LAB-JHT-PRM' => [
            'code' => 'LAB-JHT-PRM',
            'name' => 'Upah Penjahit Jas Premium',
            'unit' => 'pcs',
            'standard_cost' => 150000,
        ],
        'LAB-JHT-REV' => [
            'code' => 'LAB-JHT-REV',
            'name' => 'Upah Revisi / Perbaikan Jahitan',
            'unit' => 'pcs',
            'standard_cost' => 0,
        ],
        'LAB-JHT-CLN' => [
            'code' => 'LAB-JHT-CLN',
            'name' => 'Upah Penjahit Celana Formal',
            'unit' => 'pcs',
            'standard_cost' => 30000,
        ],
        'LAB-JHT-EXC' => [
            'code' => 'LAB-JHT-EXC',
            'name' => 'Upah Penjahit Jas Master Exclusive',
            'unit' => 'pcs',
            'standard_cost' => 250000,
        ],
    ];

    /**
     * Mengembalikan daftar tarif upah penjahit standar dalam bentuk koleksi objek.
     */
    public static function getLaborRates(): Collection
    {
        return collect(self::STANDARD_RATES)->map(function ($r) {
            return (object) [
                'id' => null,
                'code' => $r['code'],
                'name' => $r['name'],
                'unit' => $r['unit'],
                'standard_cost' => $r['standard_cost'],
                'formatted_standard_cost' => 'Rp '.number_format($r['standard_cost'], 0, ',', '.'),
            ];
        });
    }
}
