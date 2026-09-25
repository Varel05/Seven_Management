<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockMovement extends Model
{
    use HasFactory;

    public const TYPES = [
        'in' => 'Stok Masuk (Pembelian/Restock)',
        'out' => 'Stok Keluar (Produksi/Pemotongan)',
        'adjustment' => 'Penyesuaian (Stock Opname)',
    ];

    protected $fillable = [
        'material_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getFormattedQuantityAttribute(): string
    {
        $unit = $this->material ? $this->material->unit : '';
        $prefix = $this->type === 'in' ? '+' : ($this->type === 'out' ? '-' : '');

        return $prefix.number_format($this->quantity, 2, ',', '.').' '.$unit;
    }
}
