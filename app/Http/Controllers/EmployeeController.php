<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Allowance;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePointLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees and payroll summary.
     */
    public function index()
    {
        $employees = Employee::with(['assetAccount', 'latestPayroll'])
            ->orderBy('name')
            ->get();

        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();

        // Ringkasan Payroll Bulan Ini
        $activeEmployees = $employees->where('status', 'active');
        $totalActive = $activeEmployees->count();

        $allowances = Allowance::with('employee')->latest()->get();
        $totalBaseSalary = $activeEmployees->sum('base_salary');
        $totalAllowanceEstimate = $activeEmployees->sum('total_allowance');
        $totalBonusSalary = $activeEmployees->sum('bonus_salary');
        $totalPayrollEstimate = $totalBaseSalary + $totalAllowanceEstimate + $totalBonusSalary;

        $paidThisMonth = $activeEmployees->filter(fn ($e) => $e->isPaidThisMonth());
        $totalPaidThisMonth = $paidThisMonth->sum('total_salary');

        $dueOrUpcomingThisMonth = $activeEmployees->filter(fn ($e) => ! $e->isPaidThisMonth());
        $totalPendingPayroll = $dueOrUpcomingThisMonth->sum('total_salary');

        return view('employees.index', compact(
            'employees',
            'assetAccounts',
            'allowances',
            'totalActive',
            'totalBaseSalary',
            'totalAllowanceEstimate',
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
            'name' => 'required|string|max:255',
            'role' => ['nullable', Rule::enum(EmployeeRole::class)],
            'position' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'telegram_user_id' => 'nullable|string|max:50|unique:employees,telegram_user_id',
            'telegram_username' => 'nullable|string|max:50',
            'base_salary' => 'required|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
            'current_points' => 'nullable|integer|min:0',
            'rate_per_point' => 'nullable|numeric|min:0',
            'pay_day' => 'required|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'status' => 'required|in:active,inactive',
            'claim_bonus' => 'nullable|boolean',
            'is_on_duty' => 'nullable|boolean',
        ]);

        $validated['role'] = $validated['role'] ?? EmployeeRole::Staff->value;
        $validated['current_points'] = (int) ($validated['current_points'] ?? 0);
        $tierRate = Employee::getRateForPoints($validated['current_points']);
        $validated['rate_per_point'] = $tierRate > 0
            ? $tierRate
            : (float) ($validated['rate_per_point'] ?? 0);
        if ($request->has('claim_bonus')) {
            $validated['claim_bonus'] = $request->boolean('claim_bonus');
        }
        if ($request->has('is_on_duty')) {
            $validated['is_on_duty'] = $request->boolean('is_on_duty');
        }

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', "Karyawan '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => ['nullable', Rule::enum(EmployeeRole::class)],
            'position' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'telegram_user_id' => ['nullable', 'string', 'max:50', Rule::unique('employees')->ignore($employee->id)],
            'telegram_username' => 'nullable|string|max:50',
            'base_salary' => 'required|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
            'current_points' => 'nullable|integer|min:0',
            'rate_per_point' => 'nullable|numeric|min:0',
            'pay_day' => 'required|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'status' => 'required|in:active,inactive',
            'claim_bonus' => 'nullable|boolean',
            'is_on_duty' => 'nullable|boolean',
        ]);

        $validated['current_points'] = (int) ($validated['current_points'] ?? 0);
        $tierRate = Employee::getRateForPoints($validated['current_points']);
        $validated['rate_per_point'] = $tierRate > 0
            ? $tierRate
            : (float) ($validated['rate_per_point'] ?? 0);
        if ($request->has('claim_bonus')) {
            $validated['claim_bonus'] = $request->boolean('claim_bonus');
        }
        if ($request->has('is_on_duty')) {
            $validated['is_on_duty'] = $request->boolean('is_on_duty');
        }

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
            'mode' => 'required|in:set,add,subtract',
        ]);

        $points = (int) $validated['points'];
        $oldPoints = $employee->current_points;

        if ($validated['mode'] === 'set') {
            $newPoints = max(0, $points);
            $diff = $newPoints - $oldPoints;
        } elseif ($validated['mode'] === 'add') {
            $diff = $points;
            $newPoints = $oldPoints + $points;
        } elseif ($validated['mode'] === 'subtract') {
            $diff = -abs($points);
            $newPoints = max(0, $oldPoints - abs($points));
        }

        $tierRate = Employee::getRateForPoints($newPoints);
        $updateData = ['current_points' => $newPoints];

        if ($tierRate > 0) {
            $updateData['rate_per_point'] = $tierRate;
        } elseif ($employee->rate_per_point <= 2600) {
            $updateData['rate_per_point'] = 0;
        }

        $employee->update($updateData);

        if ($diff !== 0) {
            $employee->pointLogs()->create([
                'points' => $diff,
                'category' => EmployeePointLog::CATEGORY_MANUAL,
                'actor' => auth()->user()?->name ?? 'Admin Web',
                'notes' => 'Penyesuaian manual dari Web Dashboard',
            ]);
        }

        return back()->with('success', "Poin karyawan '{$employee->name}' berhasil diperbarui menjadi {$newPoints} poin.");
    }

    /**
     * Execute payroll posting directly from web dashboard.
     * Mendukung kalkulasi slip gaji riil berbasis absensi manual harian.
     */
    public function pay(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'period' => 'nullable|string|max:50',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'total_shifts' => 'nullable|integer|min:0',
            'total_present' => 'nullable|integer|min:0',
            'late_count' => 'nullable|integer|min:0',
            'discipline_present' => 'nullable|integer|min:0',
            'holiday_shifts' => 'nullable|integer|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
            'sales_bonus' => 'nullable|numeric|min:0',
            'closing_points' => 'nullable|integer|min:0',
            'closing_pcs' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $isManualAttendance = $request->filled('total_present') || $request->filled('daily_rate');

        if ($isManualAttendance) {
            $totalPresent = (int) ($validated['total_present'] ?? 27);
            $totalShifts = (int) ($validated['total_shifts'] ?? 27);
            $lateCount = (int) ($validated['late_count'] ?? 0);
            $disciplinePresent = (int) ($validated['discipline_present'] ?? max(0, $totalPresent - $lateCount));
            $holidayShifts = (int) ($validated['holiday_shifts'] ?? 0);

            $dailyRate = isset($validated['daily_rate']) ? (float) $validated['daily_rate'] : $employee->effective_daily_rate;
            $disciplineRate = isset($validated['discipline_rate']) ? (float) $validated['discipline_rate'] : $employee->effective_discipline_rate;
            $holidayRate = isset($validated['holiday_rate']) ? (float) $validated['holiday_rate'] : $employee->effective_holiday_rate;

            $mainSalary = $totalPresent * $dailyRate;
            $disciplineBonus = $disciplinePresent * $disciplineRate;
            $salesBonus = isset($validated['sales_bonus']) ? (float) $validated['sales_bonus'] : (float) $employee->bonus_salary;
            $holidayBonus = $holidayShifts * $holidayRate;
            $allowanceTotal = (float) $employee->total_allowance;

            $calculatedTHP = $mainSalary + $disciplineBonus + $salesBonus + $holidayBonus + $allowanceTotal;
        } else {
            $totalPresent = 27;
            $totalShifts = 27;
            $lateCount = 0;
            $disciplinePresent = 27;
            $holidayShifts = 0;
            $dailyRate = (float) ($employee->daily_rate ?: ($employee->base_salary / 27));
            $disciplineRate = (float) ($employee->discipline_rate ?? 0);
            $holidayRate = (float) ($employee->holiday_rate ?? 0);
            $mainSalary = (float) $employee->base_salary;
            $disciplineBonus = 0;
            $salesBonus = (float) $employee->bonus_salary;
            $holidayBonus = 0;
            $allowanceTotal = (float) $employee->total_allowance;

            $calculatedTHP = (float) $employee->total_salary;
        }

        $finalAmount = ! empty($validated['amount']) ? (float) $validated['amount'] : $calculatedTHP;

        $periodLabel = $validated['period'] ?? now()->translatedFormat('F Y');
        $payrollData = [
            'period' => $periodLabel,
            'period_start' => $validated['period_start'] ?? now()->subMonth()->setDay(26)->toDateString(),
            'period_end' => $validated['period_end'] ?? now()->setDay(25)->toDateString(),
            'payment_method' => $validated['payment_method'] ?? 'Transfer',
            'total_shifts' => $totalShifts,
            'total_present' => $totalPresent,
            'late_count' => $lateCount,
            'discipline_present' => $disciplinePresent,
            'holiday_shifts' => $holidayShifts,
            'daily_rate' => $dailyRate,
            'discipline_rate' => $disciplineRate,
            'holiday_rate' => $holidayRate,
            'closing_points' => (int) ($validated['closing_points'] ?? $employee->current_points),
            'closing_pcs' => (int) ($validated['closing_pcs'] ?? 0),
            'rate_per_point' => (float) $employee->rate_per_point,
            'main_salary' => $mainSalary,
            'discipline_bonus' => $disciplineBonus,
            'sales_bonus' => $salesBonus,
            'holiday_bonus' => $holidayBonus,
            'allowance_total' => $allowanceTotal,
            'take_home_pay' => $finalAmount,
            'notes' => $validated['notes'] ?? null,
            'hrd_name' => auth()->user()?->name ?? 'Ari Husbana',
        ];

        $journalEntry = $employee->executePayrollPosting($finalAmount, 'website', $payrollData);

        $formattedAmount = 'Rp '.number_format($finalAmount, 0, ',', '.');

        return back()->with('success', "Gaji {$employee->name} periode {$periodLabel} sebesar {$formattedAmount} berhasil dibukukan ke akun Beban Gaji (5002). Ref: {$journalEntry->reference}");
    }

    /**
     * Tampilkan atau cetak lembar slip gaji resmi (format Excel HRD).
     */
    public function showSlip(EmployeePayroll $payroll)
    {
        $payroll->load(['employee.assetAccount', 'journalEntry']);

        return view('employees.slip', compact('payroll'));
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
