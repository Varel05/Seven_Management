<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AllowanceController extends Controller
{
    /**
     * Simpan jenis tunjangan baru ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->input('target_type') === Allowance::TARGET_EMPLOYEE) {
            $hasIds = ! empty($request->input('target_employee_ids')) || ! empty($request->input('employee_id'));
            if (! $hasIds) {
                return back()->withErrors(['target_employee_ids' => 'Pilih minimal satu pegawai untuk tunjangan khusus.'])->withInput();
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'target_type' => ['required', Rule::in([Allowance::TARGET_ALL, Allowance::TARGET_ROLE, Allowance::TARGET_EMPLOYEE])],
            'target_role' => ['nullable', 'string', 'max:50', Rule::requiredIf($request->input('target_type') === Allowance::TARGET_ROLE)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'target_employee_ids' => ['nullable', 'array'],
            'target_employee_ids.*' => ['integer', 'exists:employees,id'],
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        // Jika bukan target_role, bersihkan target_role
        if ($validated['target_type'] !== Allowance::TARGET_ROLE) {
            $validated['target_role'] = null;
        }

        // Kelola target pegawai spesifik (bisa 1 atau banyak pegawai)
        if ($validated['target_type'] === Allowance::TARGET_EMPLOYEE) {
            $employeeIds = $request->input('target_employee_ids', []);
            if (empty($employeeIds) && ! empty($request->input('employee_id'))) {
                $employeeIds = [$request->input('employee_id')];
            }
            $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array) $employeeIds))));
            $validated['target_employee_ids'] = $cleanIds;
            $validated['employee_id'] = $cleanIds[0] ?? null;
        } else {
            $validated['target_employee_ids'] = null;
            $validated['employee_id'] = null;
        }

        $allowance = Allowance::create($validated);

        return redirect()->route('employees.index')->with('success', "Tunjangan '{$allowance->name}' ({$allowance->formatted_amount}) berhasil ditambahkan.");
    }

    /**
     * Perbarui data jenis tunjangan.
     */
    public function update(Request $request, Allowance $allowance): RedirectResponse
    {
        if ($request->input('target_type') === Allowance::TARGET_EMPLOYEE) {
            $hasIds = ! empty($request->input('target_employee_ids')) || ! empty($request->input('employee_id'));
            if (! $hasIds) {
                return back()->withErrors(['target_employee_ids' => 'Pilih minimal satu pegawai untuk tunjangan khusus.'])->withInput();
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'target_type' => ['required', Rule::in([Allowance::TARGET_ALL, Allowance::TARGET_ROLE, Allowance::TARGET_EMPLOYEE])],
            'target_role' => ['nullable', 'string', 'max:50', Rule::requiredIf($request->input('target_type') === Allowance::TARGET_ROLE)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'target_employee_ids' => ['nullable', 'array'],
            'target_employee_ids.*' => ['integer', 'exists:employees,id'],
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($validated['target_type'] !== Allowance::TARGET_ROLE) {
            $validated['target_role'] = null;
        }

        if ($validated['target_type'] === Allowance::TARGET_EMPLOYEE) {
            $employeeIds = $request->input('target_employee_ids', []);
            if (empty($employeeIds) && ! empty($request->input('employee_id'))) {
                $employeeIds = [$request->input('employee_id')];
            }
            $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array) $employeeIds))));
            $validated['target_employee_ids'] = $cleanIds;
            $validated['employee_id'] = $cleanIds[0] ?? null;
        } else {
            $validated['target_employee_ids'] = null;
            $validated['employee_id'] = null;
        }

        $allowance->update($validated);

        return redirect()->route('employees.index')->with('success', "Tunjangan '{$allowance->name}' berhasil diperbarui.");
    }

    /**
     * Aktifkan / Nonaktifkan status tunjangan.
     */
    public function toggle(Allowance $allowance): RedirectResponse
    {
        $allowance->update(['is_active' => ! $allowance->is_active]);
        $statusText = $allowance->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('employees.index')->with('success', "Tunjangan '{$allowance->name}' berhasil {$statusText}.");
    }

    /**
     * Hapus jenis tunjangan dari database.
     */
    public function destroy(Allowance $allowance): RedirectResponse
    {
        $name = $allowance->name;
        $allowance->delete();

        return redirect()->route('employees.index')->with('success', "Tunjangan '{$name}' berhasil dihapus.");
    }
}
