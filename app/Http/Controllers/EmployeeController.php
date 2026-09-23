<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees and payroll summary.
     */
    public function index()
    {
        $employees = Employee::with('assetAccount')
            ->orderBy('name')
            ->get();

        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();

        // Ringkasan Payroll Bulan Ini
        $activeEmployees = $employees->where('status', 'active');
        $totalActive = $activeEmployees->count();

        $totalBaseSalary = $activeEmployees->sum('base_salary');
        $totalBonusSalary = $activeEmployees->sum('bonus_salary');
        $totalPayrollEstimate = $totalBaseSalary + $totalBonusSalary;

        $paidThisMonth = $activeEmployees->filter(fn($e) => $e->last_paid_at && $e->last_paid_at->isCurrentMonth());
        $totalPaidThisMonth = $paidThisMonth->sum('total_salary');

        $dueOrUpcomingThisMonth = $activeEmployees->filter(fn($e) => !$e->last_paid_at || !$e->last_paid_at->isCurrentMonth());
        $totalPendingPayroll = $dueOrUpcomingThisMonth->sum('total_salary');

        return view('employees.index', compact(
            'employees',
            'assetAccounts',
            'totalActive',
            'totalBaseSalary',
            'totalBonusSalary',
            'totalPayrollEstimate',
            'totalPaidThisMonth',
            'totalPendingPayroll'
        ));
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'position'         => 'required|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'base_salary'      => 'required|numeric|min:0',
            'current_points'   => 'nullable|integer|min:0',
            'rate_per_point'   => 'nullable|numeric|min:0',
            'pay_day'          => 'required|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'status'           => 'required|in:active,inactive',
        ]);

        $validated['current_points'] = (int) ($validated['current_points'] ?? 0);
        $validated['rate_per_point'] = (float) ($validated['rate_per_point'] ?? 0);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', "Karyawan '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'position'         => 'required|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'base_salary'      => 'required|numeric|min:0',
            'current_points'   => 'nullable|integer|min:0',
            'rate_per_point'   => 'nullable|numeric|min:0',
            'pay_day'          => 'required|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'status'           => 'required|in:active,inactive',
        ]);

        $validated['current_points'] = (int) ($validated['current_points'] ?? 0);
        $validated['rate_per_point'] = (float) ($validated['rate_per_point'] ?? 0);

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', "Data karyawan '{$employee->name}' berhasil diperbarui.");
    }

    /**
     * Update employee points quickly.
     */
    public function updatePoints(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'points' => 'required|integer',
            'mode'   => 'required|in:set,add,subtract',
        ]);

        $points = (int) $validated['points'];
        $newPoints = $employee->current_points;

        if ($validated['mode'] === 'set') {
            $newPoints = max(0, $points);
        } elseif ($validated['mode'] === 'add') {
            $newPoints = $employee->current_points + $points;
        } elseif ($validated['mode'] === 'subtract') {
            $newPoints = max(0, $employee->current_points - $points);
        }

        $employee->update(['current_points' => $newPoints]);

        return back()->with('success', "Poin karyawan '{$employee->name}' berhasil diperbarui menjadi {$newPoints} poin.");
    }

    /**
     * Execute payroll posting directly from web dashboard.
     */
    public function pay(Request $request, Employee $employee)
    {
        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $journalEntry = $employee->executePayrollPosting($customAmount, 'website');

        $formattedAmount = 'Rp ' . number_format($customAmount ?: (float) $employee->total_salary, 0, ',', '.');

        return back()->with('success', "Gaji {$employee->name} sebesar {$formattedAmount} berhasil dibukukan ke akun Beban Gaji (5002). Ref: {$journalEntry->reference}");
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee)
    {
        $name = $employee->name;
        $employee->delete();

        return redirect()->route('employees.index')->with('success', "Data karyawan '{$name}' berhasil dihapus.");
    }
}
