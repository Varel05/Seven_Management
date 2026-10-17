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
            'type' => ['nullable', Rule::in([Allowance::TYPE_ALLOWANCE, Allowance::TYPE_DEDUCTION])],
            'target_type' => ['required', Rule::in([Allowance::TARGET_ALL, Allowance::TARGET_ROLE, Allowance::TARGET_EMPLOYEE])],
            'target_role' => ['nullable', 'string', 'max:50', Rule::requiredIf($request->input('target_type') === Allowance::TARGET_ROLE)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'target_employee_ids' => ['nullable', 'array'],
            'target_employee_ids.*' => ['integer', 'exists:employees,id'],
            'calculation_type' => ['nullable', Rule::in([Allowance::CALC_FIXED, Allowance::CALC_PERCENTAGE])],
            'amount' => 'nullable|numeric|min:0',
            'rate_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['type'] = $request->input('type', Allowance::TYPE_ALLOWANCE) ?: Allowance::TYPE_ALLOWANCE;
        $validated['calculation_type'] = $request->input('calculation_type', Allowance::CALC_FIXED) ?: Allowance::CALC_FIXED;
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($validated['calculation_type'] === Allowance::CALC_PERCENTAGE) {
            $validated['rate_percent'] = (float) $request->input('rate_percent', 0);
            $validated['amount'] = 0;
        } else {
            $validated['rate_percent'] = null;
            $validated['amount'] = (float) $request->input('amount', 0);
        }

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
        $typeLabel = $allowance->isDeduction() ? 'Potongan' : 'Tunjangan';

        return redirect()->route('employees.index')->with('success', "{$typeLabel} '{$allowance->name}' ({$allowance->formatted_amount}) berhasil ditambahkan.");
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
            'type' => ['nullable', Rule::in([Allowance::TYPE_ALLOWANCE, Allowance::TYPE_DEDUCTION])],
            'target_type' => ['required', Rule::in([Allowance::TARGET_ALL, Allowance::TARGET_ROLE, Allowance::TARGET_EMPLOYEE])],
            'target_role' => ['nullable', 'string', 'max:50', Rule::requiredIf($request->input('target_type') === Allowance::TARGET_ROLE)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'target_employee_ids' => ['nullable', 'array'],
            'target_employee_ids.*' => ['integer', 'exists:employees,id'],
            'calculation_type' => ['nullable', Rule::in([Allowance::CALC_FIXED, Allowance::CALC_PERCENTAGE])],
            'amount' => 'nullable|numeric|min:0',
            'rate_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['type'] = $request->input('type', $allowance->type ?? Allowance::TYPE_ALLOWANCE) ?: Allowance::TYPE_ALLOWANCE;
        $validated['calculation_type'] = $request->input('calculation_type', $allowance->calculation_type ?? Allowance::CALC_FIXED) ?: Allowance::CALC_FIXED;
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($validated['calculation_type'] === Allowance::CALC_PERCENTAGE) {
            $validated['rate_percent'] = (float) $request->input('rate_percent', 0);
            $validated['amount'] = 0;
        } else {
            $validated['rate_percent'] = null;
            $validated['amount'] = (float) $request->input('amount', 0);
        }

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
        $typeLabel = $allowance->isDeduction() ? 'Potongan' : 'Tunjangan';

        return redirect()->route('employees.index')->with('success', "{$typeLabel} '{$allowance->name}' berhasil diperbarui.");
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
