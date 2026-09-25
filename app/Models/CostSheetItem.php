<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostSheetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cost_sheet_variant_id',
        'material_id',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CostSheetVariant::class, 'cost_sheet_variant_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return 'Rp '.number_format($this->unit_price, 0, ',', '.');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rp '.number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedQuantityAttribute(): string
    {
        $unit = $this->material ? $this->material->unit : '';

        return number_format($this->quantity, 3, ',', '.').' '.$unit;
    }
}
