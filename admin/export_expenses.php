<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
header('Content-Type: application/json');

try {
    // Get date range from request
    $startDate = $_GET['startDate'] ?? date('Y-m-01');
    $endDate = $_GET['endDate'] ?? date('Y-m-t');

    // Fetch expenses with category and main account information
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            ec.name as category_name,
            ma.name as main_account_name
            
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN main_account ma ON e.main_account_id = ma.id

        WHERE e.date BETWEEN ? AND ?
        AND e.tenant_id = ?
        ORDER BY e.date ASC
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

     // Create Excel file
     require_once('../vendor/autoload.php');
     $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
     $sheet = $spreadsheet->getActiveSheet();
 
     // Set document properties
     $spreadsheet->getProperties()
         ->setCreator("Travel Agency")
         ->setLastModifiedBy("Travel Agency")
         ->setTitle("Expenses Report")
         ->setSubject("Expenses Report")
         ->setDescription("Expenses report generated on " . date('Y-m-d'));
 
     // Set headers
     $headers = [
         'Date',
         'Category',
         'Description',
         'Amount',
         'Currency',
         'Main Account',
         'Created At'
     ];
 
     // Add report title
     $sheet->setCellValue('A1', 'EXPENSES REPORT');
     $sheet->mergeCells('A1:H1');
     $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
     $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
 
     // Add date range
     $sheet->setCellValue('A2', 'Date Range: ' . $startDate . ' to ' . $endDate);
     $sheet->mergeCells('A2:H2');
     $sheet->getStyle('A2')->getFont()->setBold(true);
     $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
 
     // Add column headers
     $sheet->fromArray($headers, NULL, 'A4');
     
     // Style the headers
     $headerStyle = [
         'font' => [
             'bold' => true,
             'color' => ['rgb' => 'FFFFFF']
         ],
         'fill' => [
             'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
             'startColor' => ['rgb' => '4472C4']
         ],
         'alignment' => [
             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
             'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
         ],
         'borders' => [
             'allBorders' => [
                 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
             ]
         ]
     ];
     $sheet->getStyle('A4:H4')->applyFromArray($headerStyle);
 
     // Add data
     $row = 5;
     $totalUSD = 0;
     $totalAFN = 0;
     foreach ($expenses as $expense) {
         $sheet->setCellValue('A' . $row, $expense['date']);
         $sheet->setCellValue('B' . $row, $expense['category_name']);
         $sheet->setCellValue('C' . $row, $expense['description']);
         $sheet->setCellValue('D' . $row, $expense['amount']);
         $sheet->setCellValue('E' . $row, $expense['currency']);
         $sheet->setCellValue('F' . $row, $expense['main_account_name']);
         $sheet->setCellValue('H' . $row, $expense['created_at']);
         
         // Style the data row
         $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
             'borders' => [
                 'allBorders' => [
                     'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                 ]
             ],
             'alignment' => [
                 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
             ]
         ]);
         
         // Format amount column
         $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
         
         if ($expense['currency'] === 'USD') {
             $totalUSD += $expense['amount'];
         } elseif ($expense['currency'] === 'AFS') {
             $totalAFN += $expense['amount'];
         }
         $row++;
     }
 
     // Add total rows with styling
     $totalRow = $row + 1;
     $sheet->setCellValue('A' . $totalRow, 'Total Expenses (USD):');
     $sheet->setCellValue('D' . $totalRow, $totalUSD);
     $sheet->getStyle('A' . $totalRow . ':D' . $totalRow)->getFont()->setBold(true);
     $sheet->getStyle('D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
     
     $totalRow++;
     $sheet->setCellValue('A' . $totalRow, 'Total Expenses (AFS):');
     $sheet->setCellValue('D' . $totalRow, $totalAFN);
     $sheet->getStyle('A' . $totalRow . ':D' . $totalRow)->getFont()->setBold(true);
     $sheet->getStyle('D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
 
     // Auto-size columns
     foreach (range('A', 'H') as $col) {
         $sheet->getColumnDimension($col)->setAutoSize(true);
     }
 
     // Add a border around the entire data area
     $sheet->getStyle('A4:H' . ($row-1))->applyFromArray([
         'borders' => [
             'outline' => [
                 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM
             ]
         ]
     ]);
 

    // Set headers for download
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $excelData = ob_get_clean();

    echo json_encode([
        'success' => true,
        'file' => base64_encode($excelData)
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 