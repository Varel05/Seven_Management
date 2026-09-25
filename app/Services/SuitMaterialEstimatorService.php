<?php

namespace App\Services;

use App\Models\Material;

class SuitMaterialEstimatorService
{
    /**
     * Estimasi kebutuhan bahan, biaya produksi (HPP), dan rekomendasi harga jual.
     *
     * @param  string  $suitType  Jenis pakaian (misal: jas_blazer_pria, tuksedo, setelan_formal, kemeja, celana_denim)
     * @param  array  $measurements  Ukuran tubuh (chest, waist, height, weight, jacket_length, trouser_length)
     * @param  array  $customOptions  Opsi tambahan seperti tipe kain, estimasi harga kain per meter, ongkos jahit kustom
     * @param  array|null  $aiVisionData  Data hasil analisa AI (Gemini Vision) dari n8n jika tersedia
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

        // Formula proporsional pola potong penjahit profesional (Tailor Pattern Formula)
        // Menghitung rasio proporsi tubuh terhadap standar ukuran M (Tinggi 170cm, Dada 96cm, Pinggang 82cm)
        $heightRatio = $height / 170.0;
        $chestRatio = $chest / 96.0;
        $waistRatio = $waist / 82.0;

        // Jika panjang jas dan lengan diisi spesifik, perhitungkan langsung dalam rasio pola badan
        if (! empty($measurements['jacket_length']) && ! empty($measurements['sleeve_length'])) {
            $lengthRatio = (($jacketLength / 73.0) * 0.6) + (($sleeveLength / 61.0) * 0.4);
            $bodyMultiplier = ($lengthRatio * 0.50) + ($chestRatio * 0.35) + ($waistRatio * 0.15);
        } else {
            $bodyMultiplier = ($heightRatio * 0.45) + ($chestRatio * 0.40) + ($waistRatio * 0.15);
        }

        // Clamp agar tetap dalam rentang rasional kebutuhan pola jas (minimal 0.85, maksimal 1.35)
        $bodyMultiplier = round(max(0.85, min(1.35, $bodyMultiplier)), 3);

        // 1. Hitung kebutuhan bahan baku utama & pelengkap
        $materialRequirements = $this->calculateMaterials($suitType, $bodyMultiplier, $aiVisionData, $customOptions);

        // Cek apakah bahan kain dipilih dari master materials gudang
        $selectedMaterial = null;
        if (! empty($customOptions['material_id'])) {
            $selectedMaterial = Material::find($customOptions['material_id']);
            if ($selectedMaterial) {
                $materialRequirements['main_fabric_desc'] = $selectedMaterial->name;
            }
        }

        // 2. Tentukan tarif harga kain & aksesoris
        $mainFabricPricePerMeter = $selectedMaterial
            ? (float) $selectedMaterial->standard_cost
            : (float) ($customOptions['fabric_price_per_meter'] ?? $this->getDefaultFabricPrice($suitType));

        $liningPricePerMeter = 45000;  // Kain furing Dormeuil / satin
        $kufnerPricePerMeter = 55000;  // Interlining / kain keras kufner jas

        $mainFabricCost = round($materialRequirements['main_fabric_meters'] * $mainFabricPricePerMeter);
        $liningCost = round(($materialRequirements['lining_meters'] ?? 0) * $liningPricePerMeter);
        $interliningCost = round(($materialRequirements['kufner_meters'] ?? 0) * $kufnerPricePerMeter);
        $accessoriesCost = $materialRequirements['accessories_cost'] ?? 50000;

        $totalMaterialCost = $mainFabricCost + $liningCost + $interliningCost + $accessoriesCost;

        // 3. Ongkos pengerjaan penjahit (Labor Cost)
        $laborCost = (float) ($customOptions['labor_cost'] ?? $this->getDefaultLaborCost($suitType));

        // 4. Total HPP (Cost of Goods Manufactured / COGM)
        $totalCost = $totalMaterialCost + $laborCost;

        // 5. Rekomendasi Harga Jual & Proyeksi Laba
        // Bespoke tailor target margin: 45% - 55%
        $targetMarginPercent = (float) ($customOptions['target_margin_percent'] ?? 45);
        $suggestedPrice = round(($totalCost / (1 - ($targetMarginPercent / 100))) / 10000) * 10000; // Pembulatan ke kelipatan 10.000
        $projectedProfit = $suggestedPrice - $totalCost;
        $actualMargin = $suggestedPrice > 0 ? round(($projectedProfit / $suggestedPrice) * 100, 1) : 0;

        return [
            'suit_type' => $suitType,
            'summary' => $this->generateSummaryText($suitType, $materialRequirements, $totalCost, $suggestedPrice),
            'materials' => [
                'main_fabric_meters' => $materialRequirements['main_fabric_meters'],
                'main_fabric_description' => $materialRequirements['main_fabric_desc'],
                'main_fabric_unit_price' => $mainFabricPricePerMeter,
                'main_fabric_total_cost' => $mainFabricCost,
                'lining_meters' => $materialRequirements['lining_meters'],
                'lining_total_cost' => $liningCost,
                'interlining_kufner_meters' => $materialRequirements['kufner_meters'],
                'interlining_total_cost' => $interliningCost,
                'accessories_cost' => $accessoriesCost,
                'accessories_notes' => $materialRequirements['accessories_notes'],
                'total_material_cost' => $totalMaterialCost,
                'inventory' => $selectedMaterial ? [
                    'material_id' => $selectedMaterial->id,
                    'material_name' => $selectedMaterial->name,
                    'available_stock' => (float) $selectedMaterial->stock,
                    'unit' => $selectedMaterial->unit,
                    'is_sufficient' => $selectedMaterial->stock >= $materialRequirements['main_fabric_meters'],
                ] : null,
            ],
            'labor' => [
                'labor_cost' => $laborCost,
                'description' => 'Jasa potong pola, jahit bespoke, pemasangan furing, dan finishing setrika uap.',
            ],
            'financial' => [
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
     * Hitung meteran kain berdasarkan jenis pakaian.
     */
    protected function calculateMaterials(string $suitType, float $bodyMultiplier, ?array $aiVision = null, ?array $customOptions = null): array
    {
        switch ($suitType) {
            case 'tuksedo':
                $main = round(3.2 * $bodyMultiplier, 2);
                $lining = round(2.3 * $bodyMultiplier, 2);
                $kufner = 1.6;

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => 'Kain Wool Blend / Black Tuxedo Barathea + Satin Silk Lapel Facings',
                    'lining_meters' => $lining,
                    'kufner_meters' => $kufner,
                    'accessories_cost' => 120000,
                    'accessories_notes' => 'Kancing satin bungkus, bantalan bahu busa jas premium, furing saku, benang guetermann.',
                ];

            case 'setelan_formal':
                $main = round(4.2 * $bodyMultiplier, 2);
                $lining = round(2.4 * $bodyMultiplier, 2);
                $kufner = 1.6;

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => 'Kain Wool Silk / Poliviscose High Twist untuk 1 Setel (Jas + Celana Bahan)',
                    'lining_meters' => $lining,
                    'kufner_meters' => $kufner,
                    'accessories_cost' => 110000,
                    'accessories_notes' => 'Resleting YKK celana, hak kait celana, kancing jas horn, kain saku katun, bantalan bahu.',
                ];

