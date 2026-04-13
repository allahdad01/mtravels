<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Unauthorized');
}

require_once('../../includes/db.php');
require_once('../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;

$tenant_id  = $_SESSION['tenant_id'];
$branch_id  = $_SESSION['branch_id'];
$action      = $_GET['action']      ?? null;
$format      = $_GET['format']      ?? 'xlsx';
$report_type = $_GET['report_type'] ?? 'supplier';

// ─── Design tokens ────────────────────────────────────────────────────────────
const CLR_BRAND_DARK   = '0D1B2A'; // deep navy
const CLR_BRAND_MID    = '1B4F72'; // rich blue
const CLR_BRAND_LIGHT  = '2E86C1'; // accent blue
const CLR_ACCENT       = 'E67E22'; // warm amber  (totals / highlight)
const CLR_SUCCESS      = '1E8449'; // forest green (net profit)
const CLR_DANGER       = 'C0392B'; // crimson      (tax)
const CLR_ROW_ALT      = 'EBF5FB'; // very light blue (zebra)
const CLR_ROW_PLAIN    = 'FFFFFF';
const CLR_HEADER_TEXT  = 'FFFFFF';
const CLR_BODY_TEXT    = '1A1A2E';
const CLR_MUTED        = '6C757D';
const CLR_RULE         = 'BDC3C7'; // thin separator lines
const CLR_SECTION_BG   = 'F0F4F8'; // section title bg

// ─── Shared style builders ─────────────────────────────────────────────────────
function colStyle(string $hex): array {
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => CLR_HEADER_TEXT], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => CLR_RULE]]],
    ];
}

function dataStyle(bool $alt = false, bool $right = false): array {
    return [
        'font' => ['size' => 9, 'name' => 'Arial', 'color' => ['rgb' => CLR_BODY_TEXT]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $alt ? CLR_ROW_ALT : CLR_ROW_PLAIN]],
        'alignment' => [
            'horizontal' => $right ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => CLR_RULE]],
            'left'   => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => CLR_RULE]],
            'right'  => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => CLR_RULE]],
        ],
    ];
}

function totalStyle(string $bgHex, string $textHex = CLR_HEADER_TEXT, int $size = 10): array {
    return [
        'font' => ['bold' => true, 'size' => $size, 'name' => 'Arial', 'color' => ['rgb' => $textHex]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => CLR_RULE]]],
    ];
}

function sectionTitleStyle(): array {
    return [
        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => CLR_BRAND_DARK]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CLR_SECTION_BG]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => [
            'left'   => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => CLR_BRAND_LIGHT]],
            'bottom' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => CLR_RULE]],
        ],
    ];
}

function applyNumberFmt($sheet, string $cell, string $code = '#,##0.00'): void {
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($code);
}

/**
 * Draw a branded report header banner spanning cols A–lastCol.
 * Returns the next available row number.
 */
function drawReportHeader($sheet, string $title, string $subtitle, string $lastCol, int $startRow = 1): int {
    // Company / logo bar
    $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
    $sheet->setCellValue("A{$startRow}", '');
    $sheet->getRowDimension($startRow)->setRowHeight(8);
    $sheet->getStyle("A{$startRow}:{$lastCol}{$startRow}")
          ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(CLR_BRAND_DARK);

    $row = $startRow + 1;

    // Report title row
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->setCellValue("A{$row}", mb_strtoupper($title));
    $sheet->getRowDimension($row)->setRowHeight(34);
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => CLR_HEADER_TEXT]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CLR_BRAND_MID]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 2],
    ]);
    $row++;

    // Subtitle / period row
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->setCellValue("A{$row}", $subtitle);
    $sheet->getRowDimension($row)->setRowHeight(18);
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'name' => 'Arial', 'color' => ['rgb' => 'D6EAF8']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CLR_BRAND_LIGHT]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 2],
    ]);
    $row++;

    // Thin accent line
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->getRowDimension($row)->setRowHeight(4);
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")
          ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(CLR_ACCENT);
    $row++;

    // Breathing room
    $sheet->getRowDimension($row)->setRowHeight(10);
    $row++;

    return $row;
}

