<?php

require_once '../includes/conn.php';
require_once '../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];

// Fetch today's statistics
$today = date('Y-m-d');
$month = date('m');
$year = date('Y');

// For trend calculations - get yesterday's date and previous month/year
$yesterday = date('Y-m-d', strtotime('-1 day'));
$previousMonth = date('m', strtotime('-1 month'));
$previousMonthYear = date('Y', strtotime('-1 month'));
$previousYear = date('Y', strtotime('-1 year'));

// Fetch refunded tickets profit (service_penalty - ticket_bookings.profit)
// Daily refunded tickets profit
$dailyRefundedQuery = "SELECT 
    COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty 
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as afs_profit
FROM refunded_tickets rt
LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
WHERE DATE(rt.created_at) = ? AND rt.tenant_id = ?";

$stmt = $pdo->prepare($dailyRefundedQuery);
$stmt->execute([$today, $tenant_id]);
$dailyRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Daily date change tickets profit
$dailyDateChangeQuery = "SELECT 
    COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END), 0) as afs_profit
FROM date_change_tickets dt
LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
WHERE DATE(dt.created_at) = ? AND dt.tenant_id = ?";

$stmt = $pdo->prepare($dailyDateChangeQuery);
$stmt->execute([$today, $tenant_id]);
$dailyDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly refunded tickets profit
$monthlyRefundedQuery = "SELECT 
    COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as afs_profit
FROM refunded_tickets rt
LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
WHERE MONTH(rt.created_at) = ? AND YEAR(rt.created_at) = ? AND rt.tenant_id = ?";

$stmt = $pdo->prepare($monthlyRefundedQuery);
$stmt->execute([$month, $year, $tenant_id]);
$monthlyRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly date change tickets profit
$monthlyDateChangeQuery = "SELECT 
    COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END), 0) as afs_profit
FROM date_change_tickets dt
LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
WHERE MONTH(dt.created_at) = ? AND YEAR(dt.created_at) = ? AND dt.tenant_id = ?";

$stmt = $pdo->prepare($monthlyDateChangeQuery);
$stmt->execute([$month, $year, $tenant_id]);
$monthlyDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Yearly refunded tickets profit
$yearlyRefundedQuery = "SELECT 
    COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
        (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
              WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
              ELSE rt.service_penalty END) 
        ELSE 0 END), 0) as afs_profit
FROM refunded_tickets rt
LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
WHERE YEAR(rt.created_at) = ? AND rt.tenant_id = ?";

$stmt = $pdo->prepare($yearlyRefundedQuery);
$stmt->execute([$year, $tenant_id]);
$yearlyRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Yearly date change tickets profit
$yearlyDateChangeQuery = "SELECT 
    COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END), 0) as afs_profit
FROM date_change_tickets dt
LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
WHERE YEAR(dt.created_at) = ? AND dt.tenant_id = ?";

$stmt = $pdo->prepare($yearlyDateChangeQuery);
$stmt->execute([$year, $tenant_id]);
$yearlyDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch daily sales data
$dailyQuery = "SELECT 
    COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END), 0) as afs_profit,
    COALESCE(SUM(CASE WHEN currency = 'EUR' THEN profit ELSE 0 END), 0) as eur_profit,
    COALESCE(SUM(CASE WHEN currency = 'AED' THEN profit ELSE 0 END), 0) as aed_profit
