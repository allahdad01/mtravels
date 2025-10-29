<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once('../../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];

// Get filter parameters
$filter_type = isset($_POST['filter_type']) ? $_POST['filter_type'] : '';
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : '';

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'Invalid parameters',
    'data' => []
];

// Validate filter type
if (!in_array($filter_type, ['daily', 'monthly', 'yearly'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    switch ($filter_type) {
        case 'daily':
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
                $response['message'] = 'Invalid date format. Expected: YYYY-MM-DD';
                break;
            }

            $daily_query = "SELECT 
                COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END), 0) as usd_profit,
                COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END), 0) as afs_profit
            FROM (
                SELECT profit, currency FROM ticket_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
                UNION ALL
                SELECT profit, currency FROM ticket_reservations WHERE DATE(created_at) = ? AND tenant_id = ?
                UNION ALL
                SELECT tw.profit, tb.currency FROM ticket_weights tw 
                    LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id 
                    WHERE DATE(tw.created_at) = ? AND tw.tenant_id = ?
                UNION ALL
                SELECT profit, currency FROM visa_applications WHERE DATE(created_at) = ? AND tenant_id = ?
                UNION ALL
                SELECT profit, currency FROM umrah_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
                UNION ALL
                SELECT profit, currency FROM hotel_bookings WHERE DATE(created_at) = ? AND tenant_id = ?
                UNION ALL
                SELECT profit, currency FROM additional_payments WHERE DATE(created_at) = ? AND tenant_id = ?
            ) as combined_sales";

            $stmt = $pdo->prepare($daily_query);
            $stmt->execute([
                $filter_date, $tenant_id,
                $filter_date, $tenant_id,
                $filter_date, $tenant_id,
                $filter_date, $tenant_id,
                $filter_date, $tenant_id,
                $filter_date, $tenant_id,
                $filter_date, $tenant_id
            ]);
            $dailySales = $stmt->fetch(PDO::FETCH_ASSOC);

            // Refunded tickets
            $refunded_query = "SELECT 
                COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as afs_profit
            FROM refunded_tickets rt
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE DATE(rt.created_at) = ? AND rt.tenant_id = ?";

            $stmt = $pdo->prepare($refunded_query);
            $stmt->execute([$filter_date, $tenant_id]);
            $refundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            // Date change tickets
            $datechange_query = "SELECT 
                COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END),0) as afs_profit
            FROM date_change_tickets dt
            LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
            WHERE DATE(dt.created_at) = ? AND dt.tenant_id = ?";

            $stmt = $pdo->prepare($datechange_query);
            $stmt->execute([$filter_date, $tenant_id]);
            $dateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            $dailySales['usd_profit'] += $refundedProfit['usd_profit'] + $dateChangeProfit['usd_profit'];
            $dailySales['afs_profit'] += $refundedProfit['afs_profit'] + $dateChangeProfit['afs_profit'];

            $response['status'] = 'success';
            $response['message'] = 'Daily sales data retrieved successfully';
            $response['data'] = $dailySales;
            $response['display_date'] = date('d M Y', strtotime($filter_date));
            break;

        case 'monthly':
            if (!preg_match('/^\d{4}-\d{2}$/', $filter_date)) {
                $response['message'] = 'Invalid date format. Expected: YYYY-MM';
                break;
            }
            list($year, $month) = explode('-', $filter_date);

            $monthly_query = "SELECT 
                COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END),0) as afs_profit
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

            $stmt = $pdo->prepare($monthly_query);
            $stmt->execute([
                $month,$year,$tenant_id,
                $month,$year,$tenant_id,
                $month,$year,$tenant_id,
                $month,$year,$tenant_id,
                $month,$year,$tenant_id,
                $month,$year,$tenant_id,
                $month,$year,$tenant_id
            ]);
            $monthlySales = $stmt->fetch(PDO::FETCH_ASSOC);

            // Refunded and date change for monthly
            $refunded_query = "SELECT 
                COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as afs_profit
            FROM refunded_tickets rt
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE MONTH(rt.created_at) = ? AND YEAR(rt.created_at) = ? AND rt.tenant_id = ?";

            $stmt = $pdo->prepare($refunded_query);
            $stmt->execute([$month, $year, $tenant_id]);
            $refundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            $datechange_query = "SELECT 
                COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END),0) as afs_profit
            FROM date_change_tickets dt
            LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
            WHERE MONTH(dt.created_at) = ? AND YEAR(dt.created_at) = ? AND dt.tenant_id = ?";

            $stmt = $pdo->prepare($datechange_query);
            $stmt->execute([$month,$year,$tenant_id]);
            $dateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            $monthlySales['usd_profit'] += $refundedProfit['usd_profit'] + $dateChangeProfit['usd_profit'];
            $monthlySales['afs_profit'] += $refundedProfit['afs_profit'] + $dateChangeProfit['afs_profit'];

            $response['status'] = 'success';
            $response['message'] = 'Monthly sales data retrieved successfully';
            $response['data'] = $monthlySales;
            $response['display_date'] = date('M Y', strtotime("$year-$month-01"));
            break;

        case 'yearly':
            if (!preg_match('/^\d{4}$/', $filter_date)) {
                $response['message'] = 'Invalid year format. Expected: YYYY';
                break;
            }
            $year = $filter_date;

            $yearly_query = "SELECT 
                COALESCE(SUM(CASE WHEN currency = 'USD' THEN profit ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN currency = 'AFS' THEN profit ELSE 0 END),0) as afs_profit
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

            $stmt = $pdo->prepare($yearly_query);
            $stmt->execute([$year,$tenant_id,$year,$tenant_id,$year,$tenant_id,$year,$tenant_id,$year,$tenant_id,$year,$tenant_id,$year,$tenant_id]);
            $yearlySales = $stmt->fetch(PDO::FETCH_ASSOC);

            // Refunded tickets
            $refunded_query = "SELECT 
                COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN 
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END) ELSE 0 END),0) as afs_profit
            FROM refunded_tickets rt
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE YEAR(rt.created_at) = ? AND rt.tenant_id = ?";

            $stmt = $pdo->prepare($refunded_query);
            $stmt->execute([$year,$tenant_id]);
            $refundedProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            $datechange_query = "SELECT 
                COALESCE(SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END),0) as usd_profit,
                COALESCE(SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END),0) as afs_profit
            FROM date_change_tickets dt
            LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
            WHERE YEAR(dt.created_at) = ? AND dt.tenant_id = ?";

            $stmt = $pdo->prepare($datechange_query);
            $stmt->execute([$year,$tenant_id]);
            $dateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

            $yearlySales['usd_profit'] += $refundedProfit['usd_profit'] + $dateChangeProfit['usd_profit'];
            $yearlySales['afs_profit'] += $refundedProfit['afs_profit'] + $dateChangeProfit['afs_profit'];

            $response['status'] = 'success';
            $response['message'] = 'Yearly sales data retrieved successfully';
            $response['data'] = $yearlySales;
            $response['display_date'] = $year;
            break;
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
