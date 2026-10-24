<?php

namespace App\Console\Commands;

use App\Services\SalesSyncService;
use Illuminate\Console\Command;

class SyncSalesPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:sync-points 
                            {--start= : Tanggal awal periode (YYYY-MM-DD)} 
                            {--end= : Tanggal akhir periode (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi poin kinerja karyawan dan bonus penjualan dari web eksternal';

    /**
     * Execute the console command.
     */
    public function handle(SalesSyncService $syncService): int
    {
        $startDate = $this->option('start') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->option('end') ?? now()->format('Y-m-d');

        $this->info("Memulai sinkronisasi poin kinerja karyawan periode {$startDate} s/d {$endDate}...");

        $result = $syncService->syncPointsAndBonusFromApi($startDate, $endDate);

        if (! $result['success']) {
            $this->error('Sinkronisasi poin gagal: '.($result['message'] ?? 'Unknown error'));

            return Command::FAILURE;
        }

        if (! empty($result['details'])) {
            $this->table(
                ['Seri Nota', 'Karyawan', 'Poin', 'Status', 'Keterangan'],
                collect($result['details'])->map(fn ($d) => [
                    $d['seri'],
                    $d['employee_name'] ?? '-',
                    $d['points'] ?? 0,
                    strtoupper($d['status']),
                    $d['message'],
                ])
            );
        }

        if (! empty($result['employees_updated'])) {
            $this->newLine();
            $this->info('Ringkasan Pembaruan Bonus Karyawan:');
            $this->table(
                ['ID', 'Nama Karyawan', 'Jabatan', 'Total Poin', 'Tingkatan (Tier)', 'Tarif/Poin', 'Estimasi Bonus Poin'],
                collect($result['employees_updated'])->map(fn ($emp) => [
                    $emp['id'],
                    $emp['name'],
                    $emp['position'],
                    $emp['current_points'].' pt',
                    $emp['tier_name'],
                    'Rp '.number_format($emp['rate_per_point'], 0, ',', '.'),
                    $emp['formatted_bonus'],
                ])
            );
        }

        $this->info("Selesai: {$result['synced']} transaksi poin berhasil dicatat (+{$result['total_points']} poin), {$result['skipped']} transaksi dilewati.");

        return Command::SUCCESS;
    }
}
