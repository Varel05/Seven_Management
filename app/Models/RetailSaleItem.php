<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'retail_sale_id',
        'product_id',
        'quantity',
        'unit_cost_price',
        'unit_selling_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost_price' => 'float',
        'unit_selling_price' => 'float',
        'subtotal' => 'float',
    ];

    /**
     * Relasi ke transaksi penjualan retail induk.
     */
    public function retailSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class);
    }

    /**
     * Relasi ke produk pakaian retail yang terjual.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Total biaya pokok (HPP) untuk baris item ini.
     */
    public function getTotalCostAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_cost_price);
    }
}
