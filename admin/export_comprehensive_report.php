<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Database connection
require_once('../includes/db.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if PhpSpreadsheet is installed
if (!file_exists('../vendor/autoload.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'PhpSpreadsheet is not installed. Please run "composer require phpoffice/phpspreadsheet".'
    ]);
    exit;
}

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Function to calculate the average exchange rate from main account transactions
function getAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id) {
    $query = "
        SELECT AVG(exchange_rate) as avg_rate
        FROM main_account_transactions
        WHERE exchange_rate IS NOT NULL
        AND exchange_rate > 0
        AND currency = 'AFS'
        AND created_at BETWEEN ? AND ?
        AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default to 1 if no rates available
    return ($result && $result['avg_rate']) ? floatval($result['avg_rate']) : 1;
}

// Function to get the daily average exchange rate or fallback to period average
function getDailyExchangeRate($pdo, $date, $periodStart, $periodEnd, $tenant_id) {
    // Try to get average rate for this specific day
    $dayQuery = "
        SELECT AVG(exchange_rate) as avg_rate
        FROM main_account_transactions
        WHERE exchange_rate IS NOT NULL
        AND exchange_rate > 0
        AND currency = 'AFS'
        AND DATE(created_at) = DATE(?)
        AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($dayQuery);
    $stmt->execute([$date, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If found a daily rate, use it
    if ($result && $result['avg_rate']) {
        return floatval($result['avg_rate']);
    }

    // Otherwise use period average
    return getAverageExchangeRate($pdo, $periodStart, $periodEnd, $tenant_id);
}

// Function to get the average exchange rate from main account transactions for a specific day
function getDailyAverageExchangeRate($pdo, $date, $tenant_id) {
    $query = "
        SELECT AVG(exchange_rate) as avg_rate
        FROM main_account_transactions
        WHERE exchange_rate IS NOT NULL
        AND exchange_rate > 0
        AND currency = 'AFS'
        AND DATE(created_at) = DATE(?)
        AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$date, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default to null if no rates available
    return ($result && $result['avg_rate']) ? floatval($result['avg_rate']) : null;
}

// Function to get the period average exchange rate as fallback
function getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id) {
    $query = "
        SELECT AVG(exchange_rate) as avg_rate
        FROM main_account_transactions
        WHERE exchange_rate IS NOT NULL
        AND exchange_rate > 0
        AND currency = 'AFS'
        AND created_at BETWEEN ? AND ?
        AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default to 1 if no rates available
    return ($result && $result['avg_rate']) ? floatval($result['avg_rate']) : 1;
}

// Function to map source names to table names
function getTableName($source) {
    switch ($source) {
        case 'Ticket Sales':
            return 'ticket_bookings';
        case 'Ticket Reservations':
            return 'ticket_reservations';
        case 'Ticket Refunds':
            return 'refunded_tickets';
        case 'Date Changes':
            return 'date_change_tickets';
        case 'Visa Services':
            return 'visa_applications';
        case 'Umrah Bookings':
            return 'umrah_bookings';
        case 'Hotel Bookings':
            return 'hotel_bookings';
        case 'Ticket Weights':
            return 'ticket_weights';
        case 'Additional Payments':
            return 'additional_payments';
        default:
            return 'ticket_bookings'; // Default fallback
    }
}

// Function to get the appropriate query for a given source
function getSourceQuery($source) {
    switch ($source) {
        case 'Ticket Sales':
            return "
                SELECT
                    SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN tb.currency = 'AFS' THEN tb.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN tb.currency = 'EUR' THEN tb.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN tb.currency = 'DARHAM' THEN tb.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN tb.currency = 'USD' AND mat.exchange_rate > 0 THEN tb.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM ticket_bookings tb
                LEFT JOIN main_account_transactions mat ON mat.reference_id = tb.id AND mat.transaction_of = 'ticket_sale' AND mat.currency = 'AFS' AND mat.tenant_id = tb.tenant_id
                WHERE tb.created_at BETWEEN ? AND ?
               
                AND tb.tenant_id = ?
            ";
        case 'Ticket Reservations':
            return "
                SELECT
                    SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN tr.currency = 'AFS' THEN tr.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN tr.currency = 'EUR' THEN tr.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN tr.currency = 'DARHAM' THEN tr.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN tr.currency = 'USD' AND mat.exchange_rate > 0 THEN tr.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM ticket_reservations tr
                LEFT JOIN main_account_transactions mat ON mat.reference_id = tr.id AND mat.transaction_of = 'ticket_reserve' AND mat.currency = 'AFS' AND mat.tenant_id = tr.tenant_id
                WHERE tr.created_at BETWEEN ? AND ?
                AND tr.tenant_id = ?
            ";
        case 'Ticket Refunds':
            return "
                SELECT
                    SUM(CASE
                        WHEN rt.currency = 'USD' THEN
                            CASE
                                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                                ELSE rt.service_penalty
                            END
                        ELSE 0
                    END) as usd_amount,
                    SUM(CASE
                        WHEN rt.currency = 'AFS' THEN
                            CASE
                                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                                ELSE rt.service_penalty
                            END
                        ELSE 0
                    END) as afs_amount,
                    SUM(CASE
                        WHEN rt.currency = 'EUR' THEN
                            CASE
                                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                                ELSE rt.service_penalty
                            END
                        ELSE 0
                    END) as eur_amount,
                    SUM(CASE
                        WHEN rt.currency = 'DARHAM' THEN
                            CASE
                                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                                ELSE rt.service_penalty
                            END
                        ELSE 0
                    END) as darham_amount,
                    SUM(CASE
                        WHEN rt.currency = 'USD' THEN
                            CASE
                                WHEN mat.exchange_rate > 0 THEN
                                    (CASE
                                        WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                                        WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                                        ELSE rt.service_penalty
                                    END) * mat.exchange_rate
                                ELSE 0
                            END
                        ELSE 0
                    END) as afs_converted
                FROM refunded_tickets rt
                JOIN ticket_bookings tb ON rt.ticket_id = tb.id
                LEFT JOIN main_account_transactions mat ON mat.reference_id = rt.id AND mat.transaction_of = 'ticket_refund' AND mat.currency = 'AFS' AND mat.tenant_id = rt.tenant_id
                WHERE rt.created_at BETWEEN ? AND ?
                AND rt.tenant_id = ?
            ";
        case 'Date Changes':
            return "
                SELECT
                    SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN dt.currency = 'EUR' THEN dt.service_penalty ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN dt.currency = 'DARHAM' THEN dt.service_penalty ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN dt.currency = 'USD' THEN
                            CASE
                                WHEN mat.exchange_rate > 0 THEN dt.service_penalty * mat.exchange_rate
                                ELSE 0
                            END
                        ELSE 0
                    END) as afs_converted
                FROM date_change_tickets dt
                JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                LEFT JOIN main_account_transactions mat ON mat.reference_id = dt.id AND mat.transaction_of = 'date_change' AND mat.currency = 'AFS' AND mat.tenant_id = dt.tenant_id
                WHERE dt.created_at BETWEEN ? AND ?
                AND dt.tenant_id = ?
            ";
        case 'Visa Services':
            return "
                SELECT
                    SUM(CASE WHEN va.currency = 'USD' THEN va.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN va.currency = 'AFS' THEN va.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN va.currency = 'EUR' THEN va.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN va.currency = 'DARHAM' THEN va.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN va.currency = 'USD' AND mat.exchange_rate > 0 THEN va.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM visa_applications va
                LEFT JOIN main_account_transactions mat ON mat.reference_id = va.id AND mat.transaction_of = 'visa' AND mat.currency = 'AFS' AND mat.tenant_id = va.tenant_id
                WHERE va.created_at BETWEEN ? AND ?
                AND va.tenant_id = ?
            ";
        case 'Umrah Bookings':
            return "
                SELECT
                    SUM(CASE WHEN ub.currency = 'USD' THEN ub.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN ub.currency = 'AFS' THEN ub.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN ub.currency = 'EUR' THEN ub.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN ub.currency = 'DARHAM' THEN ub.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN ub.currency = 'USD' AND mat.exchange_rate > 0 THEN ub.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM umrah_bookings ub
                LEFT JOIN main_account_transactions mat ON mat.reference_id = ub.booking_id AND mat.transaction_of = 'umrah' AND mat.currency = 'AFS' AND mat.tenant_id = ub.tenant_id
                WHERE ub.created_at BETWEEN ? AND ?
                AND ub.tenant_id = ?
            ";
        case 'Hotel Bookings':
            return "
                SELECT
                    SUM(CASE WHEN hb.currency = 'USD' THEN hb.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN hb.currency = 'AFS' THEN hb.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN hb.currency = 'EUR' THEN hb.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN hb.currency = 'DARHAM' THEN hb.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN hb.currency = 'USD' AND mat.exchange_rate > 0 THEN hb.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM hotel_bookings hb
                LEFT JOIN main_account_transactions mat ON mat.reference_id = hb.id AND mat.transaction_of = 'hotel' AND mat.currency = 'AFS' AND mat.tenant_id = hb.tenant_id
                WHERE hb.created_at BETWEEN ? AND ?
                AND hb.tenant_id = ?
            ";
        case 'Ticket Weights':
            return "
                SELECT
                    SUM(CASE WHEN tb.currency = 'USD' THEN tw.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN tb.currency = 'AFS' THEN tw.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN tb.currency = 'EUR' THEN tw.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN tb.currency = 'DARHAM' THEN tw.profit ELSE 0 END) as darham_amount,
                    SUM(CASE
                        WHEN tb.currency = 'USD' AND mat.exchange_rate > 0 THEN tw.profit * mat.exchange_rate
                        ELSE 0
                    END) as afs_converted
                FROM ticket_weights tw
                JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                LEFT JOIN main_account_transactions mat ON mat.reference_id = tw.id AND mat.transaction_of = 'weight' AND mat.currency = 'AFS' AND mat.tenant_id = tw.tenant_id
                WHERE tw.created_at BETWEEN ? AND ?
                AND tw.tenant_id = ?
            ";
        case 'Additional Payments':
            return "
                SELECT
                    SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END) as usd_amount,
                    SUM(CASE WHEN ap.currency = 'AFS' THEN ap.profit ELSE 0 END) as afs_amount,
                    SUM(CASE WHEN ap.currency = 'EUR' THEN ap.profit ELSE 0 END) as eur_amount,
                    SUM(CASE WHEN ap.currency = 'DARHAM' THEN ap.profit ELSE 0 END) as darham_amount,
                    0 as afs_converted
                FROM additional_payments ap
                WHERE ap.created_at BETWEEN ? AND ?
                AND ap.tenant_id = ?
            ";
        default:
            return ""; // Empty string for unrecognized sources
    }
}

try {
    // Get date range
    $startDate = $_GET['startDate'] ?? date('Y-m-01');
    $endDate = $_GET['endDate'] ?? date('Y-m-t');
    
    // Initialize spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Travel Agency Financial System')
        ->setLastModifiedBy('Travel Agency Financial System')
        ->setTitle('Comprehensive Financial Report')
        ->setSubject('Financial Report')
        ->setDescription('Comprehensive financial report with income, expenses and profit/loss')
        ->setKeywords('financial report income expenses profit loss')
        ->setCategory('Financial Reports');
    
    // Set default font
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
    
    // Create Summary Sheet
    $summarySheet = $spreadsheet->getActiveSheet();
    $summarySheet->setTitle('Summary');
    
    // Add report title
    $summarySheet->setCellValue('A1', 'COMPREHENSIVE FINANCIAL REPORT');
    $summarySheet->mergeCells('A1:G1');
    $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Add date range
    $summarySheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
    $summarySheet->mergeCells('A2:G2');
    $summarySheet->getStyle('A2')->getFont()->setBold(true);
    $summarySheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Add space
    $summarySheet->setCellValue('A4', 'SUMMARY');
    $summarySheet->getStyle('A4')->getFont()->setBold(true)->setSize(14);
    
    // Header row styles
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];
    
    // Data row styles
    $dataStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ];
    
    // Number format for currency
    $currencyFormat = '#,##0.00_-';
    
    // Summary headers
    $summarySheet->setCellValue('A6', 'Category');
    $summarySheet->setCellValue('B6', 'USD');
    $summarySheet->setCellValue('C6', 'Pure AFS');
    $summarySheet->setCellValue('D6', 'USD to AFS');
    $summarySheet->setCellValue('E6', 'Total');
    $summarySheet->setCellValue('F6', 'EUR');
    $summarySheet->setCellValue('G6', 'DARHAM');
    $summarySheet->getStyle('A6:G6')->applyFromArray($headerStyle);
    
    // Calculate total income for the date range using the same approach as get_financial_data.php
    $incomeData = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];
    
    $totalIncomeQuery = "
        SELECT SUM(profit) as total, currency
        FROM (
            SELECT profit, currency, created_at, tenant_id FROM ticket_bookings WHERE tenant_id = ?
            UNION ALL
            SELECT profit, currency, created_at, tenant_id FROM ticket_reservations WHERE tenant_id = ?
            UNION ALL
            SELECT
                CASE
                    WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                    WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                    ELSE rt.service_penalty
                END as profit,
                rt.currency,
                rt.created_at,
                rt.tenant_id
            FROM refunded_tickets rt
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE rt.tenant_id = ?
            UNION ALL
            SELECT dt.service_penalty, dt.currency, dt.created_at, dt.tenant_id FROM date_change_tickets dt
            JOIN ticket_bookings tb ON dt.ticket_id = tb.id
            WHERE dt.tenant_id = ?
            UNION ALL
            SELECT profit, currency, created_at, tenant_id FROM visa_applications WHERE tenant_id = ?
            UNION ALL
            SELECT profit, currency, created_at, tenant_id FROM umrah_bookings WHERE tenant_id = ?
            UNION ALL
            SELECT profit, currency, created_at, tenant_id FROM hotel_bookings WHERE tenant_id = ?
            UNION ALL
            SELECT tw.profit, tb.currency, tw.created_at, tw.tenant_id FROM ticket_weights tw
                JOIN ticket_bookings tb ON tw.ticket_id = tb.id WHERE tw.tenant_id = ?
            UNION ALL
            SELECT profit, currency, created_at, tenant_id FROM additional_payments WHERE tenant_id = ?
        ) as combined_income
        WHERE created_at BETWEEN ? AND ?
        GROUP BY currency
    ";
    
    $totalIncomeStmt = $pdo->prepare($totalIncomeQuery);
    $totalIncomeStmt->execute([
        $tenant_id,  // ticket_bookings
        $tenant_id,  // ticket_reservations
        $tenant_id,  // refunded_tickets
        $tenant_id,  // date_change_tickets
        $tenant_id,  // visa_applications
        $tenant_id,  // umrah_bookings
        $tenant_id,  // hotel_bookings
        $tenant_id,  // ticket_weights
        $tenant_id,  // additional_payments
        $startDate, $endDate
    ]);
    
    while($row = $totalIncomeStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData[$currency] = floatval($row['total']);
    }

    // Add verification step to ensure totals match
    // Instead of using the complex query above, calculate totals directly from the individual sources
    // This ensures the total income matches exactly the sum of all individual sources
    
    // First, get all income sources
    // Initialize sources array (this will be populated later in the code)
    $sources = [
        'Ticket Sales' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Ticket Reservations' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Ticket Refunds' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Date Changes' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Visa Services' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Umrah Bookings' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Hotel Bookings' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Ticket Weights' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0],
        'Additional Payments' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0]
    ];
    
    // Fetch ticket bookings income
    $avgExchangeRate = getAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);

    $pureAfsTotal = 0;

    // Process each income source with proper error handling
    foreach ($sources as $sourceName => &$currencies) {
        try {
            $sourceQuery = getSourceQuery($sourceName);
            if (empty($sourceQuery)) {
                continue; // Skip if no valid query for this source
            }
            
            $stmt = $pdo->prepare($sourceQuery);
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Store USD amount directly
            $currencies['USD'] = floatval($row['usd_amount'] ?? 0);

            // First store the original AFS amount
            $afsAmount = floatval($row['afs_amount'] ?? 0);
            if (isset($currencies['AFS'])) {
                $currencies['AFS'] += $afsAmount;
            } else {
                $currencies['AFS'] = $afsAmount;
            }

            // Add the converted USD-to-AFS amount from transactions with exchange_rate
            $currencies['AFS'] += floatval($row['afs_converted'] ?? 0);

            // Accumulate pure AFS
            $pureAfsTotal += $afsAmount;

            // Special handling for Ticket Sales when exchange_rate is null/0
            if ($sourceName === 'Ticket Sales' && $currencies['USD'] > 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $ticketQuery = "
                    SELECT created_at, profit
                    FROM ticket_bookings
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $ticketStmt = $pdo->prepare($ticketQuery);
                $ticketStmt->execute([$startDate, $endDate, $tenant_id]);

                $ticketConverted = 0;

                while ($booking = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $ticketConverted += $booking['profit'] * $dailyRate;
                }

                $currencies['AFS'] += $ticketConverted;
            }

            // Special handling for Date Changes when exchange_rate is null/0
            if ($sourceName === 'Date Changes' && $currencies['USD'] > 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $dateQuery = "
                    SELECT dt.created_at, dt.service_penalty as profit
                    FROM date_change_tickets dt
                    JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                    WHERE dt.currency = 'USD'
                    AND dt.created_at BETWEEN ? AND ?
                    AND dt.tenant_id = ?
                ";
                $dateStmt = $pdo->prepare($dateQuery);
                $dateStmt->execute([$startDate, $endDate, $tenant_id]);

                $dateConverted = 0;

                while ($change = $dateStmt->fetch(PDO::FETCH_ASSOC)) {
                    $changeDate = date('Y-m-d', strtotime($change['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $changeDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $dateConverted += $change['profit'] * $dailyRate;
                }

                $currencies['AFS'] += $dateConverted;
            }

            // Special handling for Visa Services when exchange_rate is null/0
            if ($sourceName === 'Visa Services' && $currencies['USD'] > 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $visaQuery = "
                    SELECT created_at, profit
                    FROM visa_applications
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $visaStmt = $pdo->prepare($visaQuery);
                $visaStmt->execute([$startDate, $endDate, $tenant_id]);

                $visaConverted = 0;

                while ($application = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
                    $applicationDate = date('Y-m-d', strtotime($application['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $applicationDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $visaConverted += $application['profit'] * $dailyRate;
                }

                $currencies['AFS'] += $visaConverted;
            }

            // Special handling for Umrah Bookings when exchange_rate is null/0
            if ($sourceName === 'Umrah Bookings' && $currencies['USD'] > 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $umrahQuery = "
                    SELECT created_at, profit
                    FROM umrah_bookings
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $umrahStmt = $pdo->prepare($umrahQuery);
                $umrahStmt->execute([$startDate, $endDate, $tenant_id]);

                $umrahConverted = 0;

                while ($booking = $umrahStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $umrahConverted += $booking['profit'] * $dailyRate;
                }

                $currencies['AFS'] += $umrahConverted;
            }

            // Special handling for Ticket Weights when exchange_rate is null/0
            if ($sourceName === 'Ticket Weights' && $currencies['USD'] > 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $weightsQuery = "
                    SELECT tw.created_at, tw.profit
                    FROM ticket_weights tw
                    JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                    WHERE tb.currency = 'USD'
                    AND tw.created_at BETWEEN ? AND ?
                    AND tw.tenant_id = ?
                ";
                $weightsStmt = $pdo->prepare($weightsQuery);
                $weightsStmt->execute([$startDate, $endDate, $tenant_id]);

                $weightsConverted = 0;

                while ($weight = $weightsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $weightDate = date('Y-m-d', strtotime($weight['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $weightsConverted += $weight['profit'] * $dailyRate;
                }

                $currencies['AFS'] += $weightsConverted;
            }

            // For Additional Payments table which doesn't have exchange_rate field,
            // we need to handle conversion separately using daily exchange rates
            if ($sourceName === 'Additional Payments' && $currencies['USD'] > 0) {
                // Get additional payments with USD currency
                $additionalQuery = "
                    SELECT id, created_at, profit
                    FROM additional_payments
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $additionalStmt = $pdo->prepare($additionalQuery);
                $additionalStmt->execute([$startDate, $endDate, $tenant_id]);
                
                $additionalConverted = 0;
                
                // Process each payment using the daily rate for its date
                while ($payment = $additionalStmt->fetch(PDO::FETCH_ASSOC)) {
                    $paymentDate = date('Y-m-d', strtotime($payment['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $paymentDate, $tenant_id);

                    // If no daily rate, use period average
                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }
                    
                    $additionalConverted += $payment['profit'] * $dailyRate;
                }
                
                // Add this converted amount to AFS
                $currencies['AFS'] += $additionalConverted;
            }
            
            // Store EUR amount
            if (isset($currencies['EUR'])) {
                $currencies['EUR'] += floatval($row['eur_amount'] ?? 0);
            } else {
                $currencies['EUR'] = floatval($row['eur_amount'] ?? 0);
            }
            
            // Store DARHAM amount
            if (isset($currencies['DARHAM'])) {
                $currencies['DARHAM'] += floatval($row['darham_amount'] ?? 0);
            } else {
                $currencies['DARHAM'] = floatval($row['darham_amount'] ?? 0);
            }
        } catch (PDOException $e) {
            // Log error and continue with next source
            error_log("Error processing income source $sourceName: " . $e->getMessage());
            continue;
        }
    }
    
    // Now calculate the totals directly from the sources array
    $incomeData = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];
    
    foreach ($sources as $source => $amounts) {
        foreach ($amounts as $currency => $amount) {
            $incomeData[$currency] += $amount;
        }
    }
    
    // Calculate total expenses by currency
    $expenseQuery = "
        SELECT
            'Expense' as type,
            currency,
            SUM(amount) as total
        FROM (
            SELECT
                e.currency,
                e.amount
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.date BETWEEN ? AND ?
            AND e.tenant_id = ?

            UNION ALL

            SELECT
                sp.currency,
                sp.amount
            FROM salary_payments sp
            LEFT JOIN users u ON sp.user_id = u.id
            WHERE sp.payment_date BETWEEN ? AND ?
            AND sp.tenant_id = ?
        ) combined_expenses
        GROUP BY currency
    ";
    $expenseStmt = $pdo->prepare($expenseQuery);
    $expenseStmt->execute([$startDate, $endDate, $tenant_id, $startDate, $endDate, $tenant_id]);
    
    $expenseData = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];
    
    while ($row = $expenseStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $expenseData[$currency] = floatval($row['total']);
    }

    // Calculate USD expenses converted to AFS
    $expenseUsdToAfs = 0;
    $expenseByDateQuery = "
        SELECT
            date,
            amount
        FROM (
            SELECT
                e.date,
                e.amount
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.currency = 'USD'
            AND e.date BETWEEN ? AND ?
            AND e.tenant_id = ?

            UNION ALL

            SELECT
                sp.payment_date as date,
                sp.amount
            FROM salary_payments sp
            LEFT JOIN users u ON sp.user_id = u.id
            WHERE sp.currency = 'USD'
            AND sp.payment_date BETWEEN ? AND ?
            AND sp.tenant_id = ?
        ) combined_expenses
    ";
    $expenseByDateStmt = $pdo->prepare($expenseByDateQuery);
    $expenseByDateStmt->execute([$startDate, $endDate, $tenant_id, $startDate, $endDate, $tenant_id]);

    // Process each USD expense using the daily rate for its date
    while ($expense = $expenseByDateStmt->fetch(PDO::FETCH_ASSOC)) {
        $expenseDate = date('Y-m-d', strtotime($expense['date']));
        $dailyRate = getDailyAverageExchangeRate($pdo, $expenseDate, $tenant_id);

        // If no daily rate, use period average
        if ($dailyRate === null) {
            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
        }

        $expenseUsdToAfs += $expense['amount'] * $dailyRate;
    }
    
    // Calculate profit/loss
    $profitLossData = [
        'USD' => $incomeData['USD'] - $expenseData['USD'],
        'AFS' => $incomeData['AFS'] - $expenseData['AFS'],
        'EUR' => $incomeData['EUR'] - $expenseData['EUR'],
        'DARHAM' => $incomeData['DARHAM'] - $expenseData['DARHAM']
    ];
    
    // Calculate USD converted to AFS (without adding to AFS totals)
    $usdToAfsOnly = 0;
    
    // Sum up all USD to AFS conversions
    foreach ($sources as $source => $amounts) {
        if ($amounts['USD'] <= 0) {
            continue;
        }

        $afsConverted = 0;

        // Special handling for Additional Payments
        if ($source === 'Additional Payments') {
            $additionalQuery = "
                SELECT id, created_at, profit
                FROM additional_payments
                WHERE currency = 'USD'
                AND created_at BETWEEN ? AND ?
                AND tenant_id = ?
            ";
            $additionalStmt = $pdo->prepare($additionalQuery);
            $additionalStmt->execute([$startDate, $endDate, $tenant_id]);

            $additionalConverted = 0;

            while ($payment = $additionalStmt->fetch(PDO::FETCH_ASSOC)) {
                $paymentDate = date('Y-m-d', strtotime($payment['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $paymentDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $additionalConverted += $payment['profit'] * $dailyRate;
            }

            $afsConverted = $additionalConverted;
        }
        // Special handling for Ticket Sales
        elseif ($source === 'Ticket Sales') {
            $ticketQuery = "
                SELECT created_at, profit
                FROM ticket_bookings
                WHERE currency = 'USD'
                AND created_at BETWEEN ? AND ?
                AND tenant_id = ?
            ";
            $ticketStmt = $pdo->prepare($ticketQuery);
            $ticketStmt->execute([$startDate, $endDate, $tenant_id]);

            $ticketConverted = 0;

            while ($booking = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $ticketConverted += $booking['profit'] * $dailyRate;
            }

            $afsConverted = $ticketConverted;
        }
        // Special handling for Date Changes
        elseif ($source === 'Date Changes') {
            $dateQuery = "
                SELECT dt.created_at, dt.service_penalty as profit
                FROM date_change_tickets dt
                JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                WHERE dt.currency = 'USD'
                AND dt.created_at BETWEEN ? AND ?
                AND dt.tenant_id = ?
            ";
            $dateStmt = $pdo->prepare($dateQuery);
            $dateStmt->execute([$startDate, $endDate, $tenant_id]);

            $dateConverted = 0;

            while ($change = $dateStmt->fetch(PDO::FETCH_ASSOC)) {
                $changeDate = date('Y-m-d', strtotime($change['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $changeDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $dateConverted += $change['profit'] * $dailyRate;
            }

            $afsConverted = $dateConverted;
        }
        // Special handling for Visa Services
        elseif ($source === 'Visa Services') {
            $visaQuery = "
                SELECT created_at, profit
                FROM visa_applications
                WHERE currency = 'USD'
                AND created_at BETWEEN ? AND ?
                AND tenant_id = ?
            ";
            $visaStmt = $pdo->prepare($visaQuery);
            $visaStmt->execute([$startDate, $endDate, $tenant_id]);

            $visaConverted = 0;

            while ($application = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
                $applicationDate = date('Y-m-d', strtotime($application['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $applicationDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $visaConverted += $application['profit'] * $dailyRate;
            }

            $afsConverted = $visaConverted;
        }
        // Special handling for Umrah Bookings
        elseif ($source === 'Umrah Bookings') {
            $umrahQuery = "
                SELECT created_at, profit
                FROM umrah_bookings
                WHERE currency = 'USD'
                AND created_at BETWEEN ? AND ?
                AND tenant_id = ?
            ";
            $umrahStmt = $pdo->prepare($umrahQuery);
            $umrahStmt->execute([$startDate, $endDate, $tenant_id]);

            $umrahConverted = 0;

            while ($booking = $umrahStmt->fetch(PDO::FETCH_ASSOC)) {
                $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $umrahConverted += $booking['profit'] * $dailyRate;
            }

            $afsConverted = $umrahConverted;
        }
        // Special handling for Ticket Weights
        elseif ($source === 'Ticket Weights') {
            $weightsQuery = "
                SELECT tw.created_at, tw.profit
                FROM ticket_weights tw
                JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                WHERE tb.currency = 'USD'
                AND tw.created_at BETWEEN ? AND ?
                AND tw.tenant_id = ?
            ";
            $weightsStmt = $pdo->prepare($weightsQuery);
            $weightsStmt->execute([$startDate, $endDate, $tenant_id]);

            $weightsConverted = 0;

            while ($weight = $weightsStmt->fetch(PDO::FETCH_ASSOC)) {
                $weightDate = date('Y-m-d', strtotime($weight['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $weightDate, $tenant_id);

                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $weightsConverted += $weight['profit'] * $dailyRate;
            }

            $afsConverted = $weightsConverted;
        }
        // For sources with direct AFS transactions
        else {
            $sourceQuery = getSourceQuery($source);
            if (!empty($sourceQuery)) {
                try {
                    $stmt = $pdo->prepare($sourceQuery);
                    $stmt->execute([$startDate, $endDate, $tenant_id]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $afsConverted = floatval($data['afs_converted'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error getting USD conversion for $source: " . $e->getMessage());
                    continue;
                }
            }
        }

        $usdToAfsOnly += $afsConverted;
    }
    
    // Add summary data
    $summarySheet->setCellValue('A7', 'Total Income');
    $summarySheet->setCellValue('B7', $incomeData['USD']);
    $summarySheet->setCellValue('C7', $pureAfsTotal); // Pure AFS
    $summarySheet->setCellValue('D7', $usdToAfsOnly);
    $summarySheet->setCellValue('E7', $pureAfsTotal + $usdToAfsOnly); // Total
    $summarySheet->setCellValue('F7', $incomeData['EUR']);
    $summarySheet->setCellValue('G7', $incomeData['DARHAM']);
    $summarySheet->getStyle('B7:G7')->getNumberFormat()->setFormatCode($currencyFormat);
    
    $summarySheet->setCellValue('A8', 'Total Expenses');
    $summarySheet->setCellValue('B8', $expenseData['USD']);
    $summarySheet->setCellValue('C8', $expenseData['AFS']);
    $summarySheet->setCellValue('D8', $expenseUsdToAfs);  // USD to AFS conversion for expenses
    $summarySheet->setCellValue('E8', $expenseData['AFS'] + $expenseUsdToAfs); // Total
    $summarySheet->setCellValue('F8', $expenseData['EUR']);
    $summarySheet->setCellValue('G8', $expenseData['DARHAM']);
    $summarySheet->getStyle('B8:G8')->getNumberFormat()->setFormatCode($currencyFormat);
    
    // Calculate profit/loss
    $pureAfsProfit = $pureAfsTotal - $expenseData['AFS'];
    $totalAfsProfit = ($pureAfsTotal + $usdToAfsOnly) - ($expenseData['AFS'] + $expenseUsdToAfs);

    $summarySheet->setCellValue('A9', 'Profit/Loss');
    $summarySheet->setCellValue('B9', $profitLossData['USD']);
    $summarySheet->setCellValue('C9', $pureAfsProfit);
    $summarySheet->setCellValue('D9', $usdToAfsOnly - $expenseUsdToAfs); // Net USD to AFS conversion
    $summarySheet->setCellValue('E9', $totalAfsProfit);
    $summarySheet->setCellValue('F9', $profitLossData['EUR']);
    $summarySheet->setCellValue('G9', $profitLossData['DARHAM']);
    $summarySheet->getStyle('B9:G9')->getNumberFormat()->setFormatCode($currencyFormat);
    
    // Apply conditional formatting for profit/loss (green for profit, red for loss)
    $profitStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C6EFCE']],
        'font' => ['color' => ['rgb' => '006100']]
    ];
    
    $lossStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFC7CE']],
        'font' => ['color' => ['rgb' => '9C0006']]
    ];
    
    foreach (['B9', 'C9', 'D9', 'E9', 'F9', 'G9'] as $cell) {
        $value = $summarySheet->getCell($cell)->getValue();
        if ($value >= 0) {
            $summarySheet->getStyle($cell)->applyFromArray($profitStyle);
        } else {
            $summarySheet->getStyle($cell)->applyFromArray($lossStyle);
        }
    }
    
    $summarySheet->getStyle('A7:G9')->applyFromArray($dataStyle);

    // Set row position after main summary table
    $row = 9;

    // Add space
    $summarySheet->setCellValue('A' . ($row + 2), 'INCOME BY SOURCE');
    $summarySheet->getStyle('A' . ($row + 2))->getFont()->setBold(true)->setSize(14);

    // Income by source headers
    $incomeHeaderRow = $row + 4;
    $summarySheet->setCellValue('A' . $incomeHeaderRow, 'Source');
    $summarySheet->setCellValue('B' . $incomeHeaderRow, 'USD');
    $summarySheet->setCellValue('C' . $incomeHeaderRow, 'Pure AFS');
    $summarySheet->setCellValue('D' . $incomeHeaderRow, 'USD to AFS');
    $summarySheet->setCellValue('E' . $incomeHeaderRow, 'Total');
    $summarySheet->setCellValue('F' . $incomeHeaderRow, 'EUR');
    $summarySheet->setCellValue('G' . $incomeHeaderRow, 'DARHAM');
    $summarySheet->getStyle('A' . $incomeHeaderRow . ':G' . $incomeHeaderRow)->applyFromArray($headerStyle);

    $row = $incomeHeaderRow + 1;
    $pureAfsTotal = 0;

    foreach ($sources as $source => $amounts) {
        // Get the converted USD-to-AFS amount for this source
        $sourceUsdToAfs = 0;
        
        // Special handling for Additional Payments
        if ($source === 'Additional Payments' && $amounts['USD'] > 0) {
            // Get additional payments with USD currency
            $additionalQuery = "
                SELECT id, created_at, profit
                FROM additional_payments
                WHERE currency = 'USD'
                AND created_at BETWEEN ? AND ?
                AND tenant_id = ?
            ";
            $additionalStmt = $pdo->prepare($additionalQuery);
            $additionalStmt->execute([$startDate, $endDate, $tenant_id]);
            
            $additionalConverted = 0;
            
            // Process each payment using the daily rate for its date
            while ($payment = $additionalStmt->fetch(PDO::FETCH_ASSOC)) {
                $paymentDate = date('Y-m-d', strtotime($payment['created_at']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $paymentDate, $tenant_id);

                // If no daily rate, use period average
                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }
                
                $additionalConverted += $payment['profit'] * $dailyRate;
            }
            
            $sourceUsdToAfs = $additionalConverted;
        } else {
            // For other sources with the afs_converted field
            $sourceQuery = getSourceQuery($source);
            if (!empty($sourceQuery)) {
                $stmt = $pdo->prepare($sourceQuery);
                $stmt->execute([$startDate, $endDate, $tenant_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                $sourceUsdToAfs = floatval($data['afs_converted'] ?? 0);

                // Special handling for Ticket Sales when afs_converted is 0
                if ($source === 'Ticket Sales' && $sourceUsdToAfs == 0 && $amounts['USD'] > 0) {
                    $ticketQuery = "
                        SELECT created_at, profit
                        FROM ticket_bookings
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $ticketStmt = $pdo->prepare($ticketQuery);
                    $ticketStmt->execute([$startDate, $endDate, $tenant_id]);

                    $ticketConverted = 0;

                    while ($booking = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                        $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $ticketConverted += $booking['profit'] * $dailyRate;
                    }

                    $sourceUsdToAfs = $ticketConverted;
                }

                // Special handling for Date Changes when afs_converted is 0
                if ($source === 'Date Changes' && $sourceUsdToAfs == 0 && $amounts['USD'] > 0) {
                    $dateQuery = "
                        SELECT dt.created_at, dt.service_penalty as profit
                        FROM date_change_tickets dt
                        JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                        WHERE dt.currency = 'USD'
                        AND dt.created_at BETWEEN ? AND ?
                        AND dt.tenant_id = ?
                    ";
                    $dateStmt = $pdo->prepare($dateQuery);
                    $dateStmt->execute([$startDate, $endDate, $tenant_id]);

                    $dateConverted = 0;

                    while ($change = $dateStmt->fetch(PDO::FETCH_ASSOC)) {
                        $changeDate = date('Y-m-d', strtotime($change['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $changeDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $dateConverted += $change['profit'] * $dailyRate;
                    }

                    $sourceUsdToAfs = $dateConverted;
                }

                // Special handling for Visa Services when afs_converted is 0
                if ($source === 'Visa Services' && $sourceUsdToAfs == 0 && $amounts['USD'] > 0) {
                    $visaQuery = "
                        SELECT created_at, profit
                        FROM visa_applications
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $visaStmt = $pdo->prepare($visaQuery);
                    $visaStmt->execute([$startDate, $endDate, $tenant_id]);

                    $visaConverted = 0;

                    while ($application = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
                        $applicationDate = date('Y-m-d', strtotime($application['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $applicationDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $visaConverted += $application['profit'] * $dailyRate;
                    }

                    $sourceUsdToAfs = $visaConverted;
                }

                // Special handling for Umrah Bookings when exchange_rate is null/0
                if ($source === 'Umrah Bookings' && $sourceUsdToAfs == 0 && $amounts['USD'] > 0) {
                    $umrahQuery = "
                        SELECT created_at, profit
                        FROM umrah_bookings
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $umrahStmt = $pdo->prepare($umrahQuery);
                    $umrahStmt->execute([$startDate, $endDate, $tenant_id]);

                    $umrahConverted = 0;

                    while ($booking = $umrahStmt->fetch(PDO::FETCH_ASSOC)) {
                        $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $umrahConverted += $booking['profit'] * $dailyRate;
                    }

                    $sourceUsdToAfs = $umrahConverted;
                }

                // Special handling for Ticket Weights when exchange_rate is null/0
                if ($source === 'Ticket Weights' && $sourceUsdToAfs == 0 && $amounts['USD'] > 0) {
                    $weightsQuery = "
                        SELECT tw.created_at, tw.profit
                        FROM ticket_weights tw
                        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                        WHERE tb.currency = 'USD'
                        AND tw.created_at BETWEEN ? AND ?
                        AND tw.tenant_id = ?
                    ";
                    $weightsStmt = $pdo->prepare($weightsQuery);
                    $weightsStmt->execute([$startDate, $endDate, $tenant_id]);

                    $weightsConverted = 0;

                    while ($weight = $weightsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $weightDate = date('Y-m-d', strtotime($weight['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $weightDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $weightsConverted += $weight['profit'] * $dailyRate;
                    }

                    $sourceUsdToAfs = $weightsConverted;
                }
            }
        }

        // Calculate pure AFS
        $pureAfs = $amounts['AFS'] - $sourceUsdToAfs;
        $pureAfsTotal += $pureAfs;

        $summarySheet->setCellValue('A' . $row, $source);
        $summarySheet->setCellValue('B' . $row, $amounts['USD']);
        $summarySheet->setCellValue('C' . $row, $pureAfs); // Pure AFS
        $summarySheet->setCellValue('D' . $row, $sourceUsdToAfs);
        $summarySheet->setCellValue('E' . $row, $amounts['AFS']); // Total
        $summarySheet->setCellValue('F' . $row, $amounts['EUR']);
        $summarySheet->setCellValue('G' . $row, $amounts['DARHAM']);
        $summarySheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }
    
    $summarySheet->getStyle('A' . ($incomeHeaderRow + 1) . ':G' . ($row - 1))->applyFromArray($dataStyle);
    
    // Add totals row for income by source
    $summarySheet->setCellValue('A' . $row, 'TOTAL');
    $summarySheet->setCellValue('B' . $row, array_sum(array_column($sources, 'USD')));
    $summarySheet->setCellValue('C' . $row, $pureAfsTotal); // Pure AFS total
    $summarySheet->setCellValue('D' . $row, $usdToAfsOnly);
    $summarySheet->setCellValue('E' . $row, $pureAfsTotal + $usdToAfsOnly); // Total
    $summarySheet->setCellValue('F' . $row, array_sum(array_column($sources, 'EUR')));
    $summarySheet->setCellValue('G' . $row, array_sum(array_column($sources, 'DARHAM')));
    
    $summarySheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $summarySheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $summarySheet->getStyle('A' . ($incomeHeaderRow + 1) . ':G' . $row)->applyFromArray($dataStyle);
    
    // Add space
    $row += 2;
    $summarySheet->setCellValue('A' . $row, 'EXPENSES BY CATEGORY');
    $summarySheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    
    // Expense by category headers
    $expenseCategoryRow = $row + 2;
    $summarySheet->setCellValue('A' . $expenseCategoryRow, 'Category');
    $summarySheet->setCellValue('B' . $expenseCategoryRow, 'USD');
    $summarySheet->setCellValue('C' . $expenseCategoryRow, 'AFS');
    $summarySheet->setCellValue('D' . $expenseCategoryRow, 'USD to AFS');
    $summarySheet->setCellValue('E' . $expenseCategoryRow, 'Total (AFS+Converted)');
    $summarySheet->setCellValue('F' . $expenseCategoryRow, 'EUR');
    $summarySheet->setCellValue('G' . $expenseCategoryRow, 'DARHAM');
    $summarySheet->getStyle('A' . $expenseCategoryRow . ':G' . $expenseCategoryRow)->applyFromArray($headerStyle);
    
    // Fetch expenses by category including salary payments
    $expenseCategoryQuery = "
        SELECT
            category_name as category,
            currency,
            SUM(amount) as total
        FROM (
            SELECT
                ec.name as category_name,
                e.currency,
                e.amount
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.date BETWEEN ? AND ?
            AND e.tenant_id = ?

            UNION ALL

            SELECT
                'Salary Payments' as category_name,
                sp.currency,
                sp.amount
            FROM salary_payments sp
            WHERE sp.payment_date BETWEEN ? AND ?
            AND sp.tenant_id = ?
        ) combined_expenses
        GROUP BY category, currency
        ORDER BY category
    ";
    $expenseCategoryStmt = $pdo->prepare($expenseCategoryQuery);
    $expenseCategoryStmt->execute([$startDate, $endDate, $tenant_id, $startDate, $endDate, $tenant_id]);
    
    $categories = [];
    $row = $expenseCategoryRow + 1;
    $categoryTotals = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0,
        'usd_to_afs' => 0
    ];
    
    while ($data = $expenseCategoryStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($categories[$data['category']])) {
            $categories[$data['category']] = [
                'USD' => 0,
                'AFS' => 0,
                'EUR' => 0,
                'DARHAM' => 0,
                'usd_to_afs' => 0
            ];
        }
        $categories[$data['category']][$data['currency']] = $data['total'];
        $categoryTotals[$data['currency']] += $data['total'];
    }

    // Calculate USD to AFS conversion for each expense category including salary payments
    $expenseCategoryConversionQuery = "
        SELECT
            category_name as category,
            payment_date as date,
            amount
        FROM (
            SELECT
                ec.name as category_name,
                e.date as payment_date,
                e.amount
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.currency = 'USD'
            AND e.date BETWEEN ? AND ?
            AND e.tenant_id = ?

            UNION ALL

            SELECT
                'Salary Payments' as category_name,
                sp.payment_date,
                sp.amount
            FROM salary_payments sp
            WHERE sp.currency = 'USD'
            AND sp.payment_date BETWEEN ? AND ?
            AND sp.tenant_id = ?
        ) combined_expenses
        ORDER BY category_name
    ";
    $expenseCategoryConversionStmt = $pdo->prepare($expenseCategoryConversionQuery);
    $expenseCategoryConversionStmt->execute([$startDate, $endDate, $tenant_id, $startDate, $endDate, $tenant_id]);

    while ($data = $expenseCategoryConversionStmt->fetch(PDO::FETCH_ASSOC)) {
        $expenseDate = date('Y-m-d', strtotime($data['date']));
        $dailyRate = getDailyAverageExchangeRate($pdo, $expenseDate, $tenant_id);

        // If no daily rate, use period average
        if ($dailyRate === null) {
            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
        }

        $convertedAmount = $data['amount'] * $dailyRate;

        // Add to the category's usd_to_afs amount
        if (isset($categories[$data['category']])) {
            $categories[$data['category']]['usd_to_afs'] += $convertedAmount;
            $categoryTotals['usd_to_afs'] += $convertedAmount;
        }
    }

    foreach ($categories as $category => $amounts) {
        $totalAfs = $amounts['AFS'] + $amounts['usd_to_afs'];
        
        $summarySheet->setCellValue('A' . $row, $category);
        $summarySheet->setCellValue('B' . $row, $amounts['USD']);
        $summarySheet->setCellValue('C' . $row, $amounts['AFS']);
        $summarySheet->setCellValue('D' . $row, $amounts['usd_to_afs']);
        $summarySheet->setCellValue('E' . $row, $totalAfs);
        $summarySheet->setCellValue('F' . $row, $amounts['EUR']);
        $summarySheet->setCellValue('G' . $row, $amounts['DARHAM']);
        $summarySheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }
    
    // Add totals row for expenses
    $summarySheet->setCellValue('A' . $row, 'TOTAL');
    $summarySheet->setCellValue('B' . $row, $categoryTotals['USD']);
    $summarySheet->setCellValue('C' . $row, $categoryTotals['AFS']);
    $summarySheet->setCellValue('D' . $row, $categoryTotals['usd_to_afs']);
    $summarySheet->setCellValue('E' . $row, $categoryTotals['AFS'] + $categoryTotals['usd_to_afs']);
    $summarySheet->setCellValue('F' . $row, $categoryTotals['EUR']);
    $summarySheet->setCellValue('G' . $row, $categoryTotals['DARHAM']);
    
    $summarySheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $summarySheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    
    $summarySheet->getStyle('A' . ($expenseCategoryRow + 1) . ':G' . $row)->applyFromArray($dataStyle);
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $summarySheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create Income Details sheet
    $incomeSheet = $spreadsheet->createSheet();
    $incomeSheet->setTitle('Income Details');
    
    // Income details headers
    $incomeSheet->setCellValue('A1', 'Date');
    $incomeSheet->setCellValue('B1', 'Description');
    $incomeSheet->setCellValue('C1', 'Source');
    $incomeSheet->setCellValue('D1', 'Amount');
    $incomeSheet->setCellValue('E1', 'Currency');
    $incomeSheet->setCellValue('F1', 'Exchange Rate');
    $incomeSheet->setCellValue('G1', 'AFS Equivalent');
    $incomeSheet->setCellValue('H1', 'Exchange Amount');
    $incomeSheet->setCellValue('I1', 'Reference');
    $incomeSheet->getStyle('A1:I1')->applyFromArray($headerStyle);
    
    // Get average exchange rate for USD to AFS
    $avgExchangeRate = getAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
    
    // Fetch income details
    $incomeDetailsQuery = "
        SELECT
            created_at as date,
            CONCAT('Ticket: ', pnr) as description,
            'Ticket Sales' as source,
            profit as amount,
            currency,
            exchange_rate,
            id as reference_id
        FROM ticket_bookings
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        UNION ALL

        SELECT
            created_at as date,
            CONCAT('Reservation: ', pnr) as description,
            'Ticket Reservations' as source,
            profit as amount,
            currency,
            exchange_rate,
            id as reference_id
        FROM ticket_reservations
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        UNION ALL

        SELECT
            rt.created_at as date,
            CONCAT('Refund: ', tb.pnr) as description,
            'Ticket Refunds' as source,
            CASE
                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                ELSE rt.service_penalty
            END as amount,
            rt.currency,
            tb.exchange_rate,
            rt.id as reference_id
        FROM refunded_tickets rt
        LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
        WHERE rt.created_at BETWEEN ? AND ?
        AND rt.tenant_id = ?

        UNION ALL

        SELECT
            dt.created_at as date,
            CONCAT('Date Change: ', tb.pnr) as description,
            'Date Changes' as source,
            dt.service_penalty as amount,
            dt.currency,
            tb.exchange_rate,
            dt.id as reference_id
        FROM date_change_tickets dt
        JOIN ticket_bookings tb ON dt.ticket_id = tb.id
        WHERE dt.created_at BETWEEN ? AND ?
        AND dt.tenant_id = ?

        UNION ALL

        SELECT
            created_at as date,
            CONCAT('Visa: ', applicant_name) as description,
            'Visa Services' as source,
            profit as amount,
            currency,
            exchange_rate,
            id as reference_id
        FROM visa_applications
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        UNION ALL

        SELECT
            created_at as date,
            CONCAT('Umrah: ', name) as description,
            'Umrah Bookings' as source,
            profit as amount,
            currency,
            exchange_rate,
            booking_id as reference_id
        FROM umrah_bookings
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        UNION ALL

        SELECT
            created_at as date,
            CONCAT('Guest: ', first_name, ' ', last_name) as description,
            'Hotel Bookings' as source,
            profit as amount,
            currency,
            exchange_rate,
            id as reference_id
        FROM hotel_bookings
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        UNION ALL

        SELECT
            tw.created_at as date,
            CONCAT('Weight: ',tw.weight, 'kg for ', tb.passenger_name) as description,
            'Ticket Weights' as source,
            tw.profit as amount,
            tb.currency,
            tw.exchange_rate,
            tw.id as reference_id
        FROM ticket_weights tw
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
        WHERE tw.created_at BETWEEN ? AND ?
        AND tw.tenant_id = ?

        UNION ALL

        SELECT
            created_at as date,
            CONCAT('Additional Payment: ', payment_type) as description,
            'Additional Payments' as source,
            profit as amount,
            currency,
            NULL as exchange_rate,
            id as reference_id
        FROM additional_payments
        WHERE created_at BETWEEN ? AND ?
        AND tenant_id = ?

        ORDER BY date DESC
    ";
    
    $incomeDetailsStmt = $pdo->prepare($incomeDetailsQuery);
    $incomeDetailsStmt->execute([
        $startDate, $endDate, $tenant_id,  // Ticket bookings
        $startDate, $endDate, $tenant_id,  // Ticket reservations
        $startDate, $endDate, $tenant_id,  // Refunded tickets
        $startDate, $endDate, $tenant_id,  // Date change tickets
        $startDate, $endDate, $tenant_id,  // Visa applications
        $startDate, $endDate, $tenant_id,  // Umrah bookings
        $startDate, $endDate, $tenant_id,  // Hotel bookings
        $startDate, $endDate, $tenant_id,  // Ticket weights
        $startDate, $endDate, $tenant_id   // Additional payments
    ]);
    
    $row = 2;
    while ($data = $incomeDetailsStmt->fetch(PDO::FETCH_ASSOC)) {
        $incomeSheet->setCellValue('A' . $row, date('d/m/Y', strtotime($data['date'])));
        $incomeSheet->setCellValue('B' . $row, $data['description']);
        $incomeSheet->setCellValue('C' . $row, $data['source']);
        $incomeSheet->setCellValue('D' . $row, $data['amount']);
        $incomeSheet->setCellValue('E' . $row, $data['currency']);
        
        // Exchange rate handling
        $exchangeRate = null;
        $afsEquivalent = null;
        $exchangeAmount = null;
        
        if ($data['currency'] == 'USD') {
            // For tables with exchange_rate fields, use their values if available
            if (!empty($data['exchange_rate']) && $data['exchange_rate'] > 0) {
                $exchangeRate = $data['exchange_rate'];
            } else {
                // For tables without exchange_rate or null values, use daily rate
                $transactionDate = date('Y-m-d', strtotime($data['date']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $transactionDate, $tenant_id);

                // If no daily rate, use period average
                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }
                
                $exchangeRate = $dailyRate;
            }
                
            $afsEquivalent = $data['amount'] * $exchangeRate;
            $exchangeAmount = $data['amount'] * $exchangeRate;
            
            $incomeSheet->setCellValue('F' . $row, $exchangeRate);
            $incomeSheet->setCellValue('G' . $row, $afsEquivalent);
            $incomeSheet->setCellValue('H' . $row, $exchangeAmount);
        } else {
            $incomeSheet->setCellValue('F' . $row, '');
            $incomeSheet->setCellValue('G' . $row, '');
            $incomeSheet->setCellValue('H' . $row, '');
        }
        
        $incomeSheet->setCellValue('I' . $row, $data['reference_id']);
        $incomeSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $incomeSheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $incomeSheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }
    
    $incomeSheet->getStyle('A2:I' . ($row - 1))->applyFromArray($dataStyle);
    
    // Initialize totals array
    $incomeTotals = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];
    $totalAfsEquivalent = 0;
    $totalExchangeAmount = 0;
    
    // Reset statement to get totals
    $incomeDetailsStmt->execute([
        $startDate, $endDate, $tenant_id,  // Ticket bookings
        $startDate, $endDate, $tenant_id,  // Ticket reservations
        $startDate, $endDate, $tenant_id,  // Refunded tickets
        $startDate, $endDate, $tenant_id,  // Date change tickets
        $startDate, $endDate, $tenant_id,  // Visa applications
        $startDate, $endDate, $tenant_id,  // Umrah bookings
        $startDate, $endDate, $tenant_id,  // Hotel bookings
        $startDate, $endDate, $tenant_id,  // Ticket weights
        $startDate, $endDate, $tenant_id   // Additional payments
    ]);
    
    // Calculate totals
    while ($data = $incomeDetailsStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $data['currency'];
        $amount = floatval($data['amount']);

        // Add to currency total
        if (isset($incomeTotals[$currency])) {
            $incomeTotals[$currency] += $amount;
        } else {
            $incomeTotals[$currency] = $amount;
        }

        // Calculate exchange rate and converted amounts for USD
        if ($currency == 'USD') {
            // For tables with exchange_rate fields, use their values if available
            if (!empty($data['exchange_rate']) && $data['exchange_rate'] > 0) {
                $exchangeRate = $data['exchange_rate'];
            } else {
                // For tables without exchange_rate or null values, use daily rate
                $transactionDate = date('Y-m-d', strtotime($data['date']));
                $dailyRate = getDailyAverageExchangeRate($pdo, $transactionDate, $tenant_id);

                // If no daily rate, use period average
                if ($dailyRate === null) {
                    $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                }

                $exchangeRate = $dailyRate;
            }

            $afsEquivalent = $amount * $exchangeRate;
            $exchangeAmount = $amount * $exchangeRate;

            $totalAfsEquivalent += $afsEquivalent;
            $totalExchangeAmount += $exchangeAmount;
        }
    }
    
    // Create a visual separator with thick border
    $row += 1;
    $incomeSheet->setCellValue('A' . $row, '');
    $incomeSheet->getStyle('A' . $row . ':I' . $row)->getBorders()
        ->getBottom()
        ->setBorderStyle(Border::BORDER_MEDIUM)
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4472C4'));
    $row += 1;
    
    // Define totals section style
    $totalsSectionStyle = [
        'font' => ['bold' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    
    $totalsHeaderStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E7F1FF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    
    // Add totals section header
    $incomeSheet->setCellValue('A' . $row, 'INCOME SUMMARY');
    $incomeSheet->mergeCells('A' . $row . ':I' . $row);
    $incomeSheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($totalsHeaderStyle);
    $incomeSheet->getRowDimension($row)->setRowHeight(25); // Make header taller
    $row++;
    
    // Add currency totals header
    $incomeSheet->setCellValue('A' . $row, 'TOTALS BY CURRENCY');
    $incomeSheet->mergeCells('A' . $row . ':I' . $row);
    $incomeSheet->getStyle('A' . $row)->getFont()->setBold(true);
    $incomeSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $incomeSheet->getStyle('A' . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F2F7FE']]
    ]);
    $row++;
    
    // Headers for currency totals table
    $incomeSheet->setCellValue('B' . $row, 'Currency');
    $incomeSheet->setCellValue('D' . $row, 'Amount');
    $incomeSheet->getStyle('B' . $row . ':D' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    // Add currency totals
    $currencyRow = $row;
    foreach ($incomeTotals as $currency => $total) {
        if ($total > 0) {
            $incomeSheet->setCellValue('B' . $row, $currency);
            $incomeSheet->setCellValue('D' . $row, $total);
            $incomeSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }
    }
    
    // Apply alternating row colors to currency totals
    for ($i = $currencyRow; $i < $row; $i++) {
        if ($i % 2 == 0) {
            $incomeSheet->getStyle('B' . $i . ':D' . $i)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F9FAFC']]
            ]);
        }
    }
    
    // Remove the incorrect summing of different currencies
    // Add a bottom border to the last currency row to visually close the section
    $incomeSheet->getStyle('B' . ($row-1) . ':D' . ($row-1))->getBorders()
        ->getBottom()
        ->setBorderStyle(Border::BORDER_THIN)
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4472C4'));
        
    $row += 2; // Add extra space
    
    // Add USD conversion totals header
    $incomeSheet->setCellValue('A' . $row, 'USD CONVERSION SUMMARY');
    $incomeSheet->mergeCells('A' . $row . ':I' . $row);
    $incomeSheet->getStyle('A' . $row)->getFont()->setBold(true);
    $incomeSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $incomeSheet->getStyle('A' . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F2F7FE']]
    ]);
    $row++;
    
    // USD conversion table
    $conversionRow = $row;
    
    // Headers
    $incomeSheet->setCellValue('B' . $row, 'Conversion Type');
    $incomeSheet->setCellValue('G' . $row, 'AFS Equivalent');
    $incomeSheet->setCellValue('H' . $row, 'Exchange Amount');
    $incomeSheet->getStyle('B' . $row . ':H' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    // Conversion data
    $incomeSheet->setCellValue('B' . $row, 'USD to AFS Conversion');
    $incomeSheet->setCellValue('G' . $row, $totalAfsEquivalent);
    $incomeSheet->setCellValue('H' . $row, $totalExchangeAmount);
    $incomeSheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $incomeSheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $incomeSheet->getStyle('B' . $row . ':H' . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F9FAFC']]
    ]);
    
    // Add note about USD conversion
    $row += 2;
    $incomeSheet->setCellValue('B' . $row, 'Note: AFS Equivalent shows the value in AFS based on the exchange rate.');
    $incomeSheet->mergeCells('B' . $row . ':H' . $row);
    $incomeSheet->getStyle('B' . $row)->getFont()->setItalic(true);
    $incomeSheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    
    // Create a visual footer
    $row += 2;
    $incomeSheet->setCellValue('A' . $row, '');
    $incomeSheet->mergeCells('A' . $row . ':I' . $row);
    $incomeSheet->getStyle('A' . $row . ':I' . $row)->getBorders()
        ->getTop()
        ->setBorderStyle(Border::BORDER_MEDIUM)
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4472C4'));
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $incomeSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create Expense Details sheet
    $expenseSheet = $spreadsheet->createSheet();
    $expenseSheet->setTitle('Expense Details');
    
    // Expense details headers
    $expenseSheet->setCellValue('A1', 'Date');
    $expenseSheet->setCellValue('B1', 'Description');
    $expenseSheet->setCellValue('C1', 'Category');
    $expenseSheet->setCellValue('D1', 'Amount');
    $expenseSheet->setCellValue('E1', 'Currency');
    $expenseSheet->setCellValue('F1', 'Account/Allocation');
    $expenseSheet->getStyle('A1:F1')->applyFromArray($headerStyle);
    
    // Initialize totals array
    $expenseTotals = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];
    
    // Fetch expense details
    $expenseDetailsQuery = "
        SELECT
            date as expense_date,
            description,
            category,
            amount,
            currency,
            source
        FROM (
            SELECT
                e.date,
                e.description,
                ec.name as category,
                e.amount,
                e.currency,
                CASE
                    WHEN e.allocation_id IS NOT NULL THEN CONCAT('Allocation: ', ba.allocation_date)
                    WHEN e.main_account_id IS NOT NULL THEN CONCAT('Account: ', ma.name)
                    ELSE ''
                END as source
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN budget_allocations ba ON e.allocation_id = ba.id
            LEFT JOIN main_account ma ON e.main_account_id = ma.id
            WHERE e.date BETWEEN ? AND ?
            AND e.tenant_id = ?

            UNION ALL

            SELECT
                sp.payment_date as date,
                CONCAT('Salary Payment: ', e.name) as description,
                'Salary Payments' as category,
                sp.amount,
                sp.currency,
                'Salary Payment' as source
            FROM salary_payments sp
            JOIN users e ON sp.user_id = e.id
            WHERE sp.payment_date BETWEEN ? AND ?
            AND sp.tenant_id = ?
        ) combined_expenses
        ORDER BY expense_date DESC
    ";
    $expenseDetailsStmt = $pdo->prepare($expenseDetailsQuery);
    $expenseDetailsStmt->execute([$startDate, $endDate, $tenant_id, $startDate, $endDate, $tenant_id]);
    
    $row = 2;
    while ($data = $expenseDetailsStmt->fetch(PDO::FETCH_ASSOC)) {
        $expenseSheet->setCellValue('A' . $row, date('d/m/Y', strtotime($data['expense_date'])));
        $expenseSheet->setCellValue('B' . $row, $data['description']);
        $expenseSheet->setCellValue('C' . $row, $data['category']);
        $expenseSheet->setCellValue('D' . $row, $data['amount']);
        $expenseSheet->setCellValue('E' . $row, $data['currency']);
        $expenseSheet->setCellValue('F' . $row, $data['source']);
        $expenseSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        
        // Add to totals
        $expenseTotals[$data['currency']] += $data['amount'];
        
        $row++;
    }
    
    // Add a blank row
    $row++;
    
    // Add totals section
    $expenseSheet->setCellValue('A' . $row, 'TOTALS BY CURRENCY');
    $expenseSheet->mergeCells('A' . $row . ':F' . $row);
    $expenseSheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    // Add currency totals
    foreach ($expenseTotals as $currency => $total) {
        $expenseSheet->setCellValue('A' . $row, $currency . ' Total:');
        $expenseSheet->setCellValue('D' . $row, $total);
        $expenseSheet->getStyle('A' . $row)->getFont()->setBold(true);
        $expenseSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }
    
    $expenseSheet->getStyle('A2:F' . ($row - 1))->applyFromArray($dataStyle);
    
    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $expenseSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create USD to AFS Conversion Sheet
    $conversionSheet = $spreadsheet->createSheet();
    $conversionSheet->setTitle('USD-AFS Conversion');
    
    // Conversion sheet headers
    $conversionSheet->setCellValue('A1', 'CURRENCY CONVERSION REPORT');
    $conversionSheet->mergeCells('A1:F1');
    $conversionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $conversionSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $conversionSheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
    $conversionSheet->mergeCells('A2:F2');
    $conversionSheet->getStyle('A2')->getFont()->setBold(true);
    
    $conversionSheet->setCellValue('A4', 'Average Exchange Rate: ' . number_format($avgExchangeRate, 5));
    $conversionSheet->mergeCells('A4:F4');
    $conversionSheet->getStyle('A4')->getFont()->setBold(true);
    
    // Conversion details headers
    $conversionSheet->setCellValue('A6', 'Date');
    $conversionSheet->setCellValue('B6', 'PNR/Reference');
    $conversionSheet->setCellValue('C6', 'Source');
    $conversionSheet->setCellValue('D6', 'USD Amount');
    $conversionSheet->setCellValue('E6', 'Exchange Rate');
    $conversionSheet->setCellValue('F6', 'AFS Equivalent');
    $conversionSheet->setCellValue('G6', 'Exchange Amount');
    $conversionSheet->getStyle('A6:G6')->applyFromArray($headerStyle);
    
    // Fetch USD transactions
    $conversionQuery = "
        SELECT
            tb.created_at as date,
            tb.pnr as reference,
            'Ticket Sales' as source,
            tb.profit as amount,
            CASE
                WHEN tb.exchange_rate IS NULL OR tb.exchange_rate = 0 THEN ?
                ELSE tb.exchange_rate
            END as exchange_rate
        FROM ticket_bookings tb
        WHERE tb.currency = 'USD' AND tb.created_at BETWEEN ? AND ?
        AND tb.tenant_id = ?

        UNION ALL

        SELECT
            tr.created_at as date,
            tr.pnr as reference,
            'Ticket Reservations' as source,
            tr.profit as amount,
            CASE
                WHEN tr.exchange_rate IS NULL OR tr.exchange_rate = 0 THEN ?
                ELSE tr.exchange_rate
            END as exchange_rate
        FROM ticket_reservations tr
        WHERE tr.currency = 'USD' AND tr.created_at BETWEEN ? AND ?
        AND tr.tenant_id = ?

        UNION ALL

        SELECT
            rt.created_at as date,
            CONCAT('Refund: ', tb.pnr) as reference,
            'Ticket Refunds' as source,
            CASE
                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                ELSE rt.service_penalty
            END as amount,
            CASE
                WHEN tb.exchange_rate IS NULL OR tb.exchange_rate = 0 THEN ?
                ELSE tb.exchange_rate
            END as exchange_rate
        FROM refunded_tickets rt
        JOIN ticket_bookings tb ON rt.ticket_id = tb.id
        WHERE rt.currency = 'USD' AND rt.created_at BETWEEN ? AND ?
        AND rt.tenant_id = ?

        UNION ALL

        SELECT
            dt.created_at as date,
            CONCAT('Date Change: ', tb.pnr) as reference,
            'Date Changes' as source,
            dt.service_penalty as amount,
            CASE
                WHEN tb.exchange_rate IS NULL OR tb.exchange_rate = 0 THEN ?
                ELSE tb.exchange_rate
            END as exchange_rate
        FROM date_change_tickets dt
        JOIN ticket_bookings tb ON dt.ticket_id = tb.id
        WHERE dt.currency = 'USD' AND dt.created_at BETWEEN ? AND ?
        AND dt.tenant_id = ?

        UNION ALL

        SELECT
            v.created_at as date,
            v.passport_number as reference,
            'Visa Services' as source,
            v.profit as amount,
            CASE
                WHEN v.exchange_rate IS NULL OR v.exchange_rate = 0 THEN ?
                ELSE v.exchange_rate
            END as exchange_rate
        FROM visa_applications v
        WHERE v.currency = 'USD' AND v.created_at BETWEEN ? AND ?
        AND v.tenant_id = ?

        UNION ALL

        SELECT
            u.created_at as date,
            u.name as reference,
            'Umrah Bookings' as source,
            u.profit as amount,
            CASE
                WHEN u.exchange_rate IS NULL OR u.exchange_rate = 0 THEN ?
                ELSE u.exchange_rate
            END as exchange_rate
        FROM umrah_bookings u
        WHERE u.currency = 'USD' AND u.created_at BETWEEN ? AND ?
        AND u.tenant_id = ?

        UNION ALL

        SELECT
            h.created_at as date,
            CONCAT(h.first_name, ' ', h.last_name) as reference,
            'Hotel Bookings' as source,
            h.profit as amount,
            CASE
                WHEN h.exchange_rate IS NULL OR h.exchange_rate = 0 THEN ?
                ELSE h.exchange_rate
            END as exchange_rate
        FROM hotel_bookings h
        WHERE h.currency = 'USD' AND h.created_at BETWEEN ? AND ?
        AND h.tenant_id = ?

        UNION ALL

        SELECT
            tw.created_at as date,
            CONCAT('Weight: ',tw.weight, 'kg') as reference,
            'Ticket Weights' as source,
            tw.profit as amount,
            CASE
                WHEN tw.exchange_rate IS NULL OR tw.exchange_rate = 0 THEN ?
                ELSE tw.exchange_rate
            END as exchange_rate
        FROM ticket_weights tw
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
        WHERE tb.currency = 'USD' AND tw.created_at BETWEEN ? AND ?
        AND tw.tenant_id = ?

        UNION ALL

        SELECT
            ap.created_at as date,
            ap.payment_type as reference,
            'Additional Payments' as source,
            ap.profit as amount,
            ? as exchange_rate
        FROM additional_payments ap
        WHERE ap.currency = 'USD' AND ap.created_at BETWEEN ? AND ?
        AND ap.tenant_id = ?

        ORDER BY date DESC
    ";
    
    $conversionStmt = $pdo->prepare($conversionQuery);
    $conversionStmt->execute([
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Ticket bookings
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Ticket reservations
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Refunded tickets
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Date change tickets
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Visa applications
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Umrah bookings
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Hotel bookings
        $avgExchangeRate, $startDate, $endDate, $tenant_id,  // Ticket weights
        $avgExchangeRate, $startDate, $endDate, $tenant_id   // Additional payments
    ]);
    
    $row = 7;
    $totalUsd = 0;
    $totalAfs = 0;
    
    while ($data = $conversionStmt->fetch(PDO::FETCH_ASSOC)) {
        $conversionSheet->setCellValue('A' . $row, date('d/m/Y', strtotime($data['date'])));
        $conversionSheet->setCellValue('B' . $row, $data['reference']);
        $conversionSheet->setCellValue('C' . $row, $data['source']);
        $conversionSheet->setCellValue('D' . $row, $data['amount']);
        
        // Get the appropriate exchange rate (daily rate if not available in transaction)
        if ($data['exchange_rate'] !== null && $data['exchange_rate'] > 0) {
            $exchangeRate = $data['exchange_rate'];
        } else {
            // Get daily rate for this transaction's date
            $transactionDate = date('Y-m-d', strtotime($data['date']));
            $dailyRate = getDailyAverageExchangeRate($pdo, $transactionDate, $tenant_id);

            // If no daily rate, use period average
            if ($dailyRate === null) {
                $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
            }
            
            $exchangeRate = $dailyRate;
        }
        
        $conversionSheet->setCellValue('E' . $row, $exchangeRate);
        
        $afsAmount = $data['amount'] * $exchangeRate;
        $conversionSheet->setCellValue('F' . $row, $afsAmount);
        $conversionSheet->setCellValue('G' . $row, $afsAmount);
        
        $totalUsd += $data['amount'];
        $totalAfs += $afsAmount;
        
        $conversionSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $conversionSheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $conversionSheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }
    
    // Add totals row
    $conversionSheet->setCellValue('A' . $row, 'TOTAL');
    $conversionSheet->mergeCells('A' . $row . ':C' . $row);
    $conversionSheet->setCellValue('D' . $row, $totalUsd);
    $conversionSheet->setCellValue('F' . $row, $totalAfs);
    $conversionSheet->setCellValue('G' . $row, $totalAfs);
    
    $conversionSheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $conversionSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $conversionSheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $conversionSheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    
    $conversionSheet->getStyle('A7:G' . $row)->applyFromArray($dataStyle);
    
    // Add summary section showing exchange rate information
    $summaryRow = $row + 2;
    $conversionSheet->setCellValue('A' . $summaryRow, 'EXCHANGE RATE SUMMARY');
    $conversionSheet->mergeCells('A' . $summaryRow . ':G' . $summaryRow);
    $conversionSheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(12);
    
    $conversionSheet->setCellValue('A' . ($summaryRow + 2), 'Total USD Amount:');
    $conversionSheet->setCellValue('C' . ($summaryRow + 2), $totalUsd);
    $conversionSheet->getStyle('C' . ($summaryRow + 2))->getNumberFormat()->setFormatCode($currencyFormat);
    
    $conversionSheet->setCellValue('A' . ($summaryRow + 3), 'Average Exchange Rate:');
    $conversionSheet->setCellValue('C' . ($summaryRow + 3), $avgExchangeRate);
    $conversionSheet->getStyle('C' . ($summaryRow + 3))->getNumberFormat()->setFormatCode('#,##0.00000');
    
    $conversionSheet->setCellValue('A' . ($summaryRow + 4), 'Total AFS Equivalent:');
    $conversionSheet->setCellValue('C' . ($summaryRow + 4), $totalAfs);
    $conversionSheet->getStyle('C' . ($summaryRow + 4))->getNumberFormat()->setFormatCode($currencyFormat);
    
    $conversionSheet->setCellValue('A' . ($summaryRow + 5), 'Total Exchange Amount:');
    $conversionSheet->setCellValue('C' . ($summaryRow + 5), $totalAfs);
    $conversionSheet->getStyle('C' . ($summaryRow + 5))->getNumberFormat()->setFormatCode($currencyFormat);
    
    // Add a row for just USD converted to AFS (without adding to AFS)
    $conversionSheet->setCellValue('A' . ($summaryRow + 6), 'USD Converted to AFS Only:');
    $conversionSheet->setCellValue('C' . ($summaryRow + 6), $usdToAfsOnly);
    $conversionSheet->getStyle('C' . ($summaryRow + 6))->getNumberFormat()->setFormatCode($currencyFormat);
    $conversionSheet->getStyle('A' . ($summaryRow + 6))->getFont()->setBold(true);
    $conversionSheet->getStyle('C' . ($summaryRow + 6))->getFont()->setBold(true);
    $conversionSheet->getStyle('A' . ($summaryRow + 6) . ':G' . ($summaryRow + 6))->getFill()->setFillType(Fill::FILL_SOLID);
    $conversionSheet->getStyle('A' . ($summaryRow + 6) . ':G' . ($summaryRow + 6))->getFill()->getStartColor()->setRGB('E7F1FF');
    
    $conversionSheet->getStyle('A' . ($summaryRow + 2) . ':A' . ($summaryRow + 5))->getFont()->setBold(true);
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $conversionSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set active sheet to Summary
    $spreadsheet->setActiveSheetIndex(0);
    
    // Create a dedicated sheet for USD-to-AFS conversions only
    $usdToAfsSheet = $spreadsheet->createSheet();
    $usdToAfsSheet->setTitle('USD to AFS Only');
    
    // USD to AFS Only headers
    $usdToAfsSheet->setCellValue('A1', 'USD TO AFS CONVERSION (NOT ADDED TO AFS TOTALS)');
    $usdToAfsSheet->mergeCells('A1:F1');
    $usdToAfsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $usdToAfsSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $usdToAfsSheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
    $usdToAfsSheet->mergeCells('A2:F2');
    $usdToAfsSheet->getStyle('A2')->getFont()->setBold(true);
    $usdToAfsSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // USD to AFS Only column headers
    $usdToAfsSheet->setCellValue('A4', 'Source');
    $usdToAfsSheet->setCellValue('B4', 'USD Amount');
    $usdToAfsSheet->setCellValue('C4', 'Exchange Rate');
    $usdToAfsSheet->setCellValue('D4', 'AFS Converted');
    $usdToAfsSheet->getStyle('A4:D4')->applyFromArray($headerStyle);
    
    // Add data for each source
    $row = 5;
    $sourceTotals = [
        'usd' => 0,
        'afs' => 0
    ];
    
    foreach ($sources as $source => $amounts) {
        $tableName = getTableName($source);
        
        try {
            // Skip sources without USD amount
            if ($amounts['USD'] <= 0) {
                continue; 
            }
            
            // Get USD amount and conversion for this source
            $sourceQuery = getSourceQuery($source);
            if (empty($sourceQuery)) {
                continue; // Skip if no valid query for this source
            }
            
            $stmt = $pdo->prepare($sourceQuery);
            $stmt->execute([$startDate, $endDate, $tenant_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $usdAmount = floatval($data['usd_amount'] ?? 0);
            $afsConverted = floatval($data['afs_converted'] ?? 0);
            
            // Special handling for Additional Payments
            if ($source === 'Additional Payments' && $usdAmount > 0) {
                // Get additional payments with USD currency
                $additionalQuery = "
                    SELECT id, created_at, profit
                    FROM additional_payments
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $additionalStmt = $pdo->prepare($additionalQuery);
                $additionalStmt->execute([$startDate, $endDate, $tenant_id]);
                
                $additionalConverted = 0;
                
                // Process each payment using the daily rate for its date
                while ($payment = $additionalStmt->fetch(PDO::FETCH_ASSOC)) {
                    $paymentDate = date('Y-m-d', strtotime($payment['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $paymentDate, $tenant_id);

                    // If no daily rate, use period average
                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }
                    
                    $additionalConverted += $payment['profit'] * $dailyRate;
                }
                
                // Update the Additional Payments converted amount
                $afsConverted = $additionalConverted;
            }
            
            // Only add row if there's USD amount to convert
            if ($usdAmount > 0) {
                $usdToAfsSheet->setCellValue('A' . $row, $source);
                $usdToAfsSheet->setCellValue('B' . $row, $usdAmount);

                // Special handling for Ticket Sales when afs_converted is 0
                if ($source === 'Ticket Sales' && $afsConverted == 0 && $usdAmount > 0) {
                    $ticketQuery = "
                        SELECT created_at, profit
                        FROM ticket_bookings
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $ticketStmt = $pdo->prepare($ticketQuery);
                    $ticketStmt->execute([$startDate, $endDate, $tenant_id]);

                    $ticketConverted = 0;

                    while ($booking = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                        $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $ticketConverted += $booking['profit'] * $dailyRate;
                    }

                    $afsConverted = $ticketConverted;
                }

                // Special handling for Date Changes when afs_converted is 0
                if ($source === 'Date Changes' && $afsConverted == 0 && $usdAmount > 0) {
                    $dateQuery = "
                        SELECT dt.created_at, dt.service_penalty as profit
                        FROM date_change_tickets dt
                        JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                        WHERE dt.currency = 'USD'
                        AND dt.created_at BETWEEN ? AND ?
                        AND dt.tenant_id = ?
                    ";
                    $dateStmt = $pdo->prepare($dateQuery);
                    $dateStmt->execute([$startDate, $endDate, $tenant_id]);

                    $dateConverted = 0;

                    while ($change = $dateStmt->fetch(PDO::FETCH_ASSOC)) {
                        $changeDate = date('Y-m-d', strtotime($change['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $changeDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $dateConverted += $change['profit'] * $dailyRate;
                    }

                    $afsConverted = $dateConverted;
                }

                // Special handling for Visa Services when afs_converted is 0
                if ($source === 'Visa Services' && $afsConverted == 0 && $usdAmount > 0) {
                    $visaQuery = "
                        SELECT created_at, profit
                        FROM visa_applications
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $visaStmt = $pdo->prepare($visaQuery);
                    $visaStmt->execute([$startDate, $endDate, $tenant_id]);

                    $visaConverted = 0;

                    while ($application = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
                        $applicationDate = date('Y-m-d', strtotime($application['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $applicationDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $visaConverted += $application['profit'] * $dailyRate;
                    }

                    $afsConverted = $visaConverted;
                }

                // Special handling for Umrah Bookings when exchange_rate is null/0
                if ($source === 'Umrah Bookings' && $afsConverted == 0 && $usdAmount > 0) {
                    $umrahQuery = "
                        SELECT created_at, profit
                        FROM umrah_bookings
                        WHERE currency = 'USD'
                        AND created_at BETWEEN ? AND ?
                        AND tenant_id = ?
                    ";
                    $umrahStmt = $pdo->prepare($umrahQuery);
                    $umrahStmt->execute([$startDate, $endDate, $tenant_id]);

                    $umrahConverted = 0;

                    while ($booking = $umrahStmt->fetch(PDO::FETCH_ASSOC)) {
                        $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $umrahConverted += $booking['profit'] * $dailyRate;
                    }

                    $afsConverted = $umrahConverted;
                }

                // Special handling for Ticket Weights when exchange_rate is null/0
                if ($source === 'Ticket Weights' && $afsConverted == 0 && $usdAmount > 0) {
                    $weightsQuery = "
                        SELECT tw.created_at, tw.profit
                        FROM ticket_weights tw
                        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                        WHERE tb.currency = 'USD'
                        AND tw.created_at BETWEEN ? AND ?
                        AND tw.tenant_id = ?
                    ";
                    $weightsStmt = $pdo->prepare($weightsQuery);
                    $weightsStmt->execute([$startDate, $endDate, $tenant_id]);

                    $weightsConverted = 0;

                    while ($weight = $weightsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $weightDate = date('Y-m-d', strtotime($weight['created_at']));
                        $dailyRate = getDailyAverageExchangeRate($pdo, $weightDate, $tenant_id);

                        if ($dailyRate === null) {
                            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                        }

                        $weightsConverted += $weight['profit'] * $dailyRate;
                    }

                    $afsConverted = $weightsConverted;
                }

                    $usdToAfsSheet->setCellValue('C' . $row, $afsConverted > 0 ? ($afsConverted / $usdAmount) : $avgExchangeRate);
                    $usdToAfsSheet->setCellValue('D' . $row, $afsConverted);

                $usdToAfsSheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
                $usdToAfsSheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00000');
                $usdToAfsSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);

                $sourceTotals['usd'] += $usdAmount;
                $sourceTotals['afs'] += $afsConverted;

                $row++;
            }
        } catch (PDOException $e) {
            // Log error and continue with next source
            error_log("Error in USD to AFS sheet for $source: " . $e->getMessage());
            continue;
        }
    }
    
    // Add totals row
    $usdToAfsSheet->setCellValue('A' . $row, 'TOTAL');
    $usdToAfsSheet->setCellValue('B' . $row, $sourceTotals['usd']);
    $usdToAfsSheet->setCellValue('D' . $row, $usdToAfsOnly);
    
    $usdToAfsSheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
    $usdToAfsSheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $usdToAfsSheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    
    $usdToAfsSheet->getStyle('A5:D' . $row)->applyFromArray($dataStyle);
    
    // Add explanation
    $usdToAfsSheet->setCellValue('A' . ($row + 2), 'Note: This sheet shows the USD amounts converted to AFS using the exchange rates. These values are NOT included in the AFS totals on the Summary sheet.');
    $usdToAfsSheet->mergeCells('A' . ($row + 2) . ':D' . ($row + 2));
    $usdToAfsSheet->getStyle('A' . ($row + 2))->getAlignment()->setWrapText(true);
    $usdToAfsSheet->getStyle('A' . ($row + 2))->getFont()->setItalic(true);
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $usdToAfsSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Save to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'financial_report');
    $writer->save($tempFile);
    
    // Read file and encode as base64
    $fileContent = file_get_contents($tempFile);
    $base64 = base64_encode($fileContent);
    
    // Remove temporary file
    unlink($tempFile);
    
    // Return file as base64
    echo json_encode([
        'success' => true,
        'file' => $base64
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 