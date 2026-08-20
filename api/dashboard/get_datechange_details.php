<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../includes/permissions.php';
require_permission('dashboard.view');
}

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
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Set up date condition based on period and filtered date
$params = [
    ':tenant_id_tb' => $tenant_id,
    ':branch_id_tb' => $branch_id,
    ':tenant_id_ma' => $tenant_id,
    ':branch_id_ma' => $branch_id,
    ':tenant_id' => $tenant_id,
    ':branch_id' => $branch_id
];

if ($period === 'daily') {
    if ($filteredDate) {
        $dailyDate = $filteredDate;
    } else {
        $dailyDate = date('Y-m-d');
    }
    $dateCondition = "DATE(dt.created_at) = :date";
    $params[':date'] = $dailyDate;
    
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
    $params[':month'] = $month;
    $params[':year'] = $year;
    
} elseif ($period === 'yearly') {
    if ($filteredDate) {
        $year = $filteredDate;
    } else {
        $year = date('Y');
    }
    $dateCondition = "YEAR(dt.created_at) = :year";
    $params[':year'] = $year;
    
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
    LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id AND tb.tenant_id = :tenant_id_tb AND tb.branch_id = :branch_id_tb
    LEFT JOIN main_account ma ON dt.paid_to = ma.id AND ma.tenant_id = :tenant_id_ma AND ma.branch_id = :branch_id_ma
    WHERE $dateCondition AND dt.tenant_id = :tenant_id AND dt.branch_id = :branch_id
    ORDER BY dt.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $dateChanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    // Return results as JSON
    echo json_encode([
        'status' => 'success',
        'data' => $dateChanges
    ]);
    
} catch (PDOException $e) {
    $errorMessage = "Error fetching date change ticket details: " . $e->getMessage();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?> 