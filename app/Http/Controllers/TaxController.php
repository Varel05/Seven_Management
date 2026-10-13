<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\TaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxController extends Controller
{
    /**
     * Tampilkan halaman utama perhitungan dan pelaporan Pajak PPh Final UMKM.
     */
    public function index(Request $request, TaxService $taxService): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $monthlyTax = $taxService->calculateMonthlyTax($year, $month);
        $annualSummary = $taxService->calculateAnnualSummary($year);

        $paymentAccounts = Account::whereIn('code', ['1001', '1002'])
            ->orderBy('code')
            ->get();

        if ($paymentAccounts->isEmpty()) {
            $paymentAccounts = Account::where('type', 'asset')->take(2)->get();
        }

        return view('taxes.index', [
            'year' => $year,
            'month' => $month,
            'monthlyTax' => $monthlyTax,
            'annualSummary' => $annualSummary,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    /**
     * Catat pembayaran setoran PPh Final UMKM ke buku besar akuntansi.
     */
    public function recordPayment(Request $request, TaxService $taxService): RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'amount' => 'nullable|numeric|min:1',
            'account_code' => 'nullable|string',
            'ntpn' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $taxService->recordTaxPayment(
                year: (int) $validated['year'],
                month: (int) $validated['month'],
                amount: ! empty($validated['amount']) ? (float) $validated['amount'] : null,
                paymentAccountCode: $validated['account_code'] ?? '1001',
                ntpn: $validated['ntpn'] ?? null,
                source: 'website',
                notes: $validated['notes'] ?? null
            );

            return redirect()->route('taxes.index', [
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ])->with('success', 'Penyetoran PPh Final UMKM berhasil dicatat ke buku besar akuntansi.');
        } catch (\Exception $e) {
            return redirect()->route('taxes.index', [
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ])->with('error', 'Gagal mencatat penyetoran pajak: '.$e->getMessage());
        }
    }
}
