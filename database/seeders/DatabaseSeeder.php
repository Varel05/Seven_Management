<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CostSheet;
use App\Models\CostSheetItem;
use App\Models\CostSheetVariant;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $accounts = [
            ['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset'],
            ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset'],
            ['code' => '1003', 'name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset'],
            ['code' => '1004', 'name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset'],
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

        // Seed realistic sample transactions if database has fewer than 5 entries
        if (JournalEntry::count() < 5) {
            $kas = Account::where('code', '1001')->first();
            $bca = Account::where('code', '1002')->first();
            $pendapatan = Account::where('code', '4001')->first();
            $bebanOps = Account::where('code', '5001')->first();
            $bebanGaji = Account::where('code', '5002')->first();
            $bebanPerlengkapan = Account::where('code', '5003')->first();

            $currentYear = (int) now()->format('Y');

            $samples = [
                // Bulan-bulan sebelumnya di tahun berjalan
                ['ref' => 'TRX-202601-01', 'desc' => 'Pendapatan Kontrak Software Q1', 'date' => "$currentYear-01-15 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12500000, 'src' => 'manual'],
                ['ref' => 'TRX-202601-02', 'desc' => 'Biaya Server Cloud AWS Jan', 'date' => "$currentYear-01-20 14:30:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 2400000, 'src' => 'manual'],
                ['ref' => 'TRX-202602-01', 'desc' => 'Pendapatan Maintenance Sistem', 'date' => "$currentYear-02-12 11:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 8000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202602-02', 'desc' => 'Penggajian Tim Developer Feb', 'date' => "$currentYear-02-27 16:00:00", 'dr_acc' => $bebanGaji->id, 'cr_acc' => $bca->id, 'amount' => 5000000, 'src' => 'manual'],
                ['ref' => 'TRX-202603-01', 'desc' => 'Pendapatan Konsultasi IT Maret', 'date' => "$currentYear-03-10 09:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 15000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202603-02', 'desc' => 'Pembelian Perlengkapan Kantor Q1', 'date' => "$currentYear-03-18 13:45:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 1850000, 'src' => 'telegram'],
                ['ref' => 'TRX-202604-01', 'desc' => 'Pendapatan Langganan SaaS April', 'date' => "$currentYear-04-08 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 11000000, 'src' => 'manual'],
                ['ref' => 'TRX-202605-01', 'desc' => 'Pendapatan Lisensi Sistem Mei', 'date' => "$currentYear-05-14 11:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 13500000, 'src' => 'telegram'],
                ['ref' => 'TRX-202606-01', 'desc' => 'Pengeluaran Event & Workshop', 'date' => "$currentYear-06-22 15:00:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $kas->id, 'amount' => 3200000, 'src' => 'manual'],
                ['ref' => 'TRX-202607-01', 'desc' => 'Pendapatan Retainer Client Q3', 'date' => "$currentYear-07-05 10:30:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 16000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202608-01', 'desc' => 'Pendapatan Pembuatan Web App', 'date' => "$currentYear-08-19 14:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 9500000, 'src' => 'telegram'],

                // Bulan berjalan (September) - Harian
                ['ref' => 'TRX-202609-01', 'desc' => 'Pendapatan Invoice Jasa Konsultasi', 'date' => "$currentYear-09-03 09:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 4500000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-02', 'desc' => 'Pembelian ATK & Kertas Kantor', 'date' => "$currentYear-09-06 11:30:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 650000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-03', 'desc' => 'Pelunasan Modul Integrasi API', 'date' => "$currentYear-09-10 14:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 8500000, 'src' => 'manual'],
                ['ref' => 'TRX-202609-04', 'desc' => 'Biaya Internet & Server Dedicated', 'date' => "$currentYear-09-14 16:45:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 1750000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-05', 'desc' => 'Pendapatan Deployment AI Agent', 'date' => "$currentYear-09-18 10:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-06', 'desc' => 'Gaji Pokok Staf Akuntansi & Admin', 'date' => "$currentYear-09-22 15:00:00", 'dr_acc' => $bebanGaji->id, 'cr_acc' => $bca->id, 'amount' => 4200000, 'src' => 'manual'],
            ];

            foreach ($samples as $s) {
                $entry = JournalEntry::updateOrCreate(
                    ['reference' => $s['ref']],
                    [
                        'description' => $s['desc'],
                        'date' => $s['date'],
                        'source' => $s['src'],
                        'status' => 'verified',
                    ]
                );

                JournalEntryLine::updateOrCreate(
                    ['journal_entry_id' => $entry->id, 'account_id' => $s['dr_acc']],
                    [
                        'description' => $s['desc'],
                        'debit' => $s['amount'],
                        'credit' => 0,
                    ]
                );

                JournalEntryLine::updateOrCreate(
                    ['journal_entry_id' => $entry->id, 'account_id' => $s['cr_acc']],
                    [
                        'description' => $s['desc'],
                        'debit' => 0,
                        'credit' => $s['amount'],
                    ]
                );
            }
        }

        // Seed sample retail products if empty
        if (Product::count() === 0) {
            $sampleProducts = [
                [
                    'code' => 'JAS-001',
                    'name' => 'Italian Slim Fit Navy Blazer',
                    'category' => 'jas_blazer_pria',
                    'size' => 'L',
                    'color' => 'Navy Blue',
                    'cost_price' => 750000,
                    'selling_price' => 1450000,
                    'stock' => 12,
                    'min_stock' => 3,
                    'description' => 'Jas pria semi-wool premium dengan cutting Italian modern slim fit.',
                ],
                [
                    'code' => 'TXD-001',
                    'name' => 'Black Tie Tuxedo Satin Lapel',
                    'category' => 'tuksedo',
                    'size' => 'XL',
                    'color' => 'Midnight Black',
                    'cost_price' => 1100000,
                    'selling_price' => 2250000,
                    'stock' => 5,
                    'min_stock' => 2,
                    'description' => 'Tuksedo formal mewah dengan kerah shawl lapel satin sutra.',
                ],
                [
                    'code' => 'KMJ-001',
                    'name' => 'Crisp White Poplin Formal Shirt',
                    'category' => 'kemeja',
                    'size' => 'M',
                    'color' => 'Bright White',
                    'cost_price' => 120000,
                    'selling_price' => 285000,
                    'stock' => 24,
                    'min_stock' => 5,
                    'description' => 'Kemeja kerja katun poplin premium, lembut dan tidak mudah kusut.',
                ],
                [
                    'code' => 'DNM-001',
                    'name' => 'Selvedge Raw Denim Slim Straight',
                    'category' => 'celana_denim',
                    'size' => '32',
                    'color' => 'Deep Indigo',
                    'cost_price' => 220000,
                    'selling_price' => 450000,
                    'stock' => 15,
                    'min_stock' => 4,
                    'description' => 'Celana denim 14oz red line selvedge dengan jahitan rantai kokoh.',
                ],
                [
                    'code' => 'POL-001',
                    'name' => 'Pique Classic Cotton Polo',
                    'category' => 'polo',
                    'size' => 'L',
                    'color' => 'Charcoal Grey',
                    'cost_price' => 70000,
                    'selling_price' => 165000,
                    'stock' => 30,
                    'min_stock' => 5,
                    'description' => 'Kaos polo katun pique bertekstur, nyaman dan adem untuk gaya semi-formal.',
                ],
                [
                    'code' => 'STF-001',
                    'name' => 'Charcoal 2-Piece Formal Suit Set',
                    'category' => 'setelan_formal',
                    'size' => 'L',
                    'color' => 'Charcoal',
                    'cost_price' => 950000,
                    'selling_price' => 1890000,
                    'stock' => 8,
                    'min_stock' => 2,
                    'description' => 'Setelan jas dan celana bahan lengkap untuk acara resmi & pesta.',
                ],
            ];

            foreach ($sampleProducts as $p) {
                Product::create($p);
            }
        }

        // Seed Master Bahan & Komponen Biaya (Materials) jika masih kosong
        if (Material::count() === 0) {
            $accBahan = Account::where('code', '1004')->first() ?? Account::where('code', '1003')->first();
            $accBOP = Account::where('code', '5001')->first();
            $accGaji = Account::where('code', '5002')->first();

            $materials = [
                ['id' => 1, 'code' => 'MAT-JTB', 'name' => 'Kain Jetblack', 'category' => 'raw_material', 'unit' => 'meter', 'standard_cost' => 60000.00, 'stock' => 50.0, 'min_stock' => 10.0, 'account_id' => $accBahan?->id],
                ['id' => 2, 'code' => 'MAT-DRM', 'name' => 'Furing Durmil', 'category' => 'supporting_material', 'unit' => 'meter', 'standard_cost' => 13000.00, 'stock' => 50.0, 'min_stock' => 10.0, 'account_id' => $accBahan?->id],
                ['id' => 3, 'code' => 'MAT-VSL', 'name' => 'Vislin', 'category' => 'supporting_material', 'unit' => 'meter', 'standard_cost' => 7000.00, 'stock' => 40.0, 'min_stock' => 10.0, 'account_id' => $accBahan?->id],
                ['id' => 4, 'code' => 'MAT-BSB', 'name' => 'Busa Biasa', 'category' => 'supporting_material', 'unit' => 'pcs', 'standard_cost' => 910.00, 'stock' => 100.0, 'min_stock' => 20.0, 'account_id' => $accBahan?->id],
                ['id' => 5, 'code' => 'MAT-KCB', 'name' => 'Kancing Besar Niko', 'category' => 'accessory', 'unit' => 'pcs', 'standard_cost' => 700.00, 'stock' => 200.0, 'min_stock' => 50.0, 'account_id' => $accBahan?->id],
                ['id' => 6, 'code' => 'MAT-RNG', 'name' => 'Ring Lengan', 'category' => 'accessory', 'unit' => 'pasang', 'standard_cost' => 3500.00, 'stock' => 50.0, 'min_stock' => 10.0, 'account_id' => $accBahan?->id],
                ['id' => 7, 'code' => 'MAT-KCK', 'name' => 'Kancing Kecil Niko', 'category' => 'accessory', 'unit' => 'pcs', 'standard_cost' => 350.00, 'stock' => 300.0, 'min_stock' => 50.0, 'account_id' => $accBahan?->id],
                ['id' => 8, 'code' => 'MAT-KKJ', 'name' => 'Kain Keras Jas', 'category' => 'supporting_material', 'unit' => 'cm', 'standard_cost' => 300.00, 'stock' => 500.0, 'min_stock' => 100.0, 'account_id' => $accBahan?->id],
                ['id' => 9, 'code' => 'BOP-ELC', 'name' => 'Biaya Listrik Pabrik', 'category' => 'overhead', 'unit' => 'pcs', 'standard_cost' => 7000.00, 'stock' => 0, 'min_stock' => 0, 'account_id' => $accBOP?->id],
                ['id' => 10, 'code' => 'LAB-JHT', 'name' => 'Upah Penjahit Jas Reguler', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 50000.00, 'stock' => 0, 'min_stock' => 0, 'account_id' => $accGaji?->id],
                ['id' => 11, 'code' => 'LAB-STR', 'name' => 'Biaya Setrika Uap & Finishing', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 4800.00, 'stock' => 0, 'min_stock' => 0, 'account_id' => $accGaji?->id],
                ['id' => 12, 'code' => 'BOP-ETC', 'name' => 'Biaya Operasional Lain-Lain', 'category' => 'overhead', 'unit' => 'pcs', 'standard_cost' => 2000.00, 'stock' => 0, 'min_stock' => 0, 'account_id' => $accBOP?->id],
            ];

            foreach ($materials as $mat) {
                $createdMat = Material::create($mat);

                // Buat mutasi saldo awal untuk bahan fisik
                if ($createdMat->isPhysical() && $createdMat->stock > 0) {
                    MaterialStockMovement::create([
                        'material_id' => $createdMat->id,
                        'type' => 'in',
                        'quantity' => $createdMat->stock,
                        'unit_cost' => $createdMat->standard_cost,
                        'reference_type' => 'purchase',
                        'reference_number' => 'INIT-STOCK',
                        'notes' => 'Saldo awal persediaan bahan baku gudang.',
                    ]);
                }
            }

            // Hubungkan dengan produk pertama jika ada
            $productJas = Product::first();

            // Seed Header Kartu HPP
            $costSheet = CostSheet::create([
                'id' => 1,
                'code' => 'HPP-JAS-REG-JTB',
                'name' => 'Jas Reguler Jetblack',
                'category' => 'jas_reguler',
                'fabric_type' => 'Jetblack',
                'product_id' => $productJas?->id,
                'description' => 'Standar kartu HPP pembuatan jas reguler bahan jetblack furing durmil.',
            ]);

            // Seed Varian Ukuran S dan M
            $variantS = CostSheetVariant::create([
                'id' => 1,
                'cost_sheet_id' => $costSheet->id,
                'size' => 'S',
                'total_material_cost' => 143520.00,
                'total_labor_cost' => 54800.00,
                'total_overhead_cost' => 9000.00,
                'total_cost_price' => 207320.00,
                'suggested_selling_price' => 450000.00,
                'notes' => 'Varian ukuran S standar slim fit',
            ]);

            $variantM = CostSheetVariant::create([
                'id' => 2,
                'cost_sheet_id' => $costSheet->id,
                'size' => 'M',
                'total_material_cost' => 151520.00,
                'total_labor_cost' => 54800.00,
                'total_overhead_cost' => 9000.00,
                'total_cost_price' => 215320.00,
                'suggested_selling_price' => 450000.00,
                'notes' => 'Varian ukuran M standar',
            ]);

            // Resep Komponen Size S
            $itemsS = [
                ['material_id' => 1, 'quantity' => 1.600, 'unit_price' => 60000.00, 'subtotal' => 96000.00],
                ['material_id' => 2, 'quantity' => 1.600, 'unit_price' => 13000.00, 'subtotal' => 20800.00],
                ['material_id' => 3, 'quantity' => 1.300, 'unit_price' => 7000.00,  'subtotal' => 9100.00],
                ['material_id' => 4, 'quantity' => 2.000, 'unit_price' => 910.00,   'subtotal' => 1820.00],
                ['material_id' => 5, 'quantity' => 1.000, 'unit_price' => 700.00,   'subtotal' => 700.00],
                ['material_id' => 6, 'quantity' => 2.000, 'unit_price' => 3500.00,  'subtotal' => 7000.00],
                ['material_id' => 7, 'quantity' => 6.000, 'unit_price' => 350.00,   'subtotal' => 2100.00],
                ['material_id' => 8, 'quantity' => 20.000, 'unit_price' => 300.00,  'subtotal' => 6000.00],
                ['material_id' => 9, 'quantity' => 1.000, 'unit_price' => 7000.00,  'subtotal' => 7000.00],
                ['material_id' => 10, 'quantity' => 1.000, 'unit_price' => 50000.00, 'subtotal' => 50000.00],
                ['material_id' => 11, 'quantity' => 1.000, 'unit_price' => 4800.00,  'subtotal' => 4800.00],
                ['material_id' => 12, 'quantity' => 1.000, 'unit_price' => 2000.00,  'subtotal' => 2000.00],
            ];

            foreach ($itemsS as $it) {
                CostSheetItem::create([
                    'cost_sheet_variant_id' => $variantS->id,
                    'material_id' => $it['material_id'],
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['unit_price'],
                    'subtotal' => $it['subtotal'],
                ]);
            }

            // Resep Komponen Size M
            $itemsM = [
                ['material_id' => 1, 'quantity' => 1.700, 'unit_price' => 60000.00, 'subtotal' => 102000.00],
                ['material_id' => 2, 'quantity' => 1.700, 'unit_price' => 13000.00, 'subtotal' => 22100.00],
                ['material_id' => 3, 'quantity' => 1.400, 'unit_price' => 7000.00,  'subtotal' => 9800.00],
                ['material_id' => 4, 'quantity' => 2.000, 'unit_price' => 910.00,   'subtotal' => 1820.00],
                ['material_id' => 5, 'quantity' => 1.000, 'unit_price' => 700.00,   'subtotal' => 700.00],
                ['material_id' => 6, 'quantity' => 2.000, 'unit_price' => 3500.00,  'subtotal' => 7000.00],
                ['material_id' => 7, 'quantity' => 6.000, 'unit_price' => 350.00,   'subtotal' => 2100.00],
                ['material_id' => 8, 'quantity' => 20.000, 'unit_price' => 300.00,  'subtotal' => 6000.00],
                ['material_id' => 9, 'quantity' => 1.000, 'unit_price' => 7000.00,  'subtotal' => 7000.00],
                ['material_id' => 10, 'quantity' => 1.000, 'unit_price' => 50000.00, 'subtotal' => 50000.00],
                ['material_id' => 11, 'quantity' => 1.000, 'unit_price' => 4800.00,  'subtotal' => 4800.00],
                ['material_id' => 12, 'quantity' => 1.000, 'unit_price' => 2000.00,  'subtotal' => 2000.00],
            ];

            foreach ($itemsM as $it) {
                CostSheetItem::create([
                    'cost_sheet_variant_id' => $variantM->id,
                    'material_id' => $it['material_id'],
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['unit_price'],
                    'subtotal' => $it['subtotal'],
                ]);
            }

            // Sync cost price to product if exists
            if ($productJas) {
                $productJas->update([
                    'cost_sheet_variant_id' => $variantS->id,
                    'cost_price' => $variantS->total_cost_price,
                ]);
            }
        }
    }
}
