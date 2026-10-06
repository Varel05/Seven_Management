<?php

namespace App\Services;

use App\Models\CustomSuitOrder;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Models\MaterialStockMovement;
use App\Models\RecurringTransaction;
use App\Models\RetailSale;
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

            // 1. Rollback Penjualan Retail / Sewa (kembalikan stok pakaian jadi)
            $retailSale = RetailSale::where('journal_entry_id', $journalEntry->id)
                ->orWhere('invoice_number', $journalEntry->reference)
                ->first();

            if ($retailSale) {
                foreach ($retailSale->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                        $rolledBack['retail_stock'][] = [
                            'product' => $item->product->name,
                            'quantity' => $item->quantity,
                        ];
                    }
                }
                $retailSale->update([
                    'notes' => "[DIBATALKAN: {$reasonText}] ".($retailSale->notes ?? ''),
                ]);
            }

            // 2. Rollback Restock Material Gudang (kurangi kembali stok bahan baku fisik)
            $movement = MaterialStockMovement::where('reference_number', $journalEntry->reference)->first();
            if ($movement && $movement->type === 'in' && $movement->material) {
                $material = $movement->material;
                $material->decrement('stock', $movement->quantity);

                MaterialStockMovement::create([
                    'material_id' => $material->id,
                    'type' => 'out',
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'reference_type' => 'adjustment',
                    'reference_number' => 'VOID-'.$journalEntry->reference,
                    'notes' => "Pembatalan restock {$journalEntry->reference}: {$reasonText}",
                ]);

                $rolledBack['material_stock'] = [
                    'material' => $material->name,
                    'deducted_quantity' => $movement->quantity,
                ];
            }

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

            // 5. Rollback Custom Suit Order (Pesanan Jas Jahit)
            $customOrder = CustomSuitOrder::where('journal_entry_id', $journalEntry->id)
                ->orWhere('order_number', $journalEntry->reference)
                ->first();
            if ($customOrder) {
                $customOrder->update([
                    'status' => 'cancelled',
                    'notes' => "[DIBATALKAN: {$reasonText}] ".($customOrder->notes ?? ''),
                ]);
                $rolledBack['custom_suit_order'] = $customOrder->order_number;
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