// ─── Entry point ───────────────────────────────────────────────────────────────
try {
    if ($action === 'export_saved') {
        $report_id = $_GET['id']   ?? null;
        $saved_type = $_GET['type'] ?? 'supplier';
        if (!$report_id) throw new Exception('Missing report ID');
        exportSavedReport($pdo, $tenant_id, $branch_id, $report_id, $saved_type, $format);
    } elseif ($report_type === 'supplier') {
        exportSupplierReport($pdo, $tenant_id, $branch_id, $format);
    } elseif ($report_type === 'general') {
        exportGeneralReport($pdo, $tenant_id, $branch_id, $format);
    } else {
        throw new Exception('Missing required parameters');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}

// ─── exportSavedReport ─────────────────────────────────────────────────────────
function exportSavedReport($pdo, $tenant_id, $branch_id, $report_id, $report_type, $format) {
    $stmt = $pdo->prepare(
        "SELECT id, supplier_id, quarter, year, report_type, report_data, created_at
         FROM tax_reports
         WHERE id = ? AND tenant_id = ? AND branch_id = ?"
    );
    $stmt->execute([$report_id, $tenant_id, $branch_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) throw new Exception('Report not found or access denied');

    $reportData = json_decode($report['report_data'], true);

    if ($report_type === 'supplier') {
        $reconstructedData = [
            'suppliers'    => [['name' => $reportData['supplier_name'] ?? 'Unknown', 'id' => $report['supplier_id'], 'data' => $reportData]],
            'quarter'      => $report['quarter'],
            'year'         => $report['year'],
            'quarterStart' => $reportData['quarterStart'] ?? null,
            'quarterEnd'   => $reportData['quarterEnd']   ?? null,
            'exchangeRate' => $reportData['exchange_rate'] ?? 1,
            'reportType'   => 'ticket',
        ];
        exportSupplierReport($pdo, $tenant_id, $branch_id, $format, $reconstructedData);
    } elseif ($report_type === 'general') {
        $reconstructedData = [
            'suppliers'    => $reportData['suppliers'] ?? [],
            'expenses'     => $reportData['expenses']  ?? [],
            'quarter'      => $report['quarter'],
            'year'         => $report['year'],
            'quarterStart' => $reportData['quarterStart'] ?? null,
            'quarterEnd'   => $reportData['quarterEnd']   ?? null,
            'exchangeRate' => 1,
        ];
        exportGeneralReport($pdo, $tenant_id, $branch_id, $format, $reconstructedData);
    }
}

// ─── exportSupplierReport ──────────────────────────────────────────────────────
function exportSupplierReport($pdo, $tenant_id, $branch_id, $format, $data = null) {
    if ($data === null) $data = json_decode(file_get_contents('php://input'), true);

    $suppliers    = $data['suppliers']    ?? [];
    $quarter      = $data['quarter']      ?? null;
    $year         = $data['year']         ?? null;
    $date_from    = $data['quarterStart'] ?? null;
    $date_to      = $data['quarterEnd']   ?? null;
    $exchangeRate = $data['exchangeRate'] ?? 1;
    $reportType   = $data['reportType']   ?? 'ticket';

    if (empty($suppliers) || !$quarter || !$year) throw new Exception('Missing required parameters');

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('TaxReport System')
        ->setTitle("Supplier Tax Report – {$quarter} {$year}")
        ->setSubject('Tax Report');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Supplier Tax Report');

    // Column widths
    $colWidths = ['A' => 14, 'B' => 28, 'C' => 28, 'D' => 15, 'E' => 13, 'F' => 13, 'G' => 14, 'H' => 14, 'I' => 15];
    foreach ($colWidths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);

    // Freeze pane after header area (will adjust after we know row)
    $grandTotalProfit = 0;
    $lastCol = 'I';

    $period = "Period: {$quarter} {$year}  |  " . ($date_from ? "{$date_from} → {$date_to}" : 'All Dates') . "  |  Exchange Rate: {$exchangeRate}";
    $row = drawReportHeader($sheet, "Supplier Tax Report", $period, $lastCol);

    $headers = ['Issue Date', 'Passenger Name', 'Sector / Route', 'Type', 'PNR / Ref', 'Status', 'Base (USD)', 'Sold (USD)', 'Profit (USD)'];
    $headerCols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    $numericCols = ['G', 'H', 'I'];

    $supplierIndex = 0;
    foreach ($suppliers as $supplier) {
        $supplierName = $supplier['name'] ?? 'Unknown';
        $supplierId   = (int)$supplier['id'];

        $tickets = fetchTicketsByTypeForExport($pdo, $tenant_id, $branch_id, $supplierId, $reportType, $date_from, $date_to);
        usort($tickets, fn($a, $b) => strtotime($b['issue_date']) - strtotime($a['issue_date']));

        // ── Supplier section title ──────────────────────────────────────────
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", "  ▸  {$supplierName}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $sheet->getStyle("A{$row}")->applyFromArray(sectionTitleStyle());
        $row++;

        // ── Column headers ─────────────────────────────────────────────────
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach ($headers as $i => $h) {
            $col = $headerCols[$i];
            $sheet->setCellValue("{$col}{$row}", $h);
            $sheet->getStyle("{$col}{$row}")->applyFromArray(colStyle(CLR_BRAND_MID));
        }
        $row++;

        // ── Data rows ──────────────────────────────────────────────────────
        $supplierTotalProfit = 0;
        $dataStartRow = $row;

        foreach ($tickets as $idx => $ticket) {
            $profit    = (float)($ticket['profit'] ?? 0);
            $alt       = ($idx % 2 === 1);
            $baseStyle = dataStyle($alt);
            $rightStyle = dataStyle($alt, true);

            $typeLabels = [
                'ticket'              => 'Ticket',
                'ticket_refund'       => 'Refund',
                'ticket_date_change'  => 'Date Change',
                'visa'                => 'Visa',
                'umrah'               => 'Umrah',
                'hotel'               => 'Hotel',
            ];
            $typeLabel = $typeLabels[$ticket['ticket_type'] ?? 'ticket'] ?? 'Ticket';

            $values = [
                'A' => $ticket['issue_date'] ?? '',
                'B' => $ticket['full_name']   ?? '',
                'C' => $ticket['sector']       ?? '',
                'D' => $typeLabel,
                'E' => $ticket['pnr']          ?? '',
                'F' => $ticket['status']       ?? '',
                'G' => (float)($ticket['base_price'] ?? 0),
                'H' => (float)($ticket['sold_price'] ?? 0),
                'I' => $profit,
            ];

            $sheet->getRowDimension($row)->setRowHeight(16);
            foreach ($values as $col => $val) {
                $sheet->setCellValue("{$col}{$row}", $val);
                $sheet->getStyle("{$col}{$row}")->applyFromArray(in_array($col, $numericCols) ? $rightStyle : $baseStyle);
            }
            foreach ($numericCols as $nc) applyNumberFmt($sheet, "{$nc}{$row}");

            // Colour-code status
            $status = strtolower($ticket['status'] ?? '');
            if (in_array($status, ['cancelled', 'refunded'])) {
                $sheet->getStyle("F{$row}")->getFont()->setColor(new Color(CLR_DANGER));
            } elseif ($status === 'active') {
                $sheet->getStyle("F{$row}")->getFont()->setColor(new Color(CLR_SUCCESS));
            }

            $supplierTotalProfit += $profit;
            $grandTotalProfit    += $profit;
            $row++;
        }

        // ── Supplier summary block ─────────────────────────────────────────
        $supplierExchanged = $supplierTotalProfit * $exchangeRate;
        $supplierTax       = $supplierExchanged * 0.04;

        $summaryRows = [
            ['label' => "Subtotal (USD)",                   'value' => number_format($supplierTotalProfit, 2) . ' USD', 'bg' => CLR_BRAND_LIGHT],
            ['label' => "× Exchange Rate {$exchangeRate}",  'value' => number_format($supplierExchanged, 2)  . ' AFN', 'bg' => 'F39C12'],
            ['label' => "Tax @ 4%",                         'value' => number_format($supplierTax, 2)         . ' AFN', 'bg' => CLR_DANGER],
        ];

        foreach ($summaryRows as $sr) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", '    ' . $sr['label']);
            $sheet->setCellValue("I{$row}", $sr['value']);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $style = totalStyle($sr['bg']);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($style);
            $sheet->getStyle("I{$row}")->applyFromArray(array_merge($style, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]));
            $row++;
        }

        // Spacer
        $sheet->getRowDimension($row)->setRowHeight(14);
        $row++;
        $supplierIndex++;
    }

    // ── Grand total block ──────────────────────────────────────────────────
    $grandExchanged = $grandTotalProfit * $exchangeRate;
    $grandTax       = $grandExchanged * 0.04;

    $sheet->getRowDimension($row)->setRowHeight(6);
    $row++;

    // Divider line
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->getRowDimension($row)->setRowHeight(3);
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(CLR_ACCENT);
    $row++;

    $grandRows = [
        ['label' => 'GRAND TOTAL  (USD)',          'value' => number_format($grandTotalProfit, 2)  . ' USD', 'bg' => CLR_BRAND_DARK,  'size' => 11],
        ['label' => 'GRAND TOTAL EXCHANGED  (AFN)', 'value' => number_format($grandExchanged, 2)   . ' AFN', 'bg' => CLR_BRAND_MID,   'size' => 11],
        ['label' => 'GRAND TOTAL TAX @ 4%  (AFN)',  'value' => number_format($grandTax, 2)          . ' AFN', 'bg' => CLR_DANGER,      'size' => 11],
    ];

    foreach ($grandRows as $gr) {
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", '  ' . $gr['label']);
        $sheet->setCellValue("I{$row}", $gr['value']);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $s = totalStyle($gr['bg'], CLR_HEADER_TEXT, $gr['size']);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($s);
        $sheet->getStyle("I{$row}")->applyFromArray(array_merge($s, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]));
        $row++;
    }

    // Footer
    $row += 2;
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->setCellValue("A{$row}", 'Generated on ' . date('d M Y, H:i') . '  —  Confidential');
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => CLR_MUTED], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    outputFile($spreadsheet, $format, "supplier_tax_report_{$quarter}_{$year}");
}

