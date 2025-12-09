<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get client filter from request
$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Base query
$query = "
    SELECT
        rt.*,
        rt.supplier_penalty AS refund_supplier_penalty,
        rt.service_penalty AS refund_service_penalty,
        rt.refund_to_passenger,
        rt.status AS refund_status,
        rt.remarks AS refund_remarks,
        c.name AS client_name
    FROM
        refunded_tickets rt
    LEFT JOIN
        clients c ON rt.sold_to = c.id
    WHERE
        1=1 AND rt.tenant_id = ? AND rt.branch_id = ?
";

// Add client filter if specified
if ($clientId > 0) {
    $query .= " AND rt.sold_to = ?";
}

// Order by most recent first
$query .= " ORDER BY rt.id DESC";

// Execute query
$stmt = $pdo->prepare($query);
if ($clientId > 0) {
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $clientId, PDO::PARAM_INT);
} else {
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
}
$stmt->execute();

// Prepare response array
$tickets = [];

// Fetch results
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Calculate charges (sum of penalties)
    $charges = floatval($row['refund_supplier_penalty']) + floatval($row['refund_service_penalty']);

    // Add charges to the row
    $row['charges'] = $charges;

    // Add to tickets array
    $tickets[] = $row;
}

// Return JSON response
echo json_encode([
    'status' => 'success',
    'tickets' => $tickets
]);
?>