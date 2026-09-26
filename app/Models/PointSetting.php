<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'group',
        'points',
        'description',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public const DEFAULTS = [
        // Kategori Pakaian
        'item_category:tuksedo' => [
            'name' => 'Tuksedo',
            'group' => 'item_category',
            'points' => 20,
            'description' => 'Poin insentif standar penjualan Tuksedo',
        ],
        'item_category:setelan_formal' => [
            'name' => 'Setelan Formal',
            'group' => 'item_category',
            'points' => 20,
            'description' => 'Poin insentif standar penjualan Setelan Formal',
        ],
        'item_category:custom_made_jas' => [
            'name' => 'Custom-Made Jas',
            'group' => 'item_category',
            'points' => 25,
            'description' => 'Poin insentif standar penjualan Custom-Made Jas',
        ],
        'item_category:jas_blazer_pria' => [
            'name' => 'Jas / Blazer Pria',
            'group' => 'item_category',
            'points' => 15,
            'description' => 'Poin insentif standar penjualan Jas / Blazer Pria',
        ],
        'item_category:jaket' => [
            'name' => 'Jaket',
            'group' => 'item_category',
            'points' => 10,
            'description' => 'Poin insentif standar penjualan Jaket',
        ],
        'item_category:outwear' => [
            'name' => 'Outwear',
            'group' => 'item_category',
            'points' => 10,
            'description' => 'Poin insentif standar penjualan Outwear',
        ],
        'item_category:kemeja' => [
            'name' => 'Kemeja',
            'group' => 'item_category',
            'points' => 5,
            'description' => 'Poin insentif standar penjualan Kemeja',
        ],
        'item_category:celana_denim' => [
            'name' => 'Celana Denim',
            'group' => 'item_category',
            'points' => 5,
            'description' => 'Poin insentif standar penjualan Celana Denim',
        ],
        'item_category:tshirt' => [
            'name' => 'T-Shirt',
            'group' => 'item_category',
            'points' => 3,
            'description' => 'Poin insentif standar penjualan T-Shirt',
        ],
        'item_category:polo' => [
            'name' => 'Polo',
            'group' => 'item_category',
            'points' => 3,
            'description' => 'Poin insentif standar penjualan Polo',
        ],

        // Layanan & Bonus Tambahan
        'service:rental' => [
            'name' => 'Penyewaan Baju (Rental)',
            'group' => 'service',
            'points' => 10,
            'description' => 'Poin standar setiap transaksi penyewaan pakaian',
        ],
        'service:cod' => [
            'name' => 'Layanan Cash On Delivery (COD)',
            'group' => 'service',
            'points' => 10,
            'description' => 'Bonus poin saat CS melayani pesanan COD',
        ],
        'service:quantity_extra' => [
            'name' => 'Bonus Qty Tambahan (per Pcs)',
            'group' => 'service',
            'points' => 2,
            'description' => 'Tambahan poin per pcs untuk item ke-2 dst dalam 1 transaksi',
        ],
        'service:custom_suit' => [
            'name' => 'Pesanan Custom Jas Tailor',
            'group' => 'service',
            'points' => 25,
            'description' => 'Poin saat CS mencatat pesanan jahit jas custom',
        ],
        'service:review' => [
            'name' => 'Review Bagus Pelanggan',
            'group' => 'service',
            'points' => 15,
            'description' => 'Poin apresiasi review positif / bintang 5 dari pelanggan',
        ],
        'service:cross_company' => [
            'name' => 'Bantuan Antar Perusahaan',
            'group' => 'service',
            'points' => 20,
            'description' => 'Poin bonus saat CS membantu penanganan CS mitra perusahaan',
        ],
    ];

    /**
     * Mengambil nilai poin berdasarkan key, dengan fallback default.
     */
    public static function get(string $key, ?int $default = null): int
    {
        $setting = static::where('key', $key)->first();
        if ($setting) {
            return (int) $setting->points;
        }

        if (array_key_exists($key, self::DEFAULTS)) {
            return (int) self::DEFAULTS[$key]['points'];
        }

        return $default ?? 0;
    }

    /**
     * Menyimpan / memperbarui nilai poin konfigurasi.
     */
    public static function set(string $key, int $points, ?string $name = null, string $group = 'general', ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'points' => $points,
                'name' => $name ?? (self::DEFAULTS[$key]['name'] ?? $key),
                'group' => $group ?: (self::DEFAULTS[$key]['group'] ?? 'general'),
                'description' => $description ?? (self::DEFAULTS[$key]['description'] ?? null),
            ]
        );
    }

    /**
     * Memastikan semua pengaturan standar ada di database.
     */
    public static function seedDefaultsIfEmpty(): void
    {
        foreach (self::DEFAULTS as $key => $meta) {
            static::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $meta['name'],
                    'group' => $meta['group'],
                    'points' => $meta['points'],
                    'description' => $meta['description'],
                ]
            );
        }
    }
}
