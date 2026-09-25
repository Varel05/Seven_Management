<?php

namespace App\Http\Controllers;

use App\Models\CostSheet;
use App\Models\CostSheetItem;
use App\Models\CostSheetVariant;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CostSheetController extends Controller
{
    /**
     * Daftar Kartu HPP / Bill of Materials (BOM).
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');

        $query = CostSheet::with(['product', 'variants.items.material']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('fabric_type', 'like', "%{$search}%");
            });
        }

        $costSheets = $query->latest()->paginate(10)->withQueryString();

        $totalSheets = CostSheet::count();
        $totalVariants = CostSheetVariant::count();
        $linkedProductsCount = CostSheet::whereNotNull('product_id')->count();
        $products = Product::orderBy('name')->get();
        $materials = Material::orderBy('category')->orderBy('name')->get();

        return view('cost-sheets.index', [
            'costSheets' => $costSheets,
            'search' => $search,
            'totalSheets' => $totalSheets,
            'totalVariants' => $totalVariants,
            'linkedProductsCount' => $linkedProductsCount,
            'products' => $products,
            'materials' => $materials,
        ]);
    }

    /**
     * Tampilkan detail kartu HPP, rekap varian ukuran, dan rincian resep BOM.
     */
    public function show(CostSheet $costSheet, Request $request): View
    {
        $costSheet->load(['product', 'variants.items.material.account']);

        $selectedVariantId = $request->query('variant_id');
        $activeVariant = $selectedVariantId
            ? $costSheet->variants->firstWhere('id', (int) $selectedVariantId)
            : $costSheet->variants->first();

        $materials = Material::orderBy('category')->orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('cost-sheets.show', [
            'costSheet' => $costSheet,
            'activeVariant' => $activeVariant,
            'materials' => $materials,
            'products' => $products,
        ]);
    }

    /**
     * Buat Kartu HPP baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:cost_sheets,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'fabric_type' => ['nullable', 'string', 'max:100'],
            'product_id' => ['nullable', 'exists:products,id'],
            'description' => ['nullable', 'string'],
        ]);

        $costSheet = CostSheet::create($validated);

        // Buat satu varian default 'All Size'
        $variant = CostSheetVariant::create([
            'cost_sheet_id' => $costSheet->id,
            'size' => 'All Size',
            'notes' => 'Varian awal',
        ]);

        return redirect()->route('cost-sheets.show', ['costSheet' => $costSheet->id, 'variant_id' => $variant->id])
            ->with('success', "Kartu HPP '{$costSheet->name}' berhasil dibuat. Silakan tambahkan komponen biaya.");
    }

    /**
     * Tambah varian ukuran baru ke Kartu HPP.
     */
    public function storeVariant(Request $request, CostSheet $costSheet): RedirectResponse
    {
        $validated = $request->validate([
            'size' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
            'copy_from_variant_id' => ['nullable', 'exists:cost_sheet_variants,id'],
        ]);

        // Cek duplikasi ukuran
        if ($costSheet->variants()->where('size', $validated['size'])->exists()) {
            return back()->with('error', "Varian ukuran '{$validated['size']}' sudah ada pada kartu HPP ini.");
        }

        DB::transaction(function () use ($costSheet, $validated, &$variant) {
            $variant = CostSheetVariant::create([
                'cost_sheet_id' => $costSheet->id,
                'size' => $validated['size'],
                'notes' => $validated['notes'],
            ]);

            // Jika user memilih untuk menyalin resep dari varian lain
            if (! empty($validated['copy_from_variant_id'])) {
                $sourceVariant = CostSheetVariant::with('items')->find($validated['copy_from_variant_id']);
                if ($sourceVariant) {
                    foreach ($sourceVariant->items as $item) {
                        CostSheetItem::create([
                            'cost_sheet_variant_id' => $variant->id,
                            'material_id' => $item->material_id,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'notes' => $item->notes,
                        ]);
                    }
                    $variant->recalculateTotals();
                }
            }
        });

        return redirect()->route('cost-sheets.show', ['costSheet' => $costSheet->id, 'variant_id' => $variant->id])
            ->with('success', "Varian ukuran '{$variant->size}' berhasil ditambahkan.");
    }

    /**
     * Tambahkan baris komponen biaya / resep ke varian HPP.
     */
    public function storeItem(Request $request, CostSheetVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'material_id' => ['required', 'exists:materials,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $material = Material::findOrFail($validated['material_id']);
        $unitPrice = ! empty($validated['unit_price']) ? (float) $validated['unit_price'] : (float) $material->standard_cost;
        $subtotal = round((float) $validated['quantity'] * $unitPrice, 2);

        CostSheetItem::create([
            'cost_sheet_variant_id' => $variant->id,
            'material_id' => $material->id,
            'quantity' => $validated['quantity'],
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'notes' => $validated['notes'],
        ]);

        $variant->recalculateTotals();

        return back()->with('success', "Komponen '{$material->name}' berhasil ditambahkan ke varian {$variant->size}.");
    }

    /**
     * Hapus komponen dari varian HPP.
     */
    public function destroyItem(CostSheetItem $item): RedirectResponse
    {
        $variant = $item->variant;
        $itemName = $item->material ? $item->material->name : 'Komponen';
        $item->delete();

        $variant->recalculateTotals();

        return back()->with('success', "{$itemName} berhasil dihapus dari resep.");
    }

    /**
     * Sinkronisasikan nilai HPP varian ke produk baju jadi retail.
     */
    public function syncProduct(Request $request, CostSheetVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $product->update([
            'cost_sheet_variant_id' => $variant->id,
            'cost_price' => $variant->total_cost_price,
        ]);

        return back()->with('success', "HPP Produk '{$product->name}' berhasil disinkronkan ke Rp ".number_format($variant->total_cost_price, 0, ',', '.').'.');
    }
}
