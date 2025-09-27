<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve supplier ID and date range from the GET parameters
$supplierId = $_GET['supplierId'];
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Build query with date range filter
$query = "SELECT transaction_date, transaction_type, supplier_name, currency, amount, remarks FROM funding_transactions WHERE supplier_id = ? AND tenant_id = ?";

// Add date range condition if dates are provided
if ($startDate && $endDate) {
    $query .= " AND transaction_date BETWEEN ? AND ?";
} elseif ($startDate) {
    $query .= " AND transaction_date >= ?";
} elseif ($endDate) {
    $query .= " AND transaction_date <= ?";
}

// Prepare and execute query
if ($stmt = $conn->prepare($query)) {
    if ($startDate && $endDate) {
        $stmt->bind_param("iiss", $supplierId, $tenant_id, $startDate, $endDate);
    } elseif ($startDate) {
        $stmt->bind_param("iis", $supplierId, $tenant_id, $startDate);
    } elseif ($endDate) {
        $stmt->bind_param("iis", $supplierId, $tenant_id, $endDate);
    } else {
        $stmt->bind_param("ii", $supplierId, $tenant_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = [];

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    // Return as JSON
    echo json_encode($transactions);
}
$conn->close();
?>