FROM (
    SELECT profit, currency FROM ticket_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM ticket_reservations WHERE DATE(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT tw.profit, tb.currency FROM ticket_weights tw LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id WHERE DATE(tw.created_at) = ? AND tw.tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM visa_applications WHERE DATE(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM umrah_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM hotel_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM additional_payments WHERE DATE(created_at) = ? AND tenant_id = ?
) as combined_sales";

$stmt = $pdo->prepare($dailyQuery);
$stmt->execute([$today, $tenant_id, $today, $tenant_id, $today, $tenant_id, $today, $tenant_id, $today, $tenant_id, $today, $tenant_id, $today, $tenant_id]);
$dailySales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add refunded and date change profits to daily sales
$dailySales['usd_profit'] += $dailyRefundedProfit['usd_profit'] + $dailyDateChangeProfit['usd_profit'];
$dailySales['afs_profit'] += $dailyRefundedProfit['afs_profit'] + $dailyDateChangeProfit['afs_profit'];
$dailySales['eur_profit'] = $dailySales['eur_profit'] ?? 0;
$dailySales['aed_profit'] = $dailySales['aed_profit'] ?? 0;

// Fetch yesterday's sales data for trend calculation
$yesterdayQuery = $dailyQuery; // Reuse the same query structure
$stmt = $pdo->prepare($yesterdayQuery);
$stmt->execute([$yesterday, $tenant_id, $yesterday, $tenant_id, $yesterday, $tenant_id, $yesterday, $tenant_id, $yesterday, $tenant_id, $yesterday, $tenant_id, $yesterday, $tenant_id]);
$yesterdaySales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add yesterday's refunded and date change profits
$stmt = $pdo->prepare($dailyRefundedQuery);
$stmt->execute([$yesterday, $tenant_id]);
$yesterdayRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare($dailyDateChangeQuery);
$stmt->execute([$yesterday, $tenant_id]);
$yesterdayDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$yesterdaySales['usd_profit'] += $yesterdayRefundedProfit['usd_profit'] + $yesterdayDateChangeProfit['usd_profit'];
$yesterdaySales['afs_profit'] += $yesterdayRefundedProfit['afs_profit'] + $yesterdayDateChangeProfit['afs_profit'];

// Calculate daily trend percentage
$dailyTrendPercent = 0;
if ($yesterdaySales['usd_profit'] > 0) {
    $dailyTrendPercent = (($dailySales['usd_profit'] - $yesterdaySales['usd_profit']) / $yesterdaySales['usd_profit']) * 100;
} elseif ($dailySales['usd_profit'] > 0) {
    $dailyTrendPercent = 100; // If yesterday was 0 but today has sales, show 100% increase
}

// Fetch monthly sales data
$monthlyQuery = "SELECT 
    COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END), 0) as afs_profit,
    COALESCE(SUM(CASE WHEN currency = 'EUR' THEN profit ELSE 0 END), 0) as eur_profit,
    COALESCE(SUM(CASE WHEN currency = 'AED' THEN profit ELSE 0 END), 0) as aed_profit
FROM (
    SELECT profit, currency FROM ticket_bookings WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM ticket_reservations WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT tw.profit, tb.currency FROM ticket_weights tw LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id WHERE MONTH(tw.created_at) = ? AND YEAR(tw.created_at) = ? AND tw.tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM visa_applications WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM umrah_bookings WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM hotel_bookings WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM additional_payments WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND tenant_id = ?
) as combined_sales";

$stmt = $pdo->prepare($monthlyQuery);
$stmt->execute([$month, $year, $tenant_id, $month, $year, $tenant_id, $month, $year, $tenant_id, $month, $year, $tenant_id, $month, $year, $tenant_id, $month, $year, $tenant_id, $month, $year, $tenant_id]);
$monthlySales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add refunded and date change profits to monthly sales
$monthlySales['usd_profit'] += $monthlyRefundedProfit['usd_profit'] + $monthlyDateChangeProfit['usd_profit'];
$monthlySales['afs_profit'] += $monthlyRefundedProfit['afs_profit'] + $monthlyDateChangeProfit['afs_profit'];
$monthlySales['eur_profit'] = $monthlySales['eur_profit'] ?? 0;
$monthlySales['aed_profit'] = $monthlySales['aed_profit'] ?? 0;

// Fetch previous month's sales data for trend calculation
$previousMonthQuery = $monthlyQuery; // Reuse the same query structure
$stmt = $pdo->prepare($previousMonthQuery);
$stmt->execute([
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id, 
    $previousMonth, $previousMonthYear, $tenant_id
]);
$previousMonthSales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add previous month's refunded and date change profits
$stmt = $pdo->prepare($monthlyRefundedQuery);
$stmt->execute([$previousMonth, $previousMonthYear, $tenant_id]);
$previousMonthRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare($monthlyDateChangeQuery);
$stmt->execute([$previousMonth, $previousMonthYear, $tenant_id]);
$previousMonthDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$previousMonthSales['usd_profit'] += $previousMonthRefundedProfit['usd_profit'] + $previousMonthDateChangeProfit['usd_profit'];
$previousMonthSales['afs_profit'] += $previousMonthRefundedProfit['afs_profit'] + $previousMonthDateChangeProfit['afs_profit'];

// Calculate monthly trend percentage
$monthlyTrendPercent = 0;
if ($previousMonthSales['usd_profit'] > 0) {
    $monthlyTrendPercent = (($monthlySales['usd_profit'] - $previousMonthSales['usd_profit']) / $previousMonthSales['usd_profit']) * 100;
} elseif ($monthlySales['usd_profit'] > 0) {
    $monthlyTrendPercent = 100; // If previous month was 0 but this month has sales, show 100% increase
}

