<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../includes/permissions.php';
require_permission('dashboard.view');
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['status' => 'error', 'message' => 'Tenant not set']);
    exit();
}

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


// Set up date condition
$params = [
    ':tenant_id_join' => $tenant_id,
    ':branch_id_join' => $branch_id,
    ':tenant_id' => $tenant_id,
    ':branch_id' => $branch_id
];

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
    LEFT JOIN main_account ma ON hb.paid_to = ma.id AND ma.tenant_id = :tenant_id_join AND ma.branch_id = :branch_id_join
    WHERE $dateCondition AND hb.tenant_id = :tenant_id AND hb.branch_id = :branch_id
    ORDER BY hb.created_at DESC";


    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // Use named parameters only
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'status' => 'success',
        'data' => $hotels
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
