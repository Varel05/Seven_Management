<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TailorPayrollItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'rate_per_piece' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'order' => 'integer',
    ];

    public function tailorPayroll(): BelongsTo
    {
        return $this->belongsTo(TailorPayroll::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getFormattedRateAttribute(): string
    {
        return 'Rp '.number_format($this->rate_per_piece, 0, ',', '.');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rp '.number_format($this->subtotal, 0, ',', '.');
    }
}
