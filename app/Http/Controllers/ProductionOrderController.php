<?php

namespace App\Http\Controllers;

use App\Models\CostSheetVariant;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    /**
     * Tampilkan formulir manual produksi baju / potong bahan.
     */
    public function create(Request $request): View
    {
        $selectedVariantId = $request->query('variant_id');
        $selectedProductId = $request->query('product_id');

        $variants = CostSheetVariant::with(['costSheet', 'items.material'])->get();
        $products = Product::orderBy('name')->get();

        $activeVariant = null;
        if ($selectedVariantId) {
            $activeVariant = $variants->firstWhere('id', (int) $selectedVariantId);
        } elseif ($selectedProductId) {
            $prod = $products->firstWhere('id', (int) $selectedProductId);
            if ($prod && $prod->cost_sheet_variant_id) {
                $activeVariant = $variants->firstWhere('id', $prod->cost_sheet_variant_id);
            }
        }

        if (! $activeVariant && $variants->isNotEmpty()) {
            $activeVariant = $variants->first();
        }

        return view('production.create', [
            'variants' => $variants,
            'products' => $products,
            'activeVariant' => $activeVariant,
            'selectedProductId' => $selectedProductId,
        ]);
    }

    /**
     * Eksekusi batch produksi: potong stok bahan riil & tambah stok baju jadi.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cost_sheet_variant_id' => ['required', 'exists:cost_sheet_variants,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'batch_quantity' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'update_product_cost' => ['nullable', 'boolean'],
        ]);

        $variant = CostSheetVariant::with('costSheet')->findOrFail($validated['cost_sheet_variant_id']);
        $batchQty = (int) $validated['batch_quantity'];
        $batchNumber = 'PROD-'.date('Ymd-His');
        $product = ! empty($validated['product_id']) ? Product::find($validated['product_id']) : null;

        DB::transaction(function () use ($validated, $variant, $batchQty, $batchNumber, $product) {
            $totalBatchCost = 0.0;

            foreach ($validated['items'] as $itemData) {
                $material = Material::findOrFail($itemData['material_id']);
                $actualQty = (float) $itemData['actual_quantity'];
                $unitCost = (float) $itemData['unit_cost'];
                $itemSubtotal = $actualQty * $unitCost;
                $totalBatchCost += $itemSubtotal;

                // Jika material fisik dan kuantitas pemakaian > 0, kurangi stok gudang
                if ($material->isPhysical() && $actualQty > 0) {
                    $material->decrement('stock', $actualQty);

                    // Catat ke log mutasi stok bahan
                    MaterialStockMovement::create([
                        'material_id' => $material->id,
                        'type' => 'out',
                        'quantity' => $actualQty,
                        'unit_cost' => $unitCost,
                        'reference_type' => 'production_batch',
                        'reference_number' => $batchNumber,
                        'notes' => "Produksi {$batchQty} pcs {$variant->costSheet->name} ({$variant->size}). ".($validated['notes'] ?? ''),
                    ]);
                }
            }

            // HPP riil per pcs untuk batch ini
            $realHppPerPc = $batchQty > 0 ? round($totalBatchCost / $batchQty, 2) : 0;

            // Jika ada produk retail yang dituju, tambah stok produk baju jadi
            if ($product) {
                $product->increment('stock', $batchQty);

                // Update HPP produk jika opsi dicentang
                if (! empty($validated['update_product_cost'])) {
                    $product->update([
                        'cost_price' => $realHppPerPc,
                        'cost_sheet_variant_id' => $variant->id,
                    ]);
                }
            }
        });

        $productMsg = $product
            ? " dan stok produk '{$product->name}' bertambah +{$batchQty} pcs"
            : '';

        return redirect()->route('materials.index')
            ->with('success', "Batch Produksi [{$batchNumber}] berhasil disimpan. Stok bahan baku telah dipotong sesuai pemakaian riil{$productMsg}.");
    }
}
