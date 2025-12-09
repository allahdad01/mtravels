<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';

$supplierId = $_GET['supplierId'];
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Base SQL query for fetching transactions
$query = "
    SELECT st.transaction_date, st.transaction_type, s.currency, st.amount, st.remarks
    FROM supplier_transactions st left join suppliers s on st.supplier_id = s.id
    WHERE st.supplier_id = ? AND st.tenant_id = ? AND st.branch_id = ?

    UNION ALL

    SELECT transaction_date, transaction_type, currency, amount, remarks
    FROM funding_transactions
    WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?
";

// Build dynamic conditions for date range
$conditions = [];
$params = ["iiiiii"]; // Base parameter types for supplier_id
$values = [$supplierId, $tenant_id, $branch_id, $supplierId, $tenant_id, $branch_id];

if ($startDate) {
    $conditions[] = "transaction_date >= ?";
    $params[0] .= "s"; // Add a string parameter type
    $values[] = $startDate;
}

if ($endDate) {
    $conditions[] = "transaction_date <= ?";
    $params[0] .= "s"; // Add a string parameter type
    $values[] = $endDate;
}

// Append date conditions to the query
if (!empty($conditions)) {
    $query .= " AND (" . implode(" AND ", $conditions) . ")";
}

// Prepare the statement
$stmt = $pdo->prepare($query);
$stmt->execute($values);

$transactions = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($transactions);
?>
