<?php
session_start();

// Check authentication and authorization
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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$action = $_GET['action'] ?? null;
$format = $_GET['format'] ?? 'xlsx'; // xlsx or pdf
$report_type = $_GET['report_type'] ?? 'supplier'; // supplier or general

try {
    if ($action === 'export_saved') {
        // Export saved report from database
        $report_id = $_GET['id'] ?? null;
        $saved_type = $_GET['type'] ?? 'supplier';
        
        if (!$report_id) {
            throw new Exception('Missing report ID');
        }
        
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

/**
 * Export a saved report from the database
 */
function exportSavedReport($pdo, $tenant_id, $branch_id, $report_id, $report_type, $format) {
    try {
        // Fetch the saved report from database
        $query = "SELECT id, supplier_id, quarter, year, report_type, report_data, created_at 
                  FROM tax_reports 
                  WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$report_id, $tenant_id, $branch_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new Exception('Report not found or access denied');
        }

        $reportData = json_decode($report['report_data'], true);
        
        if ($report_type === 'supplier') {
            // For supplier reports, the data is already in the correct structure
            $reconstructedData = [
                'suppliers' => [
                    [
                        'name' => $reportData['supplier_name'] ?? 'Unknown',
                        'id' => $report['supplier_id'],
                        'data' => $reportData
                    ]
                ],
                'quarter' => $report['quarter'],
                'year' => $report['year'],
                'quarterStart' => $reportData['quarterStart'] ?? null,
                'quarterEnd' => $reportData['quarterEnd'] ?? null,
                'exchangeRate' => $reportData['exchange_rate'] ?? 1,
                'reportType' => 'ticket'
            ];
            
            exportSupplierReport($pdo, $tenant_id, $branch_id, $format, $reconstructedData);
        } elseif ($report_type === 'general') {
            // For general reports, use the saved data directly
            $reconstructedData = [
                'suppliers' => $reportData['suppliers'] ?? [],
                'expenses' => $reportData['expenses'] ?? [],
                'quarter' => $report['quarter'],
                'year' => $report['year'],
                'quarterStart' => $reportData['quarterStart'] ?? null,
                'quarterEnd' => $reportData['quarterEnd'] ?? null,
                'exchangeRate' => 1
            ];
            
            exportGeneralReport($pdo, $tenant_id, $branch_id, $format, $reconstructedData);
        }
    } catch (Exception $e) {
        http_response_code(500);
        throw $e;
    }
}

function exportSupplierReport($pdo, $tenant_id, $branch_id, $format, $data = null) {
    // Get data from POST (JSON payload) or use passed data - same payload structure as generateSupplierReport
    if ($data === null) {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    $suppliers = $data['suppliers'] ?? [];
    $quarter = $data['quarter'] ?? null;
    $year = $data['year'] ?? null;
    $date_from = $data['quarterStart'] ?? null;
    $date_to = $data['quarterEnd'] ?? null;
    $exchangeRate = $data['exchangeRate'] ?? 1;
    $reportType = $data['reportType'] ?? 'ticket';  // ticket, visa, umrah, hotel, all

    if (empty($suppliers) || !$quarter || !$year) {
        throw new Exception('Missing required parameters');
    }

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Tax Report');

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(12);
    $sheet->getColumnDimension('I')->setWidth(15);

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4099FF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];

    $row = 1;
    $grandTotalProfit = 0;

    // Process each supplier
    foreach ($suppliers as $supplier) {
        $supplierName = $supplier['name'] ?? 'Unknown';
        $supplierId = (int)$supplier['id'];

        // Fetch tickets based on report type
        $tickets = fetchTicketsByTypeForExport($pdo, $tenant_id, $branch_id, $supplierId, $reportType, $date_from, $date_to);
        
        // Sort by issue_date descending
        usort($tickets, function($a, $b) {
            return strtotime($b['issue_date']) - strtotime($a['issue_date']);
        });

        // Title
        $sheet->setCellValue('A' . $row, "Tax Report - $supplierName");
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $row++;

        // Period info
        $sheet->setCellValue('A' . $row, "Period: $quarter $year ($date_from to $date_to)");
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $row += 2;

        // Headers
        $headers = ['Issue Date', 'Passenger Name', 'Sector', 'Type', 'PNR', 'Status', 'Base Price', 'Sold Price', 'Profit (USD)'];
        
        foreach ($headers as $col => $header) {
            $colLetter = chr(65 + $col);
            $sheet->setCellValue($colLetter . $row, $header);
            $sheet->getStyle($colLetter . $row)->applyFromArray($headerStyle);
        }
        $row++;

        // Data rows from database
        $supplierTotalProfit = 0;
        
        foreach ($tickets as $ticket) {
            $profit = (float)($ticket['profit'] ?? 0);
            $ticketType = $ticket['ticket_type'] ?? 'ticket';
            
            // Map ticket type for display
            $typeLabel = 'Ticket';
            if ($ticketType === 'ticket_refund') {
                $typeLabel = 'Ticket Refund';
            } elseif ($ticketType === 'ticket_date_change') {
                $typeLabel = 'Date Change';
            } elseif ($ticketType === 'visa') {
                $typeLabel = 'Visa';
            } elseif ($ticketType === 'umrah') {
                $typeLabel = 'Umrah';
            } elseif ($ticketType === 'hotel') {
                $typeLabel = 'Hotel';
            }
            
            $sheet->setCellValue('A' . $row, $ticket['issue_date'] ?? '');
            $sheet->setCellValue('B' . $row, $ticket['full_name'] ?? '');
            $sheet->setCellValue('C' . $row, $ticket['sector'] ?? '');
            $sheet->setCellValue('D' . $row, $typeLabel);
            $sheet->setCellValue('E' . $row, $ticket['pnr'] ?? '');
            $sheet->setCellValue('F' . $row, $ticket['status'] ?? '');
            $sheet->setCellValue('G' . $row, (float)($ticket['base_price'] ?? 0));
            $sheet->setCellValue('H' . $row, (float)($ticket['sold_price'] ?? 0));
            $sheet->setCellValue('I' . $row, $profit);

            // Number formatting
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            $supplierTotalProfit += $profit;
            $grandTotalProfit += $profit;
            $row++;
        }
        
        // Calculate exchanged amount and tax on total
        $supplierTotalExchanged = $supplierTotalProfit * $exchangeRate;
        $supplierTotalTax = $supplierTotalExchanged * 0.04;

        // Total row for this supplier (USD)
        $row++;
        $sheet->setCellValue('A' . $row, 'TOTAL (USD)');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('I' . $row, $supplierTotalProfit);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle('I' . $row)->getFont()->setBold(true);
        $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $row++;
        
        // Exchange to AFN row
        $sheet->setCellValue('A' . $row, 'EXCHANGE TO AFN (@ ' . $exchangeRate . ')');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('I' . $row, $supplierTotalExchanged . ' AFN');
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF99');
        $sheet->getStyle('I' . $row)->getFont()->setBold(true);
        $row++;
        
        // Tax (4%) row
        $sheet->setCellValue('A' . $row, 'TAX (4% OF EXCHANGED AMOUNT)');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('I' . $row, $supplierTotalTax . ' AFN');
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFB6C6');
        $sheet->getStyle('I' . $row)->getFont()->setBold(true);
        $row += 3;
    }

    // Grand total rows
    $grandTotalExchanged = $grandTotalProfit * $exchangeRate;
    $grandTotalTax = $grandTotalExchanged * 0.04;
    
    $row++;
    $sheet->setCellValue('A' . $row, 'GRAND TOTAL (USD)');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $sheet->setCellValue('I' . $row, $grandTotalProfit);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('808080');
    $sheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle('I' . $row)->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row++;
    
    $sheet->setCellValue('A' . $row, 'GRAND TOTAL EXCHANGED (AFN)');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $sheet->setCellValue('I' . $row, $grandTotalExchanged . ' AFN');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('808080');
    $sheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle('I' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'GRAND TOTAL TAX (AFN)');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $sheet->setCellValue('I' . $row, $grandTotalTax . ' AFN');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('808080');
    $sheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle('I' . $row)->getFont()->setBold(true)->setSize(12);

    // Output
    if ($format === 'pdf') {
        $filename = "supplier_tax_report_{$quarter}_{$year}.pdf";
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        
        $writer = new Dompdf($spreadsheet);
        $writer->save('php://output');
    } else {
        $filename = "supplier_tax_report_{$quarter}_{$year}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}

function exportGeneralReport($pdo, $tenant_id, $branch_id, $format, $data = null) {
    // Get data from POST (JSON payload) or use passed data
    if ($data === null) {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;
    }
    
    $quarter = $data['quarter'] ?? null;
    $year = $data['year'] ?? null;
    $expenses = $data['expenses'] ?? [];
    $suppliers = $data['suppliers'] ?? [];
    $exchangeRate = (float)($data['exchangeRate'] ?? 1);

    if (!$quarter || !$year) {
        throw new Exception('Missing required parameters');
    }

    if (empty($expenses) && empty($suppliers)) {
        throw new Exception('No data to export');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('General Report');

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(20);

    // Title
    $sheet->setCellValue('A1', "General Tax Report");
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->mergeCells('A1:E1');

    // Period info
    $sheet->setCellValue('A2', "Period: $quarter $year");
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->mergeCells('A2:E2');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4099FF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];

    $row = 4;

    // SUPPLIER INCOME & TAX SECTION
    if (!empty($suppliers)) {
        $sheet->setCellValue('A' . $row, 'SUPPLIER INCOME & TAX');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $row++;

        // Supplier headers
        $supplierHeaders = ['Supplier Name', 'Income (USD)', 'Exchange Rate', 'Income (AFN)', 'Tax (4%)'];
        $col = 65; // ASCII 'A'
        foreach ($supplierHeaders as $header) {
            $sheet->setCellValue(chr($col) . $row, $header);
            $sheet->getStyle(chr($col) . $row)->applyFromArray($headerStyle);
            $col++;
        }
        $row++;

        $totalIncome = 0;
        $totalTax = 0;

        foreach ($suppliers as $supplier) {
             $reportData = $supplier['data'] ?? [];
             if (!empty($reportData['data'])) {
                 $supplierName = $reportData['supplier_name'] ?? 'Unknown';
                 
                 // Calculate profit from report items
                 $profit = 0;
                 foreach ($reportData['data'] as $item) {
                     $profit += (float)($item['details']['profit'] ?? 0);
                 }

                 // Use the exchange rate stored in the report, fallback to request exchange rate
                 $reportExchangeRate = (float)($reportData['exchange_rate'] ?? $exchangeRate);
                 $exchanged = $profit * $reportExchangeRate;
                 $tax = $exchanged * 0.04;

                 $sheet->setCellValue('A' . $row, $supplierName);
                 $sheet->setCellValue('B' . $row, $profit);
                 $sheet->setCellValue('C' . $row, $reportExchangeRate);
                 $sheet->setCellValue('D' . $row, $exchanged);
                 $sheet->setCellValue('E' . $row, $tax);

                // Format numbers
                $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

                $totalIncome += $exchanged;
                $totalTax += $tax;
                $row++;
            }
        }

        // Supplier totals
        $sheet->setCellValue('A' . $row, 'SUPPLIER TOTAL');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         $sheet->mergeCells('A' . $row . ':C' . $row);
         $sheet->setCellValue('D' . $row, $totalIncome);
         $sheet->setCellValue('E' . $row, $totalTax);
         $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
         $sheet->getStyle('D' . $row)->getFont()->setBold(true);
         $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         $sheet->getStyle('E' . $row)->getFont()->setBold(true);
         $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $row += 2;
    }

    // EXPENSES SECTION
    if (!empty($expenses)) {
        $sheet->setCellValue('A' . $row, 'EXPENSES');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $row++;

        // Determine date range for expense query
        $dateFrom = $data['quarterStart'] ?? null;
        $dateTo = $data['quarterEnd'] ?? null;
        
        if (!$dateFrom || !$dateTo) {
            // Use quarter-based dates
            $quarters = [
                'Q1' => ['01-01', '03-31'],
                'Q2' => ['04-01', '06-30'],
                'Q3' => ['07-01', '09-30'],
                'Q4' => ['10-01', '12-31']
            ];
            if (isset($quarters[$quarter])) {
                list($start, $end) = $quarters[$quarter];
                $dateFrom = "$year-$start";
                $dateTo = "$year-$end";
            }
        }

        $totalExpense = 0;
        
        // Process each selected expense category
        foreach ($expenses as $expense) {
            $category = $expense['category'] ?? '';
            $categoryAmount = (float)($expense['amount'] ?? 0);
            $categoryItems = $expense['items'] ?? [];  // Items fetched from frontend
            
            if ($categoryAmount <= 0) {
                continue;
            }

            // Category header
            $sheet->setCellValue('A' . $row, $category);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4F8');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $row++;

            // Column headers for this category
            $categoryHeaders = ['Date', 'Description', 'Amount'];
            for ($col = 0; $col < 3; $col++) {
                $sheet->setCellValue(chr(65 + $col) . $row, $categoryHeaders[$col]);
                $sheet->getStyle(chr(65 + $col) . $row)->getFont()->setBold(true);
                $sheet->getStyle(chr(65 + $col) . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
            }
            $row++;

            $categoryTotal = 0;

            // Display individual expenses from the fetched items
            if (!empty($categoryItems)) {
                foreach ($categoryItems as $item) {
                    $itemAmount = (float)($item['total_amount'] ?? 0);
                    $itemDate = $item['date'] ?? $item['expense_date'] ?? '';
                    
                    // If the item is already aggregated by category (from get_expenses), just display it
                    $sheet->setCellValue('A' . $row, $itemDate);
                    $sheet->setCellValue('B' . $row, $item['category'] ?? $category);
                    $sheet->setCellValue('C' . $row, $itemAmount);
                    
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $categoryTotal += $itemAmount;
                    $row++;
                }
            } else {
                // Fallback: if no items provided, just show the category total as one line
                $sheet->setCellValue('A' . $row, '');
                $sheet->setCellValue('B' . $row, 'Total for ' . $category);
                $sheet->setCellValue('C' . $row, $categoryAmount);
                
                $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $categoryTotal = $categoryAmount;
                $row++;
            }

            // Category total
            $sheet->setCellValue('A' . $row, 'Category Total: ' . $category);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('C' . $row, $categoryTotal);
            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
            $sheet->getStyle('C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            
            $totalExpense += $categoryTotal;
            $row += 2;
        }

        // Expense Category Summary
        $row += 2;
        $sheet->setCellValue('A' . $row, 'EXPENSE SUMMARY BY CATEGORY');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $row++;

        // Summary headers
        $summaryHeaders = ['Category', 'Total Amount'];
        for ($col = 0; $col < 2; $col++) {
            $sheet->setCellValue(chr(65 + $col) . $row, $summaryHeaders[$col]);
            $sheet->getStyle(chr(65 + $col) . $row)->getFont()->setBold(true);
            $sheet->getStyle(chr(65 + $col) . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4099FF');
            $sheet->getStyle(chr(65 + $col) . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
        }
        $row++;

        // Display category totals
        $categoryTotals = [];
        foreach ($expenses as $expense) {
            $category = $expense['category'] ?? '';
            if (!empty($category)) {
                if (!isset($categoryTotals[$category])) {
                    $categoryTotals[$category] = 0;
                }
                $categoryTotals[$category] += (float)($expense['amount'] ?? 0);
            }
        }

        $summaryTotal = 0;
         foreach ($categoryTotals as $category => $amount) {
             $sheet->setCellValue('A' . $row, $category);
             $sheet->setCellValue('B' . $row, $amount);
             $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
             $summaryTotal += $amount;
             $row++;
         }

         // Summary table total
         $sheet->setCellValue('A' . $row, 'SUMMARY TOTAL');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
         $sheet->setCellValue('B' . $row, $summaryTotal);
         $sheet->getStyle('B' . $row)->getFont()->setBold(true);
         $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
         $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         $row++;

         // Grand expense total
         $row++;
         $sheet->setCellValue('A' . $row, 'TOTAL EXPENSES');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
         $sheet->mergeCells('A' . $row . ':B' . $row);
         $sheet->setCellValue('C' . $row, '');  // Use column B for amount (merged)
         $sheet->setCellValue('B' . $row, $totalExpense);
         $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('808080');
         $sheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
         $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
         $sheet->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
         $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         }

         // INCOME AND NET PROFIT SUMMARY
         $row += 3;
         $sheet->setCellValue('A' . $row, 'FINANCIAL SUMMARY');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
         $sheet->mergeCells('A' . $row . ':B' . $row);
         $row++;

         // Summary headers
         $sheet->setCellValue('A' . $row, 'Description');
         $sheet->setCellValue('B' . $row, 'Amount (AFN)');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         $sheet->getStyle('B' . $row)->getFont()->setBold(true);
         $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4099FF');
         $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4099FF');
         $sheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
         $sheet->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
         $row++;

         // Calculate total income from suppliers
         $totalIncome = 0;
         if (!empty($suppliers)) {
         foreach ($suppliers as $supplier) {
             $reportData = $supplier['data'] ?? [];
             if (!empty($reportData['data'])) {
                 $profit = 0;
                 foreach ($reportData['data'] as $item) {
                     $profit += (float)($item['details']['profit'] ?? 0);
                 }
                 $reportExchangeRate = (float)($reportData['exchange_rate'] ?? 1);
                 $totalIncome += ($profit * $reportExchangeRate);
             }
         }
         }

         // Display Total Income
         $sheet->setCellValue('A' . $row, 'Total Income');
         $sheet->setCellValue('B' . $row, $totalIncome);
         $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         $row++;

         // Calculate total expenses (sum of all expenses)
         $totalExpensesAFN = 0;
         if (!empty($expenses)) {
         foreach ($expenses as $expense) {
             $totalExpensesAFN += (float)($expense['amount'] ?? 0);
         }
         }

         // Display Total Expenses
         $sheet->setCellValue('A' . $row, 'Total Expenses');
         $sheet->setCellValue('B' . $row, $totalExpensesAFN);
         $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         $row++;

         // Calculate and display Net Profit
         $netProfit = $totalIncome - $totalExpensesAFN;
         $sheet->setCellValue('A' . $row, 'Net Profit');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
         $sheet->setCellValue('B' . $row, $netProfit);
         $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('90EE90');
         $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('90EE90');
         $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         $sheet->getStyle('B' . $row)->getFont()->setBold(true);
         $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

    // Output
    if ($format === 'pdf') {
        $filename = "general_tax_report_{$quarter}_{$year}.pdf";
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        
        $writer = new Dompdf($spreadsheet);
        $writer->save('php://output');
    } else {
        $filename = "general_tax_report_{$quarter}_{$year}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}

/**
 * Fetch tickets by report type for export (similar to handler but for export)
 */
function fetchTicketsByTypeForExport($pdo, $tenant_id, $branch_id, $supplier_id, $report_type, $from_date, $to_date) {
    $tickets = [];
    
    // Ticket type includes: bookings, refunds, date_changes
    if ($report_type === 'ticket' || $report_type === 'all') {
        // 1. Regular ticket bookings
        $query = "SELECT 
                    id,
                    issue_date,
                    CONCAT(title, ' ', passenger_name) as full_name,
                    CONCAT(origin, ' - ', destination, IF(trip_type='round_trip', ' - ', ''), IF(trip_type='round_trip', return_origin, '')) as sector,
                    status,
                    pnr,
                    price as base_price,
                    sold as sold_price,
                    profit,
                    description,
                    'ticket' as ticket_type
                  FROM ticket_bookings
                  WHERE tenant_id = ? AND branch_id = ? AND supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(issue_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 2. Refunded tickets
        $query = "SELECT 
                    rt.id,
                    rt.issue_date,
                    CONCAT(rt.title, ' ', rt.passenger_name) as full_name,
                    CONCAT(rt.origin, ' - ', rt.destination) as sector,
                    rt.status,
                    rt.pnr,
                    rt.base as base_price,
                    rt.sold as sold_price,
                    0 as profit,
                    rt.remarks as description,
                    'ticket_refund' as ticket_type
                  FROM refunded_tickets rt
                  WHERE rt.tenant_id = ? AND rt.branch_id = ? AND rt.supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(rt.created_at) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 3. Date change tickets
        $query = "SELECT 
                    dc.id,
                    dc.issue_date,
                    CONCAT(dc.title, ' ', dc.passenger_name) as full_name,
                    CONCAT(dc.origin, ' - ', dc.destination) as sector,
                    dc.status,
                    dc.pnr,
                    COALESCE(dc.supplier_penalty, 0) as base_price,
                    (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold_price,
                    COALESCE(dc.service_penalty, 0) as profit,
                    dc.remarks as description,
                    'ticket_date_change' as ticket_type
                  FROM date_change_tickets dc
                  WHERE dc.tenant_id = ? AND dc.branch_id = ? AND dc.supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(dc.created_at) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Visa type (no refunds)
    if ($report_type === 'visa' || $report_type === 'all') {
        $query = "SELECT 
                    id,
                    receive_date as issue_date,
                    applicant_name as full_name,
                    CONCAT(country, ' - ', visa_type) as sector,
                    status,
                    passport_number as pnr,
                    base as base_price,
                    sold as sold_price,
                    profit,
                    remarks as description,
                    'visa' as ticket_type
                  FROM visa_applications
                  WHERE tenant_id = ? AND branch_id = ? AND supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(receive_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Umrah type (no refunds)
    if ($report_type === 'umrah' || $report_type === 'all') {
        $query = "SELECT 
                    ub.booking_id as id,
                    ub.entry_date as issue_date,
                    ub.name as full_name,
                    ub.duration as sector,
                    ub.status,
                    ub.passport_number as pnr,
                    ubs.base_price,
                    ubs.sold_price,
                    ubs.profit,
                    ub.remarks as description,
                    'umrah' as ticket_type
                  FROM umrah_bookings ub
                  JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                  WHERE ub.tenant_id = ? AND ub.branch_id = ? AND ubs.supplier_id = ? AND ub.status IN ('active', 'pending')";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(ub.entry_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Hotel type (no refunds)
    if ($report_type === 'hotel' || $report_type === 'all') {
        $query = "SELECT 
                    id,
                    issue_date,
                    CONCAT(first_name, ' ', last_name) as full_name,
                    accommodation_details as sector,
                    status,
                    order_id as pnr,
                    base_amount as base_price,
                    sold_amount as sold_price,
                    profit,
                    remarks as description,
                    'hotel' as ticket_type
                  FROM hotel_bookings
                  WHERE tenant_id = ? AND branch_id = ? AND supplier_id = ? AND status = 'active'";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(issue_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    return $tickets;
}
?>
