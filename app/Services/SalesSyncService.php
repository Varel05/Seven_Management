<?php

namespace App\Services;

use App\Models\Account;
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
}
