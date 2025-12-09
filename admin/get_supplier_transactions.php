<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/db.php';

// Retrieve supplier ID and date range from the GET parameters
$supplierId = $_GET['supplierId'];
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Build query with date range filter
$query = "SELECT transaction_date, transaction_type, supplier_name, currency, amount, remarks FROM funding_transactions WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?";

// Add date range condition if dates are provided
if ($startDate && $endDate) {
    $query .= " AND transaction_date BETWEEN ? AND ?";
} elseif ($startDate) {
    $query .= " AND transaction_date >= ?";
} elseif ($endDate) {
    $query .= " AND transaction_date <= ?";
}

// Prepare and execute query
$stmt = $pdo->prepare($query);
$params = [$supplierId, $tenant_id, $branch_id];

if ($startDate && $endDate) {
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate) {
    $params[] = $startDate;
} elseif ($endDate) {
    $params[] = $endDate;
}

$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Return as JSON
echo json_encode($transactions);
?>
