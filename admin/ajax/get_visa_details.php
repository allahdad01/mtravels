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

// Prepare parameters array with tenant_id
$params = [':tenant_id' => $tenant_id];

// Set up date condition
if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(va.created_at) = :date";
    $params[':date'] = $dailyDate;
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        [$year, $month] = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(va.created_at) = :month AND YEAR(va.created_at) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(va.created_at) = :year";
    $params[':year'] = $year;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

try {
    // Fetch visa details
    $query = "SELECT 
        va.id,
        va.applicant_name,
        va.passport_number,
        va.visa_type,
        va.created_at,
        va.profit,
        va.currency,
        ma.name as paid_to
    FROM visa_applications va
    LEFT JOIN main_account ma ON va.paid_to = ma.id
    WHERE $dateCondition AND va.tenant_id = :tenant_id
    ORDER BY va.created_at DESC";

    error_log("Executing query: $query with params: " . json_encode($params));

    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // use named parameters only
    $visas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Retrieved " . count($visas) . " visa applications");

    echo json_encode([
        'status' => 'success',
        'data' => $visas
    ]);

} catch (PDOException $e) {
    error_log("Error fetching visa details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
