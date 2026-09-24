<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringTransactionController extends Controller
{
    /**
     * Store a newly created recurring transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', Rule::in(['monthly', 'yearly', 'weekly'])],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'month_of_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'asset_account_id' => ['required', 'exists:accounts,id'],
            'status' => ['required', Rule::in(['active', 'paused'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Nama pengeluaran rutin wajib diisi.',
            'amount.required' => 'Nominal tagihan wajib diisi.',
            'amount.min' => 'Nominal harus lebih dari 0.',
            'expense_account_id.required' => 'Akun beban wajib dipilih.',
            'asset_account_id.required' => 'Akun kas/bank wajib dipilih.',
            'day_of_month.required' => 'Tanggal jatuh tempo wajib ditentukan.',
        ]);

        $recurring = RecurringTransaction::create($validated);

        return back()->with('success', "Pengeluaran rutin '{$recurring->name}' berhasil dijadwalkan.");
    }

    /**
     * Update the specified recurring transaction.
     */
    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', Rule::in(['monthly', 'yearly', 'weekly'])],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'month_of_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'asset_account_id' => ['required', 'exists:accounts,id'],
            'status' => ['required', Rule::in(['active', 'paused'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $recurringTransaction->update($validated);

        return back()->with('success', "Jadwal '{$recurringTransaction->name}' berhasil diperbarui.");
    }

    /**
     * Remove the specified recurring transaction.
     */
    public function destroy(RecurringTransaction $recurringTransaction)
    {
        $name = $recurringTransaction->name;
        $recurringTransaction->delete();

        return back()->with('success', "Pengeluaran rutin '{$name}' telah dihapus.");
    }

    /**
     * Approve and execute posting immediately from web dashboard.
     */
    public function approve(Request $request, RecurringTransaction $recurringTransaction)
    {
        if ($recurringTransaction->isPaidForCurrentPeriod()) {
            return back()->with('warning', "Tagihan '{$recurringTransaction->name}' sudah dibayar untuk periode ini.");
        }

        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $entry = $recurringTransaction->executePosting($customAmount, 'website');

        return back()->with('success', "Tagihan '{$recurringTransaction->name}' berhasil dibukukan ke buku besar dengan nomor referensi {$entry->reference}.");
    }
}