// Fetch yearly sales data
$yearlyQuery = "SELECT 
    COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END), 0) as usd_profit,
    COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END), 0) as afs_profit,
    COALESCE(SUM(CASE WHEN currency = 'EUR' THEN profit ELSE 0 END), 0) as eur_profit,
    COALESCE(SUM(CASE WHEN currency = 'AED' THEN profit ELSE 0 END), 0) as aed_profit
FROM (
    SELECT profit, currency FROM ticket_bookings WHERE YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM ticket_reservations WHERE YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT tw.profit, tb.currency FROM ticket_weights tw LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id WHERE YEAR(tw.created_at) = ? AND tw.tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM visa_applications WHERE YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM umrah_bookings WHERE YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM hotel_bookings WHERE YEAR(created_at) = ? AND tenant_id = ?
    UNION ALL
    SELECT profit, currency FROM additional_payments WHERE YEAR(created_at) = ? AND tenant_id = ?
) as combined_sales";

$stmt = $pdo->prepare($yearlyQuery);
$stmt->execute([$year, $tenant_id, $year, $tenant_id, $year, $tenant_id, $year, $tenant_id, $year, $tenant_id, $year, $tenant_id, $year, $tenant_id]);
$yearlySales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add refunded and date change profits to yearly sales
$yearlySales['usd_profit'] += $yearlyRefundedProfit['usd_profit'] + $yearlyDateChangeProfit['usd_profit'];
$yearlySales['afs_profit'] += $yearlyRefundedProfit['afs_profit'] + $yearlyDateChangeProfit['afs_profit'];
$yearlySales['eur_profit'] = $yearlySales['eur_profit'] ?? 0;
$yearlySales['aed_profit'] = $yearlySales['aed_profit'] ?? 0;

// Fetch previous year's sales data for trend calculation
$previousYearQuery = $yearlyQuery; // Reuse the same query structure
$stmt = $pdo->prepare($previousYearQuery);
$stmt->execute([$previousYear, $tenant_id, $previousYear, $tenant_id, $previousYear, $tenant_id, $previousYear, $tenant_id, $previousYear, $tenant_id, $previousYear, $tenant_id, $previousYear, $tenant_id]);
$previousYearSales = $stmt->fetch(PDO::FETCH_ASSOC);

// Add previous year's refunded and date change profits
$stmt = $pdo->prepare($yearlyRefundedQuery);
$stmt->execute([$previousYear, $tenant_id]);
$previousYearRefundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare($yearlyDateChangeQuery);
$stmt->execute([$previousYear, $tenant_id]);
$previousYearDateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

$previousYearSales['usd_profit'] += $previousYearRefundedProfit['usd_profit'] + $previousYearDateChangeProfit['usd_profit'];
$previousYearSales['afs_profit'] += $previousYearRefundedProfit['afs_profit'] + $previousYearDateChangeProfit['afs_profit'];

// Calculate yearly trend percentage
$yearlyTrendPercent = 0;
if ($previousYearSales['usd_profit'] > 0) {
    $yearlyTrendPercent = (($yearlySales['usd_profit'] - $previousYearSales['usd_profit']) / $previousYearSales['usd_profit']) * 100;
} elseif ($yearlySales['usd_profit'] > 0) {
    $yearlyTrendPercent = 100; // If previous year was 0 but this year has sales, show 100% increase
}

// Fetch recent transactions
$recentTransactionsQuery = "SELECT * FROM (
    SELECT 'ticket' as type, pnr as reference, created_at, profit, currency, tenant_id
    FROM ticket_bookings 
    UNION ALL
    SELECT 'visa' as type, passport_number as reference, created_at, profit, currency, tenant_id
    FROM visa_applications
    UNION ALL
    SELECT 'weight' as type, tw.ticket_id as reference, tw.created_at, tw.profit, tb.currency, tw.tenant_id
    FROM ticket_weights tw LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id
    UNION ALL
    SELECT 'umrah' as type, passport_number as reference, created_at, profit, currency, tenant_id
    FROM umrah_bookings
    UNION ALL
    SELECT 'hotel' as type, order_id as reference, created_at, profit, currency, tenant_id
    FROM hotel_bookings
    UNION ALL
    SELECT 'refund' as type, rt.pnr as reference, rt.created_at, 
           (rt.service_penalty - IFNULL(tb.profit, 0)) as profit, rt.currency, rt.tenant_id
    FROM refunded_tickets rt
    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
    UNION ALL
    SELECT 'datechange' as type, dt.pnr as reference, dt.created_at, 
           (dt.service_penalty - IFNULL(tb.profit, 0)) as profit, dt.currency, dt.tenant_id
    FROM date_change_tickets dt
    LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
) as combined_transactions WHERE tenant_id = ?
ORDER BY created_at DESC 
LIMIT 5";

