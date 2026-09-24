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
        'transaction_type',
        'sale_date',
        'rental_start_date',
        'rental_end_date',
        'rental_return_date',
        'rental_status',
        'customer_name',
        'customer_phone',
        'payment_method',
        'account_id',
        'total_amount',
        'total_cost',
        'deposit_amount',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'rental_start_date' => 'date',
        'rental_end_date' => 'date',
        'rental_return_date' => 'datetime',
        'total_amount' => 'float',
        'total_cost' => 'float',
        'deposit_amount' => 'float',
    ];

    /**
     * Memeriksa apakah transaksi ini adalah penyewaan.
     */
    public function isRental(): bool
    {
        return $this->transaction_type === 'rental';
    }

    /**
     * Memeriksa apakah barang sewa belum dikembalikan dan masih aktif.
     */
    public function isActiveRental(): bool
    {
        return $this->isRental() && $this->rental_status === 'active';
    }

    /**
     * Memeriksa apakah tanggal pengembalian sewa sudah terlewat / overdue.
     */
    public function isOverdue(): bool
    {
        return $this->isActiveRental() && $this->rental_end_date && $this->rental_end_date->isPast();
    }

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
