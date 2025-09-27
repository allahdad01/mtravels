<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once '../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$supplierId = $_GET['supplierId'];
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Base SQL query for fetching transactions
$query = "
    SELECT st.transaction_date, st.transaction_type, s.currency, st.amount, st.remarks 
    FROM supplier_transactions st left join suppliers s on st.supplier_id = s.id 
    WHERE st.supplier_id = ? AND st.tenant_id = ?

    UNION ALL

    SELECT transaction_date, transaction_type, currency, amount, remarks 
    FROM funding_transactions 
    WHERE supplier_id = ? AND tenant_id = ?
";

// Build dynamic conditions for date range
$conditions = [];
$params = ["iiii"]; // Base parameter types for supplier_id
$values = [$supplierId, $tenant_id, $supplierId, $tenant_id];

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
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param(...array_merge($params, $values));

    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($transactions);
} else {
    echo json_encode(["error" => "Failed to prepare statement."]);
}

$conn->close();
?>
