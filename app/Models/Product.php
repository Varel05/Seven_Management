<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * Kategori pakaian retail yang didukung.
     */
    public const CATEGORIES = [
        'jas_blazer_pria' => 'Jas / Blazer Pria',
        'tuksedo' => 'Tuksedo',
        'setelan_formal' => 'Setelan Formal',
        'jaket' => 'Jaket',
        'outwear' => 'Outwear',
        'kemeja' => 'Kemeja',
        'celana_denim' => 'Celana Denim',
        'tshirt' => 'T-Shirt',
        'polo' => 'Polo',
        'custom_made_jas' => 'Custom-Made Jas',
    ];

    protected $fillable = [
        'code',
        'name',
        'category',
        'size',
        'color',
        'cost_price',
        'selling_price',
        'rental_price',
        'is_for_rent',
        'stock',
        'min_stock',
        'image',
        'description',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'rental_price' => 'float',
        'is_for_rent' => 'boolean',
        'stock' => 'integer',
        'min_stock' => 'integer',
    ];

    /**
     * Tarif sewa efektif (jika belum diset manual, estimasi standar 30% harga jual).
     */
    public function getEffectiveRentalPriceAttribute(): float
    {
        if ($this->rental_price > 0) {
            return (float) $this->rental_price;
        }

        return (float) (round(($this->selling_price * 0.3) / 1000) * 1000);
    }

    /**
     * Format rupiah harga sewa.
     */
    public function getFormattedRentalPriceAttribute(): string
    {
        return 'Rp '.number_format($this->effective_rental_price, 0, ',', '.');
    }

    /**
     * Relasi ke item transaksi penjualan retail.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class);
    }

    /**
     * Memeriksa apakah stok baju menipis atau habis.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    /**
     * Label nama kategori yang ramah pengguna.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    /**
     * Format harga jual rupiah.
     */
    public function getFormattedSellingPriceAttribute(): string
    {
        return 'Rp '.number_format($this->selling_price, 0, ',', '.');
    }

    /**
     * Format HPP rupiah.
     */
    public function getFormattedCostPriceAttribute(): string
    {
        return 'Rp '.number_format($this->cost_price, 0, ',', '.');
    }
}
