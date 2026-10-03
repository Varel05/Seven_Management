<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PointSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds for essential production master data.
     * Seeder ini WAJIB dieksekusi saat project dideploy ke environment produksi.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Akun Pengguna Manajemen Utama (Owner & Akuntan)
            User::updateOrCreate(
                ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
                [
                    'name' => env('ADMIN_NAME', 'Owner Seven Management'),
                    'role' => 'owner',
                    'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                    'pin' => Hash::make(env('ADMIN_PIN', '123456')),
                    'email_verified_at' => now(),
                ]
            );

            User::updateOrCreate(
                ['email' => 'akuntan@seven.com'],
                [
                    'name' => 'Maya Anggraini (Akuntan)',
                    'role' => 'akuntan',
                    'password' => Hash::make('password'),
                    'pin' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ]
            );

            // 2. Bagan Akun Akuntansi Standar (Chart of Accounts / COA)
            $accounts = [
                ['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset'],
                ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset'],
                ['code' => '1003', 'name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset'],
                ['code' => '1004', 'name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset'],
                ['code' => '1005', 'name' => 'Piutang Kasbon Karyawan & Penjahit', 'type' => 'asset'],
                ['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue'],
                ['code' => '4002', 'name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue'],
                ['code' => '4003', 'name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue'],
                ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense'],
                ['code' => '5002', 'name' => 'Beban Gaji', 'type' => 'expense'],
                ['code' => '5003', 'name' => 'Beban Perlengkapan Kantor', 'type' => 'expense'],
                ['code' => '5004', 'name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense'],
            ];

            foreach ($accounts as $acc) {
                Account::updateOrCreate(['code' => $acc['code']], $acc);
            }

            // 3. Konfigurasi Standar Poin Insentif Pelayanan CS
            PointSetting::seedDefaultsIfEmpty();

            // 4. Master Data Bahan Baku, Aksesoris, Upah & Resep Formulasi HPP (BOM)
            $this->seedMaterialsAndCostSheets();
        });
    }

    /**
     * Seed master bahan baku, aksesoris, tenaga kerja langsung, overhead, dan kartu HPP produksi.
     * Catatan: Seluruh stok fisik awal gudang diinisialisasi 0 (stok riil diinput saat operasional).
     */
    protected function seedMaterialsAndCostSheets(): void
    {
        $inventoryAccountId = DB::table('accounts')->where('code', '1004')->value('id');
        $overheadAccountId = DB::table('accounts')->where('code', '5001')->value('id');
        $laborAccountId = DB::table('accounts')->where('code', '5002')->value('id');

        // Master Materials & Komponen Biaya (Standar Biaya Produksi)
        $materials = [
            // --- Bahan Baku Utama (Raw Materials) ---
            ['code' => 'MAT-JTB', 'name' => 'Kain Jetblack', 'category' => 'raw_material', 'unit' => 'meter', 'cost' => 60000.00, 'min' => 20.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-FRR', 'name' => 'Kain Ferari', 'category' => 'raw_material', 'unit' => 'meter', 'cost' => 40000.00, 'min' => 15.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-GDF', 'name' => 'Kain Grandefeel', 'category' => 'raw_material', 'unit' => 'meter', 'cost' => 104000.00, 'min' => 10.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-DRL', 'name' => 'Kain Drill Nagata', 'category' => 'raw_material', 'unit' => 'meter', 'cost' => 40000.00, 'min' => 10.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-SPX', 'name' => 'Kain Spandex Cotton Bomber', 'category' => 'raw_material', 'unit' => 'meter', 'cost' => 135000.00, 'min' => 10.0, 'acc' => $inventoryAccountId],

            // --- Bahan Pembantu & Interlining (Supporting Materials) ---
            ['code' => 'MAT-DRM', 'name' => 'Furing Durmil', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 13000.00, 'min' => 25.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-VSL', 'name' => 'Vislin Interlining', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 7000.00, 'min' => 20.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-MRG', 'name' => 'Mori Gula Premium', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 20000.00, 'min' => 10.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-HTX', 'name' => 'Hantex Canvas Bespoke', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 30000.00, 'min' => 10.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-BSB', 'name' => 'Busa Bahu Biasa', 'category' => 'supporting_material', 'unit' => 'pcs', 'cost' => 910.00, 'min' => 50.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-BST', 'name' => 'Busa Bahu Tebal Jas', 'category' => 'supporting_material', 'unit' => 'pcs', 'cost' => 2500.00, 'min' => 30.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-KKJ', 'name' => 'Kain Keras Jas', 'category' => 'supporting_material', 'unit' => 'cm', 'cost' => 300.00, 'min' => 200.0, 'acc' => $inventoryAccountId],
            ['code' => 'MAT-KKC', 'name' => 'Kain Keras Pinggang Celana', 'category' => 'supporting_material', 'unit' => 'cm', 'cost' => 210.00, 'min' => 150.0, 'acc' => $inventoryAccountId],

            // --- Aksesoris & Trims (Accessories) ---
            ['code' => 'ACC-KCB', 'name' => 'Kancing Besar Niko', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 700.00, 'min' => 100.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-KCK', 'name' => 'Kancing Kecil Niko', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 350.00, 'min' => 200.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-RNG', 'name' => 'Ring Lengan Jas', 'category' => 'accessory', 'unit' => 'pasang', 'cost' => 3500.00, 'min' => 30.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-RSL-C', 'name' => 'Resleting YKK Celana Kecil', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 5000.00, 'min' => 25.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-HAK', 'name' => 'Hak Celana Logam', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 667.00, 'min' => 50.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-RIB', 'name' => 'Rib Jaket Elastis', 'category' => 'accessory', 'unit' => 'meter', 'cost' => 101000.00, 'min' => 5.0, 'acc' => $inventoryAccountId],
            ['code' => 'ACC-KNB', 'name' => 'Knob Snap Button Besi', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 1000.00, 'min' => 80.0, 'acc' => $inventoryAccountId],

            // --- Biaya Tenaga Kerja Langsung (Direct Labor) ---
            ['code' => 'LAB-JHT-REG', 'name' => 'Upah Penjahit Jas Reguler', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 50000.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-JHT-PRM', 'name' => 'Upah Penjahit Jas Premium', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 150000.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-JHT-EXC', 'name' => 'Upah Penjahit Jas Master Exclusive', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 250000.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-JHT-CLN', 'name' => 'Upah Penjahit Celana Formal', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 30000.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-JHT-VST', 'name' => 'Upah Penjahit Rompi / Vest', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 37000.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-JHT-REV', 'name' => 'Upah Revisi / Perbaikan Jahitan', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 0.00, 'min' => 0.0, 'acc' => $laborAccountId],
            ['code' => 'LAB-STR', 'name' => 'Biaya Setrika Uap & Finishing', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 4800.00, 'min' => 0.0, 'acc' => $laborAccountId],

            // --- Biaya Overhead Pabrik (Overhead) ---
            ['code' => 'BOP-ELC-7', 'name' => 'Beban Listrik Operasional (Standar)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7000.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-ELC-10', 'name' => 'Beban Listrik Operasional (Premium)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 10000.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-ELC-EXC', 'name' => 'Beban Listrik Khusus (Exclusive)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 14902.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-ELC-74', 'name' => 'Beban Listrik Konveksi (Celana/Jaket)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7451.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-PCK', 'name' => 'Biaya Packing & Plastik Jas', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 1800.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-ETC-2', 'name' => 'Biaya Lain-lain (Benang & Jarum)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 2000.00, 'min' => 0.0, 'acc' => $overheadAccountId],
            ['code' => 'BOP-ETC-7', 'name' => 'Biaya Lain-lain & Depresiasi Mesin', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7000.00, 'min' => 0.0, 'acc' => $overheadAccountId],
        ];

        foreach ($materials as $m) {
            DB::table('materials')->updateOrInsert(
                ['code' => $m['code']],
                [
                    'name' => $m['name'],
                    'category' => $m['category'],
                    'unit' => $m['unit'],
                    'standard_cost' => $m['cost'],
                    'stock' => 0.0, // Di production dimulai dari 0
                    'min_stock' => $m['min'],
                    'account_id' => $m['acc'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Master Kartu HPP & Resep BOM
        $costSheetsData = [
            // 1. Jas Reguler Jetblack
            [
                'code' => 'HPP-JAS-REG-JTB',
                'name' => 'Jas Reguler Jetblack',
                'category' => 'jas_reguler',
                'fabric_type' => 'Jetblack',
                'description' => 'Jas formal pria reguler bahan Jetblack dengan furing Durmil dan interlining Vislin.',
                'variants' => [
                    [
                        'size' => 'S', 'price' => 450000.00, 'notes' => 'Size S Slim Fit',
                        'items' => [
                            ['MAT-JTB', 1.6], ['MAT-DRM', 1.6], ['MAT-VSL', 1.3], ['MAT-BSB', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'M', 'price' => 450000.00, 'notes' => 'Size M Standar',
                        'items' => [
                            ['MAT-JTB', 1.7], ['MAT-DRM', 1.7], ['MAT-VSL', 1.4], ['MAT-BSB', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'L', 'price' => 475000.00, 'notes' => 'Size L Regular Fit',
                        'items' => [
                            ['MAT-JTB', 1.8], ['MAT-DRM', 1.8], ['MAT-VSL', 1.5], ['MAT-BSB', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'XL', 'price' => 500000.00, 'notes' => 'Size XL Big Fit',
                        'items' => [
                            ['MAT-JTB', 1.9], ['MAT-DRM', 1.9], ['MAT-VSL', 1.6], ['MAT-BSB', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 2. Jas Reguler Ferari
            [
                'code' => 'HPP-JAS-REG-FRR',
                'name' => 'Jas Reguler Ferari',
                'category' => 'jas_reguler',
                'fabric_type' => 'Ferari',
                'description' => 'Jas formal pria bahan kain Ferari ekonomis dan awet.',
                'variants' => [
                    [
                        'size' => 'S', 'price' => 350000.00, 'notes' => 'Size S Standar',
                        'items' => [
                            ['MAT-FRR', 1.6], ['MAT-DRM', 1.6], ['MAT-BSB', 2.0], ['ACC-KCB', 1.0],
                            ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'M', 'price' => 350000.00, 'notes' => 'Size M Standar',
                        'items' => [
                            ['MAT-FRR', 1.7], ['MAT-DRM', 1.7], ['MAT-BSB', 2.0], ['ACC-KCB', 1.0],
                            ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-7', 1.0], ['LAB-JHT-REG', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 3. Jas Premium Jetblack (Full Mori Gula & Busa Tebal)
            [
                'code' => 'HPP-JAS-PRM-JTB',
                'name' => 'Jas Premium Jetblack',
                'category' => 'jas_premium',
                'fabric_type' => 'Jetblack',
                'description' => 'Jas pria premium dengan lapisan Mori Gula, busa bahu tebal, dan jahitan tailor halus.',
                'variants' => [
                    [
                        'size' => 'S', 'price' => 650000.00, 'notes' => 'Premium S Slim',
                        'items' => [
                            ['MAT-JTB', 1.6], ['MAT-DRM', 1.6], ['MAT-MRG', 1.3], ['MAT-BST', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-10', 1.0], ['LAB-JHT-PRM', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'M', 'price' => 650000.00, 'notes' => 'Premium M Standar',
                        'items' => [
                            ['MAT-JTB', 1.7], ['MAT-DRM', 1.7], ['MAT-MRG', 1.4], ['MAT-BST', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-10', 1.0], ['LAB-JHT-PRM', 1.0], ['LAB-STR', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 4. Jas Exclusive Jetblack (Full Hantex Bespoke)
            [
                'code' => 'HPP-JAS-EXC-JTB',
                'name' => 'Jas Exclusive Jetblack Tailor',
                'category' => 'jas_exclusive',
                'fabric_type' => 'Jetblack',
                'description' => 'Jas exclusive bespoke kanvas Hantex dengan pengerjaan penjahit jas senior.',
                'variants' => [
                    [
                        'size' => 'S', 'price' => 950000.00, 'notes' => 'Exclusive S Bespoke',
                        'items' => [
                            ['MAT-JTB', 1.6], ['MAT-DRM', 1.6], ['MAT-HTX', 1.0], ['MAT-BST', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-EXC', 1.0], ['LAB-JHT-EXC', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                    [
                        'size' => 'M', 'price' => 950000.00, 'notes' => 'Exclusive M Bespoke',
                        'items' => [
                            ['MAT-JTB', 1.7], ['MAT-DRM', 1.7], ['MAT-HTX', 1.0], ['MAT-BST', 2.0],
                            ['ACC-KCB', 1.0], ['ACC-RNG', 2.0], ['ACC-KCK', 6.0], ['MAT-KKJ', 20.0],
                            ['BOP-ELC-EXC', 1.0], ['LAB-JHT-EXC', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 5. Celana Formal Grandefeel
            [
                'code' => 'HPP-CLN-GDF',
                'name' => 'Celana Formal Grandefeel',
                'category' => 'celana',
                'fabric_type' => 'Grandefeel',
                'description' => 'Celana bahan formal pria kain Grandefeel premium dengan hak logam dan resleting YKK.',
                'variants' => [
                    [
                        'size' => 'XL', 'price' => 300000.00, 'notes' => 'Ukuran Pinggang XL (33-34)',
                        'items' => [
                            ['MAT-GDF', 1.3], ['MAT-KKC', 30.0], ['ACC-RSL-C', 1.0], ['ACC-HAK', 1.0],
                            ['BOP-ELC-74', 1.0], ['LAB-JHT-CLN', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 6. Rompi / Vest Formal Jetblack
            [
                'code' => 'HPP-VST-JTB',
                'name' => 'Rompi / Vest Formal Jetblack',
                'category' => 'vest',
                'fabric_type' => 'Jetblack',
                'description' => 'Rompi setelan jas formal 5 kancing bahan Jetblack dan furing Durmil.',
                'variants' => [
                    [
                        'size' => 'All Size', 'price' => 250000.00, 'notes' => 'Rompi Standar L/XL',
                        'items' => [
                            ['MAT-JTB', 1.3], ['MAT-DRM', 1.0], ['MAT-VSL', 1.0], ['ACC-KCK', 5.0],
                            ['BOP-ELC-74', 1.0], ['LAB-JHT-VST', 1.0], ['BOP-ETC-2', 1.0],
                        ],
                    ],
                ],
            ],

            // 7. Jaket Bomber Seri Grenade
            [
                'code' => 'HPP-JKT-BMR-GRN',
                'name' => 'Jaket Bomber Grenade',
                'category' => 'jaket',
                'fabric_type' => 'Spandex Cotton',
                'description' => 'Jaket bomber casual street edition kain katun spandex dengan rib kerah dan knob logam.',
                'variants' => [
                    [
                        'size' => 'S', 'price' => 350000.00, 'notes' => 'Bomber Size S',
                        'items' => [
                            ['MAT-SPX', 0.7], ['ACC-RIB', 0.2], ['ACC-KNB', 7.0],
                            ['BOP-ELC-74', 1.0], ['LAB-JHT-REG', 1.0], ['BOP-PCK', 1.0], ['BOP-ETC-7', 1.0],
                        ],
                    ],
                    [
                        'size' => 'M', 'price' => 375000.00, 'notes' => 'Bomber Size M',
                        'items' => [
                            ['MAT-SPX', 0.8], ['ACC-RIB', 0.2], ['ACC-KNB', 7.0],
                            ['BOP-ELC-74', 1.0], ['LAB-JHT-REG', 1.0], ['BOP-PCK', 1.0], ['BOP-ETC-7', 1.0],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($costSheetsData as $cs) {
            DB::table('cost_sheets')->updateOrInsert(
                ['code' => $cs['code']],
                [
                    'name' => $cs['name'],
                    'category' => $cs['category'],
                    'fabric_type' => $cs['fabric_type'],
                    'product_id' => null,
                    'is_active' => 1,
                    'description' => $cs['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $costSheetRecord = DB::table('cost_sheets')->where('code', $cs['code'])->first();

            foreach ($cs['variants'] as $var) {
                $totMaterial = 0.0;
                $totLabor = 0.0;
                $totOverhead = 0.0;
                $processedItems = [];

                foreach ($var['items'] as $item) {
                    $matCode = $item[0];
                    $qty = (float) $item[1];

                    $materialRecord = DB::table('materials')->where('code', $matCode)->first();
                    if (! $materialRecord) {
                        continue;
                    }

                    $unitCost = (float) $materialRecord->standard_cost;
                    $subtotal = $qty * $unitCost;

                    if (in_array($materialRecord->category, ['raw_material', 'supporting_material', 'accessory'])) {
                        $totMaterial += $subtotal;
                    } elseif ($materialRecord->category === 'direct_labor') {
                        $totLabor += $subtotal;
                    } else {
                        $totOverhead += $subtotal;
                    }

                    $processedItems[] = [
                        'material_id' => $materialRecord->id,
                        'quantity' => $qty,
                        'unit_price' => $unitCost,
                        'subtotal' => $subtotal,
                    ];
                }

                $grandTotalCost = $totMaterial + $totLabor + $totOverhead;

                DB::table('cost_sheet_variants')->updateOrInsert(
                    [
                        'cost_sheet_id' => $costSheetRecord->id,
                        'size' => $var['size'],
                    ],
                    [
                        'total_material_cost' => $totMaterial,
                        'total_labor_cost' => $totLabor,
                        'total_overhead_cost' => $totOverhead,
                        'total_cost_price' => $grandTotalCost,
                        'suggested_selling_price' => $var['price'],
                        'notes' => $var['notes'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $variantRecord = DB::table('cost_sheet_variants')
                    ->where('cost_sheet_id', $costSheetRecord->id)
                    ->where('size', $var['size'])
                    ->first();

                // Bersihkan resep item sebelumnya agar tidak duplikat saat seeder dijalankan ulang
                DB::table('cost_sheet_items')->where('cost_sheet_variant_id', $variantRecord->id)->delete();

                foreach ($processedItems as $pItem) {
                    DB::table('cost_sheet_items')->insert([
                        'cost_sheet_variant_id' => $variantRecord->id,
                        'material_id' => $pItem['material_id'],
                        'quantity' => $pItem['quantity'],
                        'unit_price' => $pItem['unit_price'],
                        'subtotal' => $pItem['subtotal'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
