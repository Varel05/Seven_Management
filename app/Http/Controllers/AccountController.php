<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Store a newly created finance account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
        ], [
            'code.required' => 'Kode akun wajib diisi.',
            'code.unique'   => 'Kode akun sudah digunakan oleh akun lain.',
            'code.max'      => 'Kode akun maksimal 20 karakter.',
            'name.required' => 'Nama akun wajib diisi.',
            'name.max'      => 'Nama akun maksimal 255 karakter.',
            'type.required' => 'Tipe akun wajib dipilih.',
            'type.in'       => 'Tipe akun yang dipilih tidak valid.',
        ]);

        $account = Account::create($validated);

        return back()->with('success', "Akun {$account->code} - {$account->name} berhasil ditambahkan ke bagan akun.");
    }

    /**
     * Update the specified finance account.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
        ], [
            'code.required' => 'Kode akun wajib diisi.',
            'code.unique'   => 'Kode akun sudah digunakan oleh akun lain.',
            'code.max'      => 'Kode akun maksimal 20 karakter.',
            'name.required' => 'Nama akun wajib diisi.',
            'name.max'      => 'Nama akun maksimal 255 karakter.',
            'type.required' => 'Tipe akun wajib dipilih.',
            'type.in'       => 'Tipe akun yang dipilih tidak valid.',
        ]);

        $oldCode = $account->code;
        $account->update($validated);

        return back()->with('success', "Akun {$oldCode} berhasil diperbarui.");
    }

    /**
     * Remove the specified finance account from storage.
     */
    public function destroy(Account $account)
    {
        $transactionCount = $account->lines()->count();

        if ($transactionCount > 0) {
            return back()->with('warning', "Akun {$account->code} ({$account->name}) tidak dapat dihapus karena memiliki {$transactionCount} riwayat transaksi jurnal.");
        }

        $code = $account->code;
        $name = $account->name;
        $account->delete();

        return back()->with('success', "Akun {$code} ({$name}) berhasil dihapus.");
    }
}
