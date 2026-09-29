<?php

namespace Database\Seeders;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Allowance;
use App\Models\CustomSuitOrder;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\Product;
use App\Models\RetailSale;
use App\Models\RetailSaleItem;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing and demonstration purposes.
     * Seeder ini HANYA untuk pengujian lokal / staging / demo dan TIDAK dijalankan di production.
     */
    public function run(): void
    {
        $kas = Account::where('code', '1001')->first() ?? Account::first();
        $bca = Account::where('code', '1002')->first() ?? $kas;
        $pendapatan = Account::where('code', '4001')->first() ?? $kas;
        $bebanOps = Account::where('code', '5001')->first() ?? $kas;
        $bebanGaji = Account::where('code', '5002')->first() ?? $kas;
        $bebanPerlengkapan = Account::where('code', '5003')->first() ?? $kas;

        // =========================================================================
        // 1. DATA DUMMY PRODUK RETAIL & SEWA
        // =========================================================================
        $sampleProducts = [
            [
                'code' => 'JAS-001',
                'name' => 'Italian Slim Fit Navy Blazer',
                'category' => 'jas_blazer_pria',
                'size' => 'L',
                'color' => 'Navy Blue',
                'cost_price' => 750000,
                'selling_price' => 1450000,
                'rental_price' => 350000,
                'stock' => 12,
                'min_stock' => 3,
                'point_reward' => 20,
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
                'rental_price' => 600000,
                'stock' => 5,
                'min_stock' => 2,
                'point_reward' => 25,
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
                'rental_price' => 75000,
                'stock' => 24,
                'min_stock' => 5,
                'point_reward' => 5,
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
                'rental_price' => 100000,
                'stock' => 15,
                'min_stock' => 4,
                'point_reward' => 10,
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
                'rental_price' => 45000,
                'stock' => 30,
                'min_stock' => 5,
                'point_reward' => 5,
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
                'rental_price' => 500000,
                'stock' => 8,
                'min_stock' => 2,
                'point_reward' => 22,
                'description' => 'Setelan jas dan celana bahan lengkap untuk acara resmi & pesta.',
            ],
        ];

        foreach ($sampleProducts as $p) {
            Product::updateOrCreate(['code' => $p['code']], $p);
        }

        // =========================================================================
        // 2. DATA DUMMY KARYAWAN & CS
        // =========================================================================
        $cs1 = Employee::updateOrCreate(
            ['phone' => '081234567891'],
            [
                'name' => 'Siti Rahma',
                'role' => EmployeeRole::Cs,
                'position' => 'Senior Customer Service',
                'telegram_user_id' => '11223344',
                'telegram_username' => 'siti_cs_seven',
                'base_salary' => 3200000,
                'current_points' => 320,
                'rate_per_point' => 1400,
                'claim_bonus' => true,
                'is_on_duty' => true,
                'pay_day' => 25,
                'asset_account_id' => $kas->id,
                'status' => 'active',
            ]
        );

        $cs2 = Employee::updateOrCreate(
            ['phone' => '081234567892'],
            [
                'name' => 'Budi Santoso',
                'role' => EmployeeRole::Cs,
                'position' => 'Customer Service',
                'telegram_user_id' => '22334455',
                'telegram_username' => 'budi_cs_seven',
                'base_salary' => 3000000,
                'current_points' => 210,
                'rate_per_point' => 1000,
                'claim_bonus' => false,
                'is_on_duty' => false,
                'pay_day' => 25,
                'asset_account_id' => $kas->id,
                'status' => 'active',
            ]
        );

        Employee::updateOrCreate(
            ['phone' => '081234567893'],
            [
                'name' => 'Agus Sudrajat',
                'role' => EmployeeRole::Staff,
                'position' => 'Master Tailor & Penjahit',
                'telegram_user_id' => '33445566',
                'telegram_username' => 'agus_tailor',
                'base_salary' => 3500000,
                'current_points' => 140,
                'rate_per_point' => 0,
                'claim_bonus' => true,
                'is_on_duty' => false,
                'pay_day' => 25,
                'asset_account_id' => $bca->id,
                'status' => 'active',
            ]
        );

        Employee::updateOrCreate(
            ['phone' => '081234567894'],
            [
                'name' => 'Maya Anggraini',
                'role' => EmployeeRole::Akuntan,
                'position' => 'Finance & Accounting',
                'telegram_user_id' => '44556677',
                'telegram_username' => 'maya_finance',
                'base_salary' => 4500000,
                'current_points' => 0,
                'rate_per_point' => 0,
                'claim_bonus' => true,
                'is_on_duty' => false,
                'pay_day' => 25,
                'asset_account_id' => $bca->id,
                'status' => 'active',
            ]
        );

        // =========================================================================
        // 3. DATA DUMMY TUNJANGAN KARYAWAN
        // =========================================================================
        Allowance::updateOrCreate(
            ['name' => 'Tunjangan Uang Makan Harian'],
            [
                'amount' => 500000,
                'target_type' => 'all',
                'target_role' => null,
                'notes' => 'Subsidi makan siang seluruh staf dan karyawan aktif',
            ]
        );

        Allowance::updateOrCreate(
            ['name' => 'Tunjangan Transportasi CS'],
            [
                'amount' => 300000,
                'target_type' => 'role',
                'target_role' => 'cs',
                'notes' => 'Tunjangan mobilitas dan operasional customer service',
            ]
        );

        // =========================================================================
        // 4. DATA DUMMY HISTORI TRANSAKSI KEUANGAN (JURNAL UMUM & GRAFIK)
        // =========================================================================
        $currentYear = (int) now()->format('Y');

        $samples = [
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

        // =========================================================================
        // 5. DATA DUMMY TRANSAKSI KASIR RETAIL
        // =========================================================================
        $blazer = Product::where('code', 'JAS-001')->first();
        if ($blazer) {
            $sale = RetailSale::updateOrCreate(
                ['invoice_number' => 'INV-DUMMY-001'],
                [
                    'employee_id' => $cs1->id,
                    'account_id' => $kas->id,
                    'customer_name' => 'Dimas Prakoso',
                    'customer_phone' => '081299887766',
                    'transaction_type' => 'sale',
                    'sale_date' => now()->subDays(2),
                    'total_amount' => 1450000,
                    'total_cost' => 750000,
                    'payment_method' => 'cash',
                    'notes' => 'Transaksi sampel kasir retail toko',
                    'created_at' => now()->subDays(2),
                ]
            );

            RetailSaleItem::updateOrCreate(
                ['retail_sale_id' => $sale->id, 'product_id' => $blazer->id],
                [
                    'transaction_type' => 'sale',
                    'quantity' => 1,
                    'unit_cost_price' => 750000,
                    'unit_selling_price' => 1450000,
                    'unit_rental_price' => 350000,
                    'subtotal' => 1450000,
                ]
            );
        }

        // =========================================================================
        // 6. DATA DUMMY PESANAN JAS CUSTOM (TAILOR)
        // =========================================================================
        $jetblack = Material::where('code', 'MAT-JTB')->first();
        CustomSuitOrder::updateOrCreate(
            ['order_number' => 'CST-DUMMY-001'],
            [
                'employee_id' => $cs1->id,
                'account_id' => $bca->id,
                'customer_name' => 'Rian Hidayat',
                'customer_phone' => '081388776655',
                'order_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'suit_type' => 'jas_blazer_pria',
                'material_id' => $jetblack?->id,
                'body_measurements' => [
                    'chest' => 98.0,
                    'waist' => 84.0,
                    'shoulder' => 46.0,
                    'sleeve_length' => 62.0,
                    'jacket_length' => 74.0,
                ],
                'material_cost' => 300000,
                'labor_cost' => 450000,
                'overhead_cost' => 50000,
                'total_cost' => 800000,
                'total_price' => 2500000,
                'down_payment' => 1000000,
                'payment_status' => 'partial_dp',
                'production_status' => 'fitting',
                'notes' => 'Pesanan sampel jas custom fitting pertama',
                'created_at' => now()->subDays(5),
            ]
        );
    }
}
