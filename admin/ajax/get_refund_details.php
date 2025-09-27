<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['status' => 'error', 'message' => 'Tenant not set']);
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to database
require_once('../../includes/db.php');

// Validate input
if (!isset($_POST['period']) || empty($_POST['period'])) {
    echo json_encode(['status' => 'error', 'message' => 'Period is required']);
    exit();
}

$period = $_POST['period'];
$filteredDate = $_POST['filtered_date'] ?? null;

// Log input parameters
error_log("get_refund_details.php - Input parameters: period=$period, filteredDate=$filteredDate");

// Set up date condition
$params = [':tenant_id' => $tenant_id];

if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(rt.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(rt.created_at) = :month AND YEAR(rt.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(rt.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch refunded ticket details
    $query = "SELECT 
        rt.id, 
        tb.passenger_name, 
        rt.pnr, 
        rt.created_at,
        (CASE 
            WHEN rt.calculation_method = 'base' THEN rt.service_penalty
            WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
            ELSE rt.service_penalty
        END) as profit,
        rt.currency,
        ma.name as paid_to
    FROM refunded_tickets rt
    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
    LEFT JOIN main_account ma ON rt.paid_to = ma.id
    WHERE $dateCondition AND rt.tenant_id = :tenant_id
    ORDER BY rt.created_at DESC";

    error_log("Executing query: $query with params: " . json_encode($params));

    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // Named parameters only
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Retrieved " . count($refunds) . " refunded tickets");

    echo json_encode([
        'status' => 'success',
        'data' => $refunds
    ]);

} catch (PDOException $e) {
    error_log("Error fetching refunded ticket details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
