<?php

namespace App\Http\Controllers;

use App\Models\Tailor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TailorController extends Controller
{
    /**
     * Simpan data penjahit baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'payment_period' => 'required|in:weekly,monthly',
            'payment_day' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_holder' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        Tailor::create($validated);

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Data penjahit "'.$validated['name'].'" berhasil didaftarkan.');
    }

    /**
     * Perbarui data penjahit.
     */
    public function update(Request $request, Tailor $tailor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'payment_period' => 'required|in:weekly,monthly',
            'payment_day' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_holder' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tailor->update($validated);

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Data penjahit "'.$tailor->name.'" berhasil diperbarui.');
    }

    /**
     * Hapus data penjahit.
     */
    public function destroy(Tailor $tailor): RedirectResponse
    {
        if ($tailor->payrolls()->exists()) {
            return redirect()->route('tailor-payrolls.index')
                ->with('error', 'Penjahit "'.$tailor->name.'" tidak dapat dihapus karena sudah memiliki riwayat slip upah. Anda dapat menonaktifkan statusnya.');
        }

        $name = $tailor->name;
        $tailor->delete();

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Data penjahit "'.$name.'" berhasil dihapus.');
    }
}
