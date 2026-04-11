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
    ':tenant_id_join' => $tenant_id,
    ':branch_id_join' => $branch_id,
    ':tenant_id' => $tenant_id,
    ':branch_id' => $branch_id
];

// Set up date condition
if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(ap.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(ap.created_at) = :month AND YEAR(ap.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(ap.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch additional payment details
    $query = "SELECT
        ap.id,
        ap.payment_type,
        ap.description,
        ap.created_at,
        ap.profit,
        ap.currency,
        ma.name as paid_to
    FROM additional_payments ap
    LEFT JOIN main_account ma ON ap.main_account_id = ma.id AND ma.tenant_id = :tenant_id_join AND ma.branch_id = :branch_id_join
    WHERE $dateCondition AND ap.tenant_id = :tenant_id AND ap.branch_id = :branch_id
    ORDER BY ap.created_at DESC";


    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // Use named parameters only
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'status' => 'success',
        'data' => $payments
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
