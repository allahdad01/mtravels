<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once('../../includes/db.php');

header('Content-Type: application/json');

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(s.status, 'active') as status,
            (SELECT COUNT(*) FROM supplier_transactions st WHERE st.supplier_id = s.id AND st.tenant_id = s.tenant_id AND st.branch_id = s.branch_id) > 0 AS has_transactions
        FROM suppliers s
        WHERE s.tenant_id = ? AND s.branch_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'suppliers' => $suppliers
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        'suppliers' => [],
        'error' => 'Error fetching suppliers'
    ]);
}
?>
