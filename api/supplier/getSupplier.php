<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication before reading session
enforce_auth();

require_once '../../includes/db.php';

header('Content-Type: application/json');

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

if (!$tenant_id || !$branch_id) {
    http_response_code(403);
    echo json_encode([
        'suppliers' => [],
        'error' => 'Invalid session. Please log in again.'
    ]);
    exit;
}

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
