<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostSheetVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'cost_sheet_id',
        'size',
        'total_material_cost',
        'total_labor_cost',
        'total_overhead_cost',
        'total_cost_price',
        'suggested_selling_price',
        'notes',
    ];

    protected $casts = [
        'total_material_cost' => 'float',
        'total_labor_cost' => 'float',
        'total_overhead_cost' => 'float',
        'total_cost_price' => 'float',
        'suggested_selling_price' => 'float',
    ];

    public function costSheet(): BelongsTo
    {
        return $this->belongsTo(CostSheet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CostSheetItem::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'cost_sheet_variant_id');
    }

    /**
     * Hitung ulang total HPP dari item-item komponen biaya.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing(['items.material']);

        $materialCost = 0.0;
        $laborCost = 0.0;
        $overheadCost = 0.0;

        foreach ($this->items as $item) {
            $cat = $item->material ? $item->material->category : 'raw_material';
            if (in_array($cat, ['raw_material', 'supporting_material', 'accessory'], true)) {
                $materialCost += (float) $item->subtotal;
            } elseif ($cat === 'direct_labor') {
                $laborCost += (float) $item->subtotal;
            } elseif ($cat === 'overhead') {
                $overheadCost += (float) $item->subtotal;
            }
        }

        $totalHpp = $materialCost + $laborCost + $overheadCost;

        $this->update([
            'total_material_cost' => $materialCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost_price' => $totalHpp,
        ]);
    }

    public function getFormattedMaterialCostAttribute(): string
    {
        return 'Rp '.number_format($this->total_material_cost, 0, ',', '.');
    }

    public function getFormattedLaborCostAttribute(): string
    {
        return 'Rp '.number_format($this->total_labor_cost, 0, ',', '.');
    }

    public function getFormattedOverheadCostAttribute(): string
    {
        return 'Rp '.number_format($this->total_overhead_cost, 0, ',', '.');
    }

    public function getFormattedTotalCostPriceAttribute(): string
    {
        return 'Rp '.number_format($this->total_cost_price, 0, ',', '.');
    }

    public function getFormattedSuggestedSellingPriceAttribute(): string
    {
        return 'Rp '.number_format($this->suggested_selling_price ?? 0, 0, ',', '.');
    }
}
