<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    ':tenant_id_f' => $tenant_id,
    ':branch_id_f' => $branch_id,
    ':tenant_id_ma' => $tenant_id,
    ':branch_id_ma' => $branch_id,
    ':tenant_id' => $tenant_id,
    ':branch_id' => $branch_id
];

// Set up date condition
if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(ub.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(ub.created_at) = :month AND YEAR(ub.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(ub.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch umrah booking details
    $query = "SELECT
        ub.booking_id as id,
        ub.name,
        ub.passport_number,
        ub.status,
        f.package_type,
        ub.created_at,
        ub.profit,
        ub.currency,
        ma.name as paid_to
    FROM umrah_bookings ub
    LEFT JOIN families f ON ub.family_id = f.family_id AND f.tenant_id = :tenant_id_f AND f.branch_id = :branch_id_f
    LEFT JOIN main_account ma ON ub.paid_to = ma.id AND ma.tenant_id = :tenant_id_ma AND ma.branch_id = :branch_id_ma
    WHERE $dateCondition AND ub.tenant_id = :tenant_id AND ub.branch_id = :branch_id
    ORDER BY ub.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // use named parameters only
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $bookings
    ]);

} catch (PDOException $e) {
    error_log("Error fetching umrah booking details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
