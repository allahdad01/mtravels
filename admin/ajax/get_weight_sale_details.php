<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Enable error reporting for debugging
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
$filteredDate = isset($_POST['filtered_date']) ? $_POST['filtered_date'] : null;
$type = isset($_POST['type']) ? $_POST['type'] : 'weight_sale';

// Log input parameters for debugging
error_log("get_weight_sale_details.php - Input parameters: period=$period, filteredDate=$filteredDate, type=$type");

// Set up date condition based on period and filtered date
if ($period === 'daily') {
    if ($filteredDate) {
        $dailyDate = $filteredDate;
    } else {
        $dailyDate = date('Y-m-d');
    }
    $dateCondition = "DATE(tw.created_at) = :date";
    $params = [':date' => $dailyDate];
    
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        // For monthly, filteredDate will be in format YYYY-MM
        $parts = explode('-', $filteredDate);
        $year = $parts[0];
        $month = $parts[1];
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(tw.created_at) = :month AND YEAR(tw.created_at) = :year";
    $params = [':month' => $month, ':year' => $year];
    
} elseif ($period === 'yearly') {
    if ($filteredDate) {
        $year = $filteredDate;
    } else {
        $year = date('Y');
    }
    $dateCondition = "YEAR(tw.created_at) = :year";
    $params = [':year' => $year];
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch ticket weight details
    $query = "SELECT
        tw.id, tb.passenger_name, tb.pnr, tb.airline,
        tw.created_at, tw.profit, tb.currency, ma.name as paid_to
    FROM ticket_weights tw
    LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id
    LEFT JOIN main_account ma ON tb.paid_to = ma.id
    WHERE $dateCondition AND tw.tenant_id = :tenant_id
    ORDER BY tw.created_at DESC";

    // Add tenant_id to params
    $params[':tenant_id'] = $tenant_id;

    error_log("Executing query: $query with params: " . json_encode($params));
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Retrieved " . count($weights) . " ticket weights");
    
    // Return results as JSON
    echo json_encode([
        'status' => 'success',
        'data' => $weights
    ]);
    
} catch (PDOException $e) {
    $errorMessage = "Error fetching ticket weight details: " . $e->getMessage();
    error_log($errorMessage);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?> 