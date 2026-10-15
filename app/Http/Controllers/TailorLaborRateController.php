<?php

namespace App\Http\Controllers;

use App\Models\TailorLaborRate;
use App\Models\TailorPayrollItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TailorLaborRateController extends Controller
{
    /**
     * Tambahkan tarif standar produk upah penjahit baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:tailor_labor_rates,code',
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:20',
            'standard_cost' => 'required|numeric|min:0',
        ]);

        $code = ! empty($validated['code'])
            ? strtoupper(trim($validated['code']))
            : 'LAB-JHT-'.strtoupper(Str::random(5));

        $maxOrder = (int) TailorLaborRate::max('order');

        $rate = TailorLaborRate::create([
            'code' => $code,
            'name' => trim($validated['name']),
            'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : 'pcs',
            'standard_cost' => (float) $validated['standard_cost'],
            'order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Tarif standar produk "'.$rate->name.'" berhasil ditambahkan.');
    }

    /**
     * Perbarui data tarif standar produk upah penjahit.
     */
    public function update(Request $request, TailorLaborRate $laborRate): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:tailor_labor_rates,code,'.$laborRate->id,
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:20',
            'standard_cost' => 'required|numeric|min:0',
        ]);

        $laborRate->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : 'pcs',
            'standard_cost' => (float) $validated['standard_cost'],
        ]);

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Tarif standar produk "'.$laborRate->name.'" berhasil diperbarui.');
    }

    /**
     * Hapus atau nonaktifkan tarif standar produk.
     */
    public function destroy(TailorLaborRate $laborRate): RedirectResponse
    {
        $name = $laborRate->name;

        // Jika sudah pernah dipakai di slip payroll, nonaktifkan agar tidak merusak data historis
        $isUsed = TailorPayrollItem::where('material_id', $laborRate->id)->exists();
        if ($isUsed) {
            $laborRate->update(['is_active' => false]);

            return redirect()->route('tailor-payrolls.index')
                ->with('success', 'Tarif produk "'.$name.'" dinonaktifkan karena telah tercatat pada slip upah terdahulu.');
        }

        $laborRate->delete();

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Tarif standar produk "'.$name.'" berhasil dihapus.');
    }
}
