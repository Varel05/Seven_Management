<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'raw_material' => 'Bahan Baku Utama (Kain)',
        'supporting_material' => 'Bahan Pembantu (Furing/Kain Keras/Busa)',
        'accessory' => 'Aksesoris (Kancing/Ring/Resleting)',
        'direct_labor' => 'Upah Tenaga Kerja Langsung',
        'overhead' => 'Biaya Overhead Pabrik (BOP)',
    ];

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'standard_cost',
        'stock',
        'min_stock',
        'account_id',
        'description',
    ];

    protected $casts = [
        'standard_cost' => 'float',
        'stock' => 'float',
        'min_stock' => 'float',
    ];

    /**
     * Memeriksa apakah komponen ini merupakan material fisik yang memiliki stok.
     */
    public function isPhysical(): bool
    {
        return in_array($this->category, ['raw_material', 'supporting_material', 'accessory'], true);
    }

    /**
     * Memeriksa apakah stok bahan baku menipis atau habis.
     */
    public function isLowStock(): bool
    {
        return $this->isPhysical() && $this->stock <= $this->min_stock;
    }

    /**
     * Label kategori dalam Bahasa Indonesia.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    /**
     * Format Rupiah harga standar.
     */
    public function getFormattedStandardCostAttribute(): string
    {
        return 'Rp '.number_format($this->standard_cost, 0, ',', '.');
    }

    /**
     * Format tampilan kuantitas stok beserta satuannya.
     */
    public function getFormattedStockAttribute(): string
    {
        if (! $this->isPhysical()) {
            return '-';
        }

        $formatted = (float) $this->stock == (int) $this->stock
            ? number_format($this->stock, 0, ',', '.')
            : number_format($this->stock, 2, ',', '.');

        return $formatted.' '.$this->unit;
    }

    /**
     * Filter hanya komponen barang fisik yang memiliki stok di gudang.
     */
    public function scopePhysical(Builder $query): Builder
    {
        return $query->whereIn('category', ['raw_material', 'supporting_material', 'accessory']);
    }

    /**
     * Filter hanya komponen biaya non-fisik (Tenaga Kerja & BOP).
     */
    public function scopeCostComponents(Builder $query): Builder
    {
        return $query->whereIn('category', ['direct_labor', 'overhead']);
    }

    /**
     * Relasi ke Bagan Akun Akuntansi (Chart of Accounts).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Relasi ke catatan riwayat mutasi stok bahan.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(MaterialStockMovement::class)->latest();
    }

    /**
     * Relasi ke item kartu HPP / BOM.
     */
    public function costSheetItems(): HasMany
    {
        return $this->hasMany(CostSheetItem::class);
    }
}
