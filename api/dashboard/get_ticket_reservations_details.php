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


// Prepare parameters array with tenant_id
$params = [
    ':tenant_id_join' => $tenant_id,
    ':branch_id_join' => $branch_id,
    ':tenant_id' => $tenant_id,
    ':branch_id' => $branch_id
];

// Set up date condition
if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(tr.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(tr.created_at) = :month AND YEAR(tr.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(tr.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch ticket reservation details
    $query = "SELECT
        tr.id,
        tr.passenger_name,
        tr.pnr,
        tr.airline,
        tr.created_at,
        tr.profit,
        tr.currency,
        ma.name as paid_to
    FROM ticket_reservations tr
    LEFT JOIN main_account ma ON tr.paid_to = ma.id AND ma.tenant_id = :tenant_id_join AND ma.branch_id = :branch_id_join
    WHERE $dateCondition AND tr.tenant_id = :tenant_id AND tr.branch_id = :branch_id
    ORDER BY tr.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // named parameters only
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'status' => 'success',
        'data' => $reservations
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
