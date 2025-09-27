<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

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
$type = isset($_POST['type']) ? $_POST['type'] : 'datechange';

// Log input parameters for debugging
error_log("get_datechange_details.php - Input parameters: period=$period, filteredDate=$filteredDate, type=$type");

// Set up date condition based on period and filtered date
if ($period === 'daily') {
    if ($filteredDate) {
        $dailyDate = $filteredDate;
    } else {
        $dailyDate = date('Y-m-d');
    }
    $dateCondition = "DATE(dt.created_at) = :date";
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
    $dateCondition = "MONTH(dt.created_at) = :month AND YEAR(dt.created_at) = :year";
    $params = [':month' => $month, ':year' => $year];
    
} elseif ($period === 'yearly') {
    if ($filteredDate) {
        $year = $filteredDate;
    } else {
        $year = date('Y');
    }
    $dateCondition = "YEAR(dt.created_at) = :year";
    $params = [':year' => $year];
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch date change ticket details
    $query = "SELECT 
        dt.id, 
        tb.passenger_name, 
        dt.pnr, 
        dt.created_at,
        dt.service_penalty as profit,
        dt.currency,
        ma.name as paid_to
    FROM date_change_tickets dt
    LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
    LEFT JOIN main_account ma ON dt.paid_to = ma.id
    WHERE $dateCondition
    ORDER BY dt.created_at DESC";
    
    error_log("Executing query: $query with params: " . json_encode($params));
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $dateChanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Retrieved " . count($dateChanges) . " date change tickets");
    
    // Return results as JSON
    echo json_encode([
        'status' => 'success',
        'data' => $dateChanges
    ]);
    
} catch (PDOException $e) {
    $errorMessage = "Error fetching date change ticket details: " . $e->getMessage();
    error_log($errorMessage);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?> 