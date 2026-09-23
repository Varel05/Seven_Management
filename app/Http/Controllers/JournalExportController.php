<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalExportController extends Controller
{
    /**
     * Ekspor Buku Jurnal Transaksi Bulanan ke format Excel terbaru (.xlsx)
     */
    public function exportMonthly(Request $request): Response
    {
        $monthInput = $request->query('month', now()->format('Y-m'));
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = JournalEntry::with(['lines.account'])->orderBy('date', 'asc');

        $isAllMonths = ($monthInput === 'all');
        $periodTitle = 'Semua Periode';
        $sheetTitle = 'Semua Periode';
        $filenameSuffix = 'Semua_Periode';

        if (!$isAllMonths && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            [$year, $month] = explode('-', $monthInput);
            $query->whereYear('date', (int)$year)->whereMonth('date', (int)$month);
            $carbonMonth = Carbon::createFromDate((int)$year, (int)$month, 1);
            $periodTitle = $carbonMonth->translatedFormat('F Y');
            $sheetTitle = 'Jurnal ' . $carbonMonth->translatedFormat('M Y');
            $filenameSuffix = $carbonMonth->format('Y_m');
        }

        if ($status && in_array($status, ['verified', 'pending', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($search) {
            $searchTerm = '%' . trim($search) . '%';
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

        $accountTypeMap = [
            'asset'     => 'Aset',
            'liability' => 'Kewajiban',
            'equity'    => 'Ekuitas',
            'revenue'   => 'Pendapatan',
            'expense'   => 'Beban',
        ];

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n"
            . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n"
            . '  <Default Extension="xml" ContentType="application/xml"/>' . "\n"
            . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n"
            . '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n"
            . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n"
            . '</Types>';

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n"
            . '</Relationships>';

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n"
            . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n"
            . '</Relationships>';

        // 4. xl/workbook.xml
        $cleanSheetName = substr(preg_replace('/[^A-Za-z0-9 _-]/', '', $sheetTitle), 0, 31);
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n"
            . '  <sheets>' . "\n"
            . '    <sheet name="' . $this->xmlSafe($cleanSheetName) . '" sheetId="1" r:id="rId1"/>' . "\n"
            . '  </sheets>' . "\n"
            . '</workbook>';

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
            . '  <numFmts count="1">' . "\n"
            . '    <numFmt numFmtId="164" formatCode="#,##0"/>' . "\n"
            . '  </numFmts>' . "\n"
            . '  <fonts count="10">' . "\n"
            . '    <font><sz val="10"/><name val="Segoe UI"/><color rgb="FF1E293B"/></font>' . "\n" // 0: Normal
            . '    <font><b/><sz val="15"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>' . "\n" // 1: App Title
            . '    <font><b/><sz val="12"/><name val="Segoe UI"/><color rgb="FF047857"/></font>' . "\n" // 2: Doc Title
            . '    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FF475569"/></font>' . "\n" // 3: Meta Label
            . '    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FFFFFFFF"/></font>' . "\n" // 4: Header
            . '    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>' . "\n" // 5: Total Label
            . '    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FF047857"/></font>' . "\n" // 6: Verified
            . '    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FFB45309"/></font>' . "\n" // 7: Pending
            . '    <font><b/><sz val="9"/><name val="Segoe UI"/><color rgb="FFBE123C"/></font>' . "\n" // 8: Rejected
            . '    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FF047857"/></font>' . "\n" // 9: Total Emerald
            . '  </fonts>' . "\n"
            . '  <fills count="7">' . "\n"
            . '    <fill><patternFill patternType="none"/></fill>' . "\n" // 0: None
            . '    <fill><patternFill patternType="gray125"/></fill>' . "\n" // 1: Gray125
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FF047857"/></patternFill></fill>' . "\n" // 2: Header Emerald
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>' . "\n" // 3: Total Gray
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>' . "\n" // 4: Light Green
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>' . "\n" // 5: Light Amber
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFFFE4E6"/></patternFill></fill>' . "\n" // 6: Light Rose
            . '  </fills>' . "\n"
            . '  <borders count="3">' . "\n"
            . '    <border><left/><right/><top/><bottom/></border>' . "\n" // 0: None
            . '    <border>' . "\n" // 1: Cell Border
            . '      <left><color rgb="FFE2E8F0"/></left>' . "\n"
            . '      <right style="thin"><color rgb="FFE2E8F0"/></right>' . "\n"
            . '      <top><color rgb="FFE2E8F0"/></top>' . "\n"
            . '      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>' . "\n"
            . '    </border>' . "\n"
            . '    <border>' . "\n" // 2: Total Border
            . '      <top style="medium"><color rgb="FF047857"/></top>' . "\n"
            . '      <bottom style="double"><color rgb="FF047857"/></bottom>' . "\n"
            . '    </border>' . "\n"
            . '  </borders>' . "\n"
            . '  <cellStyleXfs count="1">' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\n"
            . '  </cellStyleXfs>' . "\n"
            . '  <cellXfs count="15">' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n" // 0: Normal
            . '    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n" // 1: App Title
            . '    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n" // 2: Doc Title
            . '    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n" // 3: Meta Label
            . '    <xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . "\n" // 4: Header
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>' . "\n" // 5: Cell Left
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 6: Cell Center
            . '    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n" // 7: Cell Currency
            . '    <xf numFmtId="0" fontId="6" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 8: Status Verified
            . '    <xf numFmtId="0" fontId="7" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 9: Status Pending
            . '    <xf numFmtId="0" fontId="8" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 10: Status Rejected
            . '    <xf numFmtId="0" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n" // 11: Total Label
            . '    <xf numFmtId="164" fontId="9" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n" // 12: Total Currency
            . '    <xf numFmtId="0" fontId="6" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 13: Balanced Badge
            . '    <xf numFmtId="0" fontId="8" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" // 14: Unbalanced Badge
            . '  </cellXfs>' . "\n"
            . '</styleSheet>';

        // 6. xl/worksheets/sheet1.xml
        $statusText = $status === 'all' ? 'Semua Status' : ucfirst($status);
        $totalTrxText = $entries->count() . ' Entri Jurnal' . ($search ? ' (Kata Kunci: "' . $this->xmlSafe($search) . '")' : '');
        $exportTimeText = now()->translatedFormat('d F Y, H:i') . ' WIB';

        $sheetData = '  <sheetData>' . "\n";
        
        // Row 1: Company Title
        $sheetData .= '    <row r="1" ht="24" customHeight="1">' . "\n"
            . '      <c r="A1" s="1" t="inlineStr"><is><t>SEVEN MANAGEMENT</t></is></c>' . "\n"
            . '    </row>' . "\n";

        // Row 2: Document Title
        $sheetData .= '    <row r="2" ht="20" customHeight="1">' . "\n"
            . '      <c r="A2" s="2" t="inlineStr"><is><t>BUKU JURNAL TRANSAKSI (GENERAL JOURNAL)</t></is></c>' . "\n"
            . '    </row>' . "\n";

        // Row 3: Periode & Filter Status
        $sheetData .= '    <row r="3" ht="18" customHeight="1">' . "\n"
            . '      <c r="A3" s="3" t="inlineStr"><is><t>Periode</t></is></c>' . "\n"
            . '      <c r="B3" s="0" t="inlineStr"><is><t>: ' . $this->xmlSafe($periodTitle) . '</t></is></c>' . "\n"
            . '      <c r="E3" s="3" t="inlineStr"><is><t>Filter Status</t></is></c>' . "\n"
            . '      <c r="F3" s="0" t="inlineStr"><is><t>: ' . $this->xmlSafe($statusText) . '</t></is></c>' . "\n"
            . '    </row>' . "\n";

        // Row 4: Tanggal Unduh & Total Transaksi
        $sheetData .= '    <row r="4" ht="18" customHeight="1">' . "\n"
            . '      <c r="A4" s="3" t="inlineStr"><is><t>Tanggal Unduh</t></is></c>' . "\n"
            . '      <c r="B4" s="0" t="inlineStr"><is><t>: ' . $this->xmlSafe($exportTimeText) . '</t></is></c>' . "\n"
            . '      <c r="E4" s="3" t="inlineStr"><is><t>Total Transaksi</t></is></c>' . "\n"
            . '      <c r="F4" s="0" t="inlineStr"><is><t>: ' . $this->xmlSafe($totalTrxText) . '</t></is></c>' . "\n"
            . '    </row>' . "\n";

        // Row 5: Spacer
        $sheetData .= '    <row r="5" ht="10"/>' . "\n";

        // Row 6: Table Header
        $sheetData .= '    <row r="6" ht="26" customHeight="1">' . "\n"
            . '      <c r="A6" s="4" t="inlineStr"><is><t>No.</t></is></c>' . "\n"
            . '      <c r="B6" s="4" t="inlineStr"><is><t>Tanggal &amp; Waktu</t></is></c>' . "\n"
            . '      <c r="C6" s="4" t="inlineStr"><is><t>No. Referensi</t></is></c>' . "\n"
            . '      <c r="D6" s="4" t="inlineStr"><is><t>Keterangan Transaksi</t></is></c>' . "\n"
            . '      <c r="E6" s="4" t="inlineStr"><is><t>Kode Akun</t></is></c>' . "\n"
            . '      <c r="F6" s="4" t="inlineStr"><is><t>Nama Rekening / Akun</t></is></c>' . "\n"
            . '      <c r="G6" s="4" t="inlineStr"><is><t>Tipe Akun</t></is></c>' . "\n"
            . '      <c r="H6" s="4" t="inlineStr"><is><t>Debit (IDR)</t></is></c>' . "\n"
            . '      <c r="I6" s="4" t="inlineStr"><is><t>Kredit (IDR)</t></is></c>' . "\n"
            . '      <c r="J6" s="4" t="inlineStr"><is><t>Sumber</t></is></c>' . "\n"
            . '      <c r="K6" s="4" t="inlineStr"><is><t>Status</t></is></c>' . "\n"
            . '    </row>' . "\n";

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
                'pending'  => 9,
                default    => 10,
            };

            $sortedLines = $trx->lines->sortByDesc(fn($l) => (float)$l->debit)->values();

            if ($sortedLines->isEmpty()) {
                $sheetData .= '    <row r="' . $rowIdx . '" ht="20" customHeight="1">' . "\n"
                    . '      <c r="A' . $rowIdx . '" s="6"><v>' . $no++ . '</v></c>' . "\n"
                    . '      <c r="B' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($dateStr) . '</t></is></c>' . "\n"
                    . '      <c r="C' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($trx->reference ?? '-') . '</t></is></c>' . "\n"
                    . '      <c r="D' . $rowIdx . '" s="5" t="inlineStr"><is><t>' . $this->xmlSafe($trx->description) . '</t></is></c>' . "\n"
                    . '      <c r="E' . $rowIdx . '" s="6" t="inlineStr"><is><t>-</t></is></c>' . "\n"
                    . '      <c r="F' . $rowIdx . '" s="5" t="inlineStr"><is><t>-</t></is></c>' . "\n"
                    . '      <c r="G' . $rowIdx . '" s="6" t="inlineStr"><is><t>-</t></is></c>' . "\n"
                    . '      <c r="H' . $rowIdx . '" s="7"><v>0</v></c>' . "\n"
                    . '      <c r="I' . $rowIdx . '" s="7"><v>0</v></c>' . "\n"
                    . '      <c r="J' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($trx->source ?? 'Manual') . '</t></is></c>' . "\n"
                    . '      <c r="K' . $rowIdx . '" s="' . $statusStyleId . '" t="inlineStr"><is><t>' . $statusLabel . '</t></is></c>' . "\n"
                    . '    </row>' . "\n";
                $rowIdx++;
                continue;
            }

            foreach ($sortedLines as $idx => $line) {
                $debit = (float)$line->debit;
                $credit = (float)$line->credit;
                $totalDebit += $debit;
                $totalCredit += $credit;

                $accCode = $line->account->code ?? '-';
                $rawAccName = $line->account->name ?? '-';
                $rawType = $line->account->type ?? '-';
                $accType = $accountTypeMap[$rawType] ?? ucfirst($rawType);

                $indent = ($credit > 0 && $debit == 0) ? '     ' : '';

                $sheetData .= '    <row r="' . $rowIdx . '" ht="20" customHeight="1">' . "\n";

                if ($idx === 0) {
                    $sheetData .= '      <c r="A' . $rowIdx . '" s="6"><v>' . $no++ . '</v></c>' . "\n"
                        . '      <c r="B' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($dateStr) . '</t></is></c>' . "\n"
                        . '      <c r="C' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($trx->reference ?? '-') . '</t></is></c>' . "\n"
                        . '      <c r="D' . $rowIdx . '" s="5" t="inlineStr"><is><t>' . $this->xmlSafe($trx->description) . '</t></is></c>' . "\n";
                } else {
                    $sheetData .= '      <c r="A' . $rowIdx . '" s="6"/>' . "\n"
                        . '      <c r="B' . $rowIdx . '" s="6"/>' . "\n"
                        . '      <c r="C' . $rowIdx . '" s="6"/>' . "\n"
                        . '      <c r="D' . $rowIdx . '" s="5" t="inlineStr"><is><t>' . $this->xmlSafe($line->description ?: $trx->description) . '</t></is></c>' . "\n";
                }

                $accXml = $indent ? '<is><t xml:space="preserve">' . $indent . $this->xmlSafe($rawAccName) . '</t></is>' : '<is><t>' . $this->xmlSafe($rawAccName) . '</t></is>';

                $sheetData .= '      <c r="E' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($accCode) . '</t></is></c>' . "\n"
                    . '      <c r="F' . $rowIdx . '" s="5" t="inlineStr">' . $accXml . '</c>' . "\n"
                    . '      <c r="G' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($accType) . '</t></is></c>' . "\n"
                    . '      <c r="H' . $rowIdx . '" s="7"><v>' . $debit . '</v></c>' . "\n"
                    . '      <c r="I' . $rowIdx . '" s="7"><v>' . $credit . '</v></c>' . "\n"
                    . '      <c r="J' . $rowIdx . '" s="6" t="inlineStr"><is><t>' . $this->xmlSafe($trx->source ?? 'Manual') . '</t></is></c>' . "\n"
                    . '      <c r="K' . $rowIdx . '" s="' . $statusStyleId . '" t="inlineStr"><is><t>' . $statusLabel . '</t></is></c>' . "\n"
                    . '    </row>' . "\n";

                $rowIdx++;
            }
        }

        // Baris Total Ringkasan
        $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
        $balanceText = $isBalanced ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG';
        $balanceStyleId = $isBalanced ? 13 : 14;

        $sheetData .= '    <row r="' . $rowIdx . '" ht="24" customHeight="1">' . "\n"
            . '      <c r="A' . $rowIdx . '" s="11" t="inlineStr"><is><t>TOTAL MUTASI JURNAL :</t></is></c>' . "\n"
            . '      <c r="B' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="C' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="D' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="E' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="F' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="G' . $rowIdx . '" s="11"/>' . "\n"
            . '      <c r="H' . $rowIdx . '" s="12"><v>' . $totalDebit . '</v></c>' . "\n"
            . '      <c r="I' . $rowIdx . '" s="12"><v>' . $totalCredit . '</v></c>' . "\n"
            . '      <c r="J' . $rowIdx . '" s="' . $balanceStyleId . '" t="inlineStr"><is><t>' . $balanceText . '</t></is></c>' . "\n"
            . '      <c r="K' . $rowIdx . '" s="' . $balanceStyleId . '"/>' . "\n"
            . '    </row>' . "\n";

        $mergeRanges[] = 'A' . $rowIdx . ':G' . $rowIdx;
        $mergeRanges[] = 'J' . $rowIdx . ':K' . $rowIdx;

        $sheetData .= '  </sheetData>' . "\n";

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
            . '  <sheetViews>' . "\n"
            . '    <sheetView tabSelected="1" workbookViewId="0">' . "\n"
            . '      <pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>' . "\n"
            . '    </sheetView>' . "\n"
            . '  </sheetViews>' . "\n"
            . '  <cols>' . "\n"
            . '    <col min="1" max="1" width="6" customWidth="1"/>' . "\n"    // No
            . '    <col min="2" max="2" width="18" customWidth="1"/>' . "\n"   // Tanggal
            . '    <col min="3" max="3" width="18" customWidth="1"/>' . "\n"   // No. Referensi
            . '    <col min="4" max="4" width="36" customWidth="1"/>' . "\n"   // Keterangan
            . '    <col min="5" max="5" width="12" customWidth="1"/>' . "\n"   // Kode Akun
            . '    <col min="6" max="6" width="28" customWidth="1"/>' . "\n"   // Nama Rekening
            . '    <col min="7" max="7" width="14" customWidth="1"/>' . "\n"   // Tipe Akun
            . '    <col min="8" max="8" width="18" customWidth="1"/>' . "\n"   // Debit
            . '    <col min="9" max="9" width="18" customWidth="1"/>' . "\n"   // Kredit
            . '    <col min="10" max="10" width="14" customWidth="1"/>' . "\n" // Sumber
            . '    <col min="11" max="11" width="14" customWidth="1"/>' . "\n" // Status
            . '  </cols>' . "\n"
            . $sheetData
            . '  <mergeCells count="' . count($mergeRanges) . '">' . "\n";
        foreach ($mergeRanges as $range) {
            $sheetXml .= '    <mergeCell ref="' . $range . '"/>' . "\n";
        }
        $sheetXml .= '  </mergeCells>' . "\n"
            . '</worksheet>';

        $files = [
            '[Content_Types].xml'        => $contentTypes,
            '_rels/.rels'                => $rootRels,
            'xl/_rels/workbook.xml.rels' => $wbRels,
            'xl/workbook.xml'            => $workbook,
            'xl/styles.xml'              => $styles,
            'xl/worksheets/sheet1.xml'   => $sheetXml,
        ];

        // Buat paket ZIP .xlsx
        $xlsxContent = $this->createZipPackage($files);

        $filename = 'Buku_Jurnal_Transaksi_' . $filenameSuffix . '.xlsx';

        return response($xlsxContent, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * Membangun file ZIP (.xlsx) menggunakan ZipArchive atau fallback Pure-PHP PKZip
     */
    private function createZipPackage(array $files): string
    {
        if (class_exists(\ZipArchive::class)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            $zip = new \ZipArchive();
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

            $header = "PK\x03\x04" .
                pack('v', 20) . pack('v', 0) . pack('v', 8) .
                pack('v', $time) . pack('v', $date) .
                pack('V', $crc) . pack('V', $compressedSize) . pack('V', $uncompressedSize) .
                pack('v', strlen($filename)) . pack('v', 0) .
                $filename;

            $zipData .= $header . $compressed;

            $cd = "PK\x01\x02" .
                pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 8) .
                pack('v', $time) . pack('v', $date) .
                pack('V', $crc) . pack('V', $compressedSize) . pack('V', $uncompressedSize) .
                pack('v', strlen($filename)) . pack('v', 0) . pack('v', 0) .
                pack('v', 0) . pack('v', 0) . pack('V', 32) .
                pack('V', $offset) .
                $filename;

            $centralDir .= $cd;
            $offset = strlen($zipData);
        }

        $eocd = "PK\x05\x06" .
            pack('v', 0) . pack('v', 0) .
            pack('v', count($files)) . pack('v', count($files)) .
            pack('V', strlen($centralDir)) . pack('V', strlen($zipData)) .
            pack('v', 0);

        return $zipData . $centralDir . $eocd;
    }

    /**
     * Memastikan string aman disisipkan ke elemen XML
     */
    private function xmlSafe(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
