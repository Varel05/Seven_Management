<?php

namespace App\Console\Commands;

use App\Services\SalesSyncService;
use Illuminate\Console\Command;

class SyncSalesTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:sync-transactions 
                            {--start= : Tanggal awal periode (YYYY-MM-DD)} 
                            {--end= : Tanggal akhir periode (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi transaksi penjualan dan sewa dari web eksternal ke Jurnal Akuntansi';

    /**
     * Execute the console command.
     */
    public function handle(SalesSyncService $syncService): int
    {
        $startDate = $this->option('start') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->option('end') ?? now()->format('Y-m-d');

        $this->info("Memulai sinkronisasi transaksi penjualan & sewa periode {$startDate} s/d {$endDate}...");

        $result = $syncService->syncTransactionsFromApi($startDate, $endDate);

        if (! $result['success']) {
            $this->error('Sinkronisasi gagal!');

            return Command::FAILURE;
        }

        $this->table(
            ['Referensi', 'Status', 'Keterangan'],
            collect($result['details'])->map(fn ($d) => [
                $d['reference'],
                strtoupper($d['status']),
                $d['message'],
            ])
        );

        $this->info("Selesai: {$result['synced']} transaksi baru dicatat, {$result['skipped']} transaksi dilewati. Total nilai: Rp ".number_format($result['total_amount'], 0, ',', '.'));

        return Command::SUCCESS;
    }
}
