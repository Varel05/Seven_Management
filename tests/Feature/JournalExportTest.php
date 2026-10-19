<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class JournalExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_journal(): void
    {
        $response = $this->get('/journal-entries/export-monthly?month=2026-10');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_export_monthly_excel_with_income_statement_and_journal_sheets(): void
    {
        $user = User::factory()->create();

        $kas = Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        $revenue = Account::create(['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue']);
        $bebanOps = Account::create(['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense']);
        $bebanGaji = Account::create(['code' => '5002', 'name' => 'Beban Gaji & Upah', 'type' => 'expense']);

        // 1. Transaksi Pendapatan: Rp 10.000.000 (Verified)
        $revEntry = JournalEntry::create([
            'date' => '2026-10-05 10:00:00',
            'reference' => 'REV-202610-001',
            'description' => 'Pendapatan Jasa Tailor & Custom',
            'source' => 'Website',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $kas->id, 'debit' => 10000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 10000000]);

        // 2. Transaksi Beban Operasional: Rp 2.000.000 (Verified)
        $expOpsEntry = JournalEntry::create([
            'date' => '2026-10-10 14:00:00',
            'reference' => 'EXP-202610-001',
            'description' => 'Pembelian Perlengkapan Jahit',
            'source' => 'Manual',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $expOpsEntry->id, 'account_id' => $bebanOps->id, 'debit' => 2000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $expOpsEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 2000000]);

        // 3. Transaksi Beban Gaji & Upah: Rp 3.000.000 (Verified)
        $expGajiEntry = JournalEntry::create([
            'date' => '2026-10-15 16:00:00',
            'reference' => 'PAY-202610-001',
            'description' => 'Pembayaran Upah Penjahit Mingguan',
            'source' => 'Website',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $expGajiEntry->id, 'account_id' => $bebanGaji->id, 'debit' => 3000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $expGajiEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 3000000]);

        // 4. Transaksi Ditolak / Dibatalkan (Rejected): Rp 1.000.000 -> Tidak boleh dihitung di Laporan Laba Rugi
        $rejectedEntry = JournalEntry::create([
            'date' => '2026-10-18 09:00:00',
            'reference' => 'EXP-REJ-001',
            'description' => 'Pembelian Batal',
            'source' => 'Manual',
            'status' => 'rejected',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $rejectedEntry->id, 'account_id' => $bebanOps->id, 'debit' => 1000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $rejectedEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 1000000]);

        $response = $this->actingAs($user)->get('/journal-entries/export-monthly?month=2026-10');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Laporan_Keuangan_dan_Jurnal_2026_10.xlsx', (string) $response->headers->get('Content-Disposition'));

        $binary = $response->getContent();
        $this->assertNotEmpty($binary);

        // Uji keabsahan file paket ZIP .xlsx
        $tempFile = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        file_put_contents($tempFile, $binary);

        $zip = new ZipArchive;
        $openResult = $zip->open($tempFile);
        $this->assertTrue($openResult === true, 'File hasil ekspor harus merupakan file zip/xlsx yang valid.');

        // 1. Pastikan kedua sheet terdaftar
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $this->assertNotFalse($workbookXml);
        $this->assertStringContainsString('name="Laporan Laba Rugi"', $workbookXml);
        $this->assertStringContainsString('name="Buku Jurnal Umum"', $workbookXml);

        // 2. Pastikan relasi workbook dan content types lengkap
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        $this->assertStringContainsString('/xl/worksheets/sheet1.xml', $contentTypesXml);
        $this->assertStringContainsString('/xl/worksheets/sheet2.xml', $contentTypesXml);

        // 3. Periksa isi Sheet 1 (Laporan Laba Rugi)
        $sheet1Xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet1Xml);
        $this->assertStringContainsString('LAPORAN LABA RUGI KOMPREHENSIF', $sheet1Xml);
        $this->assertStringContainsString('TOTAL PENDAPATAN (OMZET)', $sheet1Xml);
        $this->assertStringContainsString('TOTAL BEBAN OPERASIONAL', $sheet1Xml);
        $this->assertStringContainsString('HASIL USAHA BERSIH', $sheet1Xml);

        // Total Pendapatan harus 10.000.000
        $this->assertStringContainsString('<v>10000000</v>', $sheet1Xml);
        // Total Beban harus 5.000.000 (2M + 3M, tidak termasuk 1M rejected)
        $this->assertStringContainsString('<v>5000000</v>', $sheet1Xml);
        // Laba Bersih = 10M - 5M = 5.000.000
        $this->assertStringContainsString('SURPLUS BERSIH', $sheet1Xml);

        // 4. Periksa isi Sheet 2 (Buku Jurnal Umum)
        $sheet2Xml = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertNotFalse($sheet2Xml);
        $this->assertStringContainsString('BUKU JURNAL TRANSAKSI (GENERAL JOURNAL)', $sheet2Xml);
        $this->assertStringContainsString('REV-202610-001', $sheet2Xml);
        $this->assertStringContainsString('PAY-202610-001', $sheet2Xml);
        $this->assertStringContainsString('SEIMBANG (BALANCED)', $sheet2Xml);

        $zip->close();
        @unlink($tempFile);
    }

    public function test_export_handles_all_periods_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/journal-entries/export-monthly?month=all');

        $response->assertStatus(200);
        $this->assertStringContainsString('Laporan_Keuangan_dan_Jurnal_Semua_Periode.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_export_handles_deficit_net_loss_scenario(): void
    {
        $user = User::factory()->create();

        $kas = Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        $revenue = Account::create(['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue']);
        $bebanOps = Account::create(['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense']);

        // Pendapatan 1.000.000 vs Beban 4.000.000 -> Rugi 3.000.000
        $revEntry = JournalEntry::create([
            'date' => '2026-11-01 10:00:00',
            'reference' => 'REV-202611-001',
            'description' => 'Pendapatan Minimal',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $kas->id, 'debit' => 1000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000000]);

        $expEntry = JournalEntry::create([
            'date' => '2026-11-02 11:00:00',
            'reference' => 'EXP-202611-001',
            'description' => 'Beban Besar',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $expEntry->id, 'account_id' => $bebanOps->id, 'debit' => 4000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $expEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 4000000]);

        $response = $this->actingAs($user)->get('/journal-entries/export-monthly?month=2026-11');
        $response->assertStatus(200);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_xlsx_loss_');
        file_put_contents($tempFile, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempFile) === true);
        $sheet1Xml = $zip->getFromName('xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('DEFISIT BERSIH', $sheet1Xml);
        $this->assertStringContainsString('RUGI BERSIH USAHA', $sheet1Xml);
        $this->assertStringContainsString('<v>-3000000</v>', $sheet1Xml);

        $zip->close();
        @unlink($tempFile);
    }
}