// ─── exportGeneralReport ──────────────────────────────────────────────────────
function exportGeneralReport($pdo, $tenant_id, $branch_id, $format, $data = null) {
    if ($data === null) $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;

    $quarter      = $data['quarter']      ?? null;
    $year         = $data['year']         ?? null;
    $expenses     = $data['expenses']     ?? [];
    $suppliers    = $data['suppliers']    ?? [];
    $exchangeRate = (float)($data['exchangeRate'] ?? 1);

    if (!$quarter || !$year) throw new Exception('Missing required parameters');
    if (empty($expenses) && empty($suppliers)) throw new Exception('No data to export');

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setTitle("General Tax Report – {$quarter} {$year}");

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('General Tax Report');

    $colWidths = ['A' => 32, 'B' => 18, 'C' => 15, 'D' => 20, 'E' => 20];
    foreach ($colWidths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

    $lastCol = 'E';
    $period  = "Period: {$quarter} {$year}";
    $row = drawReportHeader($sheet, "General Tax Report", $period, $lastCol);

    // ── SUPPLIER INCOME SECTION ────────────────────────────────────────────
    if (!empty($suppliers)) {
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", '  ▸  Supplier Income & Tax');
        $sheet->getStyle("A{$row}")->applyFromArray(sectionTitleStyle());
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        $supplierHeaders = ['Supplier Name', 'Income (USD)', 'Exch. Rate', 'Income (AFN)', 'Tax @ 4% (AFN)'];
        $supplierHCols   = ['A', 'B', 'C', 'D', 'E'];
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach ($supplierHeaders as $i => $h) {
            $col = $supplierHCols[$i];
            $sheet->setCellValue("{$col}{$row}", $h);
            $sheet->getStyle("{$col}{$row}")->applyFromArray(colStyle(CLR_BRAND_MID));
        }
        $row++;

        $totalIncomeAFN = 0;
        $totalTaxAFN    = 0;

        foreach ($suppliers as $idx => $supplier) {
            $reportData = $supplier['data'] ?? [];
            if (empty($reportData['data'])) continue;

            $supplierName = $reportData['supplier_name'] ?? 'Unknown';
            $profit = array_sum(array_column(array_column($reportData['data'], 'details'), 'profit'));
            $rExRate  = (float)($reportData['exchange_rate'] ?? $exchangeRate);
            $exchanged = $profit * $rExRate;
            $tax       = $exchanged * 0.04;

            $alt = ($idx % 2 === 1);
            $baseStyle  = dataStyle($alt);
            $rightStyle = dataStyle($alt, true);

            $sheet->getRowDimension($row)->setRowHeight(16);
            $sheet->setCellValue("A{$row}", $supplierName);   $sheet->getStyle("A{$row}")->applyFromArray($baseStyle);
            $sheet->setCellValue("B{$row}", $profit);          $sheet->getStyle("B{$row}")->applyFromArray($rightStyle); applyNumberFmt($sheet, "B{$row}");
            $sheet->setCellValue("C{$row}", $rExRate);         $sheet->getStyle("C{$row}")->applyFromArray($rightStyle); applyNumberFmt($sheet, "C{$row}", '#,##0.0000');
            $sheet->setCellValue("D{$row}", $exchanged);       $sheet->getStyle("D{$row}")->applyFromArray($rightStyle); applyNumberFmt($sheet, "D{$row}");
            $sheet->setCellValue("E{$row}", $tax);             $sheet->getStyle("E{$row}")->applyFromArray($rightStyle); applyNumberFmt($sheet, "E{$row}");

            $totalIncomeAFN += $exchanged;
            $totalTaxAFN    += $tax;
            $row++;
        }

        // Supplier totals
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", '  Supplier Total');
        $sheet->setCellValue("D{$row}", $totalIncomeAFN);
        $sheet->setCellValue("E{$row}", $totalTaxAFN);
        $sheet->getRowDimension($row)->setRowHeight(18);
        foreach (['A', 'D', 'E'] as $c) $sheet->getStyle("{$c}{$row}")->applyFromArray(totalStyle(CLR_BRAND_LIGHT));
        $sheet->getStyle("B{$row}")->applyFromArray(totalStyle(CLR_BRAND_LIGHT));
        $sheet->getStyle("C{$row}")->applyFromArray(totalStyle(CLR_BRAND_LIGHT));
        foreach (['D', 'E'] as $c) applyNumberFmt($sheet, "{$c}{$row}");
        $row += 2;
    }

    // ── EXPENSES SECTION ───────────────────────────────────────────────────
    $totalExpenseAFN = 0;

    if (!empty($expenses)) {
        // Determine date range
        $dateFrom = $data['quarterStart'] ?? null;
        $dateTo   = $data['quarterEnd']   ?? null;
        if (!$dateFrom || !$dateTo) {
            $quarters = ['Q1' => ['01-01','03-31'], 'Q2' => ['04-01','06-30'], 'Q3' => ['07-01','09-30'], 'Q4' => ['10-01','12-31']];
            if (isset($quarters[$quarter])) {
                [$start, $end] = $quarters[$quarter];
                $dateFrom = "{$year}-{$start}";
                $dateTo   = "{$year}-{$end}";
            }
        }

        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", '  ▸  Expenses');
        $sheet->getStyle("A{$row}")->applyFromArray(sectionTitleStyle());
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        foreach ($expenses as $expense) {
            $category     = $expense['category'] ?? '';
            $categoryItems = $expense['items']   ?? [];
            $categoryAmt  = (float)($expense['amount'] ?? 0);
            if ($categoryAmt <= 0) continue;

            // Category sub-header
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", "    {$category}");
            $sheet->getRowDimension($row)->setRowHeight(18);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => CLR_BRAND_MID]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF5FB']],
                'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => CLR_BRAND_LIGHT]]],
            ]);
            $row++;

            // Item column headers
            $expHeaders = ['Date', 'Description', 'Amount (AFN)', '', ''];
            $expCols    = ['A', 'B', 'C', 'D', 'E'];
            $sheet->getRowDimension($row)->setRowHeight(16);
            foreach ($expHeaders as $i => $h) {
                $c = $expCols[$i];
                $sheet->setCellValue("{$c}{$row}", $h);
                $sheet->getStyle("{$c}{$row}")->applyFromArray(colStyle('95A5A6'));
            }
            $row++;

            $categoryTotal = 0;

            if (!empty($categoryItems)) {
                foreach ($categoryItems as $idx => $item) {
                    $amt   = (float)($item['total_amount'] ?? 0);
                    $iDate = $item['date'] ?? $item['expense_date'] ?? '';
                    $desc  = $item['category'] ?? $category;
                    $alt   = ($idx % 2 === 1);

                    $sheet->getRowDimension($row)->setRowHeight(15);
                    $sheet->setCellValue("A{$row}", $iDate);  $sheet->getStyle("A{$row}")->applyFromArray(dataStyle($alt));
                    $sheet->setCellValue("B{$row}", $desc);   $sheet->getStyle("B{$row}")->applyFromArray(dataStyle($alt));
                    $sheet->setCellValue("C{$row}", $amt);    $sheet->getStyle("C{$row}")->applyFromArray(dataStyle($alt, true)); applyNumberFmt($sheet, "C{$row}");
                    foreach (['D', 'E'] as $c) { $sheet->setCellValue("{$c}{$row}", ''); $sheet->getStyle("{$c}{$row}")->applyFromArray(dataStyle($alt)); }

                    $categoryTotal += $amt;
                    $row++;
                }
            } else {
                $sheet->getRowDimension($row)->setRowHeight(15);
                $sheet->setCellValue("A{$row}", '');
                $sheet->setCellValue("B{$row}", "Total for {$category}");
                $sheet->setCellValue("C{$row}", $categoryAmt);
                $sheet->getStyle("A{$row}")->applyFromArray(dataStyle());
                $sheet->getStyle("B{$row}")->applyFromArray(dataStyle());
                $sheet->getStyle("C{$row}")->applyFromArray(dataStyle(false, true));
                applyNumberFmt($sheet, "C{$row}");
                foreach (['D', 'E'] as $c) { $sheet->setCellValue("{$c}{$row}", ''); $sheet->getStyle("{$c}{$row}")->applyFromArray(dataStyle()); }
                $categoryTotal = $categoryAmt;
                $row++;
            }

            // Category sub-total
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", "    Subtotal – {$category}");
            $sheet->setCellValue("C{$row}", $categoryTotal);
            $sheet->getRowDimension($row)->setRowHeight(17);
            $subStyle = totalStyle('D5D8DC', CLR_BODY_TEXT, 9);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($subStyle);
            $sheet->getStyle("C{$row}")->applyFromArray(array_merge($subStyle, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]));
            foreach (['D', 'E'] as $c) $sheet->getStyle("{$c}{$row}")->applyFromArray($subStyle);
            applyNumberFmt($sheet, "C{$row}");

            $totalExpenseAFN += $categoryTotal;
            $row += 2;
        }

        // Total expenses row
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", '  TOTAL EXPENSES');
        $sheet->setCellValue("C{$row}", $totalExpenseAFN);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $ts = totalStyle(CLR_BRAND_DARK, CLR_HEADER_TEXT, 11);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($ts);
        $sheet->getStyle("C{$row}")->applyFromArray(array_merge($ts, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]));
        foreach (['D', 'E'] as $c) $sheet->getStyle("{$c}{$row}")->applyFromArray($ts);
        applyNumberFmt($sheet, "C{$row}");
        $row += 3;
    }

    // ── FINANCIAL SUMMARY SECTION ──────────────────────────────────────────
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->setCellValue("A{$row}", '  ▸  Financial Summary');
    $sheet->getStyle("A{$row}")->applyFromArray(sectionTitleStyle());
    $sheet->getRowDimension($row)->setRowHeight(22);
    $row++;

    // Summary table header
    $sheet->getRowDimension($row)->setRowHeight(18);
    foreach (['A' => 'Description', 'B' => 'Amount (AFN)'] as $col => $h) {
        $sheet->setCellValue("{$col}{$row}", $h);
        $sheet->getStyle("{$col}{$row}")->applyFromArray(colStyle(CLR_BRAND_MID));
    }
    foreach (['C', 'D', 'E'] as $c) $sheet->getStyle("{$c}{$row}")->applyFromArray(colStyle(CLR_BRAND_MID));
    $row++;

    // Recalculate total income from suppliers
    $totalIncomeAFN = 0;
    foreach ($suppliers as $supplier) {
        $rd = $supplier['data'] ?? [];
        if (!empty($rd['data'])) {
            $profit = array_sum(array_column(array_column($rd['data'], 'details'), 'profit'));
            $totalIncomeAFN += $profit * (float)($rd['exchange_rate'] ?? 1);
        }
    }

    $netProfit = $totalIncomeAFN - $totalExpenseAFN;

    $summaryRows = [
        ['desc' => 'Total Income (AFN)',   'val' => $totalIncomeAFN,  'bg' => 'EBF5FB', 'text' => CLR_BODY_TEXT],
        ['desc' => 'Total Expenses (AFN)', 'val' => $totalExpenseAFN, 'bg' => 'FDEDEC', 'text' => CLR_BODY_TEXT],
        ['desc' => 'Net Profit (AFN)',     'val' => $netProfit,       'bg' => ($netProfit >= 0 ? '1E8449' : CLR_DANGER), 'text' => CLR_HEADER_TEXT],
    ];

    foreach ($summaryRows as $sr) {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", '    ' . $sr['desc']);
        $sheet->mergeCells("B{$row}:B{$row}"); // will be overwritten
        // Put value in B through merged approach: place in A then value cell
        $sheet->getStyle("A{$row}")->applyFromArray(totalStyle($sr['bg'], $sr['text'], 10));
        // Unmerge and use B for value
        $sheet->unmergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("B{$row}", $sr['val']);
        $sheet->getStyle("A{$row}")->applyFromArray(totalStyle($sr['bg'], $sr['text'], 10));
        $sheet->getStyle("B{$row}")->applyFromArray(array_merge(totalStyle($sr['bg'], $sr['text'], 10), ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]));
        applyNumberFmt($sheet, "B{$row}");
        foreach (['C', 'D', 'E'] as $c) $sheet->getStyle("{$c}{$row}")->applyFromArray(totalStyle($sr['bg'], $sr['text']));
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    // Footer
    $row += 2;
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->setCellValue("A{$row}", 'Generated on ' . date('d M Y, H:i') . '  —  Confidential');
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => CLR_MUTED], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    outputFile($spreadsheet, $format, "general_tax_report_{$quarter}_{$year}");
}

