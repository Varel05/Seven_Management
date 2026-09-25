<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomSuitOrder extends Model
{
    use HasFactory;

    public const PRODUCTION_STATUSES = [
        'consultation' => 'Konsultasi / Ukur',
        'cutting_sewing' => 'Potong & Jahit',
        'fitting' => 'Fitting Klien',
        'finishing' => 'Finishing & Pressing',
        'ready' => 'Siap Diambil',
        'completed' => 'Selesai Diserahkan',
        'cancelled' => 'Dibatalkan',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Belum Bayar',
        'partial_dp' => 'DP (Uang Muka)',
        'paid' => 'Lunas',
    ];

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_phone',
        'order_date',
        'due_date',
        'suit_type',
        'fabric_type',
        'material_id',
        'color',
        'body_measurements',
        'reference_image',
        'ai_estimation',
        'material_cost',
        'material_meters',
        'is_material_cut',
        'labor_cost',
        'overhead_cost',
        'total_cost',
        'total_price',
        'down_payment',
        'payment_status',
        'production_status',
        'account_id',
        'journal_entry_id',
        'source',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
        'body_measurements' => 'array',
        'ai_estimation' => 'array',
        'material_cost' => 'float',
        'material_meters' => 'float',
        'is_material_cut' => 'boolean',
        'labor_cost' => 'float',
        'overhead_cost' => 'float',
        'total_cost' => 'float',
        'total_price' => 'float',
        'down_payment' => 'float',
    ];

    /**
     * Master bahan kain fisik yang digunakan dari stok gudang.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Akun kas/bank penerima transaksi pesanan.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Entri pencatatan buku besar akuntansi.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Sisa tagihan yang belum dibayar oleh pelanggan.
     */
    public function getRemainingPaymentAttribute(): float
    {
        return max(0, (float) ($this->total_price - $this->down_payment));
    }

    /**
     * Laba kotor dari pembuatan jas custom ini.
     */
    public function getGrossProfitAttribute(): float
    {
        return (float) ($this->total_price - $this->total_cost);
    }

    /**
     * Margin keuntungan dalam persentase (%).
     */
    public function getProfitMarginPercentageAttribute(): float
    {
        if ($this->total_price <= 0) {
            return 0.0;
        }

        return round(($this->gross_profit / $this->total_price) * 100, 1);
    }

    /**
     * Label jenis pakaian / jas.
     */
    public function getSuitTypeLabelAttribute(): string
    {
        return Product::CATEGORIES[$this->suit_type] ?? ucfirst(str_replace('_', ' ', $this->suit_type));
    }

    /**
     * Format rupiah harga pesanan.
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return 'Rp '.number_format($this->total_price, 0, ',', '.');
    }

    /**
     * Format rupiah total modal/HPP.
     */
    public function getFormattedTotalCostAttribute(): string
    {
        return 'Rp '.number_format($this->total_cost, 0, ',', '.');
    }

    /**
     * Format rupiah biaya bahan baku.
     */
    public function getFormattedMaterialCostAttribute(): string
    {
        return 'Rp '.number_format($this->material_cost, 0, ',', '.');
    }

    /**
     * Format rupiah ongkos pengerjaan penjahit.
     */
    public function getFormattedLaborCostAttribute(): string
    {
        return 'Rp '.number_format($this->labor_cost, 0, ',', '.');
    }

    /**
     * Format rupiah kost tambahan / overhead (listrik, benang, packing).
     */
    public function getFormattedOverheadCostAttribute(): string
    {
        return 'Rp '.number_format($this->overhead_cost, 0, ',', '.');
    }

    /**
     * Format rupiah laba kotor.
     */
    public function getFormattedGrossProfitAttribute(): string
    {
        return 'Rp '.number_format($this->gross_profit, 0, ',', '.');
    }
}
