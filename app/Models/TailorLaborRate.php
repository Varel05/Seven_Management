<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TailorLaborRate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'standard_cost' => 'decimal:2',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Format nominal tarif ke format rupiah.
     */
    public function getFormattedStandardCostAttribute(): string
    {
        return 'Rp '.number_format((float) $this->standard_cost, 0, ',', '.');
    }

    /**
     * Nama bersih tanpa prefix "Upah Penjahit " atau "Biaya ".
     */
    public function getCleanNameAttribute(): string
    {
        return str_replace(['Upah Penjahit ', 'Biaya '], '', $this->name);
    }
}