            case 'jaket':
            case 'outwear':
                $main = round(2.2 * $bodyMultiplier, 2);
                $lining = round(1.8 * $bodyMultiplier, 2);
                $kufner = 0.5;

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => 'Kain Outerwear (Twill / Semi-Wool / Canvas)',
                    'lining_meters' => $lining,
                    'kufner_meters' => $kufner,
                    'accessories_cost' => 75000,
                    'accessories_notes' => 'Resleting jacket metal, kancing snap, rib karet kerah/lengan (jika bomber).',
                ];

            case 'kemeja':
                $main = round(1.6 * $bodyMultiplier, 2);

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => 'Kain Katun Dobby / Poplin / Oxford 100% Katun',
                    'lining_meters' => 0,
                    'kufner_meters' => 0.4, // Kain keras kerah & manset
                    'accessories_cost' => 30000,
                    'accessories_notes' => 'Kancing mutiara kemeja, kain keras kerah interlining, benang.',
                ];

            case 'celana_denim':
                $main = round(1.6 * $bodyMultiplier, 2);

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => 'Kain Denim 12-14oz Raw / Selvedge Denim',
                    'lining_meters' => 0,
                    'kufner_meters' => 0,
                    'accessories_cost' => 45000,
                    'accessories_notes' => 'Kancing donut button fly / zipper brass, rivet tembaga, kain kantong twill.',
                ];

            case 'tshirt':
            case 'polo':
                $main = round(1.3 * $bodyMultiplier, 2);

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => $suitType === 'polo' ? 'Kain Pique Lacoste Cotton' : 'Kain Cotton Combed 24s/30s',
                    'lining_meters' => 0,
                    'kufner_meters' => 0,
                    'accessories_cost' => 15000,
                    'accessories_notes' => 'Kerah rajut polo, kancing jahit, label satin.',
                ];

            case 'custom_made_jas':
            case 'jas_blazer_pria':
            default:
                $main = round(2.75 * $bodyMultiplier, 2);
                $isHalfLined = ($customOptions['lining_style'] ?? null) === 'half_lined'
                    || ($aiVision['construction'] ?? null) === 'unconstructed';
                $lining = $isHalfLined ? round(1.2 * $bodyMultiplier, 2) : round(2.0 * $bodyMultiplier, 2);
                $kufner = $isHalfLined ? 1.0 : 1.5;

                $fabricDesc = ! empty($customOptions['fabric_type'])
                    ? $customOptions['fabric_type']
                    : 'Kain Jas / Blazer Semi-Wool atau Cotton Twill Khaki';

                return [
                    'main_fabric_meters' => $main,
                    'main_fabric_desc' => $fabricDesc,
                    'lining_meters' => $lining,
                    'kufner_meters' => $kufner,
                    'accessories_cost' => 85000,
                    'accessories_notes' => 'Bantalan bahu soft, kancing jas horn 2 lubang, kain saku paspoal, benang lapel.',
                ];
        }
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
     * Estimasi ongkos jahit standar untuk penjahit.
     */
    protected function getDefaultLaborCost(string $suitType): float
    {
        return match ($suitType) {
            'tuksedo' => 900000,
            'setelan_formal' => 850000,
            'jas_blazer_pria', 'custom_made_jas' => 600000,
            'jaket', 'outwear' => 350000,
            'celana_denim' => 180000,
            'kemeja' => 120000,
            'polo', 'tshirt' => 45000,
            default => 500000,
        };
    }

    /**
     * Ringkasan naratif penjelasan kebutuhan bahan.
     */
    protected function generateSummaryText(string $suitType, array $m, float $totalCost, float $suggestedPrice): string
    {
        $kategori = ucwords(str_replace('_', ' ', $suitType));

        return "Untuk pembuatan {$kategori}, dibutuhkan bahan utama {$m['main_fabric_meters']} meter ({$m['main_fabric_desc']})"
            .($m['lining_meters'] > 0 ? " serta {$m['lining_meters']} meter furing." : '.')
            .' Total estimasi HPP (kain, bahan pembantu & ongkos jahit) adalah Rp '.number_format($totalCost, 0, ',', '.')
            .' dengan rekomendasi harga jual ke klien sebesar Rp '.number_format($suggestedPrice, 0, ',', '.').'.';
    }
}
