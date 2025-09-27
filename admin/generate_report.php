<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use Dompdf\Dompdf;

// Database connection
require '../includes/conn.php';

// Get query parameters
$supplierId = $_GET['supplierId'] ?? '';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';
$format = strtolower($_GET['format'] ?? '');

// Validate input
if (empty($supplierId) || empty($format)) {
    die("Invalid parameters");
}

// Base query to fetch data from `supplier_transactions` table
$query = "
    SELECT t.passenger_name, t.pnr, st.transaction_date, st.transaction_type, s.currency, st.amount, st.remarks, s.name 
    FROM supplier_transactions st 
    LEFT JOIN suppliers s ON st.supplier_id = s.id
    LEFT JOIN ticket_bookings t ON st.ticket_id = t.id 
    WHERE st.supplier_id = ? AND st.tenant_id = ?
";

$params = [$supplierId, $tenant_id];
$types = 'i';

// Add date filters
if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND st.transaction_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
} elseif (!empty($startDate)) {
    $query .= " AND st.transaction_date >= ?";
    $params[] = $startDate;
    $types .= 's';
} elseif (!empty($endDate)) {
    $query .= " AND st.transaction_date <= ?";
    $params[] = $endDate;
    $types .= 's';
}

// Add query for `funding_transactions` table with date filters
$query .= "
    UNION ALL

    SELECT NULL AS passenger_name, NULL AS pnr, ft.transaction_date, ft.transaction_type, ft.currency, ft.amount, ft.remarks, NULL AS name 
    FROM funding_transactions ft 
    LEFT JOIN suppliers s ON ft.supplier_id = s.id
     
    WHERE ft.supplier_id = ? AND ft.tenant_id = ?
";

$params[] = $supplierId;
$params[] = $tenant_id;
$types .= 'i';

// Add date filters for funding transactions
if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND ft.transaction_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
} elseif (!empty($startDate)) {
    $query .= " AND ft.transaction_date >= ?";
    $params[] = $startDate;
    $types .= 's';
} elseif (!empty($endDate)) {
    $query .= " AND ft.transaction_date <= ?";
    $params[] = $endDate;
    $types .= 's';
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters
$stmt->bind_param($types, ...$params);
$stmt->execute();

// Get result
$result = $stmt->get_result();

// Fetch transactions
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$stmt->close();

// Generate the report
if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Supplier Transactions');

     // Add the supplier's name at the top
    $supplierName = $transactions[0]['name'] ?? 'Unknown Supplier';  // Get the supplier name from the first transaction
    $sheet->setCellValue('A1', 'Supplier: ' . $supplierName);  // Display the supplier's name in cell A1


    // Header row
    $headers = ['P/Name','PNR','Date', 'Transaction Type', 'Currency', 'Amount', 'Remarks'];
    $sheet->fromArray($headers, null, 'A3');

    // Data rows
    $rowNumber = 4;
    foreach ($transactions as $transaction) {
        $sheet->setCellValue("A$rowNumber", $transaction['passenger_name']);
        $sheet->setCellValue("B$rowNumber", $transaction['pnr']);
        $sheet->setCellValue("C$rowNumber", $transaction['transaction_date']);
        $sheet->setCellValue("D$rowNumber", $transaction['transaction_type']);
        $sheet->setCellValue("E$rowNumber", $transaction['currency']);
        $sheet->setCellValue("F$rowNumber", $transaction['amount']);
        $sheet->setCellValue("G$rowNumber", $transaction['remarks']);
        $rowNumber++;
    }

    // Output file
    header("Content-Disposition: attachment; filename=report.xlsx");
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} elseif ($format === 'word') {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();

       // Get the supplier's name
    $supplierName = $transactions[0]['name'] ?? 'Unknown Supplier';  // Get the supplier name from the first transaction

    // Display the supplier's name at the top
    $section->addText('Supplier: ' . $supplierName, ['bold' => true, 'size' => 14], ['alignment' => 'center']);
    $section->addTextBreak(2);  // Add a small gap between the supplier name and table


    // Table
    $table = $section->addTable();

    // Header row
    $headers = ['P/Name','PNR','Date', 'Transaction Type', 'Currency', 'Amount', 'Remarks'];
    $headerStyle = ['bold' => true];
    $table->addRow();
    foreach ($headers as $header) {
        $table->addCell(2000)->addText($header, $headerStyle);
    }

    // Data rows
    foreach ($transactions as $transaction) {
        $table->addRow();
        $table->addCell(2000)->addText($transaction['passenger_name']);
        $table->addCell(2000)->addText($transaction['pnr']);
        $table->addCell(2000)->addText($transaction['transaction_date']);
        $table->addCell(2000)->addText($transaction['transaction_type']);
        $table->addCell(2000)->addText($transaction['currency']);
        $table->addCell(2000)->addText($transaction['amount']);
        $table->addCell(2000)->addText($transaction['remarks']);
    }

    // Output file
    header("Content-Disposition: attachment; filename=report.docx");
    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');
    $phpWord->save($tempFile);
    readfile($tempFile);
    unlink($tempFile); // Clean up
    exit;
} elseif ($format === 'pdf') {
    $dompdf = new Dompdf();

    // Create HTML for the PDF
   if (!empty($transactions)) {
    $supplierName = $transactions[0]['name']; // Get the name from the first transaction
} else {
    $supplierName = 'Supplier'; // Default name if no transactions exist
}

$html = '<h1 style="text-align: center;">' . htmlspecialchars($supplierName) . ' Transactions Report</h1>';

    $html .= '<table border="1" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>P/Name</th>
                        <th>PNR</th>
                        <th>Date</th>
                        <th>Transaction Type</th>
                        <th>Currency</th>
                        <th>Amount</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($transactions as $transaction) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($transaction['passenger_name']) . '</td>
                    <td>' . htmlspecialchars($transaction['pnr']) . '</td>
                    <td>' . htmlspecialchars($transaction['transaction_date']) . '</td>
                    <td>' . htmlspecialchars($transaction['transaction_type']) . '</td>
                    <td>' . htmlspecialchars($transaction['currency']) . '</td>
                    <td>' . htmlspecialchars($transaction['amount']) . '</td>
                    <td>' . htmlspecialchars($transaction['remarks']) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output file
    header("Content-Disposition: attachment; filename=report.pdf");
    header("Content-Type: application/pdf");
    echo $dompdf->output();
    exit;
} else {
    die("Unsupported format");
}
?>
