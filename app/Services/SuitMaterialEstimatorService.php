<?php

namespace App\Services;

use App\Models\Material;

class SuitMaterialEstimatorService
{
    /**
     * Katalog standar HPP (Harga Pokok Produksi) master bahan & komponen biaya.
     * Digunakan sebagai acuan patokan harga standar dan fallback bila master belum di-seed.
     */
    public const DEFAULT_CATALOG = [
        // --- Bahan Pembantu & Interlining ---
        'MAT-DRM' => ['name' => 'Furing Durmil', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 13000.0],
        'MAT-VSL' => ['name' => 'Vislin Interlining', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 7000.0],
        'MAT-MRG' => ['name' => 'Mori Gula Premium', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 20000.0],
        'MAT-HTX' => ['name' => 'Hantex Canvas Bespoke', 'category' => 'supporting_material', 'unit' => 'meter', 'cost' => 30000.0],
        'MAT-BSB' => ['name' => 'Busa Bahu Biasa', 'category' => 'supporting_material', 'unit' => 'pcs', 'cost' => 910.0],
        'MAT-BST' => ['name' => 'Busa Bahu Tebal Jas', 'category' => 'supporting_material', 'unit' => 'pcs', 'cost' => 2500.0],
        'MAT-KKJ' => ['name' => 'Kain Keras Jas', 'category' => 'supporting_material', 'unit' => 'cm', 'cost' => 300.0],
        'MAT-KKC' => ['name' => 'Kain Keras Pinggang Celana', 'category' => 'supporting_material', 'unit' => 'cm', 'cost' => 210.0],

        // --- Aksesoris & Trims ---
        'ACC-KCB' => ['name' => 'Kancing Besar Niko', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 700.0],
        'ACC-KCK' => ['name' => 'Kancing Kecil Niko', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 350.0],
        'ACC-RNG' => ['name' => 'Ring Lengan Jas', 'category' => 'accessory', 'unit' => 'pasang', 'cost' => 3500.0],
        'ACC-RSL-C' => ['name' => 'Resleting YKK Celana Kecil', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 5000.0],
        'ACC-HAK' => ['name' => 'Hak Celana Logam', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 667.0],
        'ACC-RIB' => ['name' => 'Rib Jaket Elastis', 'category' => 'accessory', 'unit' => 'meter', 'cost' => 101000.0],
        'ACC-KNB' => ['name' => 'Knob Snap Button Besi', 'category' => 'accessory', 'unit' => 'pcs', 'cost' => 1000.0],

        // --- Biaya Tenaga Kerja Langsung (Jasa Jahit) ---
        'LAB-JHT-REG' => ['name' => 'Upah Penjahit Jas Reguler', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 50000.0],
        'LAB-JHT-PRM' => ['name' => 'Upah Penjahit Jas Premium', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 150000.0],
        'LAB-JHT-EXC' => ['name' => 'Upah Penjahit Jas Master Exclusive', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 250000.0],
        'LAB-JHT-CLN' => ['name' => 'Upah Penjahit Celana Formal', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 30000.0],
        'LAB-JHT-VST' => ['name' => 'Upah Penjahit Rompi / Vest', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 37000.0],
        'LAB-STR' => ['name' => 'Biaya Setrika Uap & Finishing', 'category' => 'direct_labor', 'unit' => 'pcs', 'cost' => 4800.0],

        // --- Kost Tambahan / Biaya Overhead Pabrik (BOP) ---
        'BOP-ELC-7' => ['name' => 'Beban Listrik Operasional (Standar)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7000.0],
        'BOP-ELC-10' => ['name' => 'Beban Listrik Operasional (Premium)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 10000.0],
        'BOP-ELC-EXC' => ['name' => 'Beban Listrik Khusus (Exclusive)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 14902.0],
        'BOP-ELC-74' => ['name' => 'Beban Listrik Konveksi (Celana/Jaket)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7451.0],
        'BOP-PCK' => ['name' => 'Biaya Packing & Plastik Jas', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 1800.0],
        'BOP-ETC-2' => ['name' => 'Biaya Lain-lain (Benang & Jarum)', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 2000.0],
        'BOP-ETC-7' => ['name' => 'Biaya Lain-lain & Depresiasi Mesin', 'category' => 'overhead', 'unit' => 'pcs', 'cost' => 7000.0],
    ];

    /**
     * Estimasi kebutuhan bahan, biaya produksi (HPP), dan rekomendasi harga jual.
     * Mengintegrasikan secara otomatis bahan utama, bahan tambahan, jasa jahit, dan overhead listrik.
     *
     * @param  string  $suitType  Jenis pakaian (jas_blazer_pria, tuksedo, setelan_formal, kemeja, celana_denim, dll)
     * @param  array  $measurements  Ukuran tubuh (chest, waist, height, jacket_length, sleeve_length, trouser_length)
     * @param  array  $customOptions  Opsi custom (material_id, quality_tier, fabric_price_per_meter, labor_cost, target_margin_percent)
     * @param  array|null  $aiVisionData  Data hasil analisa AI Vision jika tersedia
     */
    public function estimate(
        string $suitType,
        array $measurements = [],
        array $customOptions = [],
        ?array $aiVisionData = null
    ): array {
        $height = (float) ($measurements['height'] ?? 170); // cm
        $chest = (float) ($measurements['chest'] ?? 96);    // cm
        $waist = (float) ($measurements['waist'] ?? 82);    // cm
        $jacketLength = (float) ($measurements['jacket_length'] ?? round($height * 0.43, 1));
        $sleeveLength = (float) ($measurements['sleeve_length'] ?? round($height * 0.36, 1));

        // Tailor Pattern Formula: Proporsi tubuh terhadap standar ukuran M
        $heightRatio = $height / 170.0;
        $chestRatio = $chest / 96.0;
        $waistRatio = $waist / 82.0;

        if (! empty($measurements['jacket_length']) && ! empty($measurements['sleeve_length'])) {
            $lengthRatio = (($jacketLength / 73.0) * 0.6) + (($sleeveLength / 61.0) * 0.4);
            $bodyMultiplier = ($lengthRatio * 0.50) + ($chestRatio * 0.35) + ($waistRatio * 0.15);
        } else {
            $bodyMultiplier = ($heightRatio * 0.45) + ($chestRatio * 0.40) + ($waistRatio * 0.15);
        }

        $bodyMultiplier = round(max(0.85, min(1.35, $bodyMultiplier)), 3);

        // Quality Tier: reguler, premium, exclusive
        $tier = $customOptions['quality_tier'] ?? $this->inferQualityTier($suitType, $customOptions);

        // Ambil data Material dari database agar selalu real-time mengikuti stok & biaya master
        $materialsByCode = Material::whereIn('code', array_keys(self::DEFAULT_CATALOG))
            ->get()
            ->keyBy('code');

        // 1. Kebutuhan Bahan Utama (Kain)
        $mainFabricMeters = $this->calculateMainFabricMeters($suitType, $bodyMultiplier);
        $selectedMaterial = null;
        if (! empty($customOptions['material_id'])) {
            $selectedMaterial = Material::find($customOptions['material_id']);
        }

        $mainFabricPricePerMeter = $selectedMaterial
            ? (float) $selectedMaterial->standard_cost
            : (float) ($customOptions['fabric_price_per_meter'] ?? $this->getDefaultFabricPrice($suitType));

        $mainFabricDesc = $selectedMaterial
            ? $selectedMaterial->name
            : (! empty($customOptions['fabric_type']) ? $customOptions['fabric_type'] : $this->getDefaultFabricDesc($suitType));

        $mainFabricCost = round($mainFabricMeters * $mainFabricPricePerMeter);

        // 2. Kebutuhan Bahan Tambahan & Aksesoris (Otomatis terintegrasi dari tabel HPP)
        $supportingItems = $this->buildSupportingMaterialItems($suitType, $bodyMultiplier, $tier, $materialsByCode, $customOptions);
        $totalSupportingCost = array_sum(array_column($supportingItems, 'subtotal'));
        $totalMaterialCost = $mainFabricCost + $totalSupportingCost;

        // Ekstraksi nilai furing, kufner, dan aksesoris untuk kompatibilitas data
        $liningItem = collect($supportingItems)->first(fn ($i) => $i['code'] === 'MAT-DRM');
        $liningMeters = $liningItem ? $liningItem['quantity'] : 0.0;
        $liningCost = $liningItem ? $liningItem['subtotal'] : 0.0;

        $interliningItem = collect($supportingItems)->first(fn ($i) => in_array($i['code'], ['MAT-VSL', 'MAT-MRG', 'MAT-HTX'], true));
        $interliningMeters = $interliningItem ? $interliningItem['quantity'] : 0.0;
        $interliningCost = $interliningItem ? $interliningItem['subtotal'] : 0.0;

        $accessoryItems = array_filter($supportingItems, fn ($i) => ! in_array($i['code'], ['MAT-DRM', 'MAT-VSL', 'MAT-MRG', 'MAT-HTX'], true));
        $accessoriesCost = array_sum(array_column($accessoryItems, 'subtotal'));
        $accessoriesNotes = implode(', ', array_map(fn ($i) => "{$i['name']} ({$i['quantity']} {$i['unit']})", $accessoryItems));

        // 3. Jasa Jahit & Finishing (Direct Labor) berdasarkan acuan tabel HPP
        $laborItems = $this->buildLaborItems($suitType, $tier, $materialsByCode);
        $standardLaborCost = array_sum(array_column($laborItems, 'subtotal'));
        // Jika user memasukkan ongkos jahit kustom, gunakan nilai kustom tersebut
        $laborCost = ! empty($customOptions['labor_cost'])
            ? (float) $customOptions['labor_cost']
            : $standardLaborCost;

        // 4. Kost Tambahan / Beban Overhead Pabrik (Listrik, Benang, Packing) berdasarkan acuan tabel HPP
        $overheadItems = $this->buildOverheadItems($suitType, $tier, $materialsByCode);
        $overheadCost = array_sum(array_column($overheadItems, 'subtotal'));

        // 5. Total HPP (Cost of Goods Manufactured / COGM)
        $totalCost = $totalMaterialCost + $laborCost + $overheadCost;

        // 6. Rekomendasi Harga Jual & Proyeksi Laba
        $targetMarginPercent = (float) ($customOptions['target_margin_percent'] ?? 45);
        $suggestedPrice = round(($totalCost / (1 - ($targetMarginPercent / 100))) / 10000) * 10000;
        $projectedProfit = $suggestedPrice - $totalCost;
        $actualMargin = $suggestedPrice > 0 ? round(($projectedProfit / $suggestedPrice) * 100, 1) : 0;

        return [
            'suit_type' => $suitType,
            'quality_tier' => $tier,
            'summary' => $this->generateSummaryText(
                $suitType,
                $mainFabricMeters,
                $mainFabricDesc,
                $supportingItems,
                $laborCost,
                $overheadCost,
                $totalCost,
                $suggestedPrice
            ),
            'materials' => [
                'main_fabric_meters' => $mainFabricMeters,
                'main_fabric_description' => $mainFabricDesc,
                'main_fabric_unit_price' => $mainFabricPricePerMeter,
                'main_fabric_total_cost' => $mainFabricCost,
                'lining_meters' => $liningMeters,
                'lining_total_cost' => $liningCost,
                'interlining_kufner_meters' => $interliningMeters,
                'interlining_total_cost' => $interliningCost,
                'accessories_cost' => $accessoriesCost,
                'accessories_notes' => $accessoriesNotes,
                'supporting_materials' => $supportingItems,
                'total_supporting_cost' => $totalSupportingCost,
                'total_material_cost' => $totalMaterialCost,
                'inventory' => $selectedMaterial ? [
                    'material_id' => $selectedMaterial->id,
                    'material_name' => $selectedMaterial->name,
                    'available_stock' => (float) $selectedMaterial->stock,
                    'unit' => $selectedMaterial->unit,
                    'is_sufficient' => $selectedMaterial->stock >= $mainFabricMeters,
                ] : null,
            ],
            'labor' => [
                'labor_cost' => $laborCost,
                'description' => 'Upah pengerjaan penjahit pola, jahit bespoke, pemasangan furing, dan finishing setrika uap.',
                'items' => $laborItems,
            ],
            'overhead' => [
                'total_overhead_cost' => $overheadCost,
                'description' => 'Kost tambahan operasional (listrik, benang, jarum & packing) mengacu tabel HPP.',
                'items' => $overheadItems,
            ],
            'financial' => [
                'material_cost' => $totalMaterialCost,
                'raw_material_cost' => $mainFabricCost,
                'supporting_material_cost' => $totalSupportingCost,
                'labor_cost' => $laborCost,
                'overhead_cost' => $overheadCost,
                'total_cost' => $totalCost,
                'suggested_price' => $suggestedPrice,
                'projected_profit' => $projectedProfit,
                'profit_margin_percent' => $actualMargin,
            ],
            'ai_insights' => $aiVisionData ?? [
                'model_detected' => $suitType,
                'lapel_style' => 'Notch Lapel Standar',
                'button_layout' => '2 Kancing Single-Breasted',
                'recommended_cut' => 'Modern Slim Fit',
            ],
        ];
    }

    /**
     * Hitung meteran kain utama berdasarkan jenis pakaian dan rasio proporsi tubuh.
     */
    protected function calculateMainFabricMeters(string $suitType, float $bodyMultiplier): float
    {
        return match ($suitType) {
            'tuksedo' => round(3.2 * $bodyMultiplier, 2),
            'setelan_formal' => round(4.2 * $bodyMultiplier, 2),
            'jaket', 'outwear' => round(2.2 * $bodyMultiplier, 2),
            'celana_denim' => round(1.6 * $bodyMultiplier, 2),
            'kemeja' => round(1.6 * $bodyMultiplier, 2),
            'polo', 'tshirt' => round(1.3 * $bodyMultiplier, 2),
            'custom_made_jas', 'jas_blazer_pria' => round(2.75 * $bodyMultiplier, 2),
            default => round(2.5 * $bodyMultiplier, 2),
        };
    }

    /**
     * Menentukan grade / tier kualitas berdasarkan jenis pakaian.
     */
    protected function inferQualityTier(string $suitType, array $customOptions = []): string
    {
        if (! empty($customOptions['quality_tier'])) {
            return $customOptions['quality_tier'];
        }

        return match ($suitType) {
            'tuksedo' => 'exclusive',
            'setelan_formal', 'custom_made_jas' => 'premium',
            default => 'reguler',
        };
    }

    /**
     * Menyusun daftar kebutuhan bahan tambahan (supporting materials & accessories)
     * otomatis terintegrasi dari tabel Harga Pokok Produksi (HPP).
     */
    protected function buildSupportingMaterialItems(
        string $suitType,
        float $bodyMultiplier,
        string $tier,
        $materialsByCode,
        array $customOptions = []
    ): array {
        $recipe = [];

        switch ($suitType) {
            case 'tuksedo':
                $recipe[] = ['code' => 'MAT-DRM', 'qty' => round(2.3 * $bodyMultiplier, 2)];
                $recipe[] = ['code' => 'MAT-HTX', 'qty' => 1.0];
                $recipe[] = ['code' => 'MAT-MRG', 'qty' => 1.4];
                $recipe[] = ['code' => 'MAT-BST', 'qty' => 2.0];
                $recipe[] = ['code' => 'MAT-KKJ', 'qty' => 20.0];
                $recipe[] = ['code' => 'ACC-KCB', 'qty' => 2.0];
                $recipe[] = ['code' => 'ACC-KCK', 'qty' => 6.0];
                $recipe[] = ['code' => 'ACC-RNG', 'qty' => 2.0];
                break;

            case 'setelan_formal':
                $recipe[] = ['code' => 'MAT-DRM', 'qty' => round(2.4 * $bodyMultiplier, 2)];
                $recipe[] = ['code' => ($tier === 'exclusive' ? 'MAT-HTX' : 'MAT-MRG'), 'qty' => round(1.4 * $bodyMultiplier, 2)];
                $recipe[] = ['code' => 'MAT-BST', 'qty' => 2.0];
                $recipe[] = ['code' => 'MAT-KKJ', 'qty' => 20.0];
                $recipe[] = ['code' => 'MAT-KKC', 'qty' => 40.0];
                $recipe[] = ['code' => 'ACC-RSL-C', 'qty' => 1.0];
                $recipe[] = ['code' => 'ACC-HAK', 'qty' => 1.0];
                $recipe[] = ['code' => 'ACC-KCB', 'qty' => 2.0];
                $recipe[] = ['code' => 'ACC-KCK', 'qty' => 6.0];
                $recipe[] = ['code' => 'ACC-RNG', 'qty' => 2.0];
                break;

            case 'celana_denim':
                $recipe[] = ['code' => 'MAT-KKC', 'qty' => 40.0];
                $recipe[] = ['code' => 'ACC-RSL-C', 'qty' => 1.0];
                $recipe[] = ['code' => 'ACC-HAK', 'qty' => 1.0];
                break;

            case 'jaket':
            case 'outwear':
                $recipe[] = ['code' => 'MAT-DRM', 'qty' => round(1.8 * $bodyMultiplier, 2)];
                $recipe[] = ['code' => 'MAT-VSL', 'qty' => 0.5];
                $recipe[] = ['code' => 'ACC-KNB', 'qty' => 6.0];
                break;

            case 'kemeja':
                $recipe[] = ['code' => 'MAT-VSL', 'qty' => 0.4];
                $recipe[] = ['code' => 'ACC-KCK', 'qty' => 8.0];
                break;

            case 'tshirt':
            case 'polo':
                if ($suitType === 'polo') {
                    $recipe[] = ['code' => 'ACC-KCK', 'qty' => 3.0];
                }
                break;

            case 'custom_made_jas':
            case 'jas_blazer_pria':
            default:
                $isHalfLined = ($customOptions['lining_style'] ?? null) === 'half_lined';
                $liningQty = $isHalfLined ? round(1.2 * $bodyMultiplier, 2) : round(1.7 * $bodyMultiplier, 2);
                $interliningCode = match ($tier) {
                    'exclusive' => 'MAT-HTX',
                    'premium' => 'MAT-MRG',
                    default => 'MAT-VSL',
                };
                $interliningQty = $isHalfLined ? 1.0 : round(1.4 * $bodyMultiplier, 2);
                $busaCode = in_array($tier, ['exclusive', 'premium'], true) ? 'MAT-BST' : 'MAT-BSB';

                $recipe[] = ['code' => 'MAT-DRM', 'qty' => $liningQty];
                $recipe[] = ['code' => $interliningCode, 'qty' => $interliningQty];
                $recipe[] = ['code' => $busaCode, 'qty' => 2.0];
                $recipe[] = ['code' => 'MAT-KKJ', 'qty' => 20.0];
                $recipe[] = ['code' => 'ACC-KCB', 'qty' => 2.0];
                $recipe[] = ['code' => 'ACC-KCK', 'qty' => 6.0];
                $recipe[] = ['code' => 'ACC-RNG', 'qty' => 2.0];
                break;
        }

        $items = [];
        foreach ($recipe as $r) {
            $items[] = $this->resolveMaterialComponent($r['code'], $r['qty'], $materialsByCode);
        }

        return $items;
    }

    /**
     * Menyusun komponen Biaya Tenaga Kerja Langsung (Jasa Jahit & Finishing).
     */
    protected function buildLaborItems(string $suitType, string $tier, $materialsByCode): array
    {
        $laborRecipe = [];

        switch ($suitType) {
            case 'tuksedo':
                $laborRecipe[] = ['code' => 'LAB-JHT-EXC', 'qty' => 1.0];
                $laborRecipe[] = ['code' => 'LAB-STR', 'qty' => 1.0];
                break;

            case 'setelan_formal':
                $jasLaborCode = $tier === 'exclusive' ? 'LAB-JHT-EXC' : 'LAB-JHT-PRM';
                $laborRecipe[] = ['code' => $jasLaborCode, 'qty' => 1.0];
                $laborRecipe[] = ['code' => 'LAB-JHT-CLN', 'qty' => 1.0];
                $laborRecipe[] = ['code' => 'LAB-STR', 'qty' => 1.0];
                break;

            case 'celana_denim':
                $laborRecipe[] = ['code' => 'LAB-JHT-CLN', 'qty' => 1.0];
                break;

            case 'jaket':
            case 'outwear':
                $laborRecipe[] = ['code' => 'LAB-JHT-REG', 'qty' => 1.0];
                break;

            case 'kemeja':
                $laborRecipe[] = ['code' => 'LAB-JHT-CLN', 'qty' => 1.0]; // Benchmark upah standar konveksi
                break;

            case 'tshirt':
            case 'polo':
                $laborRecipe[] = ['code' => 'LAB-STR', 'qty' => 1.0];
                break;

            case 'custom_made_jas':
            case 'jas_blazer_pria':
            default:
                $laborCode = match ($tier) {
                    'exclusive' => 'LAB-JHT-EXC',
                    'premium' => 'LAB-JHT-PRM',
                    default => 'LAB-JHT-REG',
                };
                $laborRecipe[] = ['code' => $laborCode, 'qty' => 1.0];
                $laborRecipe[] = ['code' => 'LAB-STR', 'qty' => 1.0];
                break;
        }

        $items = [];
        foreach ($laborRecipe as $r) {
            $items[] = $this->resolveMaterialComponent($r['code'], $r['qty'], $materialsByCode);
        }

        return $items;
    }

    /**
     * Menyusun komponen Biaya Overhead Pabrik (BOP) seperti beban listrik, benang & jarum, dan packing.
     */
    protected function buildOverheadItems(string $suitType, string $tier, $materialsByCode): array
    {
        $overheadRecipe = [];

        switch ($suitType) {
            case 'tuksedo':
                $overheadRecipe[] = ['code' => 'BOP-ELC-EXC', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-PCK', 'qty' => 1.0];
                break;

            case 'setelan_formal':
                $jasElcCode = $tier === 'exclusive' ? 'BOP-ELC-EXC' : 'BOP-ELC-10';
                $overheadRecipe[] = ['code' => $jasElcCode, 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ELC-74', 'qty' => 1.0]; // Listrik celana
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-PCK', 'qty' => 1.0];
                break;

            case 'celana_denim':
                $overheadRecipe[] = ['code' => 'BOP-ELC-74', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                break;

            case 'jaket':
            case 'outwear':
                $overheadRecipe[] = ['code' => 'BOP-ELC-74', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-PCK', 'qty' => 1.0];
                break;

            case 'kemeja':
            case 'polo':
            case 'tshirt':
                $overheadRecipe[] = ['code' => 'BOP-ELC-7', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                break;

            case 'custom_made_jas':
            case 'jas_blazer_pria':
            default:
                $elcCode = match ($tier) {
                    'exclusive' => 'BOP-ELC-EXC',
                    'premium' => 'BOP-ELC-10',
                    default => 'BOP-ELC-7',
                };
                $overheadRecipe[] = ['code' => $elcCode, 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-ETC-2', 'qty' => 1.0];
                $overheadRecipe[] = ['code' => 'BOP-PCK', 'qty' => 1.0];
                break;
        }

        $items = [];
        foreach ($overheadRecipe as $r) {
            $items[] = $this->resolveMaterialComponent($r['code'], $r['qty'], $materialsByCode);
        }

        return $items;
    }

    /**
     * Resolusi komponen item dari database Material atau katalog default standar HPP.
     */
    protected function resolveMaterialComponent(string $code, float $quantity, $materialsByCode): array
    {
        $material = $materialsByCode->get($code);
        $default = self::DEFAULT_CATALOG[$code] ?? [
            'name' => $code,
            'category' => 'supporting_material',
            'unit' => 'pcs',
            'cost' => 0.0,
        ];

        $name = $material ? $material->name : $default['name'];
        $category = $material ? $material->category : $default['category'];
        $unit = $material ? $material->unit : $default['unit'];
        $unitPrice = $material ? (float) $material->standard_cost : (float) $default['cost'];
        $subtotal = round($quantity * $unitPrice);
        $stock = $material ? (float) $material->stock : 0.0;
        $isSufficient = $material ? ($material->stock >= $quantity) : true;

        return [
            'code' => $code,
            'material_id' => $material?->id,
            'name' => $name,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'stock' => $stock,
            'is_sufficient' => $isSufficient,
        ];
    }

    /**
     * Deskripsi bahan utama standar berdasarkan jenis pakaian.
     */
    protected function getDefaultFabricDesc(string $suitType): string
    {
        return match ($suitType) {
            'tuksedo' => 'Kain Wool Blend / Black Tuxedo Barathea + Satin Silk Lapel Facings',
            'setelan_formal' => 'Kain Wool Silk / Poliviscose High Twist untuk 1 Setel (Jas + Celana Bahan)',
            'jaket', 'outwear' => 'Kain Outerwear (Twill / Semi-Wool / Canvas)',
            'celana_denim' => 'Kain Denim 12-14oz Raw / Selvedge Denim',
            'kemeja' => 'Kain Katun Dobby / Poplin / Oxford 100% Katun',
            'polo' => 'Kain Pique Lacoste Cotton',
            'tshirt' => 'Kain Cotton Combed 24s/30s',
            default => 'Kain Jas / Blazer Semi-Wool atau Cotton Twill Khaki',
        };
    }

    /**
     * Estimasi harga kain default per meter berdasarkan kategori.
     */
    protected function getDefaultFabricPrice(string $suitType): float
    {
        return match ($suitType) {
            'tuksedo' => 320000,
            'setelan_formal', 'custom_made_jas' => 250000,
            'jas_blazer_pria' => 220000,
            'jaket', 'outwear' => 140000,
            'celana_denim' => 95000,
            'kemeja' => 85000,
            'polo' => 65000,
            'tshirt' => 50000,
            default => 200000,
        };
    }

    /**
     * Ringkasan naratif penjelasan kebutuhan bahan dan struktur HPP.
     */
    protected function generateSummaryText(
        string $suitType,
        float $mainFabricMeters,
        string $mainFabricDesc,
        array $supportingItems,
        float $laborCost,
        float $overheadCost,
        float $totalCost,
        float $suggestedPrice
    ): string {
        $kategori = ucwords(str_replace('_', ' ', $suitType));
        $supportingCount = count($supportingItems);

        return "Untuk pembuatan {$kategori}, dibutuhkan kain utama {$mainFabricMeters}m ({$mainFabricDesc}), terintegrasi otomatis dengan {$supportingCount} bahan pendukung & aksesoris (furing, interlining, busa, kancing, dll)."
            .' Biaya pengerjaan jahit Rp '.number_format($laborCost, 0, ',', '.')
            .' dan kost overhead listrik operasional Rp '.number_format($overheadCost, 0, ',', '.')
            .'. Total HPP acuan produksi adalah Rp '.number_format($totalCost, 0, ',', '.')
            .' dengan rekomendasi harga jual ke klien sebesar Rp '.number_format($suggestedPrice, 0, ',', '.').'.';
    }
}
