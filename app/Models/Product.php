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
        'stock',
        'min_stock',
        'image',
        'description',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'stock' => 'integer',
        'min_stock' => 'integer',
    ];

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
