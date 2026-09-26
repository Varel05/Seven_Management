<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePointLog extends Model
{
    use HasFactory;

    public const CATEGORY_ITEM_SALE = 'item_sale';

    public const CATEGORY_ITEM_RENT = 'item_rent';

    public const CATEGORY_QUANTITY = 'quantity';

    public const CATEGORY_COD = 'cod';

    public const CATEGORY_REVIEW = 'review';

    public const CATEGORY_CROSS_COMPANY = 'cross_company';

    public const CATEGORY_MANUAL = 'manual';

    public const CATEGORIES = [
        self::CATEGORY_ITEM_SALE => 'Penjualan Item',
        self::CATEGORY_ITEM_RENT => 'Penyewaan Item',
        self::CATEGORY_QUANTITY => 'Bonus Kuantitas (Qty)',
        self::CATEGORY_COD => 'Layanan Cash On Delivery (COD)',
        self::CATEGORY_REVIEW => 'Review Bagus Pelanggan',
        self::CATEGORY_CROSS_COMPANY => 'Bantuan Antar Perusahaan',
        self::CATEGORY_MANUAL => 'Penyesuaian Manual',
    ];

    /**
     * Besaran poin standar untuk setiap kategori produk / layanan.
     */
    public const PRODUCT_POINTS = [
        'tuksedo' => 20,
        'setelan_formal' => 20,
        'custom_made_jas' => 25,
        'jas_blazer_pria' => 15,
        'jaket' => 10,
        'outwear' => 10,
        'kemeja' => 5,
        'celana_denim' => 5,
        'tshirt' => 3,
        'polo' => 3,
    ];

    public const DEFAULT_RENT_POINTS = 10;

    public const DEFAULT_COD_POINTS = 10;

    public const DEFAULT_REVIEW_POINTS = 15;

    public const DEFAULT_QTY_EXTRA_POINTS = 2; // Poin tambahan per pcs untuk item ke-2 dst

    protected $fillable = [
        'employee_id',
        'points',
        'category',
        'reference_type',
        'reference_id',
        'actor',
        'notes',
    ];

    protected $casts = [
        'points' => 'integer',
        'reference_id' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function getFormattedPointsAttribute(): string
    {
        $sign = $this->points > 0 ? '+' : '';

        return $sign.$this->points.' pt';
    }
}
