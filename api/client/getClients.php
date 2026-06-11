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
    $stmt = $pdo->prepare("
        SELECT c.*,
            (
                SELECT COUNT(*) FROM client_transactions ct
                WHERE ct.client_id = c.id AND ct.tenant_id = c.tenant_id AND ct.branch_id = c.branch_id
            ) > 0 AS has_transactions
        FROM clients c
        WHERE c.tenant_id = ? AND c.branch_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($clients);
} catch (PDOException $e) {
    echo json_encode([]);
}
