<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal otomatis sinkronisasi transaksi penjualan dan sewa dari Web E-Commerce
Schedule::command('sales:sync-transactions')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Jadwal otomatis sinkronisasi poin kinerja CS / karyawan dari nota selesai
Schedule::command('sales:sync-points')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Jadwal harian pemeriksaan pengeluaran berulang (recurring expenses)
Schedule::command('recurring:check --notify')
    ->dailyAt('08:00');
