<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_attendance_for_employee_with_nip_successfully(): void
    {
        Http::fake([
            '*/api/presensi/rekap*' => Http::response([
                'status' => 200,
                'message' => 'Berhasil mengambil rekap presensi',
                'count' => 1,
                'data' => [
                    [
                        'pegawai_id' => 20,
                        'nip' => 'HRD/27-03-2013',
                        'username' => 'husbanaarisetia',
                        'period_start' => '2026-09-26',
                        'period_end' => '2026-10-25',
                        'total_shifts' => 27,
                        'total_present' => 15,
                        'late_count' => 1,
                        'discipline_present' => 14,
                        'holiday_shifts' => 0,
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $employee = Employee::create([
            'name' => 'Ari Husbana',
            'nip' => 'HRD/27-03-2013',
            'position' => 'HRD',
            'base_salary' => 3000000,
            'daily_rate' => 110000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson(route('employees.fetch-attendance', [
            'employee' => $employee,
            'start_date' => '2026-09-26',
            'end_date' => '2026-10-25',
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'nip' => 'HRD/27-03-2013',
                    'total_shifts' => 27,
                    'total_present' => 15,
                    'late_count' => 1,
                    'discipline_present' => 14,
                ],
            ]);
    }

    public function test_fails_fetch_if_employee_has_no_nip(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'name' => 'Karyawan Tanpa NIP',
            'nip' => null,
            'position' => 'Staff',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson(route('employees.fetch-attendance', [
            'employee' => $employee,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('belum memiliki NIP', $response->json('message'));
    }

    public function test_can_fetch_available_attendance_list(): void
    {
        Http::fake([
            '*/api/presensi/rekap*' => Http::response([
                'status' => 200,
                'message' => 'Berhasil mengambil rekap presensi',
                'count' => 2,
                'data' => [
                    [
                        'pegawai_id' => 20,
                        'nip' => 'HRD/27-03-2013',
                        'username' => 'husbanaarisetia',
                    ],
                    [
                        'pegawai_id' => 245,
                        'nip' => 'HRD/22-07-2025',
                        'username' => 'fajarriyani',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('employees.attendance-list'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }
}
