# Seven Management — Sistem Manajemen Keuangan & SDM UMKM

**Seven Management** (SevenAccounting) adalah aplikasi web manajemen bisnis berbasis Laravel yang dirancang khusus untuk UMKM konveksi / garmen. Aplikasi ini mengintegrasikan akuntansi pembukuan, penggajian karyawan, pengelolaan penjahit borongan, pelaporan pajak PPh Final UMKM, dan otomatisasi transaksi via bot Telegram (n8n / AI Agent).

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Tech Stack](#tech-stack)
- [Arsitektur Aplikasi](#arsitektur-aplikasi)
- [Struktur Database (Model)](#struktur-database-model)
- [Sistem Penggajian Karyawan](#sistem-penggajian-karyawan)
- [Integrasi Webhook & Telegram](#integrasi-webhook--telegram)
- [Instalasi & Setup](#instalasi--setup)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Struktur Direktori](#struktur-direktori)
- [Rute Utama](#rute-utama)
- [Webhook API Endpoints](#webhook-api-endpoints)
- [Menjalankan Tes](#menjalankan-tes)

---

## Fitur Utama

### Dashboard Keuangan Real-Time
- Ringkasan saldo Kas & Bank dari semua akun aset
- KPI bulan berjalan: Pemasukan, Pengeluaran, dan Laba Bersih
- Grafik arus keuangan harian (bulan ini) & bulanan (tahun ini)
- Daftar transaksi jurnal dengan status *pending*, *verified*, dan *rejected*
- Live polling otomatis untuk memperbarui data tanpa refresh halaman

### Akuntansi Double-Entry
- Pencatatan jurnal entri dengan metode double-entry (debit/kredit)
- Manajemen Bagan Akun (Chart of Accounts) dengan tipe: `asset`, `revenue`, `expense`, `liability`, `equity`
- Verifikasi, penolakan, dan pembatalan transaksi jurnal
- Ekspor buku jurnal bulanan ke format Excel (`.xls`)
- Pengeluaran berulang / langganan (*recurring transactions*) dengan persetujuan manual

### Manajemen Karyawan & Penggajian
- Data master karyawan dengan peran/role bertingkat (Owner, Manager, HRD, Supervisor, Staff, CS, Akuntan)
- Sistem gaji berbasis shift harian dengan dukungan:
  - Gaji pokok / honor
  - Bonus disiplin (hadir tepat waktu)
  - Bonus hari libur nasional
  - Tunjangan & potongan (nominal tetap atau persentase)
  - Bonus poin kinerja berjenjang (5 tier)
- Slip gaji digital dengan opsi unduh PDF
- Pembukuan gaji otomatis ke buku besar (akun `5002 - Beban Gaji`)
- Sinkronisasi data absensi dari API eksternal (sistem presensi CI4)
- Rekap daftar hadir karyawan

### Penjahit & Upah Borongan
- Master data penjahit (Tailor) terpisah dari karyawan tetap
- Tarif upah borongan per jenis item jahitan (*labor rates*)
- Penggajian penjahit berbasis item hasil kerja (*tailor payroll*)
- Pembayaran uang muka (advance) penjahit
- Pembukuan otomatis ke jurnal akuntansi

### Pajak PPh Final UMKM
- Perhitungan otomatis pajak PPh Final 0,5% dari omzet bulanan
- Pencatatan pembayaran setoran pajak
- Laporan status pajak bulanan & tahunan
- Integrasi via webhook Telegram

### Integrasi Bot Telegram & n8n
- Webhook API lengkap untuk integrasi dengan n8n / AI Agent
- Identifikasi pengguna Telegram via nomor HP atau Telegram User ID
- Pencatatan transaksi, verifikasi, dan pembatalan via chat Telegram
- Pengelolaan payroll karyawan & penjahit via Telegram
- Laporan saldo, ringkasan keuangan, dan laba rugi via Telegram
- Pengelolaan poin kinerja karyawan via Telegram

---

## Tech Stack

| Kategori     | Teknologi                                     |
|--------------|-----------------------------------------------|
| **Backend**  | PHP 8.3, Laravel 13.x                         |
| **Frontend** | Blade Templates, Alpine.js, Tailwind CSS 3.x  |
| **Build**    | Vite 8.x                                      |
| **Database** | SQLite (default), MySQL/PostgreSQL (opsional) |
| **Auth**     | Laravel Breeze (session-based)                |
| **API Auth** | Laravel Sanctum + custom `webhook.secret`     |
| **Testing**  | PHPUnit 12.x                                  |
| **Linter**   | Laravel Pint                                  |

---

## Arsitektur Aplikasi

```
+----------------------------------------------------------+
|                     Web Browser                          |
|          (Blade + Alpine.js + Tailwind CSS)              |
+-------------------------+--------------------------------+
                          | HTTP
+-------------------------v--------------------------------+
|                   Laravel Application                    |
|                                                          |
|  Controllers      Services                Models         |
|  -----------      --------                ------         |
|  Dashboard        TaxService             Employee        |
|  Employee         AttendanceSync         EmployeePayroll |
|  TailorPayroll    SalesSync              Tailor          |
|  JournalExport    TransactionCancel      Allowance       |
|  WebhookAPI                              JournalEntry    |
|                                          Account         |
|                                                          |
|  +----------------------------------------------------+  |
|  |              Database (SQLite / MySQL)             |  |
|  +----------------------------------------------------+  |
+----------------------------------------------------------+
                          | Webhook API
+-------------------------v--------------------------------+
|              n8n / AI Agent / Telegram Bot               |
+----------------------------------------------------------+
```

---

## Struktur Database (Model)

### `users`
Akun login pengguna dashboard web.

| Kolom    | Tipe   | Keterangan              |
|----------|--------|-------------------------|
| name     | string | Nama pengguna           |
| email    | string | Email (unik)            |
| password | string | Password ter-hash       |
| role     | string | Peran di sistem web     |
| pin      | string | PIN untuk aksi sensitif |

### `employees`
Data master karyawan perusahaan.

| Kolom           | Tipe    | Keterangan                                        |
|-----------------|---------|---------------------------------------------------|
| name            | string  | Nama lengkap karyawan                             |
| nip             | string  | Nomor Induk Pegawai                               |
| position        | string  | Jabatan/posisi                                    |
| role            | enum    | `owner`, `manager`, `hrd`, `supervisor`, `staff`, `cs`, `akuntan` |
| phone           | string  | Nomor HP (untuk autentikasi Telegram)             |
| status          | string  | `active` / `inactive`                             |
| base_salary     | decimal | Gaji pokok bulanan                                |
| daily_rate      | decimal | Tarif per shift/hari                              |
| discipline_rate | decimal | Bonus disiplin per hari hadir tepat waktu         |
| holiday_rate    | decimal | Bonus masuk hari libur nasional                   |
| current_points  | integer | Poin kinerja terkini                              |
| rate_per_point  | decimal | Nilai rupiah per poin (otomatis per tier)         |
| claim_bonus     | boolean | Apakah memilih ambil bonus poin bulan ini         |
| pay_day         | integer | Tanggal gajian tiap bulan                         |
| asset_account_id| FK      | Akun kas/bank default untuk pembayaran gaji       |

### `employee_payrolls`
Slip gaji & riwayat pembayaran gaji karyawan.

| Kolom            | Keterangan                                    |
|------------------|-----------------------------------------------|
| employee_id      | FK ke employees                               |
| journal_entry_id | FK ke journal_entries (pembukuan akuntansi)   |
| period           | Periode gaji (mis. "Oktober 2026")            |
| total_shifts     | Total shift dalam periode                     |
| total_present    | Jumlah hadir                                  |
| late_count       | Jumlah terlambat                              |
| holiday_shifts   | Jumlah masuk di hari libur                    |
| main_salary      | Komponen gaji pokok/honor                     |
| discipline_bonus | Total bonus disiplin                          |
| holiday_bonus    | Total bonus hari libur                        |
| sales_bonus      | Bonus poin kinerja                            |
| allowance_total  | Total tunjangan                               |
| deduction_total  | Total potongan                                |
| take_home_pay    | Gaji bersih yang diterima                     |

### `allowances`
Komponen tunjangan & potongan gaji yang dapat dikonfigurasi.

| Kolom               | Keterangan                                        |
|---------------------|---------------------------------------------------|
| name                | Nama tunjangan/potongan                           |
| type                | `allowance` (penambah) atau `deduction` (pengurang)|
| calculation_type    | `fixed` atau `percentage`                         |
| amount/rate_percent | Nilai nominal atau persen                         |
| target_type         | Target: `all`, `above_staff`, `staff`, `specific` |
| target_employee_ids | ID karyawan spesifik (jika target `specific`)     |
| is_active           | Status aktif                                      |

### `accounts` (Chart of Accounts)
Bagan akun keuangan perusahaan.

| Kolom | Keterangan                                            |
|-------|-------------------------------------------------------|
| code  | Kode akun (mis. `1001`, `5002`)                       |
| name  | Nama akun                                             |
| type  | `asset`, `revenue`, `expense`, `liability`, `equity`  |

**Akun default yang digunakan sistem:**
- `1001` — Kas Operasional
- `5002` — Beban Gaji

### `journal_entries`
Header entri jurnal akuntansi.

| Kolom       | Keterangan                                    |
|-------------|-----------------------------------------------|
| reference   | Kode referensi unik (mis. `PAY-XXXXXXXX`)     |
| description | Deskripsi transaksi                           |
| date        | Tanggal transaksi                             |
| source      | Sumber: `website`, `telegram`, dll.           |
| status      | `pending`, `verified`, `rejected`             |

### `journal_entry_lines`
Detail baris debit/kredit dari setiap jurnal entri.

### `recurring_transactions`
Template transaksi berulang (langganan, pengeluaran rutin).

### `tailors`
Data master penjahit borongan.

### `tailor_payrolls` & `tailor_payroll_items`
Penggajian penjahit berdasarkan item hasil kerja.

### `tailor_labor_rates`
Tarif upah borongan per jenis item jahitan.

### `employee_point_logs`
Riwayat perubahan poin kinerja karyawan.

---

## Sistem Penggajian Karyawan

### Komponen Gaji

```
Gaji Bersih (Take Home Pay) =
    Gaji Pokok / Honor
  + Bonus Disiplin   (hadir_tepat_waktu x tarif_disiplin)
  + Bonus Hari Libur (jumlah_shift_libur x tarif_libur)
  + Tunjangan        (fixed atau % dari gaji pokok)
  - Potongan         (fixed atau % dari gaji pokok)
  + Bonus Poin Kinerja
```

### Sistem Tier Poin Kinerja

Poin kinerja karyawan dikumulasikan dan dicairkan saat penggajian:

| Tier   | Syarat Poin | Nilai per Poin |
|--------|-------------|----------------|
| Tier 1 | 200 - 294   | Rp 1.000       |
| Tier 2 | 295 - 369   | Rp 1.400       |
| Tier 3 | 370 - 444   | Rp 1.800       |
| Tier 4 | 445 - 499   | Rp 2.200       |
| Tier 5 | >= 500      | Rp 2.600       |
| -      | < 200       | Rp 0 (belum tier) |

- Karyawan dapat memilih untuk **tidak** mencairkan bonus poin (`claim_bonus = false`) agar poin terus terakumulasi ke tier berikutnya.
- Saat bonus dicairkan, poin dikurangi sebesar `min_points` tier yang dicapai.

---

## Integrasi Webhook & Telegram

Semua endpoint webhook diamankan dengan header `X-Webhook-Secret`. Konfigurasi di `.env`:

```env
WEBHOOK_SECRET=your_secret_key_here
```

Autentikasi pengguna Telegram via nomor HP yang didaftarkan:

```env
TELEGRAM_OWNER_PHONES=08123456789
TELEGRAM_AKUNTAN_PHONES=08123456788
```

---

## Instalasi & Setup

### Prasyarat
- PHP 8.3+
- Composer
- Node.js 18+ & npm
- SQLite (default) atau MySQL

### Setup Otomatis (Recommended)

```bash
git clone <repository-url> seven-management
cd seven-management
composer run setup
```

Perintah `composer run setup` secara otomatis:
1. Menginstall dependensi PHP
2. Menyalin `.env.example` ke `.env`
3. Generate application key
4. Menjalankan semua migrasi database
5. Menginstall dependensi Node.js
6. Build aset frontend (Vite)

### Setup Manual

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

---

## Konfigurasi Environment

Salin `.env.example` ke `.env` dan sesuaikan:

```env
# Aplikasi
APP_NAME="SevenAccounting"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

# Database (SQLite default — tidak perlu konfigurasi tambahan)
DB_CONNECTION=sqlite

# MySQL (opsional)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=seven_management
# DB_USERNAME=root
# DB_PASSWORD=
# DB_PREFIX=sm_

# Webhook API Security
WEBHOOK_SECRET=your_secret_key_here

# n8n AI Agent Integration
N8N_CUSTOM_SUIT_WEBHOOK_URL=http://localhost:5678/webhook/custom-suit-ai

# Autentikasi Telegram (nomor HP karyawan yang berhak akses)
TELEGRAM_OWNER_PHONES=08123456789
TELEGRAM_AKUNTAN_PHONES=08123456788

# Telegram User ID (opsional, cadangan)
TELEGRAM_OWNER_IDS=
TELEGRAM_AKUNTAN_IDS=

# API Presensi Karyawan (sistem CI4 eksternal)
KARYAWAN_API_URL=http://localhost:8080
KARYAWAN_API_KEY=
KARYAWAN_API_TIMEOUT=15
```

---

## Menjalankan Aplikasi

### Development (dengan hot-reload)

```bash
composer run dev
```

Atau jalankan terpisah:

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

### Production Build

```bash
npm run build
php artisan optimize
```

---

## Struktur Direktori

```
Seven_Management/
+-- app/
|   +-- Console/
|   +-- Enums/
|   |   +-- EmployeeRole.php        # Enum peran karyawan
|   +-- Http/
|   |   +-- Controllers/
|   |   |   +-- Api/
|   |   |   |   +-- WebhookTransactionController.php  # Semua webhook endpoint
|   |   |   +-- AccountController.php
|   |   |   +-- AllowanceController.php
|   |   |   +-- DashboardController.php
|   |   |   +-- EmployeeController.php
|   |   |   +-- JournalExportController.php
|   |   |   +-- RecurringTransactionController.php
|   |   |   +-- TailorController.php
|   |   |   +-- TailorLaborRateController.php
|   |   |   +-- TailorPayrollController.php
|   |   |   +-- TaxController.php
|   |   +-- Middleware/
|   |   +-- Requests/
|   +-- Models/
|   |   +-- Account.php
|   |   +-- Allowance.php
|   |   +-- Employee.php            # Model utama; logika tier poin & penggajian
|   |   +-- EmployeePayroll.php
|   |   +-- EmployeePointLog.php
|   |   +-- JournalEntry.php
|   |   +-- JournalEntryLine.php
|   |   +-- RecurringTransaction.php
|   |   +-- Tailor.php
|   |   +-- TailorPayroll.php
|   |   +-- User.php
|   +-- Providers/
|   +-- Services/
|       +-- AttendanceSyncService.php       # Sinkronisasi presensi dari API
|       +-- SalesSyncService.php            # Sinkronisasi data penjualan
|       +-- TaxService.php                  # Kalkulasi PPh Final UMKM
|       +-- TransactionCancellationService.php
+-- database/
|   +-- factories/
|   +-- migrations/
|   +-- seeders/
+-- resources/
|   +-- css/
|   +-- js/
|   +-- views/
|       +-- auth/
|       +-- components/
|       +-- dashboard/
|       +-- employees/
|       +-- layouts/
|       +-- tailor-payrolls/
|       +-- taxes/
+-- routes/
|   +-- api.php           # Webhook API routes
|   +-- auth.php          # Route autentikasi Breeze
|   +-- web.php           # Web routes
+-- tests/
```

---

## Rute Utama

| Method | Path                              | Keterangan                       |
|--------|-----------------------------------|----------------------------------|
| GET    | `/dashboard`                      | Halaman utama dashboard          |
| GET    | `/dashboard/live-data`            | Polling data real-time (JSON)    |
| GET    | `/employees`                      | Daftar & manajemen karyawan      |
| POST   | `/employees/{id}/pay`             | Eksekusi pembayaran gaji         |
| POST   | `/employees/{id}/generate-slip`   | Buat draft slip gaji             |
| GET    | `/employees/payroll/{id}/slip`    | Lihat slip gaji                  |
| GET    | `/employees/payroll/{id}/pdf`     | Unduh slip gaji PDF              |
| GET    | `/employees/attendance-list`      | Rekap daftar hadir               |
| GET    | `/journal-entries/export-monthly` | Ekspor jurnal ke Excel           |
| GET    | `/taxes`                          | Laporan pajak PPh Final UMKM     |
| POST   | `/taxes/pay`                      | Catat pembayaran pajak           |

---

## Webhook API Endpoints

Base path: `/api` — semua endpoint membutuhkan header:
```
X-Webhook-Secret: {WEBHOOK_SECRET}
```

### Transaksi Keuangan

| Endpoint                           | Method   | Keterangan                   |
|------------------------------------|----------|------------------------------|
| `/webhook/transactions`            | GET      | Daftar semua transaksi       |
| `/webhook/transaction`             | POST     | Catat transaksi baru         |
| `/webhook/transaction/cancel`      | GET/POST | Batalkan transaksi           |
| `/webhook/transactions/pending`    | GET/POST | Transaksi pending            |
| `/webhook/transaction/verify`      | GET/POST | Verifikasi transaksi         |
| `/webhook/balance`                 | GET/POST | Saldo kas & bank             |
| `/webhook/summary`                 | GET/POST | Ringkasan keuangan           |
| `/webhook/profit-loss`             | GET/POST | Laporan laba rugi            |

### Pengeluaran Berulang (Recurring)

| Endpoint                                | Method   | Keterangan              |
|-----------------------------------------|----------|-------------------------|
| `/webhook/recurring/due`                | GET/POST | Jatuh tempo hari ini    |
| `/webhook/recurring/{id}/approve`       | POST     | Setujui recurring       |
| `/webhook/recurring/{id}/skip`          | POST     | Lewati recurring        |
| `/webhook/recurring/create`             | POST     | Buat template baru      |

### Payroll Karyawan

| Endpoint                              | Method   | Keterangan                |
|---------------------------------------|----------|---------------------------|
| `/webhook/payroll/due`                | GET/POST | Karyawan jatuh tempo gaji |
| `/webhook/payroll/{id}/approve`       | POST     | Bayar gaji karyawan       |
| `/webhook/payroll/{id}/skip`          | POST     | Lewati penggajian         |
| `/webhook/payroll/points`             | GET/POST | Daftar poin karyawan      |
| `/webhook/payroll/points/update`      | POST     | Update poin karyawan      |
| `/webhook/payroll/my-points`          | GET/POST | Cek poin sendiri          |
| `/webhook/payroll/bonus-preference`   | GET/POST | Toggle ambil/tunda bonus  |
| `/webhook/payroll/slip`               | GET/POST | Generate slip gaji        |
| `/webhook/tailor-payroll`             | GET/POST | Kelola payroll penjahit   |

### Penjahit & Tarif

| Endpoint                       | Method   | Keterangan              |
|--------------------------------|----------|-------------------------|
| `/webhook/tailors`             | GET/POST | Daftar penjahit         |
| `/webhook/tailors/register`    | POST     | Daftarkan penjahit baru |
| `/webhook/tailor-rates`        | GET/POST | Tarif upah borongan     |

### Pajak

| Endpoint              | Method   | Keterangan              |
|-----------------------|----------|-------------------------|
| `/webhook/tax`        | GET/POST | Status & ringkasan pajak|
| `/webhook/tax/pay`    | POST     | Catat pembayaran pajak  |

### Autentikasi & Utilitas

| Endpoint                    | Method   | Keterangan                    |
|-----------------------------|----------|-------------------------------|
| `/webhook/auth/identify`    | GET/POST | Identifikasi pengguna Telegram|
| `/webhook/accounts`         | GET/POST | Daftar Chart of Accounts      |
| `/webhook/sales-order`      | POST     | Catat transaksi penjualan     |
| `/webhook/sales/sync`       | GET/POST | Sinkronisasi data penjualan   |

---

## Menjalankan Tes

```bash
# Semua tes
composer run test

# Via artisan
php artisan test

# Filter tes tertentu
php artisan test --filter=EmployeeTest --compact

# Tes file spesifik
php artisan test tests/Feature/EmployeePayrollTest.php
```

---

## Lisensi

Proyek ini dikembangkan untuk keperluan internal manajemen bisnis UMKM. Hak cipta dilindungi.
