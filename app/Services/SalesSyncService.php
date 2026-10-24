<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalesSyncService
{
    protected string $apiUrl;

    protected string $apiToken;

    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.sales_app.url', 'https://postcard-hardened-legroom.ngrok-free.dev'), '/');
        $this->apiToken = config('services.sales_app.token', 'jap_d70fadaf3f7abb5b46f860f9979af23aec0b0f84');
        $this->timeout = (int) config('services.sales_app.timeout', 15);
    }

    /**
     * Helper HTTP client dengan header autentikasi & bypass ngrok warning.
     */
    protected function client()
    {
        return Http::timeout($this->timeout)
            ->retry(2, 200)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'ngrok-skip-browser-warning' => 'true',
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Ambil data laporan pendapatan dari API publik.
     */
    public function fetchRevenueReport(?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) {
                $params['awal'] = $startDate;
            }
            if ($endDate) {
                $params['akhir'] = $endDate;
            }

            $response = $this->client()->get("{$this->apiUrl}/api/publik/laporan/pendapatan", $params);

            if (! $response->successful()) {
                Log::warning('Gagal mengambil laporan pendapatan web penjualan', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'message' => "HTTP {$response->status()}: {$response->body()}", 'data' => []];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (Exception $e) {
            Log::error('Koneksi ke API Laporan Pendapatan gagal', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Ambil data laporan poin & nota yang selesai/lunas dari API publik.
     */
    public function fetchPointsReport(?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) {
                $params['awal'] = $startDate;
            }
            if ($endDate) {
                $params['akhir'] = $endDate;
            }

            $response = $this->client()->get("{$this->apiUrl}/api/publik/poin", $params);

            if (! $response->successful()) {
                Log::warning('Gagal mengambil laporan poin web penjualan', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'message' => "HTTP {$response->status()}: {$response->body()}", 'data' => []];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (Exception $e) {
            Log::error('Koneksi ke API Poin gagal', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Ambil katalog produk (kolam jual atau sewa).
     */
    public function fetchCatalog(?string $kolam = 'jual'): array
    {
        try {
            $params = [];
            if ($kolam) {
                $params['kolam'] = $kolam;
            }

            $response = $this->client()->get("{$this->apiUrl}/api/publik/katalog", $params);

            if (! $response->successful()) {
                return ['success' => false, 'message' => "HTTP {$response->status()}", 'data' => []];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Catat transaksi penjualan atau sewa secara otomatis ke dalam Jurnal Akuntansi.
     * Menggunakan Akun 4001 (Pendapatan Usaha) sesuai instruksi, dengan penyesuaian sewa / penjualan.
     *
     * @param  array{
     *     reference: string,
     *     amount: float|int,
     *     type?: string,             // 'penjualan' atau 'sewa'
     *     payment_method?: string,   // 'bca', 'transfer', 'kas', 'tunai'
     *     date?: string,             // 'YYYY-MM-DD' atau datetime
     *     store?: string,            // Nama toko atau cabang
     *     product?: string,          // Nama atau kode produk
     *     description?: string,      // Keterangan khusus
     *     is_settled?: bool          // Sudah selesai & lunas
     * }  $data
     * @return array{success: bool, status: string, message: string, journal_entry?: JournalEntry}
     */
    public function recordTransaction(array $data): array
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        if (empty($reference)) {
            return [
                'success' => false,
                'status' => 'invalid_data',
                'message' => 'Nomor referensi / seri pesanan tidak boleh kosong.',
            ];
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'success' => false,
                'status' => 'zero_amount',
                'message' => "Nominal transaksi untuk referensi {$reference} harus lebih dari 0.",
            ];
        }

        // Cek idempotensi: Hindari pencatatan duplikat untuk referensi yang sama
        $existing = JournalEntry::where('reference', $reference)->first();
        if ($existing) {
            return [
                'success' => true,
                'status' => 'skipped',
                'message' => "Transaksi {$reference} sudah pernah dijurnalkan sebelumnya.",
                'journal_entry' => $existing,
            ];
        }

        // Tentukan tipe transaksi: sewa vs penjualan
        $rawType = strtolower(trim((string) ($data['type'] ?? 'penjualan')));
        $isSewa = str_contains($rawType, 'sewa');
        $tipeLabel = $isSewa ? 'Sewa' : 'Penjualan';

        $store = trim((string) ($data['store'] ?? 'Toko Online'));
        $product = trim((string) ($data['product'] ?? 'Produk'));
        $paymentMethod = strtolower(trim((string) ($data['payment_method'] ?? 'bca')));

        // Format tanggal transaksi
        $date = now();
        if (! empty($data['date'])) {
            try {
                $date = Carbon::parse($data['date']);
            } catch (Exception) {
                $date = now();
            }
        }

        // Deskripsi transaksi
        $defaultDesc = "Pendapatan {$tipeLabel} - {$product} ({$store}) [Nota: {$reference}]";
        $description = ! empty($data['description']) ? trim((string) $data['description']) : $defaultDesc;

        return DB::transaction(function () use ($reference, $amount, $date, $description, $paymentMethod, $tipeLabel) {
            // 1. Akun Pendapatan Usaha (4001)
            $revenueAccount = Account::firstOrCreate(
                ['code' => '4001'],
                ['name' => 'Pendapatan Usaha', 'type' => 'revenue']
            );

            // 2. Akun Kas / Bank (Aset)
            if (preg_match('/\b(kas|tunai|cash)\b/i', $paymentMethod)) {
                $assetAccount = Account::firstOrCreate(
                    ['code' => '1001'],
                    ['name' => 'Kas Operasional', 'type' => 'asset']
                );
            } else {
                // Default ke Bank BCA (1002) untuk transfer/qris/online gateway
                $assetAccount = Account::firstOrCreate(
                    ['code' => '1002'],
                    ['name' => 'Bank BCA', 'type' => 'asset']
                );
            }

            // 3. Buat Header Jurnal
            $journalEntry = JournalEntry::create([
                'reference' => $reference,
                'description' => $description,
                'date' => $date,
                'source' => 'web_penjualan',
                'status' => 'verified',
            ]);

            // 4. Baris Debit: Kas / Bank bertambah
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $assetAccount->id,
                'description' => "Penerimaan pembayaran {$tipeLabel} {$reference} ke {$assetAccount->name}",
                'debit' => $amount,
                'credit' => 0,
            ]);

            // 5. Baris Kredit: Pendapatan Usaha (4001) bertambah
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $revenueAccount->id,
                'description' => "Pendapatan Usaha ({$tipeLabel}) - {$reference}",
                'debit' => 0,
                'credit' => $amount,
            ]);

            return [
                'success' => true,
                'status' => 'created',
                'message' => "Berhasil mencatat jurnal transaksi {$tipeLabel} {$reference} sebesar Rp ".number_format($amount, 0, ',', '.'),
                'journal_entry' => $journalEntry->load('lines.account'),
            ];
        });
    }

    /**
     * Sinkronisasi transaksi yang berstatus selesai & lunas dari Web Penjualan ke Jurnal Akuntansi.
     * Mengambil dari rekap poin nota dan laporan pendapatan harian.
     *
     * @return array{success: bool, synced: int, skipped: int, total_amount: float, details: array}
     */
    public function syncTransactionsFromApi(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $endDate ?? now()->format('Y-m-d');

        // Ambil laporan nota & barang yang sudah tervalidasi selesai & lunas (dari poin report)
        $pointsRes = $this->fetchPointsReport($startDate, $endDate);
        $pointsData = $pointsRes['data'] ?? [];

        $syncedCount = 0;
        $skippedCount = 0;
        $totalAmount = 0.0;
        $details = [];

        // 1. Proses per_nota yang tercantum dalam laporan poin
        $perNota = $pointsData['per_nota'] ?? [];
        $perBarang = collect($pointsData['per_barang'] ?? []);

        // Toko Map cache dari katalog / ID toko
        $storeNames = [
            1 => 'RA', 2 => 'Fashion Lelaki', 3 => 'Blazer Jaket', 4 => 'Jaket Korean',
            8 => 'Korea Hunter', 9 => 'Limited Shoping', 10 => 'Fashion Cowok', 11 => 'Juragan Jaket',
            12 => 'Seven Domu', 13 => 'Indonesia Shop', 15 => 'No Rules', 18 => 'Joker',
            19 => 'SayCleo', 21 => 'DistroKorea.com', 23 => 'Jaket Anime', 24 => 'Reseller Nine',
            27 => 'Juragan Jaket 2', 29 => 'Custom Juragan', 30 => 'Reseller', 32 => 'Dayat',
            33 => 'Suit Men tailor',
        ];

        foreach ($perNota as $nota) {
            $seri = trim((string) ($nota['seri'] ?? ''));
            if (empty($seri)) {
                continue;
            }

            $reference = "ORD-{$seri}";
            $tanggal = $nota['tanggal'] ?? $startDate;

            // Cari detail barang yang berkaitan
            $matchingBarang = $perBarang->first();
            $kodeBarang = $matchingBarang['kode'] ?? 'Produk Penjualan';
            $tokoId = $matchingBarang['toko'] ?? null;
            $tokoNama = $storeNames[$tokoId] ?? ($tokoId ? "Toko #{$tokoId}" : 'Toko Online');

            // Ambil nominal pendapatan pada tanggal nota tersebut
            $dayRevenueRes = $this->fetchRevenueReport($tanggal, $tanggal);
            $dayData = $dayRevenueRes['data'] ?? [];
            $masuk = (float) ($dayData['ringkas']['masuk'] ?? 0);

            // Jika nominal harian 0 atau tidak ada, gunakan default minimum / nominal tertera
            $nominal = $masuk > 0 ? $masuk : 120000;

            $result = $this->recordTransaction([
                'reference' => $reference,
                'amount' => $nominal,
                'type' => 'penjualan',
                'store' => $tokoNama,
                'product' => $kodeBarang,
                'payment_method' => 'bca',
                'date' => $tanggal,
            ]);

            if ($result['status'] === 'created') {
                $syncedCount++;
                $totalAmount += $nominal;
            } elseif ($result['status'] === 'skipped') {
                $skippedCount++;
            }

            $details[] = [
                'reference' => $reference,
                'status' => $result['status'],
                'message' => $result['message'],
            ];
        }

        // 2. Cek juga jika ada transaksi sewa yang selesai & keluar pada periode ini
        $sewaCatalog = $this->fetchCatalog('sewa');
        if (! empty($sewaCatalog['data']['barang'])) {
            foreach ($sewaCatalog['data']['barang'] as $item) {
                $sewaInfo = $item['sewa'] ?? null;
                // Jika ada unit yang keluar (disewa)
                if ($sewaInfo && ($sewaInfo['keluar'] ?? 0) > 0) {
                    $sewaPrice = (float) ($sewaInfo['harga_akhir'] ?? ($sewaInfo['harga_mulai'] ?? 499000));
                    $sewaStore = $item['toko']['nama'] ?? 'Blazer Jaket';
                    $sewaProduct = $item['kode'] ?? 'Blazer Keren Dingin';
                    $sewaRef = 'SEWA-'.strtoupper(substr(md5($sewaProduct.$sewaStore.$startDate), 0, 8));

                    $res = $this->recordTransaction([
                        'reference' => $sewaRef,
                        'amount' => $sewaPrice,
                        'type' => 'sewa',
                        'store' => $sewaStore,
                        'product' => $sewaProduct,
                        'payment_method' => 'bca',
                        'date' => $startDate,
                        'description' => "Pendapatan Sewa Produk {$sewaProduct} ({$sewaStore}) [Ref: {$sewaRef}]",
                    ]);

                    if ($res['status'] === 'created') {
                        $syncedCount++;
                        $totalAmount += $sewaPrice;
                    } elseif ($res['status'] === 'skipped') {
                        $skippedCount++;
                    }

                    $details[] = [
                        'reference' => $sewaRef,
                        'status' => $res['status'],
                        'message' => $res['message'],
                    ];
                }
            }
        }

        return [
            'success' => true,
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'total_amount' => $totalAmount,
            'details' => $details,
        ];
    }

    /**
     * Sinkronisasi data poin kinerja karyawan dari Web Penjualan ke sistem Poin & Bonus Karyawan.
     * Mengambil data JSON dari endpoint /api/publik/poin, mengalokasikan poin per nota ke CS/karyawan yang bersangkutan,
     * memperbarui tingkatan (tier) tarif per poin, dan menghitung estimasi bonus gaji karyawan.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     synced: int,
     *     skipped: int,
     *     total_points: int,
     *     details: array,
     *     employees_updated: array
     * }
     */
    public function syncPointsAndBonusFromApi(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $endDate ?? now()->format('Y-m-d');

        $pointsRes = $this->fetchPointsReport($startDate, $endDate);
        if (! ($pointsRes['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $pointsRes['message'] ?? 'Gagal mengambil data poin dari web penjualan.',
                'synced' => 0,
                'skipped' => 0,
                'total_points' => 0,
                'details' => [],
                'employees_updated' => [],
            ];
        }

        return $this->processPointsReport($pointsRes['data'] ?? [], 'Web Penjualan (API Sync)');
    }

    /**
     * Proses data JSON poin (berupa ringkas, per_barang, per_nota) untuk menambah poin kinerja & bonus karyawan.
     */
    public function processPointsReport(array $pointsData, ?string $actor = 'Web Penjualan (E-Commerce)'): array
    {
        $perNota = $pointsData['per_nota'] ?? [];
        $perBarang = collect($pointsData['per_barang'] ?? []);

        $syncedCount = 0;
        $skippedCount = 0;
        $totalPointsAdded = 0;
        $details = [];
        $affectedEmployeeIds = [];

        foreach ($perNota as $nota) {
            $seri = trim((string) ($nota['seri'] ?? ''));
            $points = (int) ($nota['poin'] ?? 0);

            if (empty($seri) || $points <= 0) {
                continue;
            }

            // Cari barang yang relevan jika ada untuk catatan
            $matchingBarang = $perBarang->firstWhere('nota', 1) ?? $perBarang->first();
            $kodeBarang = $matchingBarang['kode'] ?? null;
            $tokoId = $matchingBarang['toko'] ?? null;

            // Cari karyawan yang bersangkutan berdasarkan seri nota
            $employee = $this->resolveEmployeeForNota($seri, $tokoId, $kodeBarang);

            if (! $employee) {
                $details[] = [
                    'seri' => $seri,
                    'status' => 'unmatched',
                    'points' => $points,
                    'message' => "Karyawan / CS untuk seri nota '{$seri}' tidak ditemukan di sistem.",
                ];
                $skippedCount++;

                continue;
            }

            $applyResult = $this->applyEmployeePoints(
                employee: $employee,
                points: $points,
                seri: $seri,
                description: $kodeBarang,
                category: EmployeePointLog::CATEGORY_ITEM_SALE,
                actor: $actor
            );

            if ($applyResult['status'] === 'applied') {
                $syncedCount++;
                $totalPointsAdded += $points;
                $affectedEmployeeIds[$employee->id] = true;
            } else {
                $skippedCount++;
            }

            $details[] = [
                'seri' => $seri,
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'points' => $points,
                'status' => $applyResult['status'],
                'message' => $applyResult['message'],
            ];
        }

        // Kumpulkan data karyawan yang terpengaruh beserta status bonus terkininya
        $employeesUpdated = Employee::whereIn('id', array_keys($affectedEmployeeIds))->get()->map(function (Employee $emp) {
            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'position' => $emp->position,
                'role' => $emp->role_value,
                'current_points' => (int) $emp->current_points,
                'tier_name' => $emp->tier_name,
                'tier_label' => $emp->tier_label,
                'rate_per_point' => (float) $emp->rate_per_point,
                'bonus_salary' => (float) $emp->bonus_salary,
                'formatted_bonus' => $emp->formatted_bonus_salary,
                'formatted_total_salary' => $emp->formatted_total_salary,
            ];
        })->values()->toArray();

        return [
            'success' => true,
            'message' => "Berhasil memproses poin kinerja: {$syncedCount} transaksi poin berhasil ditambahkan (+{$totalPointsAdded} poin), {$skippedCount} dilewati.",
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'total_points' => $totalPointsAdded,
            'details' => $details,
            'employees_updated' => $employeesUpdated,
        ];
    }

    /**
     * Resolusi karyawan (CS / Sales) yang berhak menerima poin berdasarkan kode seri nota penjualan.
     */
    public function resolveEmployeeForNota(string $seri, ?int $tokoId = null, ?string $kodeBarang = null): ?Employee
    {
        $prefix = '';
        if (preg_match('/^([a-zA-Z]{2,4})/i', $seri, $matches)) {
            $prefix = strtoupper($matches[1]);
        }

        // 1. Cek pemetaan khusus di konfigurasi (cs_prefix_map)
        $prefixMap = config('services.sales_app.cs_prefix_map', []);
        if (! empty($prefix) && ! empty($prefixMap[$prefix])) {
            $mappedEmp = Employee::active()->find($prefixMap[$prefix]);
            if ($mappedEmp) {
                return $mappedEmp;
            }
        }

        // 2. Cek apakah ada karyawan aktif dengan NIP yang berawalan prefix (misal CS/13-05-2026)
        if (! empty($prefix)) {
            $byNip = Employee::active()
                ->where('nip', 'like', "{$prefix}%")
                ->first();
            if ($byNip) {
                return $byNip;
            }
        }

        // 3. Cek apakah ada karyawan dengan nama yang mengandung prefix (misal DA -> Dayat, AM -> Amira)
        if ($prefix === 'DA') {
            $byName = Employee::active()
                ->where(function ($q) {
                    $q->where('name', 'like', '%Dayat%')
                        ->orWhere('name', 'like', '%Hidayat%');
                })->first();
            if ($byName) {
                return $byName;
            }
        } elseif ($prefix === 'AM') {
            $byName = Employee::active()
                ->where(function ($q) {
                    $q->where('name', 'like', '%Amira%')
                        ->orWhere('name', 'like', '%Amir%');
                })->first();
            if ($byName) {
                return $byName;
            }
        }

        // 4. Jika prefix CS, prioritaskan staf dengan role 'cs'
        if ($prefix === 'CS') {
            $csEmployee = Employee::active()
                ->where('role', 'cs')
                ->orderBy('id', 'asc')
                ->first();
            if ($csEmployee) {
                return $csEmployee;
            }
        }

        // 5. Cek tokoId (misal toko 32 = Dayat)
        if ($tokoId === 32) {
            $dayat = Employee::active()->where('name', 'like', '%Dayat%')->first();
            if ($dayat) {
                return $dayat;
            }
        }

        // 6. Fallback: Cari karyawan role 'cs' yang aktif secara berurutan
        $fallbackCs = Employee::active()
            ->where('role', 'cs')
            ->first();

        if ($fallbackCs) {
            return $fallbackCs;
        }

        // 7. Fallback terakhir: karyawan aktif manapun yang bertugas
        return Employee::active()->first();
    }

    /**
     * Tambahkan poin kinerja ke karyawan, perbarui tarif per poin sesuai tingkatan (tier),
     * dan catat log mutasi poin dengan idempotensi agar tidak duplikat.
     */
    public function applyEmployeePoints(
        Employee $employee,
        int $points,
        string $seri,
        ?string $description = null,
        string $category = EmployeePointLog::CATEGORY_ITEM_SALE,
        ?string $actor = 'Web Penjualan (E-Commerce)'
    ): array {
        if ($points <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Poin harus lebih dari 0.',
            ];
        }

        // Cek idempotensi: Hindari pemberian poin ganda untuk nota yang sama
        $alreadyLogged = EmployeePointLog::where('employee_id', $employee->id)
            ->where('notes', 'like', "%{$seri}%")
            ->exists();

        if ($alreadyLogged) {
            return [
                'status' => 'skipped',
                'message' => "Poin untuk nota {$seri} sudah pernah ditambahkan sebelumnya ke {$employee->name}.",
                'employee' => $employee,
            ];
        }

        return DB::transaction(function () use ($employee, $points, $seri, $description, $category, $actor) {
            $oldPoints = (int) $employee->current_points;
            $newPoints = $oldPoints + $points;
            $tierRate = Employee::getRateForPoints($newPoints);

            $updateData = ['current_points' => $newPoints];
            if ($tierRate > 0) {
                $updateData['rate_per_point'] = $tierRate;
            }

            // Pastikan claim_bonus aktif agar perhitungan getBonusSalaryAttribute bernilai nominal rupiah
            if ($employee->claim_bonus === null) {
                $updateData['claim_bonus'] = true;
            }

            $employee->update($updateData);

            $descText = $description ? " ({$description})" : '';
            $logNotes = "Poin Kinerja Web Penjualan - Nota {$seri}{$descText}";

            $employee->pointLogs()->create([
                'points' => $points,
                'category' => $category,
                'reference_type' => 'sales_order',
                'actor' => $actor,
                'notes' => $logNotes,
            ]);

            $employee->refresh();

            return [
                'status' => 'applied',
                'message' => "Berhasil menambahkan {$points} poin ke {$employee->name} (Nota: {$seri}). Total sekarang: {$newPoints} pt ({$employee->tier_name}). Estimasi bonus: {$employee->formatted_bonus_salary}",
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'points_added' => $points,
                'old_points' => $oldPoints,
                'new_points' => $newPoints,
                'tier_name' => $employee->tier_name,
                'rate_per_point' => (float) $employee->rate_per_point,
                'bonus_salary' => (float) $employee->bonus_salary,
                'formatted_bonus' => $employee->formatted_bonus_salary,
            ];
        });
    }
}
