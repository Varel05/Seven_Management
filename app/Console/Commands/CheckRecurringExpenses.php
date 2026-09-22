<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use Illuminate\Console\Command;

class CheckRecurringExpenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:check {--notify : Mark checked items as notified}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa dan tampilkan tagihan pengeluaran rutin yang jatuh tempo hari ini';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $allActive = RecurringTransaction::with(['expenseAccount', 'assetAccount'])
            ->active()
            ->get();

        $dueList = $allActive->filter(fn($item) => $item->isDueToday())->values();

        if ($dueList->isEmpty()) {
            $this->info('Tidak ada pengeluaran rutin yang jatuh tempo hari ini (' . now()->translatedFormat('d F Y') . ').');
            return Command::SUCCESS;
        }

        $this->warn("Ditemukan {$dueList->count()} pengeluaran rutin yang jatuh tempo hari ini:");

        $tableData = $dueList->map(function ($item) {
            return [
                'ID'             => $item->id,
                'Nama Tagihan'   => $item->name,
                'Nominal'        => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                'Siklus'         => ucfirst($item->frequency),
                'Jatuh Tempo'    => 'Tgl ' . $item->day_of_month,
                'Akun Beban'     => $item->expenseAccount->name ?? '-',
                'Sumber Kas/Bank'=> $item->assetAccount->name ?? '-',
            ];
        });

        $this->table(['ID', 'Nama Tagihan', 'Nominal', 'Siklus', 'Jatuh Tempo', 'Akun Beban', 'Sumber Kas/Bank'], $tableData);

        if ($this->option('notify')) {
            foreach ($dueList as $item) {
                $item->update(['last_notified_at' => now()]);
            }
            $this->info('Semua tagihan telah ditandai sebagai ternotifikasi.');
        }

        return Command::SUCCESS;
    }
}
