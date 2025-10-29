<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
require_once('../vendor/autoload.php'); // Make sure you have PhpSpreadsheet installed

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? '';
    $startDate = $_GET['startDate'] ?? date('Y-m-01');
    $endDate = $_GET['endDate'] ?? date('Y-m-t');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    switch($type) {
        case 'income':
            // Set headers
            $sheet->setCellValue('A1', 'Category');
            $sheet->setCellValue('B1', 'USD Amount');
            $sheet->setCellValue('C1', 'AFS Amount');

            // Initialize row counter
            $row = 2;

            // Tickets
            $sheet->setCellValue('A' . $row, 'Tickets');
            $stmt = $pdo->prepare("
                SELECT SUM(profit) as total, currency 
                FROM ticket_bookings 
                WHERE created_at BETWEEN ? AND ?
                AND tenant_id = ?
                GROUP BY currency
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $data['currency'] ?? 'USD';
                $column = ($currency == 'USD') ? 'B' : 'C';
                $sheet->setCellValue($column . $row, $data['total'] ?? 0);
            }
            $row++;

            // Refunds
            $sheet->setCellValue('A' . $row, 'Refunds');
            $stmt = $pdo->prepare("
                SELECT SUM(service_penalty) as total, currency 
                FROM refunded_tickets 
                WHERE created_at BETWEEN ? AND ?
                AND tenant_id = ?
                GROUP BY currency
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $data['currency'] ?? 'USD';
                $column = ($currency == 'USD') ? 'B' : 'C';
                $sheet->setCellValue($column . $row, $data['total'] ?? 0);
            }
            $row++;

            // Date Changes
            $sheet->setCellValue('A' . $row, 'Date Changes');
            $stmt = $pdo->prepare("
                SELECT SUM(service_penalty) as total, currency 
                FROM date_change_tickets 
                WHERE created_at BETWEEN ? AND ?
                AND tenant_id = ?
                GROUP BY currency
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $data['currency'] ?? 'USD';
                $column = ($currency == 'USD') ? 'B' : 'C';
                $sheet->setCellValue($column . $row, $data['total'] ?? 0);
            }
            $row++;

            // Visa
            $sheet->setCellValue('A' . $row, 'Visa');
            $stmt = $pdo->prepare("
                SELECT SUM(profit) as total, currency 
                FROM visa_applications 
                WHERE created_at BETWEEN ? AND ?
                AND tenant_id = ?
                GROUP BY currency
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $data['currency'] ?? 'USD';
                $column = ($currency == 'USD') ? 'B' : 'C';
                $sheet->setCellValue($column . $row, $data['total'] ?? 0);
            }
            $row++;

            // Umrah
            $sheet->setCellValue('A' . $row, 'Umrah');
            $stmt = $pdo->prepare("
                SELECT SUM(profit) as total, currency 
                FROM umrah_bookings 
                WHERE created_at BETWEEN ? AND ?
                AND tenant_id = ?
                GROUP BY currency
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $data['currency'] ?? 'USD';
                $column = ($currency == 'USD') ? 'B' : 'C';
                $sheet->setCellValue($column . $row, $data['total'] ?? 0);
            }

            // Initialize cells with 0 where no data exists
            for($i = 2; $i <= 6; $i++) {
                if($sheet->getCell('B' . $i)->getValue() === null) {
                    $sheet->setCellValue('B' . $i, 0);
                }
                if($sheet->getCell('C' . $i)->getValue() === null) {
                    $sheet->setCellValue('C' . $i, 0);
                }
            }

            // Create the chart
            $dataSeriesLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1:$C$1', null, 2),
            ];
            $xAxisTickValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$6', null, 5),
            ];
            $dataSeriesValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$6', null, 5),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$6', null, 5),
            ];

            break;

        case 'expenses':
            // Set headers
            $sheet->setCellValue('A1', 'Category');
            $sheet->setCellValue('B1', 'USD Amount');
            $sheet->setCellValue('C1', 'AFS Amount');

            // Fetch expense data
            $stmt = $pdo->prepare("
                SELECT 
                    ec.name as Category,
                    SUM(CASE WHEN e.currency = 'USD' OR e.currency IS NULL THEN e.amount ELSE 0 END) as USD_Amount,
                    SUM(CASE WHEN e.currency = 'AFS' THEN e.amount ELSE 0 END) as AFS_Amount
                FROM expense_categories ec
                LEFT JOIN expenses e ON e.category_id = ec.id 
                WHERE (e.date BETWEEN ? AND ? OR e.date IS NULL)
                AND e.tenant_id = ?
                GROUP BY ec.id, ec.name
            ");
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            
            $row = 2;
            while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sheet->setCellValue('A' . $row, $data['Category']);
                $sheet->setCellValue('B' . $row, $data['USD_Amount']);
                $sheet->setCellValue('C' . $row, $data['AFS_Amount']);
                $row++;
            }

            // Create chart for expenses
            $lastRow = $row - 1;
            $dataSeriesLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1:$C$1', null, 2),
            ];
            $xAxisTickValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$' . $lastRow, null, $lastRow-1),
            ];
            $dataSeriesValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$' . $lastRow, null, $lastRow-1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$' . $lastRow, null, $lastRow-1),
            ];

            break;

        case 'profitLoss':
            // Set headers
            $sheet->setCellValue('A1', 'Month');
            $sheet->setCellValue('B1', 'USD Income');
            $sheet->setCellValue('C1', 'USD Expenses');
            $sheet->setCellValue('D1', 'USD Profit/Loss');
            $sheet->setCellValue('E1', 'AFS Income');
            $sheet->setCellValue('F1', 'AFS Expenses');
            $sheet->setCellValue('G1', 'AFS Profit/Loss');

            // Fetch and populate data
            $row = 2;
            $startDateTime = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);
            $interval = DateInterval::createFromDateString('1 month');
            $period = new DatePeriod($startDateTime, $interval, $endDateTime);

            foreach ($period as $dt) {
                $month = $dt->format('M Y');
                $monthStart = $dt->format('Y-m-01');
                $monthEnd = $dt->format('Y-m-t');

                $sheet->setCellValue('A' . $row, $month);
                
                // Add your queries to populate the amounts
                // ... (rest of your profit/loss calculation logic)

                $row++;
            }

            // Create chart for profit/loss
            $lastRow = $row - 1;
            $dataSeriesLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$D$1:$G$1', null, 2),
            ];
            $xAxisTickValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$' . $lastRow, null, $lastRow-1),
            ];
            $dataSeriesValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$D$2:$D$' . $lastRow, null, $lastRow-1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$G$2:$G$' . $lastRow, null, $lastRow-1),
            ];

            break;
    }

    // Create the chart object
    $series = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_STANDARD,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );

    $plotArea = new PlotArea(null, [$series]);
    $legend = new Legend(Legend::POSITION_RIGHT, null, false);
    $title = new Title($type . ' Overview');

    $chart = new Chart(
        'chart1',
        $title,
        $legend,
        $plotArea
    );

    // Set chart position
    $chart->setTopLeftPosition('A' . ($row + 2));
    $chart->setBottomRightPosition('H' . ($row + 15));

    // Add chart to worksheet
    $sheet->addChart($chart);

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    $filename = $type . '_report_' . date('Y-m-d') . '.xlsx';
    $writer->setIncludeCharts(true);
    
    // Save to temp file and return path
    $tempFile = tempnam(sys_get_temp_dir(), 'excel');
    $writer->save($tempFile);

    echo json_encode([
        'success' => true,
        'file' => base64_encode(file_get_contents($tempFile))
    ]);

    // Clean up temp file
    unlink($tempFile);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 