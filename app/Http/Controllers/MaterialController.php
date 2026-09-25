<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialController extends Controller
{
    /**
     * Tampilkan katalog master bahan baku & pemantauan stok gudang.
     */
    public function index(Request $request): View
    {
        $categoryFilter = $request->query('category');
        $search = $request->query('q');
        $stockFilter = $request->query('stock_status'); // 'all', 'low', 'out'

        $query = Material::with('account');

        if ($categoryFilter && array_key_exists($categoryFilter, Material::CATEGORIES)) {
            $query->where('category', $categoryFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($stockFilter === 'low') {
            $query->whereIn('category', ['raw_material', 'supporting_material', 'accessory'])
                ->whereColumn('stock', '<=', 'min_stock')
                ->where('stock', '>', 0);
        } elseif ($stockFilter === 'out') {
            $query->whereIn('category', ['raw_material', 'supporting_material', 'accessory'])
                ->where('stock', '<=', 0);
        }

        $materials = $query->orderBy('category')->orderBy('name')->paginate(15)->withQueryString();

        // Metrik statistik inventaris
        $totalMaterialKinds = Material::count();
        $physicalCount = Material::physical()->count();
        $lowStockCount = Material::physical()->whereColumn('stock', '<=', 'min_stock')->count();

        // Total valuasi nilai stok fisik di gudang
        $totalStockValuation = Material::physical()
            ->selectRaw('SUM(stock * standard_cost) as total_val')
            ->value('total_val') ?? 0;

        // Riwayat 10 mutasi stok terakhir
        $recentMovements = MaterialStockMovement::with('material')
            ->latest()
            ->take(10)
            ->get();

        // Akun untuk mapping
        $accounts = Account::orderBy('code')->get();

        return view('materials.index', [
            'materials' => $materials,
            'categories' => Material::CATEGORIES,
            'selectedCategory' => $categoryFilter,
            'selectedStock' => $stockFilter,
            'search' => $search,
            'totalMaterialKinds' => $totalMaterialKinds,
            'physicalCount' => $physicalCount,
            'lowStockCount' => $lowStockCount,
            'totalStockValuation' => (float) $totalStockValuation,
            'recentMovements' => $recentMovements,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Simpan bahan baku atau komponen biaya baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:materials,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Material::CATEGORIES))],
            'unit' => ['required', 'string', 'max:50'],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
        ]);

        $isPhysical = in_array($validated['category'], ['raw_material', 'supporting_material', 'accessory'], true);
        $initialStock = $isPhysical ? (float) ($validated['stock'] ?? 0) : 0;
        $validated['stock'] = $initialStock;
        $validated['min_stock'] = $isPhysical ? (float) ($validated['min_stock'] ?? 0) : 0;

        DB::transaction(function () use ($validated, $isPhysical, $initialStock) {
            $material = Material::create($validated);

            if ($isPhysical && $initialStock > 0) {
                MaterialStockMovement::create([
                    'material_id' => $material->id,
                    'type' => 'in',
                    'quantity' => $initialStock,
                    'unit_cost' => $material->standard_cost,
                    'reference_type' => 'purchase',
                    'reference_number' => 'INIT-STOCK',
                    'notes' => 'Saldo awal pencatatan bahan baru di gudang.',
                ]);
            }
        });

        return redirect()->route('materials.index')->with('success', "Bahan '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Perbarui data bahan baku / komponen biaya.
     */
    public function update(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($material->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Material::CATEGORIES))],
            'unit' => ['required', 'string', 'max:50'],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
        ]);

        $material->update($validated);

        return redirect()->route('materials.index')->with('success', "Data bahan '{$material->name}' berhasil diperbarui.");
    }

    /**
     * Aksi Tambah Stok Masuk (Restock / Pembelian Bahan Baru).
     */
    public function restock(Request $request, Material $material): RedirectResponse
    {
        if (! $material->isPhysical()) {
            return back()->with('error', 'Hanya bahan berwujud fisik yang dapat dilakukan penambahan stok.');
        }

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $qty = (float) $validated['quantity'];
        $unitCost = ! empty($validated['unit_cost']) ? (float) $validated['unit_cost'] : (float) $material->standard_cost;

        DB::transaction(function () use ($material, $qty, $unitCost, $validated) {
            $material->increment('stock', $qty);

            // Update standard_cost jika harga beli baru diinput
            if (! empty($validated['unit_cost']) && (float) $validated['unit_cost'] > 0) {
                $material->update(['standard_cost' => $unitCost]);
            }

            MaterialStockMovement::create([
                'material_id' => $material->id,
                'type' => 'in',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'reference_type' => 'purchase',
                'reference_number' => $validated['reference_number'] ?: ('RESTOCK-'.date('Ymd-His')),
                'notes' => $validated['notes'] ?: 'Penambahan stok bahan masuk ke gudang.',
            ]);
        });

        return redirect()->route('materials.index')->with('success', "Berhasil menambahkan {$qty} {$material->unit} stok {$material->name}.");
    }

    /**
     * Hapus bahan baku jika belum terikat ke resep kartu HPP.
     */
    public function destroy(Material $material): RedirectResponse
    {
        if ($material->costSheetItems()->exists()) {
            return back()->with('error', "Bahan '{$material->name}' tidak dapat dihapus karena masih digunakan di dalam resep Kartu HPP.");
        }

        $material->delete();

        return redirect()->route('materials.index')->with('success', "Bahan '{$material->name}' berhasil dihapus.");
    }
}
