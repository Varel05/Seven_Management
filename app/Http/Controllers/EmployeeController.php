<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Allowance;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\AttendanceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees and payroll summary.
     */
    public function index(Request $request, AttendanceSyncService $syncService)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));

        // Otomatis sinkronisasi karyawan dan presensi dari REST API Web Karyawan (CI4)
        $syncResult = $syncService->syncEmployeesFromApi($startDate, $endDate);

        // Ambil data karyawan (prioritaskan karyawan hasil sinkronisasi API)
        $employees = Employee::with([
            'assetAccount',
            'latestPayroll.journalEntry.lines.account',
            'payrolls.journalEntry',
        ])
            ->orderByRaw('CASE WHEN nip IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        // Pasangkan data kehadiran periode berjalan ke setiap karyawan
        $attendanceMap = $syncService->getAttendanceMap($startDate, $endDate);
        foreach ($employees as $emp) {
            $emp->api_attendance = $attendanceMap[$emp->nip] ?? null;
        }

        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();

        // Ringkasan Payroll Bulan Ini
        $activeEmployees = $employees->where('status', 'active');
        $totalActive = $activeEmployees->count();

        $allowances = Allowance::with('employee')->latest()->get();
        $totalBaseSalary = $activeEmployees->sum('base_salary');
        $averageDailyRate = $totalActive > 0 ? (float) $activeEmployees->avg('effective_daily_rate') : 0;
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
            'averageDailyRate',
            'totalAllowanceEstimate',
            'totalBonusSalary',
            'totalPayrollEstimate',
            'totalPaidThisMonth',
            'totalPendingPayroll',
            'syncResult',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50|unique:employees,nip',
            'role' => ['nullable', Rule::enum(EmployeeRole::class)],
            'position' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'base_salary' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
            'current_points' => 'nullable|integer|min:0',
            'rate_per_point' => 'nullable|numeric|min:0',
            'pay_day' => 'nullable|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'status' => 'required|in:active,inactive',
            'claim_bonus' => 'nullable|boolean',
        ]);

        $validated['role'] = $validated['role'] ?? EmployeeRole::Staff->value;
        $validated['pay_day'] = (int) ($validated['pay_day'] ?? 25);
        $validated['base_salary'] = (float) ($validated['base_salary'] ?? 0);
        $validated['daily_rate'] = (float) ($validated['daily_rate'] ?? 0);
        $validated['discipline_rate'] = (float) ($validated['discipline_rate'] ?? 0);
        $validated['holiday_rate'] = (float) ($validated['holiday_rate'] ?? 0);
        $validated['current_points'] = (int) ($validated['current_points'] ?? 0);
        $tierRate = Employee::getRateForPoints($validated['current_points']);
        $validated['rate_per_point'] = $tierRate > 0
            ? $tierRate
            : (float) ($validated['rate_per_point'] ?? 0);
        $validated['claim_bonus'] = $request->has('claim_bonus')
            ? $request->boolean('claim_bonus')
            : true;

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
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'nip')->ignore($employee->id)],
            'role' => ['nullable', Rule::enum(EmployeeRole::class)],
            'position' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
            'pay_day' => 'nullable|integer|min:1|max:31',
            'asset_account_id' => 'nullable|exists:accounts,id',
            'base_salary' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
            'current_points' => 'nullable|integer|min:0',
            'rate_per_point' => 'nullable|numeric|min:0',
            'claim_bonus' => 'nullable|boolean',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'position' => $validated['position'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ];

        if (array_key_exists('nip', $validated)) {
            $updateData['nip'] = $validated['nip'];
        }

        if (array_key_exists('role', $validated) && $validated['role'] !== null) {
            $updateData['role'] = $validated['role'];
        }

        if (array_key_exists('pay_day', $validated) && $validated['pay_day'] !== null) {
            $updateData['pay_day'] = $validated['pay_day'];
        }

        if (array_key_exists('asset_account_id', $validated)) {
            $updateData['asset_account_id'] = $validated['asset_account_id'];
        }

        // Field gaji hanya diupdate jika memang dikirimkan dalam request
        if (array_key_exists('base_salary', $validated) && $validated['base_salary'] !== null) {
            $updateData['base_salary'] = $validated['base_salary'];
        }

        if (array_key_exists('daily_rate', $validated)) {
            $updateData['daily_rate'] = $validated['daily_rate'];
        }

        if (array_key_exists('discipline_rate', $validated)) {
            $updateData['discipline_rate'] = $validated['discipline_rate'];
        }

        if (array_key_exists('holiday_rate', $validated)) {
            $updateData['holiday_rate'] = $validated['holiday_rate'];
        }

        if (array_key_exists('current_points', $validated) && $validated['current_points'] !== null) {
            $updateData['current_points'] = (int) $validated['current_points'];
            $tierRate = Employee::getRateForPoints($updateData['current_points']);
            $updateData['rate_per_point'] = $tierRate > 0
                ? $tierRate
                : (float) ($validated['rate_per_point'] ?? $employee->rate_per_point);
        }

        if ($request->has('claim_bonus')) {
            $updateData['claim_bonus'] = $request->boolean('claim_bonus');
        }

        $employee->update($updateData);

        return redirect()->route('employees.index')->with('success', "Data karyawan '{$employee->name}' berhasil diperbarui.");
    }

    /**
     * Update employee base salary and shift rates directly from table.
     */
    public function updateSalary(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'daily_rate' => 'required|numeric|min:0',
            'discipline_rate' => 'nullable|numeric|min:0',
            'holiday_rate' => 'nullable|numeric|min:0',
        ]);

        $dailyRate = (float) $validated['daily_rate'];
        $baseSalary = $dailyRate * 27;

        $employee->update([
            'daily_rate' => $dailyRate,
            'base_salary' => $baseSalary,
            'discipline_rate' => (float) ($validated['discipline_rate'] ?? 0),
            'holiday_rate' => (float) ($validated['holiday_rate'] ?? 0),
        ]);

        return redirect()->route('employees.index')->with('success', "Tarif gaji per shift karyawan '{$employee->name}' berhasil diperbarui (Rp ".number_format($dailyRate, 0, ',', '.').'/shift).');
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
            'asset_account_id' => 'nullable|exists:accounts,id',
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
            'period_start' => $validated['period_start'] ?? now()->startOfMonth()->toDateString(),
            'period_end' => $validated['period_end'] ?? now()->endOfMonth()->toDateString(),
            'payment_method' => $validated['payment_method'] ?? 'Transfer',
            'asset_account_id' => $validated['asset_account_id'] ?? null,
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

        $payroll = EmployeePayroll::where('journal_entry_id', $journalEntry->id)->first()
            ?? $employee->latestPayroll;

        $formattedAmount = 'Rp '.number_format($finalAmount, 0, ',', '.');

        if ($payroll) {
            return redirect()->route('employees.payroll.slip', ['payroll' => $payroll, 'auto_pdf' => 1])
                ->with('success', "Slip gaji {$employee->name} periode {$periodLabel} sebesar {$formattedAmount} berhasil dibukukan (Ref: {$journalEntry->reference}) dan slip PDF otomatis dibuat.");
        }

        return redirect()->route('employees.index')
            ->with('success', "Gaji {$employee->name} periode {$periodLabel} sebesar {$formattedAmount} berhasil dibukukan ke akun Beban Gaji (5002). Ref: {$journalEntry->reference}");
    }

    /**
     * Generate atau edit lembar slip gaji karyawan (Tahap 1: Belum dicatat ke transaksi).
     */
    public function generateSlip(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'period' => 'nullable|string|max:50',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'asset_account_id' => 'nullable|exists:accounts,id',
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

        $periodLabel = $validated['period'] ?? now()->translatedFormat('F Y');
        $periodStart = $validated['period_start'] ?? now()->startOfMonth()->toDateString();
        $periodEnd = $validated['period_end'] ?? now()->endOfMonth()->toDateString();

        // Cari apakah slip gaji untuk periode ini sudah pernah dibuat sebelumnya
        $existingPayroll = EmployeePayroll::where('employee_id', $employee->id)
            ->where(function ($q) use ($periodLabel, $periodStart) {
                $q->where('period', $periodLabel)
                    ->orWhere('period_start', $periodStart);
            })
            ->latest()
            ->first();

        // Jika slip sudah dibukukan ke jurnal transaksi keuangan, kunci agar tidak di-overwrite
        if ($existingPayroll && $existingPayroll->isPaid()) {
            return redirect()->route('employees.payroll.slip', $existingPayroll)
                ->with('warning', "Slip gaji {$employee->name} periode {$periodLabel} sudah dibukukan ke transaksi keuangan (Ref: {$existingPayroll->journalEntry?->reference}) dan tidak dapat diedit lagi.");
        }

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

        $payrollData = [
            'employee_id' => $employee->id,
            'period' => $periodLabel,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'payment_method' => $validated['payment_method'] ?? 'Transfer',
            'asset_account_id' => $validated['asset_account_id'] ?? $employee->asset_account_id,
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

        if ($existingPayroll) {
            $existingPayroll->update($payrollData);
            $payroll = $existingPayroll;
            $message = "Slip gaji {$employee->name} periode {$periodLabel} berhasil diperbarui. Silakan periksa sebelum mencatat ke transaksi.";
        } else {
            $payroll = EmployeePayroll::create($payrollData);
            $message = "Slip gaji {$employee->name} periode {$periodLabel} berhasil dibuat. Silakan periksa sebelum mencatat ke transaksi.";
        }

        return redirect()->route('employees.payroll.slip', $payroll)->with('success', $message);
    }

    /**
     * Bukukan slip gaji yang telah dibuat ke transaksi jurnal keuangan (Tahap 2).
     */
    public function postTransaction(Request $request, EmployeePayroll $payroll): RedirectResponse
    {
        if ($payroll->isPaid()) {
            return redirect()->route('employees.payroll.slip', $payroll)
                ->with('warning', "Slip gaji periode {$payroll->period} sudah dicatat ke transaksi keuangan sebelumnya (Ref: {$payroll->journalEntry?->reference}).");
        }

        $validated = $request->validate([
            'asset_account_id' => 'nullable|exists:accounts,id',
        ]);

        $assetAccountId = $validated['asset_account_id'] ?? $payroll->asset_account_id;

        $journalEntry = DB::transaction(function () use ($payroll, $assetAccountId) {
            $employee = $payroll->employee;
            $finalAmount = (float) $payroll->take_home_pay;
            $period = $payroll->period;

            // Pastikan akun 5002 (Beban Gaji) ada
            $expenseAccount = Account::firstOrCreate(
                ['code' => '5002'],
                ['name' => 'Beban Gaji', 'type' => 'expense']
            );

            $assetAccount = ($assetAccountId ? Account::find($assetAccountId) : null)
                ?? $payroll->assetAccount
                ?? $employee->assetAccount
                ?? Account::where('code', '1001')->first()
                ?? Account::where('type', 'asset')->first();

            $reference = 'PAY-'.strtoupper(Str::random(8));

            $journalEntry = JournalEntry::create([
                'reference' => $reference,
                'description' => "Penggajian Karyawan: {$employee->name} ({$period})",
                'date' => now(),
                'source' => 'website',
                'status' => 'verified',
            ]);

            // 1. Debit Akun Beban Gaji (5002)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'description' => "Gaji {$employee->name} (Pokok/Honor: Rp ".number_format($payroll->main_salary, 0, ',', '.').')',
                'debit' => $finalAmount,
                'credit' => 0,
            ]);

            // 2. Kredit Akun Kas/Bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $assetAccount->id,
                'description' => "Pembayaran Gaji {$employee->name} via {$assetAccount->name}",
                'debit' => 0,
                'credit' => $finalAmount,
            ]);

            $payroll->update([
                'journal_entry_id' => $journalEntry->id,
                'asset_account_id' => $assetAccount->id,
                'paid_at' => now(),
            ]);

            if ($payroll->period_end && now()->format('Y-m') === $payroll->period_end->format('Y-m')) {
                $employee->update(['last_paid_at' => now()]);
            }

            return $journalEntry;
        });

        $formattedAmount = 'Rp '.number_format($payroll->take_home_pay, 0, ',', '.');

        return redirect()->route('employees.payroll.slip', $payroll)
            ->with('success', "Slip gaji {$payroll->employee->name} periode {$payroll->period} sebesar {$formattedAmount} berhasil dibukukan ke transaksi keuangan (Ref: {$journalEntry->reference}).");
    }

    /**
     * Tampilkan atau cetak lembar slip gaji resmi (format Excel HRD).
     */
    public function showSlip(EmployeePayroll $payroll)
    {
        $payroll->load(['employee.assetAccount', 'assetAccount', 'journalEntry.lines.account']);
        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();

        return view('employees.slip', compact('payroll', 'assetAccounts'));
    }

    /**
     * Redirect langsung untuk memicu pembuatan dan pengunduhan file PDF slip gaji.
     */
    public function downloadPdf(EmployeePayroll $payroll)
    {
        return redirect()->route('employees.payroll.slip', ['payroll' => $payroll, 'auto_pdf' => 1]);
    }

    /**
     * Preview lembar slip gaji untuk draft / kalkulasi berjalan sebelum dibukukan.
     */
    public function previewSlip(Request $request, Employee $employee)
    {
        $employee->load('assetAccount');
        $period = $request->query('period') ?: now()->translatedFormat('F Y');
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();
        $totalShifts = (int) ($request->query('total_shifts') ?? 27);
        $totalPresent = (int) ($request->query('total_present') ?? 27);
        $lateCount = (int) ($request->query('late_count') ?? 0);
        $disciplinePresent = (int) ($request->query('discipline_present') ?? max(0, $totalPresent - $lateCount));
        $holidayShifts = (int) ($request->query('holiday_shifts') ?? 0);
        $dailyRate = $employee->effective_daily_rate;
        $disciplineRate = $employee->effective_discipline_rate;
        $holidayRate = $employee->effective_holiday_rate;
        $mainSalary = $totalPresent * $dailyRate;
        $disciplineBonus = $disciplinePresent * $disciplineRate;
        $salesBonus = (float) $employee->bonus_salary;
        $holidayBonus = $holidayShifts * $holidayRate;
        $allowanceTotal = (float) $employee->total_allowance;
        $takeHomePay = $mainSalary + $disciplineBonus + $salesBonus + $holidayBonus + $allowanceTotal;

        $payroll = new EmployeePayroll([
            'employee_id' => $employee->id,
            'period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'payment_method' => 'Transfer',
            'total_shifts' => $totalShifts,
            'total_present' => $totalPresent,
            'late_count' => $lateCount,
            'discipline_present' => $disciplinePresent,
            'holiday_shifts' => $holidayShifts,
            'daily_rate' => $dailyRate,
            'discipline_rate' => $disciplineRate,
            'holiday_rate' => $holidayRate,
            'closing_points' => (int) $employee->current_points,
            'closing_pcs' => (int) $employee->current_points,
            'rate_per_point' => (float) $employee->rate_per_point,
            'main_salary' => $mainSalary,
            'discipline_bonus' => $disciplineBonus,
            'sales_bonus' => $salesBonus,
            'holiday_bonus' => $holidayBonus,
            'allowance_total' => $allowanceTotal,
            'take_home_pay' => $takeHomePay,
            'notes' => 'Draft / Estimasi Berjalan (Belum Dibayar)',
            'hrd_name' => 'Ari Husbana',
        ]);
        $payroll->setRelation('employee', $employee);

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

    /**
     * Ambil data presensi karyawan dari API Web Karyawan (CI4).
     */
    public function fetchAttendance(Request $request, Employee $employee, AttendanceSyncService $syncService)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $result = $syncService->getAttendanceForEmployee($employee, $startDate, $endDate);

        return response()->json($result);
    }

    /**
     * Ambil seluruh daftar presensi/pegawai yang tersedia dari API Web Karyawan (CI4).
     */
    public function attendanceList(Request $request, AttendanceSyncService $syncService)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $result = $syncService->fetchAttendance(null, $startDate, $endDate);

        return response()->json($result);
    }

    /**
     * Pemicu manual untuk menyinkronkan data karyawan dan absensi dari REST API CI4.
     */
    public function syncFromApi(Request $request, AttendanceSyncService $syncService)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        $result = $syncService->syncEmployeesFromApi($startDate, $endDate);

        return back()->with($result['success'] ? 'success' : 'warning', $result['message']);
    }
}
