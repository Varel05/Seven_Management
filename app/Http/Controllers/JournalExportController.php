<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalExportController extends Controller
{
    /**
     * Ekspor Laporan Laba Rugi Bulanan & Buku Jurnal Transaksi ke format Excel (.xlsx)
     * Menghasilkan workbook multi-sheet:
     * - Sheet 1: Laporan Laba Rugi (Income Statement & Ringkasan KPI)
     * - Sheet 2: Buku Jurnal Umum (General Journal Detail)
     */
    public function exportMonthly(Request $request): Response
    {
        $monthInput = $request->query('month', now()->format('Y-m'));
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = JournalEntry::with(['lines.account'])->orderBy('date', 'asc');

        $isAllMonths = ($monthInput === 'all');
        $periodTitle = 'Semua Periode';
        $filenameSuffix = 'Semua_Periode';

        if (! $isAllMonths && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            [$year, $month] = explode('-', $monthInput);
            $query->whereYear('date', (int) $year)->whereMonth('date', (int) $month);
            $carbonMonth = Carbon::createFromDate((int) $year, (int) $month, 1);
            $periodTitle = $carbonMonth->translatedFormat('F Y');
            $filenameSuffix = $carbonMonth->format('Y_m');
        }

        if ($status && in_array($status, ['verified', 'pending', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($search) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                    ->orWhere('reference', 'like', $searchTerm)
                    ->orWhereHas('lines.account', function ($qa) use ($searchTerm) {
                        $qa->where('name', 'like', $searchTerm)
                            ->orWhere('code', 'like', $searchTerm);
                    });
            });
        }

        $entries = $query->get();
        $statusText = $status === 'all' ? 'Semua Status' : ucfirst($status);
        $exportTimeText = now()->translatedFormat('d F Y, H:i').' WIB';

        // =========================================================================
        // KALKULASI DATA LAPORAN LABA RUGI & ARUS KAS (SHEET 1)
        // Hanya menghitung entri yang aktif (status bukan 'rejected')
        // =========================================================================
        $activeEntries = $entries->where('status', '!=', 'rejected');

        $linesByAccount = [];
        foreach ($activeEntries as $entry) {
            foreach ($entry->lines as $line) {
                if (! $line->account) {
                    continue;
                }
                $accId = $line->account_id;
                if (! isset($linesByAccount[$accId])) {
                    $linesByAccount[$accId] = [
                        'account' => $line->account,
                        'debit' => 0.0,
                        'credit' => 0.0,
                    ];
                }
                $linesByAccount[$accId]['debit'] += (float) $line->debit;
                $linesByAccount[$accId]['credit'] += (float) $line->credit;
            }
        }

        $masterAccounts = Account::orderBy('code', 'asc')->get();

        // 1. Akun Pendapatan (Revenue)
        $revenueItems = [];
        $totalRevenue = 0.0;
        $revenueAccounts = $masterAccounts->where('type', 'revenue');

        foreach ($revenueAccounts as $acc) {
            $deb = $linesByAccount[$acc->id]['debit'] ?? 0.0;
            $cred = $linesByAccount[$acc->id]['credit'] ?? 0.0;
            $net = $cred - $deb; // Saldo normal pendapatan = Kredit - Debit
            $totalRevenue += $net;

            $revenueItems[] = [
                'code' => $acc->code,
                'name' => $acc->name,
                'debit' => $deb,
                'credit' => $cred,
                'net' => $net,
                'category' => 'Pendapatan Pokok Usaha',
            ];
        }

        // Tambahkan pendapatan dari akun dinamis yang ada di mutasi namun belum terdaftar di master
        foreach ($linesByAccount as $accId => $data) {
            if ($data['account']->type === 'revenue' && ! $revenueAccounts->contains('id', $accId)) {
                $deb = $data['debit'];
                $cred = $data['credit'];
                $net = $cred - $deb;
                $totalRevenue += $net;
                $revenueItems[] = [
                    'code' => $data['account']->code,
                    'name' => $data['account']->name,
                    'debit' => $deb,
                    'credit' => $cred,
                    'net' => $net,
                    'category' => 'Pendapatan Lainnya',
                ];
            }
        }

        // 2. Akun Beban (Expenses)
        $expenseItems = [];
        $totalExpense = 0.0;
        $expenseAccounts = $masterAccounts->where('type', 'expense');

        foreach ($expenseAccounts as $acc) {
            $deb = $linesByAccount[$acc->id]['debit'] ?? 0.0;
            $cred = $linesByAccount[$acc->id]['credit'] ?? 0.0;
            $net = $deb - $cred; // Saldo normal beban = Debit - Kredit
            $totalExpense += $net;

            $category = match ($acc->code) {
                '5001' => 'Beban Operasional Rutin',
                '5002' => 'Beban SDM & Upah',
                '5003' => 'Beban Perlengkapan Usaha',
                '5005' => 'Beban Perpajakan (PPh Final)',
                default => 'Beban Operasional',
            };

            $expenseItems[] = [
                'code' => $acc->code,
                'name' => $acc->name,
                'debit' => $deb,
                'credit' => $cred,
                'net' => $net,
                'category' => $category,
            ];
        }

        foreach ($linesByAccount as $accId => $data) {
            if ($data['account']->type === 'expense' && ! $expenseAccounts->contains('id', $accId)) {
                $deb = $data['debit'];
                $cred = $data['credit'];
                $net = $deb - $cred;
                $totalExpense += $net;
                $expenseItems[] = [
                    'code' => $data['account']->code,
                    'name' => $data['account']->name,
                    'debit' => $deb,
                    'credit' => $cred,
                    'net' => $net,
                    'category' => 'Beban Lain-Lain',
                ];
            }
        }

        // 3. Akun Aset / Kas & Bank
        $assetItems = [];
        $totalAssetDebit = 0.0;
        $totalAssetCredit = 0.0;
        $assetAccounts = $masterAccounts->where('type', 'asset');

        foreach ($assetAccounts as $acc) {
            $deb = $linesByAccount[$acc->id]['debit'] ?? 0.0;
            $cred = $linesByAccount[$acc->id]['credit'] ?? 0.0;
            $net = $deb - $cred;
            $totalAssetDebit += $deb;
            $totalAssetCredit += $cred;

            $category = match ($acc->code) {
                '1001' => 'Kas Tunai Operasional',
                '1002' => 'Rekening Giro / Bank',
                '1005' => 'Piutang Kasbon',
                default => 'Aset Lancar',
            };

            $assetItems[] = [
                'code' => $acc->code,
                'name' => $acc->name,
                'debit' => $deb,
                'credit' => $cred,
                'net' => $net,
                'category' => $category,
            ];
        }

        $netCashFlow = $totalAssetDebit - $totalAssetCredit;
        $netProfit = $totalRevenue - $totalExpense;
        $profitMarginRatio = ($totalRevenue > 0) ? ($netProfit / $totalRevenue) : 0.0;
        $expenseRatio = ($totalRevenue > 0) ? ($totalExpense / $totalRevenue) : 0.0;

        // Hitung persentase kontribusi per baris terhadap total pendapatan
        foreach ($revenueItems as &$rev) {
            $rev['pct'] = ($totalRevenue > 0) ? ($rev['net'] / $totalRevenue) : 0.0;
        }
        unset($rev);

        foreach ($expenseItems as &$exp) {
            $exp['pct'] = ($totalRevenue > 0) ? ($exp['net'] / $totalRevenue) : 0.0;
        }
        unset($exp);

        foreach ($assetItems as &$ast) {
            $ast['pct'] = ($totalAssetDebit > 0) ? ($ast['debit'] / $totalAssetDebit) : 0.0;
        }
        unset($ast);

        // =========================================================================
        // SUSUN PAKET OPENXML SPREADSHEET (MULTI-SHEET WORKBOOK)
        // =========================================================================

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'."\n"
            .'  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'."\n"
            .'  <Default Extension="xml" ContentType="application/xml"/>'."\n"
            .'  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'."\n"
            .'  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'."\n"
            .'  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'."\n"
            .'  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'."\n"
            .'</Types>';

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'."\n"
            .'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'."\n"
            .'</Relationships>';

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'."\n"
            .'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'."\n"
            .'  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'."\n"
            .'  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'."\n"
            .'</Relationships>';

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'."\n"
            .'  <sheets>'."\n"
            .'    <sheet name="Laporan Laba Rugi" sheetId="1" r:id="rId1"/>'."\n"
            .'    <sheet name="Buku Jurnal Umum" sheetId="2" r:id="rId2"/>'."\n"
            .'  </sheets>'."\n"
            .'</workbook>';

        // 5. xl/styles.xml
        $styles = $this->buildStylesXml();

        // 6. xl/worksheets/sheet1.xml (Laporan Laba Rugi)
        $sheet1Xml = $this->buildIncomeStatementSheetXml([
            'periodTitle' => $periodTitle,
            'statusText' => $statusText,
            'exportTimeText' => $exportTimeText,
            'revenueItems' => $revenueItems,
            'totalRevenue' => $totalRevenue,
            'expenseItems' => $expenseItems,
            'totalExpense' => $totalExpense,
            'assetItems' => $assetItems,
            'totalAssetDebit' => $totalAssetDebit,
            'totalAssetCredit' => $totalAssetCredit,
            'netCashFlow' => $netCashFlow,
            'netProfit' => $netProfit,
            'profitMarginRatio' => $profitMarginRatio,
            'expenseRatio' => $expenseRatio,
        ]);

        // 7. xl/worksheets/sheet2.xml (Buku Jurnal Umum)
        $sheet2Xml = $this->buildJournalSheetXml($entries, [
            'periodTitle' => $periodTitle,
            'statusText' => $statusText,
            'exportTimeText' => $exportTimeText,
            'search' => $search,
        ]);

        $files = [
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels' => $rootRels,
            'xl/_rels/workbook.xml.rels' => $wbRels,
            'xl/workbook.xml' => $workbook,
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet1Xml,
            'xl/worksheets/sheet2.xml' => $sheet2Xml,
        ];

        // Buat file ZIP paket .xlsx
        $xlsxContent = $this->createZipPackage($files);

        $filename = 'Laporan_Keuangan_dan_Jurnal_'.$filenameSuffix.'.xlsx';

        return response($xlsxContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Membangun XML untuk Sheet 1: Laporan Laba Rugi (Income Statement)
     */
    private function buildIncomeStatementSheetXml(array $data): string
    {
        $periodTitle = $data['periodTitle'];
        $statusText = $data['statusText'];
        $exportTimeText = $data['exportTimeText'];
        $revenueItems = $data['revenueItems'];
        $totalRevenue = (float) $data['totalRevenue'];
        $expenseItems = $data['expenseItems'];
        $totalExpense = (float) $data['totalExpense'];
        $assetItems = $data['assetItems'];
        $totalAssetDebit = (float) $data['totalAssetDebit'];
        $totalAssetCredit = (float) $data['totalAssetCredit'];
        $netCashFlow = (float) $data['netCashFlow'];
        $netProfit = (float) $data['netProfit'];
        $profitMarginRatio = (float) $data['profitMarginRatio'];
        $expenseRatio = (float) $data['expenseRatio'];

        $isProfit = $netProfit >= 0;

        $sheetData = '  <sheetData>'."\n";
        $mergeRanges = [];

        // Row 1: Company Title
        $sheetData .= '    <row r="1" ht="26" customHeight="1">'."\n"
            .'      <c r="A1" s="1" t="inlineStr"><is><t>SEVEN ACCOUNTING</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.'1" s="1"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A1:H1';

        // Row 2: Document Title
        $sheetData .= '    <row r="2" ht="22" customHeight="1">'."\n"
            .'      <c r="A2" s="2" t="inlineStr"><is><t>LAPORAN LABA RUGI KOMPREHENSIF (INCOME STATEMENT)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.'2" s="2"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A2:H2';

        // Row 3: Periode & Filter Status
        $sheetData .= '    <row r="3" ht="18" customHeight="1">'."\n"
            .'      <c r="A3" s="3" t="inlineStr"><is><t>Periode</t></is></c>'."\n"
            .'      <c r="B3" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($periodTitle).'</t></is></c>'."\n"
            .'      <c r="C3" s="0"/>'."\n"
            .'      <c r="D3" s="0"/>'."\n"
            .'      <c r="E3" s="3" t="inlineStr"><is><t>Filter Status</t></is></c>'."\n"
            .'      <c r="F3" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($statusText).'</t></is></c>'."\n"
            .'      <c r="G3" s="0"/>'."\n"
            .'      <c r="H3" s="0"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'B3:D3';
        $mergeRanges[] = 'F3:H3';

        // Row 4: Tanggal Unduh & Entitas Usaha
        $sheetData .= '    <row r="4" ht="18" customHeight="1">'."\n"
            .'      <c r="A4" s="3" t="inlineStr"><is><t>Tanggal Unduh</t></is></c>'."\n"
            .'      <c r="B4" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($exportTimeText).'</t></is></c>'."\n"
            .'      <c r="C4" s="0"/>'."\n"
            .'      <c r="D4" s="0"/>'."\n"
            .'      <c r="E4" s="3" t="inlineStr"><is><t>Entitas Usaha</t></is></c>'."\n"
            .'      <c r="F4" s="0" t="inlineStr"><is><t>: CV / PT Seven Management (Badan Usaha)</t></is></c>'."\n"
            .'      <c r="G4" s="0"/>'."\n"
            .'      <c r="H4" s="0"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'B4:D4';
        $mergeRanges[] = 'F4:H4';

        // Row 5: Spacer
        $sheetData .= '    <row r="5" ht="10"/>'."\n";

        // =========================================================================
        // KPI SUMMARY CARDS (ROWS 6 - 8)
        // =========================================================================
        // Card 1: A6:B8 -> Total Pendapatan
        // Card 2: C6:D8 -> Total Beban
        // Card 3: E6:F8 -> Laba / Rugi Bersih
        // Card 4: G6:H8 -> Arus Kas Masuk
        $profitCardValStyle = $isProfit ? 18 : 19;
        $profitCardNote = ($isProfit ? 'Surplus Laba (' : 'Defisit Rugi (').number_format(abs($profitMarginRatio * 100), 1).'%)';

        // Row 6: KPI Card Titles
        $sheetData .= '    <row r="6" ht="20" customHeight="1">'."\n"
            .'      <c r="A6" s="16" t="inlineStr"><is><t>TOTAL PENDAPATAN (OMZET)</t></is></c><c r="B6" s="16"/>'."\n"
            .'      <c r="C6" s="16" t="inlineStr"><is><t>TOTAL BEBAN OPERASIONAL</t></is></c><c r="D6" s="16"/>'."\n"
            .'      <c r="E6" s="16" t="inlineStr"><is><t>HASIL USAHA BERSIH</t></is></c><c r="F6" s="16"/>'."\n"
            .'      <c r="G6" s="16" t="inlineStr"><is><t>PENERIMAAN KAS &amp; BANK</t></is></c><c r="H6" s="16"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A6:B6';
        $mergeRanges[] = 'C6:D6';
        $mergeRanges[] = 'E6:F6';
        $mergeRanges[] = 'G6:H6';

        // Row 7: KPI Card Values
        $sheetData .= '    <row r="7" ht="28" customHeight="1">'."\n"
            .'      <c r="A7" s="17"><v>'.$totalRevenue.'</v></c><c r="B7" s="17"/>'."\n"
            .'      <c r="C7" s="17"><v>'.$totalExpense.'</v></c><c r="D7" s="17"/>'."\n"
            .'      <c r="E7" s="'.$profitCardValStyle.'"><v>'.$netProfit.'</v></c><c r="F7" s="'.$profitCardValStyle.'"/>'."\n"
            .'      <c r="G7" s="17"><v>'.$totalAssetDebit.'</v></c><c r="H7" s="17"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A7:B7';
        $mergeRanges[] = 'C7:D7';
        $mergeRanges[] = 'E7:F7';
        $mergeRanges[] = 'G7:H7';

        // Row 8: KPI Card Subtitles / Badges
        $sheetData .= '    <row r="8" ht="18" customHeight="1">'."\n"
            .'      <c r="A8" s="29" t="inlineStr"><is><t>100.0% Pemasukan Bruto</t></is></c><c r="B8" s="29"/>'."\n"
            .'      <c r="C8" s="29" t="inlineStr"><is><t>Rasio Beban: '.number_format($expenseRatio * 100, 1).'%</t></is></c><c r="D8" s="29"/>'."\n"
            .'      <c r="E8" s="29" t="inlineStr"><is><t>'.$this->xmlSafe($profitCardNote).'</t></is></c><c r="F8" s="29"/>'."\n"
            .'      <c r="G8" s="29" t="inlineStr"><is><t>Arus Masuk Operasional</t></is></c><c r="H8" s="29"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A8:B8';
        $mergeRanges[] = 'C8:D8';
        $mergeRanges[] = 'E8:F8';
        $mergeRanges[] = 'G8:H8';

        // Row 9: Spacer
        $sheetData .= '    <row r="9" ht="12"/>'."\n";

        $curRow = 10;

        // =========================================================================
        // SEKSI I: PENDAPATAN USAHA (REVENUE)
        // =========================================================================
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="15" t="inlineStr"><is><t>I. PENDAPATAN USAHA (REVENUE)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.$curRow.'" s="15"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':H'.$curRow;
        $curRow++;

        // Table Header Revenue
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="4" t="inlineStr"><is><t>Kode Akun</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="4" t="inlineStr"><is><t>Nama Rekening / Akun Pendapatan</t></is></c>'."\n"
            .'      <c r="C'.$curRow.'" s="4" t="inlineStr"><is><t>Mutasi Debit (IDR)</t></is></c>'."\n"
            .'      <c r="D'.$curRow.'" s="4" t="inlineStr"><is><t>Mutasi Kredit (IDR)</t></is></c>'."\n"
            .'      <c r="E'.$curRow.'" s="4" t="inlineStr"><is><t>Total Pendapatan (IDR)</t></is></c>'."\n"
            .'      <c r="F'.$curRow.'" s="4" t="inlineStr"><is><t>% Omzet</t></is></c>'."\n"
            .'      <c r="G'.$curRow.'" s="4" t="inlineStr"><is><t>Klasifikasi</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="4" t="inlineStr"><is><t>Status</t></is></c>'."\n"
            .'    </row>'."\n";
        $curRow++;

        foreach ($revenueItems as $rev) {
            $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
                .'      <c r="A'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($rev['code']).'</t></is></c>'."\n"
                .'      <c r="B'.$curRow.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($rev['name']).'</t></is></c>'."\n"
                .'      <c r="C'.$curRow.'" s="7"><v>'.$rev['debit'].'</v></c>'."\n"
                .'      <c r="D'.$curRow.'" s="7"><v>'.$rev['credit'].'</v></c>'."\n"
                .'      <c r="E'.$curRow.'" s="7"><v>'.$rev['net'].'</v></c>'."\n"
                .'      <c r="F'.$curRow.'" s="20"><v>'.round($rev['pct'], 4).'</v></c>'."\n"
                .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($rev['category']).'</t></is></c>'."\n"
                .'      <c r="H'.$curRow.'" s="8" t="inlineStr"><is><t>AKTIF</t></is></c>'."\n"
                .'    </row>'."\n";
            $curRow++;
        }

        // Subtotal Revenue
        $sheetData .= '    <row r="'.$curRow.'" ht="22" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="11" t="inlineStr"><is><t>TOTAL PENDAPATAN USAHA (A) :</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="11"/><c r="C'.$curRow.'" s="11"/><c r="D'.$curRow.'" s="11"/>'."\n"
            .'      <c r="E'.$curRow.'" s="12"><v>'.$totalRevenue.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.($totalRevenue > 0 ? 1.0 : 0.0).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="13" t="inlineStr"><is><t>SUMBER OMZET</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="13"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Spacer
        $sheetData .= '    <row r="'.$curRow.'" ht="12"/>'."\n";
        $curRow++;

        // =========================================================================
        // SEKSI II: BEBAN OPERASIONAL & USAHA (EXPENSES)
        // =========================================================================
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="15" t="inlineStr"><is><t>II. BEBAN OPERASIONAL &amp; USAHA (OPERATING EXPENSES)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.$curRow.'" s="15"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':H'.$curRow;
        $curRow++;

        // Table Header Expenses
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="4" t="inlineStr"><is><t>Kode Akun</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="4" t="inlineStr"><is><t>Nama Rekening / Akun Beban</t></is></c>'."\n"
            .'      <c r="C'.$curRow.'" s="4" t="inlineStr"><is><t>Mutasi Debit (IDR)</t></is></c>'."\n"
            .'      <c r="D'.$curRow.'" s="4" t="inlineStr"><is><t>Mutasi Kredit (IDR)</t></is></c>'."\n"
            .'      <c r="E'.$curRow.'" s="4" t="inlineStr"><is><t>Total Beban (IDR)</t></is></c>'."\n"
            .'      <c r="F'.$curRow.'" s="4" t="inlineStr"><is><t>% Omzet</t></is></c>'."\n"
            .'      <c r="G'.$curRow.'" s="4" t="inlineStr"><is><t>Klasifikasi</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="4" t="inlineStr"><is><t>Status</t></is></c>'."\n"
            .'    </row>'."\n";
        $curRow++;

        foreach ($expenseItems as $exp) {
            $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
                .'      <c r="A'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($exp['code']).'</t></is></c>'."\n"
                .'      <c r="B'.$curRow.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($exp['name']).'</t></is></c>'."\n"
                .'      <c r="C'.$curRow.'" s="7"><v>'.$exp['debit'].'</v></c>'."\n"
                .'      <c r="D'.$curRow.'" s="7"><v>'.$exp['credit'].'</v></c>'."\n"
                .'      <c r="E'.$curRow.'" s="7"><v>'.$exp['net'].'</v></c>'."\n"
                .'      <c r="F'.$curRow.'" s="20"><v>'.round($exp['pct'], 4).'</v></c>'."\n"
                .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($exp['category']).'</t></is></c>'."\n"
                .'      <c r="H'.$curRow.'" s="8" t="inlineStr"><is><t>AKTIF</t></is></c>'."\n"
                .'    </row>'."\n";
            $curRow++;
        }

        // Subtotal Expenses
        $sheetData .= '    <row r="'.$curRow.'" ht="22" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="11" t="inlineStr"><is><t>TOTAL BEBAN OPERASIONAL (B) :</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="11"/><c r="C'.$curRow.'" s="11"/><c r="D'.$curRow.'" s="11"/>'."\n"
            .'      <c r="E'.$curRow.'" s="12"><v>'.$totalExpense.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.round($expenseRatio, 4).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="10" t="inlineStr"><is><t>PENGELUARAN</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="10"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Spacer
        $sheetData .= '    <row r="'.$curRow.'" ht="12"/>'."\n";
        $curRow++;

        // =========================================================================
        // SEKSI III: PERHITUNGAN LABA / (RUGI) BERSIH
        // =========================================================================
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="15" t="inlineStr"><is><t>III. PERHITUNGAN HASIL USAHA BERSIH (NET INCOME / LOSS)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.$curRow.'" s="15"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':H'.$curRow;
        $curRow++;

        // Baris 1: Total Pendapatan
        $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="21" t="inlineStr"><is><t>Total Pendapatan Usaha Bruto (A)</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="21"/><c r="C'.$curRow.'" s="21"/><c r="D'.$curRow.'" s="21"/>'."\n"
            .'      <c r="E'.$curRow.'" s="22"><v>'.$totalRevenue.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.($totalRevenue > 0 ? 1.0 : 0.0).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>Pemasukan Bruto</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="6"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Baris 2: Total Beban
        $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="21" t="inlineStr"><is><t>Total Beban &amp; Pengeluaran Operasional (B)</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="21"/><c r="C'.$curRow.'" s="21"/><c r="D'.$curRow.'" s="21"/>'."\n"
            .'      <c r="E'.$curRow.'" s="22"><v>'.$totalExpense.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.round($expenseRatio, 4).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>Biaya Operasional</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="6"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Baris 3: Laba / Rugi Bersih (Grand Total)
        $grandLabel = $isProfit ? 'LABA BERSIH USAHA (NET PROFIT) [A - B] :' : 'RUGI BERSIH USAHA (NET DEFICIT) [A - B] :';
        $grandBadge = $isProfit ? 'SURPLUS BERSIH' : 'DEFISIT BERSIH';
        $grandBadgeStyle = $isProfit ? 13 : 14;
        $grandLabelStyle = $isProfit ? 24 : 26;
        $grandValStyle = $isProfit ? 25 : 27;

        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="'.$grandLabelStyle.'" t="inlineStr"><is><t>'.$grandLabel.'</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="'.$grandLabelStyle.'"/><c r="C'.$curRow.'" s="'.$grandLabelStyle.'"/><c r="D'.$curRow.'" s="'.$grandLabelStyle.'"/>'."\n"
            .'      <c r="E'.$curRow.'" s="'.$grandValStyle.'"><v>'.$netProfit.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.round($profitMarginRatio, 4).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="'.$grandBadgeStyle.'" t="inlineStr"><is><t>'.$grandBadge.'</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="'.$grandBadgeStyle.'"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Baris 4: Margin Laba Bersih
        $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="21" t="inlineStr"><is><t>Margin Laba Bersih (Net Profit Margin)</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="21"/><c r="C'.$curRow.'" s="21"/><c r="D'.$curRow.'" s="21"/>'."\n"
            .'      <c r="E'.$curRow.'" s="23"><v>'.round($profitMarginRatio, 4).'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.round($profitMarginRatio, 4).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>Rasio Profitabilitas</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="6"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':D'.$curRow;
        $mergeRanges[] = 'E'.$curRow.':F'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Spacer
        $sheetData .= '    <row r="'.$curRow.'" ht="12"/>'."\n";
        $curRow++;

        // =========================================================================
        // SEKSI IV: RINGKASAN REKENING KAS & BANK
        // =========================================================================
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="15" t="inlineStr"><is><t>IV. RINGKASAN MUTASI REKENING KAS &amp; BANK (LIQUIDITY MOVEMENT)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.$curRow.'" s="15"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':H'.$curRow;
        $curRow++;

        // Table Header Kas & Bank
        $sheetData .= '    <row r="'.$curRow.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="4" t="inlineStr"><is><t>Kode Akun</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="4" t="inlineStr"><is><t>Nama Rekening Kas / Bank</t></is></c>'."\n"
            .'      <c r="C'.$curRow.'" s="4" t="inlineStr"><is><t>Penerimaan Kas (Debit)</t></is></c>'."\n"
            .'      <c r="D'.$curRow.'" s="4" t="inlineStr"><is><t>Pengeluaran Kas (Kredit)</t></is></c>'."\n"
            .'      <c r="E'.$curRow.'" s="4" t="inlineStr"><is><t>Arus Bersih (IDR)</t></is></c>'."\n"
            .'      <c r="F'.$curRow.'" s="4" t="inlineStr"><is><t>Porsi Arus</t></is></c>'."\n"
            .'      <c r="G'.$curRow.'" s="4" t="inlineStr"><is><t>Kategori Aset</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="4" t="inlineStr"><is><t>Status</t></is></c>'."\n"
            .'    </row>'."\n";
        $curRow++;

        foreach ($assetItems as $ast) {
            $sheetData .= '    <row r="'.$curRow.'" ht="20" customHeight="1">'."\n"
                .'      <c r="A'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($ast['code']).'</t></is></c>'."\n"
                .'      <c r="B'.$curRow.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($ast['name']).'</t></is></c>'."\n"
                .'      <c r="C'.$curRow.'" s="7"><v>'.$ast['debit'].'</v></c>'."\n"
                .'      <c r="D'.$curRow.'" s="7"><v>'.$ast['credit'].'</v></c>'."\n"
                .'      <c r="E'.$curRow.'" s="7"><v>'.$ast['net'].'</v></c>'."\n"
                .'      <c r="F'.$curRow.'" s="20"><v>'.round($ast['pct'], 4).'</v></c>'."\n"
                .'      <c r="G'.$curRow.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($ast['category']).'</t></is></c>'."\n"
                .'      <c r="H'.$curRow.'" s="8" t="inlineStr"><is><t>AKTIF</t></is></c>'."\n"
                .'    </row>'."\n";
            $curRow++;
        }

        // Subtotal Kas & Bank
        $sheetData .= '    <row r="'.$curRow.'" ht="22" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="11" t="inlineStr"><is><t>TOTAL ARUS KAS &amp; BANK :</t></is></c>'."\n"
            .'      <c r="B'.$curRow.'" s="11"/>'."\n"
            .'      <c r="C'.$curRow.'" s="12"><v>'.$totalAssetDebit.'</v></c>'."\n"
            .'      <c r="D'.$curRow.'" s="12"><v>'.$totalAssetCredit.'</v></c>'."\n"
            .'      <c r="E'.$curRow.'" s="12"><v>'.$netCashFlow.'</v></c>'."\n"
            .'      <c r="F'.$curRow.'" s="23"><v>'.($totalAssetDebit > 0 ? 1.0 : 0.0).'</v></c>'."\n"
            .'      <c r="G'.$curRow.'" s="13" t="inlineStr"><is><t>ARUS LIKUIDITAS</t></is></c>'."\n"
            .'      <c r="H'.$curRow.'" s="13"/>'."\n"
            .'    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':B'.$curRow;
        $mergeRanges[] = 'G'.$curRow.':H'.$curRow;
        $curRow++;

        // Spacer
        $sheetData .= '    <row r="'.$curRow.'" ht="14"/>'."\n";
        $curRow++;

        // Catatan Resmi
        $sheetData .= '    <row r="'.$curRow.'" ht="18" customHeight="1">'."\n"
            .'      <c r="A'.$curRow.'" s="28" t="inlineStr"><is><t>* Laporan ini dihasilkan secara otomatis oleh sistem Seven Accounting berdasarkan standar akuntansi berpasangan (double-entry bookkeeping) transaksi aktif.</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheetData .= '      <c r="'.$col.$curRow.'" s="28"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";
        $mergeRanges[] = 'A'.$curRow.':H'.$curRow;

        $sheetData .= '  </sheetData>'."\n";

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'."\n"
            .'  <sheetViews>'."\n"
            .'    <sheetView tabSelected="1" workbookViewId="0"/>'."\n"
            .'  </sheetViews>'."\n"
            .'  <cols>'."\n"
            .'    <col min="1" max="1" width="14" customWidth="1"/>'."\n" // Kode Akun
            .'    <col min="2" max="2" width="36" customWidth="1"/>'."\n" // Nama Rekening
            .'    <col min="3" max="3" width="18" customWidth="1"/>'."\n" // Debit
            .'    <col min="4" max="4" width="18" customWidth="1"/>'."\n" // Kredit
            .'    <col min="5" max="5" width="22" customWidth="1"/>'."\n" // Total / Saldo
            .'    <col min="6" max="6" width="16" customWidth="1"/>'."\n" // % Omzet
            .'    <col min="7" max="7" width="24" customWidth="1"/>'."\n" // Kategori
            .'    <col min="8" max="8" width="16" customWidth="1"/>'."\n" // Status
            .'  </cols>'."\n"
            .$sheetData
            .'  <mergeCells count="'.count($mergeRanges).'">'."\n";
        foreach ($mergeRanges as $range) {
            $sheetXml .= '    <mergeCell ref="'.$range.'"/>'."\n";
        }
        $sheetXml .= '  </mergeCells>'."\n"
            .'</worksheet>';

        return $sheetXml;
    }

    /**
     * Membangun XML untuk Sheet 2: Buku Jurnal Umum (General Journal)
     */
    private function buildJournalSheetXml($entries, array $meta): string
    {
        $periodTitle = $meta['periodTitle'];
        $statusText = $meta['statusText'];
        $exportTimeText = $meta['exportTimeText'];
        $search = $meta['search'] ?? null;

        $accountTypeMap = [
            'asset' => 'Aset',
            'liability' => 'Kewajiban',
            'equity' => 'Ekuitas',
            'revenue' => 'Pendapatan',
            'expense' => 'Beban',
        ];

        $totalTrxText = $entries->count().' Entri Jurnal'.($search ? ' (Kata Kunci: "'.$this->xmlSafe($search).'")' : '');

        $sheetData = '  <sheetData>'."\n";

        // Row 1: Company Title
        $sheetData .= '    <row r="1" ht="24" customHeight="1">'."\n"
            .'      <c r="A1" s="1" t="inlineStr"><is><t>SEVEN ACCOUNTING</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $sheetData .= '      <c r="'.$col.'1" s="1"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";

        // Row 2: Document Title
        $sheetData .= '    <row r="2" ht="20" customHeight="1">'."\n"
            .'      <c r="A2" s="2" t="inlineStr"><is><t>BUKU JURNAL TRANSAKSI (GENERAL JOURNAL)</t></is></c>'."\n";
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $sheetData .= '      <c r="'.$col.'2" s="2"/>'."\n";
        }
        $sheetData .= '    </row>'."\n";

        // Row 3: Periode & Filter Status
        $sheetData .= '    <row r="3" ht="18" customHeight="1">'."\n"
            .'      <c r="A3" s="3" t="inlineStr"><is><t>Periode</t></is></c>'."\n"
            .'      <c r="B3" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($periodTitle).'</t></is></c>'."\n"
            .'      <c r="C3" s="0"/><c r="D3" s="0"/>'."\n"
            .'      <c r="E3" s="3" t="inlineStr"><is><t>Filter Status</t></is></c>'."\n"
            .'      <c r="F3" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($statusText).'</t></is></c>'."\n"
            .'      <c r="G3" s="0"/><c r="H3" s="0"/><c r="I3" s="0"/><c r="J3" s="0"/><c r="K3" s="0"/>'."\n"
            .'    </row>'."\n";

        // Row 4: Tanggal Unduh & Total Transaksi
        $sheetData .= '    <row r="4" ht="18" customHeight="1">'."\n"
            .'      <c r="A4" s="3" t="inlineStr"><is><t>Tanggal Unduh</t></is></c>'."\n"
            .'      <c r="B4" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($exportTimeText).'</t></is></c>'."\n"
            .'      <c r="C4" s="0"/><c r="D4" s="0"/>'."\n"
            .'      <c r="E4" s="3" t="inlineStr"><is><t>Total Transaksi</t></is></c>'."\n"
            .'      <c r="F4" s="0" t="inlineStr"><is><t>: '.$this->xmlSafe($totalTrxText).'</t></is></c>'."\n"
            .'      <c r="G4" s="0"/><c r="H4" s="0"/><c r="I4" s="0"/><c r="J4" s="0"/><c r="K4" s="0"/>'."\n"
            .'    </row>'."\n";

        // Row 5: Spacer
        $sheetData .= '    <row r="5" ht="10"/>'."\n";

        // Row 6: Table Header
        $sheetData .= '    <row r="6" ht="26" customHeight="1">'."\n"
            .'      <c r="A6" s="4" t="inlineStr"><is><t>No.</t></is></c>'."\n"
            .'      <c r="B6" s="4" t="inlineStr"><is><t>Tanggal &amp; Waktu</t></is></c>'."\n"
            .'      <c r="C6" s="4" t="inlineStr"><is><t>No. Referensi</t></is></c>'."\n"
            .'      <c r="D6" s="4" t="inlineStr"><is><t>Keterangan Transaksi</t></is></c>'."\n"
            .'      <c r="E6" s="4" t="inlineStr"><is><t>Kode Akun</t></is></c>'."\n"
            .'      <c r="F6" s="4" t="inlineStr"><is><t>Nama Rekening / Akun</t></is></c>'."\n"
            .'      <c r="G6" s="4" t="inlineStr"><is><t>Tipe Akun</t></is></c>'."\n"
            .'      <c r="H6" s="4" t="inlineStr"><is><t>Debit (IDR)</t></is></c>'."\n"
            .'      <c r="I6" s="4" t="inlineStr"><is><t>Kredit (IDR)</t></is></c>'."\n"
            .'      <c r="J6" s="4" t="inlineStr"><is><t>Sumber</t></is></c>'."\n"
            .'      <c r="K6" s="4" t="inlineStr"><is><t>Status</t></is></c>'."\n"
            .'    </row>'."\n";

        $rowIdx = 7;
        $no = 1;
        $totalDebit = 0;
        $totalCredit = 0;
        $mergeRanges = [
            'A1:K1',
            'A2:K2',
            'B3:D3',
            'F3:K3',
            'B4:D4',
            'F4:K4',
        ];

        foreach ($entries as $trx) {
            $dateStr = $trx->date ? Carbon::parse($trx->date)->format('Y-m-d H:i') : '-';
            $statusLabel = strtoupper($trx->status);
            $statusStyleId = match ($trx->status) {
                'verified' => 8,
                'pending' => 9,
                default => 10,
            };

            $sortedLines = $trx->lines->sortByDesc(fn ($l) => (float) $l->debit)->values();

            if ($sortedLines->isEmpty()) {
                $sheetData .= '    <row r="'.$rowIdx.'" ht="20" customHeight="1">'."\n"
                    .'      <c r="A'.$rowIdx.'" s="6"><v>'.$no++.'</v></c>'."\n"
                    .'      <c r="B'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($dateStr).'</t></is></c>'."\n"
                    .'      <c r="C'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($trx->reference ?? '-').'</t></is></c>'."\n"
                    .'      <c r="D'.$rowIdx.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($trx->description).'</t></is></c>'."\n"
                    .'      <c r="E'.$rowIdx.'" s="6" t="inlineStr"><is><t>-</t></is></c>'."\n"
                    .'      <c r="F'.$rowIdx.'" s="5" t="inlineStr"><is><t>-</t></is></c>'."\n"
                    .'      <c r="G'.$rowIdx.'" s="6" t="inlineStr"><is><t>-</t></is></c>'."\n"
                    .'      <c r="H'.$rowIdx.'" s="7"><v>0</v></c>'."\n"
                    .'      <c r="I'.$rowIdx.'" s="7"><v>0</v></c>'."\n"
                    .'      <c r="J'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($trx->source ?? 'Manual').'</t></is></c>'."\n"
                    .'      <c r="K'.$rowIdx.'" s="'.$statusStyleId.'" t="inlineStr"><is><t>'.$statusLabel.'</t></is></c>'."\n"
                    .'    </row>'."\n";
                $rowIdx++;

                continue;
            }

            foreach ($sortedLines as $idx => $line) {
                $debit = (float) $line->debit;
                $credit = (float) $line->credit;
                if ($trx->status !== 'rejected') {
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                $accCode = $line->account->code ?? '-';
                $rawAccName = $line->account->name ?? '-';
                $rawType = $line->account->type ?? '-';
                $accType = $accountTypeMap[$rawType] ?? ucfirst($rawType);

                $indent = ($credit > 0 && $debit == 0) ? '     ' : '';

                $sheetData .= '    <row r="'.$rowIdx.'" ht="20" customHeight="1">'."\n";

                if ($idx === 0) {
                    $sheetData .= '      <c r="A'.$rowIdx.'" s="6"><v>'.$no++.'</v></c>'."\n"
                        .'      <c r="B'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($dateStr).'</t></is></c>'."\n"
                        .'      <c r="C'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($trx->reference ?? '-').'</t></is></c>'."\n"
                        .'      <c r="D'.$rowIdx.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($trx->description).'</t></is></c>'."\n";
                } else {
                    $sheetData .= '      <c r="A'.$rowIdx.'" s="6"/>'."\n"
                        .'      <c r="B'.$rowIdx.'" s="6"/>'."\n"
                        .'      <c r="C'.$rowIdx.'" s="6"/>'."\n"
                        .'      <c r="D'.$rowIdx.'" s="5" t="inlineStr"><is><t>'.$this->xmlSafe($line->description ?: $trx->description).'</t></is></c>'."\n";
                }

                $accXml = $indent ? '<is><t xml:space="preserve">'.$indent.$this->xmlSafe($rawAccName).'</t></is>' : '<is><t>'.$this->xmlSafe($rawAccName).'</t></is>';

                $sheetData .= '      <c r="E'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($accCode).'</t></is></c>'."\n"
                    .'      <c r="F'.$rowIdx.'" s="5" t="inlineStr">'.$accXml.'</c>'."\n"
                    .'      <c r="G'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($accType).'</t></is></c>'."\n"
                    .'      <c r="H'.$rowIdx.'" s="7"><v>'.$debit.'</v></c>'."\n"
                    .'      <c r="I'.$rowIdx.'" s="7"><v>'.$credit.'</v></c>'."\n"
                    .'      <c r="J'.$rowIdx.'" s="6" t="inlineStr"><is><t>'.$this->xmlSafe($trx->source ?? 'Manual').'</t></is></c>'."\n"
                    .'      <c r="K'.$rowIdx.'" s="'.$statusStyleId.'" t="inlineStr"><is><t>'.$statusLabel.'</t></is></c>'."\n"
                    .'    </row>'."\n";

                $rowIdx++;
            }
        }

        // Baris Total Ringkasan
        $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
        $balanceText = $isBalanced ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG';
        $balanceStyleId = $isBalanced ? 13 : 14;

        $sheetData .= '    <row r="'.$rowIdx.'" ht="24" customHeight="1">'."\n"
            .'      <c r="A'.$rowIdx.'" s="11" t="inlineStr"><is><t>TOTAL MUTASI JURNAL (AKTIF) :</t></is></c>'."\n"
            .'      <c r="B'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="C'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="D'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="E'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="F'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="G'.$rowIdx.'" s="11"/>'."\n"
            .'      <c r="H'.$rowIdx.'" s="12"><v>'.$totalDebit.'</v></c>'."\n"
            .'      <c r="I'.$rowIdx.'" s="12"><v>'.$totalCredit.'</v></c>'."\n"
            .'      <c r="J'.$rowIdx.'" s="'.$balanceStyleId.'" t="inlineStr"><is><t>'.$balanceText.'</t></is></c>'."\n"
            .'      <c r="K'.$rowIdx.'" s="'.$balanceStyleId.'"/>'."\n"
            .'    </row>'."\n";

        $mergeRanges[] = 'A'.$rowIdx.':G'.$rowIdx;
        $mergeRanges[] = 'J'.$rowIdx.':K'.$rowIdx;

        $sheetData .= '  </sheetData>'."\n";

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'."\n"
            .'  <sheetViews>'."\n"
            .'    <sheetView tabSelected="0" workbookViewId="0">'."\n"
            .'      <pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>'."\n"
            .'    </sheetView>'."\n"
            .'  </sheetViews>'."\n"
            .'  <cols>'."\n"
            .'    <col min="1" max="1" width="6" customWidth="1"/>'."\n"    // No
            .'    <col min="2" max="2" width="18" customWidth="1"/>'."\n"   // Tanggal
            .'    <col min="3" max="3" width="18" customWidth="1"/>'."\n"   // No. Referensi
            .'    <col min="4" max="4" width="36" customWidth="1"/>'."\n"   // Keterangan
            .'    <col min="5" max="5" width="12" customWidth="1"/>'."\n"   // Kode Akun
            .'    <col min="6" max="6" width="28" customWidth="1"/>'."\n"   // Nama Rekening
            .'    <col min="7" max="7" width="14" customWidth="1"/>'."\n"   // Tipe Akun
            .'    <col min="8" max="8" width="18" customWidth="1"/>'."\n"   // Debit
            .'    <col min="9" max="9" width="18" customWidth="1"/>'."\n"   // Kredit
            .'    <col min="10" max="10" width="14" customWidth="1"/>'."\n" // Sumber
            .'    <col min="11" max="11" width="14" customWidth="1"/>'."\n" // Status
            .'  </cols>'."\n"
            .$sheetData
            .'  <mergeCells count="'.count($mergeRanges).'">'."\n";
        foreach ($mergeRanges as $range) {
            $sheetXml .= '    <mergeCell ref="'.$range.'"/>'."\n";
        }
        $sheetXml .= '  </mergeCells>'."\n"
            .'</worksheet>';

        return $sheetXml;
    }

    /**
     * Membangun file styles.xml yang komprehensif untuk format laporan akuntansi
     */
    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'."\n"
            .'  <numFmts count="2">'."\n"
            .'    <numFmt numFmtId="164" formatCode="#,##0"/>'."\n"
            .'    <numFmt numFmtId="165" formatCode="0.0%"/>'."\n"
            .'  </numFmts>'."\n"
            .'  <fonts count="18">'."\n"
            .'    <font><sz val="10"/><name val="Segoe UI"/><color rgb="FF1E293B"/></font>'."\n" // 0: Normal
            .'    <font><b/><sz val="15"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>'."\n" // 1: App Title
            .'    <font><b/><sz val="12"/><name val="Segoe UI"/><color rgb="FF047857"/></font>'."\n" // 2: Doc Title
            .'    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FF475569"/></font>'."\n" // 3: Meta Label
            .'    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FFFFFFFF"/></font>'."\n" // 4: Header Table
            .'    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>'."\n" // 5: Total Label
            .'    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FF047857"/></font>'."\n" // 6: Status Verified
            .'    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FFB45309"/></font>'."\n" // 7: Status Pending
            .'    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FFBE123C"/></font>'."\n" // 8: Status Rejected
            .'    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FF047857"/></font>'."\n" // 9: Total Emerald
            .'    <font><b/><sz val="11"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>'."\n" // 10: Section Title
            .'    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FF64748B"/></font>'."\n" // 11: KPI Card Label
            .'    <font><b/><sz val="13"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>'."\n" // 12: KPI Card Value Large
            .'    <font><b/><sz val="13"/><name val="Segoe UI"/><color rgb="FF047857"/></font>'."\n" // 13: KPI Card Value Emerald
            .'    <font><b/><sz val="13"/><name val="Segoe UI"/><color rgb="FFBE123C"/></font>'."\n" // 14: KPI Card Value Rose
            .'    <font><b/><sz val="11"/><name val="Segoe UI"/><color rgb="FF047857"/></font>'."\n" // 15: Grand Total Bold Emerald
            .'    <font><b/><sz val="11"/><name val="Segoe UI"/><color rgb="FFBE123C"/></font>'."\n" // 16: Grand Total Bold Rose
            .'    <font><i/><sz val="9"/><name val="Segoe UI"/><color rgb="FF64748B"/></font>'."\n" // 17: Muted Note
            .'  </fonts>'."\n"
            .'  <fills count="11">'."\n"
            .'    <fill><patternFill patternType="none"/></fill>'."\n" // 0: None
            .'    <fill><patternFill patternType="gray125"/></fill>'."\n" // 1: Gray125
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FF047857"/></patternFill></fill>'."\n" // 2: Header Emerald
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'."\n" // 3: Total Gray
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>'."\n" // 4: Light Green
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>'."\n" // 5: Light Amber
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFFFE4E6"/></patternFill></fill>'."\n" // 6: Light Rose
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'."\n" // 7: Card Background Slate
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>'."\n" // 8: Subheader Slate
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/></patternFill></fill>'."\n" // 9: Soft Green Card
            .'    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF1F2"/></patternFill></fill>'."\n" // 10: Soft Rose Card
            .'  </fills>'."\n"
            .'  <borders count="6">'."\n"
            .'    <border><left/><right/><top/><bottom/></border>'."\n" // 0: None
            .'    <border>'."\n" // 1: Cell Border Thin
            .'      <left style="thin"><color rgb="FFE2E8F0"/></left>'."\n"
            .'      <right style="thin"><color rgb="FFE2E8F0"/></right>'."\n"
            .'      <top style="thin"><color rgb="FFE2E8F0"/></top>'."\n"
            .'      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>'."\n"
            .'    </border>'."\n"
            .'    <border>'."\n" // 2: Total Border Emerald
            .'      <left style="thin"><color rgb="FFE2E8F0"/></left>'."\n"
            .'      <right style="thin"><color rgb="FFE2E8F0"/></right>'."\n"
            .'      <top style="medium"><color rgb="FF047857"/></top>'."\n"
            .'      <bottom style="double"><color rgb="FF047857"/></bottom>'."\n"
            .'    </border>'."\n"
            .'    <border>'."\n" // 3: Card Border
            .'      <left style="thin"><color rgb="FFCBD5E1"/></left>'."\n"
            .'      <right style="thin"><color rgb="FFCBD5E1"/></right>'."\n"
            .'      <top style="thin"><color rgb="FFCBD5E1"/></top>'."\n"
            .'      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'."\n"
            .'    </border>'."\n"
            .'    <border>'."\n" // 4: Total Border Rose
            .'      <left style="thin"><color rgb="FFE2E8F0"/></left>'."\n"
            .'      <right style="thin"><color rgb="FFE2E8F0"/></right>'."\n"
            .'      <top style="medium"><color rgb="FFBE123C"/></top>'."\n"
            .'      <bottom style="double"><color rgb="FFBE123C"/></bottom>'."\n"
            .'    </border>'."\n"
            .'    <border>'."\n" // 5: Subtotal Border Gray
            .'      <left style="thin"><color rgb="FFE2E8F0"/></left>'."\n"
            .'      <right style="thin"><color rgb="FFE2E8F0"/></right>'."\n"
            .'      <top style="thin"><color rgb="FF94A3B8"/></top>'."\n"
            .'      <bottom style="thin"><color rgb="FF94A3B8"/></bottom>'."\n"
            .'    </border>'."\n"
            .'  </borders>'."\n"
            .'  <cellStyleXfs count="1">'."\n"
            .'    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'."\n"
            .'  </cellStyleXfs>'."\n"
            .'  <cellXfs count="30">'."\n"
            .'    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'."\n" // 0: Normal
            .'    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'."\n" // 1: App Title
            .'    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'."\n" // 2: Doc Title
            .'    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'."\n" // 3: Meta Label
            .'    <xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'."\n" // 4: Header Table
            .'    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'."\n" // 5: Cell Left
            .'    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 6: Cell Center
            .'    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 7: Cell Currency
            .'    <xf numFmtId="0" fontId="6" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 8: Status Verified
            .'    <xf numFmtId="0" fontId="7" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 9: Status Pending
            .'    <xf numFmtId="0" fontId="8" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 10: Status Rejected
            .'    <xf numFmtId="0" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 11: Total Label
            .'    <xf numFmtId="164" fontId="9" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 12: Total Currency
            .'    <xf numFmtId="0" fontId="6" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 13: Balanced Badge
            .'    <xf numFmtId="0" fontId="8" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 14: Unbalanced Badge
            .'    <xf numFmtId="0" fontId="10" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'."\n" // 15: Section Title
            .'    <xf numFmtId="0" fontId="11" fillId="7" borderId="3" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 16: KPI Card Header
            .'    <xf numFmtId="164" fontId="12" fillId="7" borderId="3" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 17: KPI Card Value Large
            .'    <xf numFmtId="164" fontId="13" fillId="9" borderId="3" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 18: KPI Card Value Emerald
            .'    <xf numFmtId="164" fontId="14" fillId="10" borderId="3" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 19: KPI Card Value Rose
            .'    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 20: Cell Percent
            .'    <xf numFmtId="0" fontId="5" fillId="3" borderId="5" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 21: Subtotal Label
            .'    <xf numFmtId="164" fontId="5" fillId="3" borderId="5" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 22: Subtotal Currency
            .'    <xf numFmtId="165" fontId="5" fillId="3" borderId="5" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 23: Subtotal Percent
            .'    <xf numFmtId="0" fontId="15" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 24: Grand Total Label Emerald
            .'    <xf numFmtId="164" fontId="15" fillId="4" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 25: Grand Total Currency Emerald
            .'    <xf numFmtId="0" fontId="16" fillId="6" borderId="4" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 26: Grand Total Label Rose
            .'    <xf numFmtId="164" fontId="16" fillId="6" borderId="4" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'."\n" // 27: Grand Total Currency Rose
            .'    <xf numFmtId="0" fontId="17" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="left" vertical="center"/></xf>'."\n" // 28: Muted Note
            .'    <xf numFmtId="0" fontId="3" fillId="7" borderId="3" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'."\n" // 29: KPI Card Subtitle
            .'  </cellXfs>'."\n"
            .'</styleSheet>';
    }

    /**
     * Membangun file ZIP (.xlsx) menggunakan ZipArchive atau fallback Pure-PHP PKZip
     */
    private function createZipPackage(array $files): string
    {
        if (class_exists(\ZipArchive::class)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            $zip = new \ZipArchive;
            if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($files as $path => $content) {
                    $zip->addFromString($path, $content);
                }
                $zip->close();
                $data = file_get_contents($tempFile);
                @unlink($tempFile);
                if ($data !== false && strlen($data) > 0) {
                    return $data;
                }
            }
        }

        // Fallback: Pure-PHP PKZip generator menggunakan gzdeflate
        $zipData = '';
        $centralDir = '';
        $offset = 0;

        foreach ($files as $filename => $content) {
            $uncompressedSize = strlen($content);
            $crc = crc32($content);
            $compressed = gzdeflate($content);
            $compressedSize = strlen($compressed);

            $time = (date('H') << 11) | (date('i') << 5) | (date('s') >> 1);
            $date = ((date('Y') - 1980) << 9) | (date('m') << 5) | date('d');

            $header = "PK\x03\x04".
                pack('v', 20).pack('v', 0).pack('v', 8).
                pack('v', $time).pack('v', $date).
                pack('V', $crc).pack('V', $compressedSize).pack('V', $uncompressedSize).
                pack('v', strlen($filename)).pack('v', 0).
                $filename;

            $zipData .= $header.$compressed;

            $cd = "PK\x01\x02".
                pack('v', 20).pack('v', 20).pack('v', 0).pack('v', 8).
                pack('v', $time).pack('v', $date).
                pack('V', $crc).pack('V', $compressedSize).pack('V', $uncompressedSize).
                pack('v', strlen($filename)).pack('v', 0).pack('v', 0).
                pack('v', 0).pack('v', 0).pack('V', 32).
                pack('V', $offset).
                $filename;

            $centralDir .= $cd;
            $offset = strlen($zipData);
        }

        $eocd = "PK\x05\x06".
            pack('v', 0).pack('v', 0).
            pack('v', count($files)).pack('v', count($files)).
            pack('V', strlen($centralDir)).pack('V', strlen($zipData)).
            pack('v', 0);

        return $zipData.$centralDir.$eocd;
    }

    /**
     * Memastikan string aman disisipkan ke elemen XML
     */
    private function xmlSafe(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