$stmt = $pdo->prepare($recentTransactionsQuery);
$stmt->execute([$tenant_id]);
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch monthly statistics for chart (including refunded and date change)
$monthlyStatsQuery = "SELECT 
    months.month,
    COALESCE(sales.usd_profit, 0) + COALESCE(refund.usd_profit, 0) + COALESCE(datechange.usd_profit, 0) as usd_profit,
    COALESCE(sales.afs_profit, 0) + COALESCE(refund.afs_profit, 0) + COALESCE(datechange.afs_profit, 0) as afs_profit
FROM (
    SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m') as month
    FROM (
        SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    ) months
) months
LEFT JOIN (
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END) as usd_profit,
        SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END) as afs_profit
    FROM (
        SELECT created_at, profit, currency FROM ticket_bookings WHERE tenant_id = ?
        UNION ALL
        SELECT tw.created_at, tw.profit, tb.currency 
        FROM ticket_weights tw 
        LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id 
        WHERE tw.tenant_id = ?
        UNION ALL
        SELECT created_at, profit, currency FROM visa_applications WHERE tenant_id = ?
        UNION ALL
        SELECT created_at, profit, currency FROM umrah_bookings WHERE tenant_id = ?
        UNION ALL
        SELECT created_at, profit, currency FROM hotel_bookings WHERE tenant_id = ?
        UNION ALL
        SELECT created_at, profit, currency FROM additional_payments WHERE tenant_id = ?
    ) as combined_data 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
) sales ON months.month = sales.month
LEFT JOIN (
    SELECT 
        DATE_FORMAT(rt.created_at, '%Y-%m') as month,
        SUM(CASE WHEN rt.currency = 'USD' THEN (rt.service_penalty - IFNULL(tb.profit, 0)) ELSE 0 END) as usd_profit,
        SUM(CASE WHEN rt.currency = 'AFS' THEN (rt.service_penalty - IFNULL(tb.profit, 0)) ELSE 0 END) as afs_profit
    FROM refunded_tickets rt
    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
    WHERE rt.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND rt.tenant_id = ?
    GROUP BY DATE_FORMAT(rt.created_at, '%Y-%m')
) refund ON months.month = refund.month
LEFT JOIN (
    SELECT 
        DATE_FORMAT(dt.created_at, '%Y-%m') as month,
        SUM(CASE WHEN dt.currency = 'USD' THEN (dt.service_penalty - IFNULL(tb.profit, 0)) ELSE 0 END) as usd_profit,
        SUM(CASE WHEN dt.currency = 'AFS' THEN (dt.service_penalty - IFNULL(tb.profit, 0)) ELSE 0 END) as afs_profit
    FROM date_change_tickets dt
    LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
    WHERE dt.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND dt.tenant_id = ?
    GROUP BY DATE_FORMAT(dt.created_at, '%Y-%m')
) datechange ON months.month = datechange.month
ORDER BY months.month";

$stmt = $pdo->prepare($monthlyStatsQuery);
$stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id]);
$monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * Get top performers by ticket profit
 * @return array Array of top performers with their total profit
 */
function getTopPerformersByTicketProfit() {
    global $pdo, $tenant_id;

    $query = "SELECT
                u.name AS user_name,
                u.id AS user_id,
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) AS total_profit_usd,
                COALESCE(SUM(CASE WHEN tb.currency = 'AFS' THEN tb.profit ELSE 0 END), 0) AS total_profit_afs,
                COUNT(tb.id) AS total_tickets
              FROM users u
              LEFT JOIN ticket_bookings tb 
                     ON u.id = tb.created_by 
                    AND tb.tenant_id = ?
              WHERE u.tenant_id = ?
              GROUP BY u.id, u.name
              HAVING (total_profit_usd > 0 OR total_profit_afs > 0)
              ORDER BY total_profit_usd DESC, total_profit_afs DESC
              LIMIT 10";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenant_id, $tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting top performers: " . $e->getMessage());
        return [];
    }
}


/**
 * Get clients with negative balances (debts)
 * @return array Array of clients with negative balances
 */
function getClientsWithDebts() {
    global $pdo, $tenant_id;

    $query = "SELECT id, name, usd_balance, afs_balance
              FROM clients
              WHERE (usd_balance < 0 OR afs_balance < 0)
                AND tenant_id = ?
              ORDER BY name ASC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting clients with debts: " . $e->getMessage());
        return [];
    }
}



?>