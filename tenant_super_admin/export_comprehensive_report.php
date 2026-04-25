<?php
// Include security module only if not skipped
if (!defined('SKIP_SESSION_CHECK')) {
    require_once '../includes/session_check.php';

    // Enforce authentication
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }

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
}
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

// Function to get the daily average exchange rate from main account transactions for a specific day
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
                LEFT JOIN main_account_transactions mat ON mat.reference_id = tr.id AND mat.transaction_of IN ('ticket_reserve', 'reservation', 'ticket_reservation') AND mat.currency = 'AFS' AND mat.tenant_id = tr.tenant_id
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
                LEFT JOIN main_account_transactions mat ON mat.reference_id = rt.id AND mat.transaction_of IN ('ticket_refund', 'refund', 'ticket_refund_penalty') AND mat.currency = 'AFS' AND mat.tenant_id = rt.tenant_id
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
                LEFT JOIN main_account_transactions mat ON mat.reference_id = hb.id AND mat.transaction_of IN ('hotel', 'hotel_booking', 'accommodation') AND mat.currency = 'AFS' AND mat.tenant_id = hb.tenant_id
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
    $selectedBranchId = $_GET['branch_id'] ?? '';

    if (!isset($tenant_id)) {
        $tenant_id = $_SESSION['tenant_id'] ?? null;
    }

    if (empty($tenant_id)) {
        throw new Exception('Tenant not found for report export.');
    }

    $branchQuerySql = "SELECT id, name FROM branches WHERE tenant_id = ?";
    $branchQueryParams = [$tenant_id];

    if ($selectedBranchId !== '') {
        $branchQuerySql .= " AND id = ?";
        $branchQueryParams[] = $selectedBranchId;
    }

    $branchQuerySql .= " ORDER BY name";

    // Initialize spreadsheet
    $spreadsheet = new Spreadsheet();

    // Get branches for the current tenant or the selected branch
    $branchesQuery = $pdo->prepare($branchQuerySql);
    $branchesQuery->execute($branchQueryParams);
    $branches = $branchesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($branches)) {
        throw new Exception('No branches found for the selected filters.');
    }

    $reportTitle = count($branches) === 1
        ? 'Comprehensive Financial Report - ' . $branches[0]['name']
        : 'Comprehensive Financial Report - All Branches';

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Travel Agency Financial System')
        ->setLastModifiedBy('Travel Agency Financial System')
        ->setTitle($reportTitle)
        ->setSubject($reportTitle)
        ->setDescription($reportTitle . ' with income, expenses and profit/loss details')
        ->setKeywords('financial report income expenses profit loss branches')
        ->setCategory('Financial Reports');

    // Set default font
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    $comparisonData = [];

    foreach ($branches as $branch) {
        $current_branch_id = $branch['id'];
        $branch_name = $branch['name'];

        if (count($comparisonData) == 0) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($branch_name);
        } else {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($branch_name);
        }

        $categoryTotals = [
            'USD' => 0,
            'AFS' => 0,
            'EUR' => 0,
            'DARHAM' => 0,
            'usd_to_afs' => 0
        ];

        // Add report title
        $sheet->setCellValue('A1', 'COMPREHENSIVE FINANCIAL REPORT - ' . $branch_name);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add date range
        $sheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add space
        $sheet->setCellValue('A4', 'SUMMARY');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(14);

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
        $sheet->setCellValue('A6', 'Category');
        $sheet->setCellValue('B6', 'USD');
        $sheet->setCellValue('C6', 'Pure AFS');
        $sheet->setCellValue('D6', 'USD to AFS');
        $sheet->setCellValue('E6', 'Total');
        $sheet->setCellValue('F6', 'EUR');
        $sheet->setCellValue('G6', 'DARHAM');
        $sheet->getStyle('A6:G6')->applyFromArray($headerStyle);

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
        $current_branch_id,  // ticket_bookings
        $current_branch_id,  // ticket_reservations
        $current_branch_id,  // refunded_tickets
        $current_branch_id,  // date_change_tickets
        $current_branch_id,  // visa_applications
        $current_branch_id,  // umrah_bookings
        $current_branch_id,  // hotel_bookings
        $current_branch_id,  // ticket_weights
        $current_branch_id,  // additional_payments
        $startDate, $endDate
    ]);

    while($row = $totalIncomeStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData[$currency] = floatval($row['total']);
    }

    // Initialize sources array with separate tracking for pure AFS and converted AFS
    $sources = [
        'Ticket Sales' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Ticket Reservations' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Ticket Refunds' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Date Changes' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Visa Services' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Umrah Bookings' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Hotel Bookings' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Ticket Weights' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0],
        'Additional Payments' => ['USD' => 0, 'AFS' => 0, 'EUR' => 0, 'DARHAM' => 0, 'pure_afs' => 0, 'usd_to_afs' => 0]
    ];

    // Fetch ticket bookings income
    $avgExchangeRate = getAverageExchangeRate($pdo, $startDate, $endDate, $current_branch_id);

    // Process each income source with proper error handling
    foreach ($sources as $sourceName => &$currencies) {
        try {
            $sourceQuery = getSourceQuery($sourceName);
            if (empty($sourceQuery)) {
                continue; // Skip if no valid query for this source
            }

            $stmt = $pdo->prepare($sourceQuery);
            $stmt->execute([$startDate, $endDate, $current_branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Store USD amount directly
            $currencies['USD'] = floatval($row['usd_amount'] ?? 0);

            // Store pure AFS amount (original AFS transactions)
            $pureAfsAmount = floatval($row['afs_amount'] ?? 0);
            $currencies['pure_afs'] = $pureAfsAmount;

            // Store USD-to-AFS converted amount
            $usdToAfsAmount = floatval($row['afs_converted'] ?? 0);
            $currencies['usd_to_afs'] = $usdToAfsAmount;

            // Total AFS = pure AFS + converted USD-to-AFS
            $currencies['AFS'] = $pureAfsAmount + $usdToAfsAmount;


            // Special handling for Ticket Sales when exchange_rate is null/0
            if ($sourceName === 'Ticket Sales' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $ticketQuery = "
                    SELECT created_at, profit
                    FROM ticket_bookings
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $ticketStmt = $pdo->prepare($ticketQuery);
                $ticketStmt->execute([$startDate, $endDate, $current_branch_id]);

                $ticketConverted = 0;

                while ($booking = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $ticketConverted += $booking['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $ticketConverted;
                $currencies['AFS'] += $ticketConverted;
            }

            // Special handling for Date Changes when exchange_rate is null/0
            if ($sourceName === 'Date Changes' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $dateQuery = "
                    SELECT dt.created_at, dt.service_penalty as profit
                    FROM date_change_tickets dt
                    JOIN ticket_bookings tb ON dt.ticket_id = tb.id
                    WHERE dt.currency = 'USD'
                    AND dt.created_at BETWEEN ? AND ?
                    AND dt.tenant_id = ?
                ";
                $dateStmt = $pdo->prepare($dateQuery);
                $dateStmt->execute([$startDate, $endDate, $current_branch_id]);

                $dateConverted = 0;

                while ($change = $dateStmt->fetch(PDO::FETCH_ASSOC)) {
                    $changeDate = date('Y-m-d', strtotime($change['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $changeDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $dateConverted += $change['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $dateConverted;
                $currencies['AFS'] += $dateConverted;
            }

            // Special handling for Visa Services when exchange_rate is null/0
            if ($sourceName === 'Visa Services' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $visaQuery = "
                    SELECT created_at, profit
                    FROM visa_applications
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $visaStmt = $pdo->prepare($visaQuery);
                $visaStmt->execute([$startDate, $endDate, $current_branch_id]);

                $visaConverted = 0;

                while ($application = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
                    $applicationDate = date('Y-m-d', strtotime($application['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $applicationDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $visaConverted += $application['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $visaConverted;
                $currencies['AFS'] += $visaConverted;
            }

            // Special handling for Umrah Bookings when exchange_rate is null/0
            if ($sourceName === 'Umrah Bookings' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $umrahQuery = "
                    SELECT created_at, profit
                    FROM umrah_bookings
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $umrahStmt = $pdo->prepare($umrahQuery);
                $umrahStmt->execute([$startDate, $endDate, $current_branch_id]);

                $umrahConverted = 0;

                while ($booking = $umrahStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $umrahConverted += $booking['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $umrahConverted;
                $currencies['AFS'] += $umrahConverted;
            }

            // Special handling for Ticket Reservations when exchange_rate is null/0
            if ($sourceName === 'Ticket Reservations' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $reservationQuery = "
                    SELECT created_at, profit
                    FROM ticket_reservations
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $reservationStmt = $pdo->prepare($reservationQuery);
                $reservationStmt->execute([$startDate, $endDate, $current_branch_id]);

                $reservationConverted = 0;

                while ($reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC)) {
                    $reservationDate = date('Y-m-d', strtotime($reservation['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $reservationDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $reservationConverted += $reservation['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $reservationConverted;
                $currencies['AFS'] += $reservationConverted;
            }

            // Special handling for Ticket Refunds when exchange_rate is null/0
            if ($sourceName === 'Ticket Refunds' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $refundQuery = "
                    SELECT rt.created_at,
                        CASE
                            WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                            WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                            ELSE rt.service_penalty
                        END as profit
                    FROM refunded_tickets rt
                    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
                    WHERE rt.currency = 'USD'
                    AND rt.created_at BETWEEN ? AND ?
                    AND rt.tenant_id = ?
                ";
                $refundStmt = $pdo->prepare($refundQuery);
                $refundStmt->execute([$startDate, $endDate, $current_branch_id]);

                $refundConverted = 0;

                while ($refund = $refundStmt->fetch(PDO::FETCH_ASSOC)) {
                    $refundDate = date('Y-m-d', strtotime($refund['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $refundDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $refundConverted += $refund['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $refundConverted;
                $currencies['AFS'] += $refundConverted;
            }

            // Special handling for Hotel Bookings when exchange_rate is null/0
            if ($sourceName === 'Hotel Bookings' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $hotelQuery = "
                    SELECT created_at, profit
                    FROM hotel_bookings
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $hotelStmt = $pdo->prepare($hotelQuery);
                $hotelStmt->execute([$startDate, $endDate, $current_branch_id]);

                $hotelConverted = 0;

                while ($booking = $hotelStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bookingDate = date('Y-m-d', strtotime($booking['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $bookingDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $hotelConverted += $booking['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $hotelConverted;
                $currencies['AFS'] += $hotelConverted;
            }

            // Special handling for Ticket Weights when exchange_rate is null/0
            if ($sourceName === 'Ticket Weights' && $currencies['USD'] != 0 && floatval($row['afs_converted'] ?? 0) == 0) {
                $weightsQuery = "
                    SELECT tw.created_at, tw.profit
                    FROM ticket_weights tw
                    JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                    WHERE tb.currency = 'USD'
                    AND tw.created_at BETWEEN ? AND ?
                    AND tw.tenant_id = ?
                ";
                $weightsStmt = $pdo->prepare($weightsQuery);
                $weightsStmt->execute([$startDate, $endDate, $current_branch_id]);

                $weightsConverted = 0;

                while ($weight = $weightsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $weightDate = date('Y-m-d', strtotime($weight['created_at']));
                    $dailyRate = getDailyAverageExchangeRate($pdo, $weightDate, $tenant_id);

                    if ($dailyRate === null) {
                        $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $tenant_id);
                    }

                    $weightsConverted += $weight['profit'] * $dailyRate;
                }

                $currencies['usd_to_afs'] += $weightsConverted;
                $currencies['AFS'] += $weightsConverted;
            }

            // For Additional Payments table which doesn't have exchange_rate field,
            // we need to handle conversion separately using daily exchange rates
            if ($sourceName === 'Additional Payments' && $currencies['USD'] != 0) {
                // Get additional payments with USD currency
                $additionalQuery = "
                    SELECT id, created_at, profit
                    FROM additional_payments
                    WHERE currency = 'USD'
                    AND created_at BETWEEN ? AND ?
                    AND tenant_id = ?
                ";
                $additionalStmt = $pdo->prepare($additionalQuery);
                $additionalStmt->execute([$startDate, $endDate, $current_branch_id]);

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

                // Add this converted amount to both usd_to_afs and AFS
                $currencies['usd_to_afs'] += $additionalConverted;
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
            continue;
        }
    }

    // Calculate totals from the processed sources array
    $incomeData = [
        'USD' => 0,
        'AFS' => 0,
        'EUR' => 0,
        'DARHAM' => 0
    ];

    $pureAfsTotal = 0;
    $usdToAfsTotal = 0;

    foreach ($sources as $source => $amounts) {
        foreach (['USD', 'AFS', 'EUR', 'DARHAM'] as $currency) {
            if (isset($amounts[$currency])) {
                $incomeData[$currency] += $amounts[$currency];
            }
        }
        // Accumulate pure AFS and USD-to-AFS totals
        $pureAfsTotal += $amounts['pure_afs'] ?? 0;
        $usdToAfsTotal += $amounts['usd_to_afs'] ?? 0;
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
    $expenseStmt->execute([$startDate, $endDate, $current_branch_id, $startDate, $endDate, $current_branch_id]);

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
    $expenseByDateStmt->execute([$startDate, $endDate, $current_branch_id, $startDate, $endDate, $current_branch_id]);

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

    // Get the total from a simple query
    $usdToAfsTotalQuery = "
        SELECT SUM(afs_equivalent) as total_afs FROM (
            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN tb.profit * mat.exchange_rate
                    ELSE tb.profit * ?
                END as afs_equivalent
            FROM ticket_bookings tb
            LEFT JOIN main_account_transactions mat ON mat.reference_id = tb.id AND mat.transaction_of = 'ticket_sale' AND mat.currency = 'AFS' AND mat.tenant_id = tb.tenant_id
            WHERE tb.currency = 'USD' AND tb.created_at BETWEEN ? AND ?
            AND tb.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN tr.profit * mat.exchange_rate
                    ELSE tr.profit * ?
                END as afs_equivalent
            FROM ticket_reservations tr
            LEFT JOIN main_account_transactions mat ON mat.reference_id = tr.id AND mat.transaction_of IN ('ticket_reserve', 'reservation', 'ticket_reservation') AND mat.currency = 'AFS' AND mat.tenant_id = tr.tenant_id
            WHERE tr.currency = 'USD' AND tr.created_at BETWEEN ? AND ?
            AND tr.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN
                        CASE
                            WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                            WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                            ELSE rt.service_penalty
                        END * mat.exchange_rate
                    ELSE
                        CASE
                            WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                            WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                            ELSE rt.service_penalty
                        END * ?
                END as afs_equivalent
            FROM refunded_tickets rt
            JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            LEFT JOIN main_account_transactions mat ON mat.reference_id = rt.id AND mat.transaction_of IN ('ticket_refund', 'refund', 'ticket_refund_penalty') AND mat.currency = 'AFS' AND mat.tenant_id = rt.tenant_id
            WHERE rt.currency = 'USD' AND rt.created_at BETWEEN ? AND ?
            AND rt.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN dt.service_penalty * mat.exchange_rate
                    ELSE dt.service_penalty * ?
                END as afs_equivalent
            FROM date_change_tickets dt
            JOIN ticket_bookings tb ON dt.ticket_id = tb.id
            LEFT JOIN main_account_transactions mat ON mat.reference_id = dt.id AND mat.transaction_of = 'date_change' AND mat.currency = 'AFS' AND mat.tenant_id = dt.tenant_id
            WHERE dt.currency = 'USD' AND dt.created_at BETWEEN ? AND ?
            AND dt.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN va.profit * mat.exchange_rate
                    ELSE va.profit * ?
                END as afs_equivalent
            FROM visa_applications va
            LEFT JOIN main_account_transactions mat ON mat.reference_id = va.id AND mat.transaction_of = 'visa' AND mat.currency = 'AFS' AND mat.tenant_id = va.tenant_id
            WHERE va.currency = 'USD' AND va.created_at BETWEEN ? AND ?
            AND va.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN ub.profit * mat.exchange_rate
                    ELSE ub.profit * ?
                END as afs_equivalent
            FROM umrah_bookings ub
            LEFT JOIN main_account_transactions mat ON mat.reference_id = ub.booking_id AND mat.transaction_of = 'umrah' AND mat.currency = 'AFS' AND mat.tenant_id = ub.tenant_id
            WHERE ub.currency = 'USD' AND ub.created_at BETWEEN ? AND ?
            AND ub.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN hb.profit * mat.exchange_rate
                    ELSE hb.profit * ?
                END as afs_equivalent
            FROM hotel_bookings hb
            LEFT JOIN main_account_transactions mat ON mat.reference_id = hb.id AND mat.transaction_of IN ('hotel', 'hotel_booking', 'accommodation') AND mat.currency = 'AFS' AND mat.tenant_id = hb.tenant_id
            WHERE hb.currency = 'USD' AND hb.created_at BETWEEN ? AND ?
            AND hb.tenant_id = ?

            UNION ALL

            SELECT
                CASE
                    WHEN mat.exchange_rate IS NOT NULL AND mat.exchange_rate > 0 THEN tw.profit * mat.exchange_rate
                    ELSE tw.profit * ?
                END as afs_equivalent
            FROM ticket_weights tw
            JOIN ticket_bookings tb ON tw.ticket_id = tb.id
            LEFT JOIN main_account_transactions mat ON mat.reference_id = tw.id AND mat.transaction_of = 'weight' AND mat.currency = 'AFS' AND mat.tenant_id = tw.tenant_id
            WHERE tb.currency = 'USD' AND tw.created_at BETWEEN ? AND ?
            AND tw.tenant_id = ?

            UNION ALL

            SELECT ap.profit * ? as afs_equivalent
            FROM additional_payments ap
            WHERE ap.currency = 'USD' AND ap.created_at BETWEEN ? AND ?
            AND ap.tenant_id = ?
        ) usd_conversions
    ";

    $usdToAfsTotalStmt = $pdo->prepare($usdToAfsTotalQuery);
    $usdToAfsTotalStmt->execute([
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Ticket bookings
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Ticket reservations
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Refunded tickets
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Date change tickets
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Visa applications
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Umrah bookings
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Hotel bookings
        $avgExchangeRate, $startDate, $endDate, $current_branch_id,  // Ticket weights
        $avgExchangeRate, $startDate, $endDate, $current_branch_id   // Additional payments
    ]);

    $totalResult = $usdToAfsTotalStmt->fetch(PDO::FETCH_ASSOC);
    $usdToAfsOnly = floatval($totalResult['total_afs'] ?? 0);

        // Add summary data
        $sheet->setCellValue('A7', 'Total Income');
        $sheet->setCellValue('B7', $incomeData['USD']);
        $sheet->setCellValue('C7', $pureAfsTotal); // Pure AFS
        $sheet->setCellValue('D7', $usdToAfsTotal);
        $sheet->setCellValue('E7', $pureAfsTotal + $usdToAfsTotal); // Total
        $sheet->setCellValue('F7', $incomeData['EUR']);
        $sheet->setCellValue('G7', $incomeData['DARHAM']);
        $sheet->getStyle('B7:G7')->getNumberFormat()->setFormatCode($currencyFormat);

        $sheet->setCellValue('A8', 'Total Expenses');
        $sheet->setCellValue('B8', $expenseData['USD']);
        $sheet->setCellValue('C8', $expenseData['AFS']);
        $sheet->setCellValue('D8', $expenseUsdToAfs);  // USD to AFS conversion for expenses
        $sheet->setCellValue('E8', $expenseData['AFS'] + $expenseUsdToAfs); // Total
        $sheet->setCellValue('F8', $expenseData['EUR']);
        $sheet->setCellValue('G8', $expenseData['DARHAM']);
        $sheet->getStyle('B8:G8')->getNumberFormat()->setFormatCode($currencyFormat);

    // Calculate profit/loss
    $pureAfsProfit = $pureAfsTotal - $expenseData['AFS'];
    $totalAfsProfit = ($pureAfsTotal + $usdToAfsTotal) - ($expenseData['AFS'] + $expenseUsdToAfs);

        $sheet->setCellValue('A9', 'Profit/Loss');
        $sheet->setCellValue('B9', $profitLossData['USD']);
        $sheet->setCellValue('C9', $pureAfsProfit);
        $sheet->setCellValue('D9', $usdToAfsTotal - $expenseUsdToAfs); // Net USD to AFS conversion
        $sheet->setCellValue('E9', $totalAfsProfit);
        $sheet->setCellValue('F9', $profitLossData['EUR']);
        $sheet->setCellValue('G9', $profitLossData['DARHAM']);
        $sheet->getStyle('B9:G9')->getNumberFormat()->setFormatCode($currencyFormat);

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
        $value = $sheet->getCell($cell)->getValue();
        if ($value >= 0) {
            $sheet->getStyle($cell)->applyFromArray($profitStyle);
        } else {
            $sheet->getStyle($cell)->applyFromArray($lossStyle);
        }
    }

        $sheet->getStyle('A7:G9')->applyFromArray($dataStyle);

    $comparisonData[$branch_name] = [
        'income' => $incomeData,
        'expenses' => $expenseData,
        'profit' => $profitLossData
    ];

    // Update summary cells for expenses and profit
    $sheet->setCellValue('B8', $expenseData['USD']);
    $sheet->setCellValue('C8', $expenseData['AFS']);
    $sheet->setCellValue('D8', $categoryTotals['usd_to_afs']);  // USD to AFS for expenses
    $sheet->setCellValue('E8', $expenseData['AFS']);  // Total
    $sheet->setCellValue('F8', $expenseData['EUR']);
    $sheet->setCellValue('G8', $expenseData['DARHAM']);
    $sheet->setCellValue('B9', $profitLossData['USD']);
    $sheet->setCellValue('C9', $profitLossData['AFS']);
    $sheet->setCellValue('D9', $incomeData['AFS'] - $expenseData['AFS']);  // Net AFS
    $sheet->setCellValue('E9', $profitLossData['AFS']);
    $sheet->setCellValue('F9', $profitLossData['EUR']);
    $sheet->setCellValue('G9', $profitLossData['DARHAM']);

    // Update conditional formatting for profit/loss
    foreach (['B9', 'C9', 'D9', 'E9', 'F9', 'G9'] as $cell) {
        $value = $sheet->getCell($cell)->getValue();
        if ($value >= 0) {
            $sheet->getStyle($cell)->applyFromArray($profitStyle);
        } else {
            $sheet->getStyle($cell)->applyFromArray($lossStyle);
        }
    }

    // Set row position after main summary table
    $row = 9;

    // Add space
    $sheet->setCellValue('A' . ($row + 2), 'INCOME BY SOURCE');
    $sheet->getStyle('A' . ($row + 2))->getFont()->setBold(true)->setSize(14);

    // Income by source headers
    $incomeHeaderRow = $row + 4;
    $sheet->setCellValue('A' . $incomeHeaderRow, 'Source');
    $sheet->setCellValue('B' . $incomeHeaderRow, 'USD');
    $sheet->setCellValue('C' . $incomeHeaderRow, 'Pure AFS');
    $sheet->setCellValue('D' . $incomeHeaderRow, 'USD to AFS');
    $sheet->setCellValue('E' . $incomeHeaderRow, 'Total');
    $sheet->setCellValue('F' . $incomeHeaderRow, 'EUR');
    $sheet->setCellValue('G' . $incomeHeaderRow, 'DARHAM');
    $sheet->getStyle('A' . $incomeHeaderRow . ':G' . $incomeHeaderRow)->applyFromArray($headerStyle);

    $row = $incomeHeaderRow + 1;
    $pureAfsTotal = 0;

    foreach ($sources as $source => $amounts) {
        // Use the pre-calculated pure AFS and USD-to-AFS amounts
        $pureAfs = $amounts['pure_afs'] ?? 0;
        $sourceUsdToAfs = $amounts['usd_to_afs'] ?? 0;

        $sheet->setCellValue('A' . $row, $source);
        $sheet->setCellValue('B' . $row, $amounts['USD']);
        $sheet->setCellValue('C' . $row, $pureAfs); // Pure AFS
        $sheet->setCellValue('D' . $row, $sourceUsdToAfs);
        $sheet->setCellValue('E' . $row, $amounts['AFS']); // Total AFS
        $sheet->setCellValue('F' . $row, $amounts['EUR']);
        $sheet->setCellValue('G' . $row, $amounts['DARHAM']);
        $sheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }

    $sheet->getStyle('A' . ($incomeHeaderRow + 1) . ':G' . ($row - 1))->applyFromArray($dataStyle);

    // Add totals row for income by source
    $totalPureAfs = 0;
    $totalUsdToAfs = 0;

    foreach ($sources as $source => $amounts) {
        $totalPureAfs += $amounts['pure_afs'] ?? 0;
        $totalUsdToAfs += $amounts['usd_to_afs'] ?? 0;
    }

    $sheet->setCellValue('A' . $row, 'TOTAL');
    $sheet->setCellValue('B' . $row, array_sum(array_column($sources, 'USD')));
    $sheet->setCellValue('C' . $row, $totalPureAfs); // Pure AFS total
    $sheet->setCellValue('D' . $row, $totalUsdToAfs);
    $sheet->setCellValue('E' . $row, $totalPureAfs + $totalUsdToAfs); // Total AFS
    $sheet->setCellValue('F' . $row, array_sum(array_column($sources, 'EUR')));
    $sheet->setCellValue('G' . $row, array_sum(array_column($sources, 'DARHAM')));

    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle('A' . ($incomeHeaderRow + 1) . ':G' . $row)->applyFromArray($dataStyle);

    // Add space
    $row += 2;
    $sheet->setCellValue('A' . $row, 'EXPENSES BY CATEGORY');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);

    // Expense by category headers
    $expenseCategoryRow = $row + 2;
    $sheet->setCellValue('A' . $expenseCategoryRow, 'Category');
    $sheet->setCellValue('B' . $expenseCategoryRow, 'USD');
    $sheet->setCellValue('C' . $expenseCategoryRow, 'AFS');
    $sheet->setCellValue('D' . $expenseCategoryRow, 'USD to AFS');
    $sheet->setCellValue('E' . $expenseCategoryRow, 'Total (AFS+Converted)');
    $sheet->setCellValue('F' . $expenseCategoryRow, 'EUR');
    $sheet->setCellValue('G' . $expenseCategoryRow, 'DARHAM');
    $sheet->getStyle('A' . $expenseCategoryRow . ':G' . $expenseCategoryRow)->applyFromArray($headerStyle);

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
    $expenseCategoryStmt->execute([$startDate, $endDate, $current_branch_id, $startDate, $endDate, $current_branch_id]);

    $categories = [];
    $row = $expenseCategoryRow + 1;

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
    $expenseCategoryConversionStmt->execute([$startDate, $endDate, $current_branch_id, $startDate, $endDate, $current_branch_id]);

    while ($data = $expenseCategoryConversionStmt->fetch(PDO::FETCH_ASSOC)) {
        $expenseDate = date('Y-m-d', strtotime($data['date']));
        $dailyRate = getDailyAverageExchangeRate($pdo, $expenseDate, $current_branch_id);

        // If no daily rate, use period average
        if ($dailyRate === null) {
            $dailyRate = getPeriodAverageExchangeRate($pdo, $startDate, $endDate, $current_branch_id);
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

        $sheet->setCellValue('A' . $row, $category);
        $sheet->setCellValue('B' . $row, $amounts['USD']);
        $sheet->setCellValue('C' . $row, $amounts['AFS']);
        $sheet->setCellValue('D' . $row, $amounts['usd_to_afs']);
        $sheet->setCellValue('E' . $row, $totalAfs);
        $sheet->setCellValue('F' . $row, $amounts['EUR']);
        $sheet->setCellValue('G' . $row, $amounts['DARHAM']);
        $sheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row++;
    }

    // Update expenseData and profitLossData with category totals
    $expenseData = [
        'USD' => $categoryTotals['USD'],
        'AFS' => $categoryTotals['AFS'] + $categoryTotals['usd_to_afs'],
        'EUR' => $categoryTotals['EUR'],
        'DARHAM' => $categoryTotals['DARHAM']
    ];

    $profitLossData = [
        'USD' => $incomeData['USD'] - $expenseData['USD'],
        'AFS' => $incomeData['AFS'] - $expenseData['AFS'],
        'EUR' => $incomeData['EUR'] - $expenseData['EUR'],
        'DARHAM' => $incomeData['DARHAM'] - $expenseData['DARHAM']
    ];

    // Add totals row for expenses
    $sheet->setCellValue('A' . $row, 'TOTAL');
    $sheet->setCellValue('B' . $row, $categoryTotals['USD']);
    $sheet->setCellValue('C' . $row, $categoryTotals['AFS']);
    $sheet->setCellValue('D' . $row, $categoryTotals['usd_to_afs']);
    $sheet->setCellValue('E' . $row, $categoryTotals['AFS'] + $categoryTotals['usd_to_afs']);
    $sheet->setCellValue('F' . $row, $categoryTotals['EUR']);
    $sheet->setCellValue('G' . $row, $categoryTotals['DARHAM']);

    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode($currencyFormat);

    $sheet->getStyle('A' . ($expenseCategoryRow + 1) . ':G' . $row)->applyFromArray($dataStyle);

    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    }

    if (count($branches) > 1) {
        // Create comparison sheet
        $comparisonSheet = $spreadsheet->createSheet();
    $comparisonSheet->setTitle('Branch Comparison');

    $comparisonSheet->setCellValue('A1', 'BRANCH COMPARISON');
    $comparisonSheet->mergeCells('A1:M1');
    $comparisonSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $comparisonSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $comparisonSheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
    $comparisonSheet->mergeCells('A2:M2');
    $comparisonSheet->getStyle('A2')->getFont()->setBold(true);
    $comparisonSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 4;
    $comparisonSheet->setCellValue('A' . $row, 'Branch');
    $comparisonSheet->setCellValue('B' . $row, 'Income USD');
    $comparisonSheet->setCellValue('C' . $row, 'Income AFS');
    $comparisonSheet->setCellValue('D' . $row, 'Income EUR');
    $comparisonSheet->setCellValue('E' . $row, 'Income DARHAM');
    $comparisonSheet->setCellValue('F' . $row, 'Expenses USD');
    $comparisonSheet->setCellValue('G' . $row, 'Expenses AFS');
    $comparisonSheet->setCellValue('H' . $row, 'Expenses EUR');
    $comparisonSheet->setCellValue('I' . $row, 'Expenses DARHAM');
    $comparisonSheet->setCellValue('J' . $row, 'Profit USD');
    $comparisonSheet->setCellValue('K' . $row, 'Profit AFS');
    $comparisonSheet->setCellValue('L' . $row, 'Profit EUR');
    $comparisonSheet->setCellValue('M' . $row, 'Profit DARHAM');
    $comparisonSheet->getStyle('A' . $row . ':M' . $row)->applyFromArray($headerStyle);

    $row++;
    foreach ($comparisonData as $branch => $data) {
        $comparisonSheet->setCellValue('A' . $row, $branch);
        $comparisonSheet->setCellValue('B' . $row, $data['income']['USD']);
        $comparisonSheet->setCellValue('C' . $row, $data['income']['AFS']);
        $comparisonSheet->setCellValue('D' . $row, $data['income']['EUR']);
        $comparisonSheet->setCellValue('E' . $row, $data['income']['DARHAM']);
        $comparisonSheet->setCellValue('F' . $row, $data['expenses']['USD']);
        $comparisonSheet->setCellValue('G' . $row, $data['expenses']['AFS']);
        $comparisonSheet->setCellValue('H' . $row, $data['expenses']['EUR']);
        $comparisonSheet->setCellValue('I' . $row, $data['expenses']['DARHAM']);
        $comparisonSheet->setCellValue('J' . $row, $data['profit']['USD']);
        $comparisonSheet->setCellValue('K' . $row, $data['profit']['AFS']);
        $comparisonSheet->setCellValue('L' . $row, $data['profit']['EUR']);
        $comparisonSheet->setCellValue('M' . $row, $data['profit']['DARHAM']);
        $row++;
    }

    // Add totals row
    $comparisonSheet->setCellValue('A' . $row, 'TOTAL');
    $totalIncomeUSD = 0;
    $totalIncomeAFS = 0;
    $totalIncomeEUR = 0;
    $totalIncomeDARHAM = 0;
    $totalExpensesUSD = 0;
    $totalExpensesAFS = 0;
    $totalExpensesEUR = 0;
    $totalExpensesDARHAM = 0;
    $totalProfitUSD = 0;
    $totalProfitAFS = 0;
    $totalProfitEUR = 0;
    $totalProfitDARHAM = 0;
    foreach ($comparisonData as $data) {
        $totalIncomeUSD += $data['income']['USD'];
        $totalIncomeAFS += $data['income']['AFS'];
        $totalIncomeEUR += $data['income']['EUR'];
        $totalIncomeDARHAM += $data['income']['DARHAM'];
        $totalExpensesUSD += $data['expenses']['USD'];
        $totalExpensesAFS += $data['expenses']['AFS'];
        $totalExpensesEUR += $data['expenses']['EUR'];
        $totalExpensesDARHAM += $data['expenses']['DARHAM'];
        $totalProfitUSD += $data['profit']['USD'];
        $totalProfitAFS += $data['profit']['AFS'];
        $totalProfitEUR += $data['profit']['EUR'];
        $totalProfitDARHAM += $data['profit']['DARHAM'];
    }
    $comparisonSheet->setCellValue('B' . $row, $totalIncomeUSD);
    $comparisonSheet->setCellValue('C' . $row, $totalIncomeAFS);
    $comparisonSheet->setCellValue('D' . $row, $totalIncomeEUR);
    $comparisonSheet->setCellValue('E' . $row, $totalIncomeDARHAM);
    $comparisonSheet->setCellValue('F' . $row, $totalExpensesUSD);
    $comparisonSheet->setCellValue('G' . $row, $totalExpensesAFS);
    $comparisonSheet->setCellValue('H' . $row, $totalExpensesEUR);
    $comparisonSheet->setCellValue('I' . $row, $totalExpensesDARHAM);
    $comparisonSheet->setCellValue('J' . $row, $totalProfitUSD);
    $comparisonSheet->setCellValue('K' . $row, $totalProfitAFS);
    $comparisonSheet->setCellValue('L' . $row, $totalProfitEUR);
    $comparisonSheet->setCellValue('M' . $row, $totalProfitDARHAM);

    $comparisonSheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
    $comparisonSheet->getStyle('B' . $row . ':M' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
    $comparisonSheet->getStyle('A4:M' . $row)->applyFromArray($dataStyle);

    foreach (range('A', 'M') as $col) {
        $comparisonSheet->getColumnDimension($col)->setAutoSize(true);
    }
    }

    // Create Excel file
    $writer = new Xlsx($spreadsheet);

    // Save to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'financial_report');
    $writer->save($tempFile);

    // Read file and encode as base64
    $fileContent = file_get_contents($tempFile);
    $base64 = base64_encode($fileContent);

    $filenameLabel = count($branches) === 1
        ? preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($branches[0]['name']))
        : 'all_branches';
    $filename = 'comprehensive_financial_report_' . $filenameLabel . '_' . $startDate . '_to_' . $endDate . '.xlsx';

    // Remove temporary file
    unlink($tempFile);

    // Return file as base64
    echo json_encode([
        'success' => true,
        'file' => $base64,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