// ─── outputFile ───────────────────────────────────────────────────────────────
function outputFile(Spreadsheet $spreadsheet, string $format, string $basename): void {
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment;filename=\"{$basename}.pdf\"");
        $writer = new Dompdf($spreadsheet);
        $writer->save('php://output');
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$basename}.xlsx\"");
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}

// ─── fetchTicketsByTypeForExport ──────────────────────────────────────────────
function fetchTicketsByTypeForExport($pdo, $tenant_id, $branch_id, $supplier_id, $report_type, $from_date, $to_date) {
    $tickets = [];
    $dateClause = ($from_date && $to_date) ? " AND DATE(%s) BETWEEN ? AND ?" : '';

    if ($report_type === 'ticket' || $report_type === 'all') {
        // Regular bookings
        $q = "SELECT id, issue_date,
                     CONCAT(title, ' ', passenger_name) AS full_name,
                     CONCAT(origin, ' - ', destination,
                            IF(trip_type='round_trip',' - ',''),
                            IF(trip_type='round_trip', return_origin,'')) AS sector,
                     status, pnr,
                     price AS base_price, sold AS sold_price, profit,
                     description, 'ticket' AS ticket_type
              FROM ticket_bookings
              WHERE tenant_id=? AND branch_id=? AND supplier=?"
              . ($from_date ? " AND DATE(issue_date) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Refunds
        $q = "SELECT rt.id, rt.issue_date,
                     CONCAT(rt.title,' ',rt.passenger_name) AS full_name,
                     CONCAT(rt.origin,' - ',rt.destination) AS sector,
                     rt.status, rt.pnr,
                     rt.base AS base_price, rt.sold AS sold_price,
                     0 AS profit, rt.remarks AS description,
                     'ticket_refund' AS ticket_type
              FROM refunded_tickets rt
              WHERE rt.tenant_id=? AND rt.branch_id=? AND rt.supplier=?"
              . ($from_date ? " AND DATE(rt.created_at) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Date changes
        $q = "SELECT dc.id, dc.issue_date,
                     CONCAT(dc.title,' ',dc.passenger_name) AS full_name,
                     CONCAT(dc.origin,' - ',dc.destination) AS sector,
                     dc.status, dc.pnr,
                     COALESCE(dc.supplier_penalty,0) AS base_price,
                     (COALESCE(dc.supplier_penalty,0)+COALESCE(dc.service_penalty,0)) AS sold_price,
                     COALESCE(dc.service_penalty,0) AS profit,
                     dc.remarks AS description,
                     'ticket_date_change' AS ticket_type
              FROM date_change_tickets dc
              WHERE dc.tenant_id=? AND dc.branch_id=? AND dc.supplier=?"
              . ($from_date ? " AND DATE(dc.created_at) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($report_type === 'visa' || $report_type === 'all') {
        $q = "SELECT id, receive_date AS issue_date,
                     applicant_name AS full_name,
                     CONCAT(country,' - ',visa_type) AS sector,
                     status, passport_number AS pnr,
                     base AS base_price, sold AS sold_price, profit,
                     remarks AS description, 'visa' AS ticket_type
              FROM visa_applications
              WHERE tenant_id=? AND branch_id=? AND supplier=?"
              . ($from_date ? " AND DATE(receive_date) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($report_type === 'umrah' || $report_type === 'all') {
        $q = "SELECT ub.booking_id AS id, ub.entry_date AS issue_date,
                     ub.name AS full_name, ub.duration AS sector,
                     ub.status, ub.passport_number AS pnr,
                     ubs.base_price, ubs.sold_price, ubs.profit,
                     ub.remarks AS description, 'umrah' AS ticket_type
              FROM umrah_bookings ub
              JOIN umrah_booking_services ubs ON ub.booking_id=ubs.booking_id
              WHERE ub.tenant_id=? AND ub.branch_id=? AND ubs.supplier_id=?
                AND ub.status IN ('active','pending')"
              . ($from_date ? " AND DATE(ub.entry_date) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($report_type === 'hotel' || $report_type === 'all') {
        $q = "SELECT id, issue_date,
                     CONCAT(first_name,' ',last_name) AS full_name,
                     accommodation_details AS sector,
                     status, order_id AS pnr,
                     base_amount AS base_price, sold_amount AS sold_price, profit,
                     remarks AS description, 'hotel' AS ticket_type
              FROM hotel_bookings
              WHERE tenant_id=? AND branch_id=? AND supplier_id=? AND status='active'"
              . ($from_date ? " AND DATE(issue_date) BETWEEN ? AND ?" : '');
        $p = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date) array_push($p, $from_date, $to_date);
        $stmt = $pdo->prepare($q); $stmt->execute($p);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    return $tickets;
}
?>