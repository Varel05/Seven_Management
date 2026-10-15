<?php

namespace App\Services;

use App\Enums\EmployeeRole;
use App\Models\Employee;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceSyncService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.karyawan.api_url', 'http://localhost:8080'), '/');
        $this->apiKey = config('services.karyawan.api_key', 'kunci-rahasia-seven-2026');
        $this->timeout = (int) config('services.karyawan.timeout', 15);
    }

    /**
     * Ambil data rekapitulasi presensi dari Web Karyawan (CI4).
     *
     * @return array{success: bool, message: string, count: int, data: array}
     */
    public function fetchAttendance(?string $nip = null, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $endpoint = "{$this->apiUrl}/api/presensi/rekap";

            $params = [];
            if ($nip) {
                $params['nip'] = $nip;
            }
            if ($startDate) {
                $params['start_date'] = $startDate;
            }
            if ($endDate) {
                $params['end_date'] = $endDate;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-KEY' => $this->apiKey,
                ])
                ->get($endpoint, $params);

            if (! $response->successful()) {
                $status = $response->status();
                $body = $response->json('message') ?? $response->body();
                Log::warning('Gagal memanggil API Presensi CI4', ['status' => $status, 'body' => $body]);

                return [
                    'success' => false,
                    'message' => "Server Presensi merespon HTTP {$status}: {$body}",
                    'count' => 0,
                    'data' => [],
                ];
            }

            $json = $response->json();
            $data = $json['data'] ?? [];

            return [
                'success' => true,
                'message' => $json['message'] ?? 'Berhasil mengambil data presensi.',
                'count' => count($data),
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error('Koneksi ke API Presensi CI4 gagal', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Gagal menghubungi server presensi: '.$e->getMessage(),
                'count' => 0,
                'data' => [],
            ];
        }
    }

    /**
     * Ambil data presensi spesifik untuk seorang karyawan berdasarkan NIP dan rentang tanggal cut-off.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function getAttendanceForEmployee(Employee $employee, ?string $startDate = null, ?string $endDate = null): array
    {
        if (empty($employee->nip)) {
            return [
                'success' => false,
                'message' => "Karyawan '{$employee->name}' belum memiliki NIP. Silakan lengkapi NIP terlebih dahulu pada data karyawan.",
            ];
        }

        $result = $this->fetchAttendance($employee->nip, $startDate, $endDate);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
            ];
        }

        if (empty($result['data'])) {
            return [
                'success' => false,
                'message' => "Tidak ditemukan data presensi di web karyawan untuk NIP '{$employee->nip}' pada periode tersebut.",
            ];
        }

        // Ambil data pertama yang cocok
        $attendance = $result['data'][0];

        return [
            'success' => true,
            'message' => 'Data presensi berhasil disinkronisasi.',
            'data' => [
                'nip' => $attendance['nip'] ?? $employee->nip,
                'username' => $attendance['username'] ?? null,
                'total_shifts' => (int) ($attendance['total_shifts'] ?? ($attendance['total_present'] ?? 0)),
                'total_present' => (int) ($attendance['total_present'] ?? 0),
                'late_count' => (int) ($attendance['late_count'] ?? 0),
                'discipline_present' => (int) ($attendance['discipline_present'] ?? max(0, ($attendance['total_present'] ?? 0) - ($attendance['late_count'] ?? 0))),
                'holiday_shifts' => (int) ($attendance['holiday_shifts'] ?? 0),
                'period_start' => $attendance['period_start'] ?? $startDate,
                'period_end' => $attendance['period_end'] ?? $endDate,
            ],
        ];
    }

    /**
     * Sinkronisasi data karyawan dan kehadiran dari REST API Web Karyawan (CI4).
     * Otomatis mendaftarkan/memperbarui data master karyawan di Seven_Management.
     *
     * @return array{success: bool, synced: int, total_api: int, message: string}
     */
    public function syncEmployeesFromApi(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $endDate ?? now()->endOfMonth()->format('Y-m-d');

        $result = $this->fetchAttendance(null, $startDate, $endDate);

        if (! $result['success'] || empty($result['data'])) {
            return [
                'success' => false,
                'synced' => 0,
                'total_api' => 0,
                'message' => $result['message'] ?? 'Tidak ada data presensi yang ditemukan.',
            ];
        }

        $syncedCount = 0;
        foreach ($result['data'] as $item) {
            $nip = trim($item['nip'] ?? '');
            if (empty($nip)) {
                continue;
            }

            $employee = Employee::firstOrNew(['nip' => $nip]);

            // Tentukan role dan posisi dari format NIP
            $role = EmployeeRole::Staff->value;
            $position = 'Staff Umum';
            if (str_starts_with($nip, 'HRD/')) {
                $role = EmployeeRole::Hrd->value;
                $position = 'HRD / Personalia';
            } elseif (str_starts_with($nip, 'CS/')) {
                $role = EmployeeRole::Cs->value;
                $position = 'Customer Service (CS)';
            } elseif (str_starts_with($nip, 'ADM/')) {
                $role = EmployeeRole::Staff->value;
                $position = 'Staff Administrasi';
            } elseif (str_starts_with($nip, 'HR/')) {
                $role = EmployeeRole::Hrd->value;
                $position = 'Human Resources';
            }

            // Nama lengkap: utamakan dari API (nama), fallback ke meta jika ada, lalu ke username
            $rawName = ! empty($item['nama']) ? trim($item['nama']) : null;
            if (! $rawName && ! empty($item['pegawai_id'])) {
                try {
                    $metaRow = DB::selectOne(
                        "SELECT t_val FROM dnzwygdo_pr355.new_pegawai_meta WHERE pegawai_id = ? AND t_key = 'nama' LIMIT 1",
                        [$item['pegawai_id']]
                    );
                    if ($metaRow && ! empty($metaRow->t_val)) {
                        $rawName = trim($metaRow->t_val);
                    }
                } catch (\Throwable) {
                    // abaikan jika tidak bisa akses langsung tabel meta
                }
            }
            if (! $rawName) {
                $rawName = ! empty($item['username']) ? ucwords(str_replace(['_', '.'], ' ', $item['username'])) : $nip;
            }

            // Nomor HP
            $phone = ! empty($item['phone']) ? trim($item['phone']) : (! empty($item['hp']) ? trim($item['hp']) : null);
            if (! $phone && ! empty($item['pegawai_id'])) {
                try {
                    $metaRow = DB::selectOne(
                        "SELECT t_val FROM dnzwygdo_pr355.new_pegawai_meta WHERE pegawai_id = ? AND t_key = 'hp' LIMIT 1",
                        [$item['pegawai_id']]
                    );
                    if ($metaRow && ! empty($metaRow->t_val)) {
                        $phone = trim($metaRow->t_val);
                    }
                } catch (\Throwable) {
                    // abaikan
                }
            }

            $employee->name = $rawName;
            if ($phone) {
                $employee->phone = $phone;
            }
            $employee->position = $position;
            $employee->role = $role;
            $employee->status = 'active';
            $employee->pay_day = 25;

            if (! $employee->exists) {
                $employee->base_salary = 0;
                $employee->daily_rate = 100000;
                $employee->discipline_rate = 10000;
                $employee->holiday_rate = 50000;
            }

            $employee->save();
            $syncedCount++;
        }

        return [
            'success' => true,
            'synced' => $syncedCount,
            'total_api' => count($result['data']),
            'message' => "Berhasil menyinkronkan {$syncedCount} data karyawan dari REST API Web Karyawan.",
        ];
    }

    /**
     * Ambil peta kehadiran terindeks per NIP untuk periode berjalan.
     *
     * @return array<string, array>
     */
    public function getAttendanceMap(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $endDate ?? now()->endOfMonth()->format('Y-m-d');

        $result = $this->fetchAttendance(null, $startDate, $endDate);

        if (! $result['success'] || empty($result['data'])) {
            return [];
        }

        $map = [];
        foreach ($result['data'] as $item) {
            $nip = trim($item['nip'] ?? '');
            if (! empty($nip)) {
                $map[$nip] = [
                    'nip' => $nip,
                    'total_shifts' => (int) ($item['total_shifts'] ?? ($item['total_present'] ?? 0)),
                    'total_present' => (int) ($item['total_present'] ?? 0),
                    'late_count' => (int) ($item['late_count'] ?? 0),
                    'discipline_present' => (int) ($item['discipline_present'] ?? max(0, ($item['total_present'] ?? 0) - ($item['late_count'] ?? 0))),
                    'holiday_shifts' => (int) ($item['holiday_shifts'] ?? 0),
                    'period_start' => $item['period_start'] ?? $startDate,
                    'period_end' => $item['period_end'] ?? $endDate,
                ];
            }
        }

        return $map;
    }
}
