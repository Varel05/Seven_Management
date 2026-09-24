<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'sale_date',
        'customer_name',
        'customer_phone',
        'payment_method',
        'account_id',
        'total_amount',
        'total_cost',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'total_amount' => 'float',
        'total_cost' => 'float',
    ];

    /**
     * Rincian produk yang terjual dalam invoice ini.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class);
    }

    /**
     * Akun kas/bank penerima pembayaran.
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
     * Laba kotor dari transaksi penjualan ini.
     */
    public function getGrossProfitAttribute(): float
    {
        return (float) ($this->total_amount - $this->total_cost);
    }

    /**
     * Format total penjualan rupiah.
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return 'Rp '.number_format($this->total_amount, 0, ',', '.');
    }
}
