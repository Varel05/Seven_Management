<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Models\RecurringTransaction;
use App\Models\TailorPayroll;
use Illuminate\Support\Facades\DB;

class TransactionCancellationService
{
    /**
     * Cancel / void a journal entry and rollback all associated records (stock, payroll, orders, recurring).
     *
     * @return array{success: bool, message: string, rolled_back: array<string, mixed>, journal_entry?: JournalEntry}
     */
    public function cancel(JournalEntry $journalEntry, ?string $reason = null, ?string $actor = null): array
    {
        if ($journalEntry->status === 'rejected') {
            return [
                'success' => false,
                'message' => "Transaksi {$journalEntry->reference} sudah dalam status dibatalkan/ditolak sebelumnya.",
                'rolled_back' => [],
            ];
        }

        return DB::transaction(function () use ($journalEntry, $reason, $actor) {
            $rolledBack = [];
            $reasonText = $reason && trim($reason) !== '' ? trim($reason) : 'Kesalahan input transaksi';

            // 3. Rollback Employee Payroll (Slip Gaji Karyawan)
            $payroll = EmployeePayroll::where('journal_entry_id', $journalEntry->id)->first();
            if ($payroll) {
                $employee = $payroll->employee;
                if ($employee) {
                    // Cari tanggal pembayaran payroll sebelum ini
                    $lastPriorPayroll = EmployeePayroll::where('employee_id', $employee->id)
                        ->where('id', '!=', $payroll->id)
                        ->latest('paid_at')
                        ->first();

                    $employee->update([
                        'last_paid_at' => $lastPriorPayroll?->paid_at,
                    ]);

                    // Rollback poin bonus jika ada pengurangan poin pada periode slip gaji ini
                    $pointDeduction = EmployeePointLog::where('employee_id', $employee->id)
                        ->where('category', EmployeePointLog::CATEGORY_MANUAL)
                        ->where('points', '<', 0)
                        ->where('notes', 'LIKE', "%{$payroll->period}%")
                        ->latest()
                        ->first();

                    if ($pointDeduction) {
                        $restoredPoints = abs($pointDeduction->points);
                        $employee->increment('current_points', $restoredPoints);
                        $employee->pointLogs()->create([
                            'points' => $restoredPoints,
                            'category' => EmployeePointLog::CATEGORY_MANUAL,
                            'actor' => $actor ?? 'System',
                            'notes' => "Pemulihan poin karena pembatalan slip gaji {$journalEntry->reference}",
                        ]);
                    }

                    $rolledBack['payroll'] = [
                        'employee' => $employee->name,
                        'period' => $payroll->period,
                    ];
                }
                $payroll->delete();
            } else {
                // Cek jika ada penggajian via deskripsi transaksi gaji langsung
                $descLower = strtolower($journalEntry->description);
                if (str_contains($descLower, 'gaji')) {
                    $matchedEmployee = Employee::where('status', 'active')->get()->first(function ($emp) use ($descLower) {
                        return str_contains($descLower, strtolower($emp->name));
                    });
                    if ($matchedEmployee && $matchedEmployee->last_paid_at) {
                        $matchedEmployee->update(['last_paid_at' => null]);
                        $rolledBack['payroll_employee'] = $matchedEmployee->name;
                    }
                }
            }

            // 4. Rollback Tailor Payroll (Upah Borongan Penjahit)
            $tailorPayroll = TailorPayroll::where('journal_entry_id', $journalEntry->id)->first();
            if ($tailorPayroll) {
                $rolledBack['tailor_payroll'] = [
                    'employee' => $tailorPayroll->employee?->name,
                    'period' => $tailorPayroll->payroll_date?->format('d/m/Y'),
                ];
                $tailorPayroll->delete();
            }

            // 6. Rollback Recurring Transaction (Pengeluaran Rutin)
            $recurringMatch = RecurringTransaction::get()->first(function ($rec) use ($journalEntry) {
                return str_contains($journalEntry->description, $rec->name);
            });
            if ($recurringMatch && $recurringMatch->last_posted_at) {
                $recurringMatch->update(['last_posted_at' => null]);
                $rolledBack['recurring_transaction'] = $recurringMatch->name;
            }

            // 7. Update status JournalEntry menjadi rejected
            $prefix = "[BATAL: {$reasonText}] ";
            $newDescription = str_starts_with($journalEntry->description, '[BATAL')
                ? $journalEntry->description
                : ($prefix.$journalEntry->description);

            $journalEntry->update([
                'status' => 'rejected',
                'description' => substr($newDescription, 0, 255),
            ]);

            return [
                'success' => true,
                'message' => "Transaksi {$journalEntry->reference} berhasil dibatalkan.",
                'rolled_back' => $rolledBack,
                'journal_entry' => $journalEntry,
            ];
        });
    }
}
