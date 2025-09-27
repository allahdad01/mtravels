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
$type = $_POST['type'] ?? 'hotel';

// Log input parameters
error_log("get_hotel_details.php - Input parameters: period=$period, filteredDate=$filteredDate, type=$type");

// Set up date condition
$params = [':tenant_id' => $tenant_id];

if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(hb.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(hb.created_at) = :month AND YEAR(hb.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(hb.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch hotel booking details
    $query = "SELECT 
        hb.id, 
        CONCAT(hb.first_name, ' ', hb.last_name) as name, 
        hb.accommodation_details,
        hb.order_id,
        hb.created_at, 
        hb.profit, 
        hb.currency,
        ma.name as paid_to
    FROM hotel_bookings hb
    LEFT JOIN main_account ma ON hb.paid_to = ma.id
    WHERE $dateCondition AND hb.tenant_id = :tenant_id
    ORDER BY hb.created_at DESC";

    error_log("Executing query: $query with params: " . json_encode($params));

    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // Use named parameters only
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Retrieved " . count($hotels) . " hotel bookings");

    echo json_encode([
        'status' => 'success',
        'data' => $hotels
    ]);

} catch (PDOException $e) {
    error_log("Error fetching hotel booking details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
