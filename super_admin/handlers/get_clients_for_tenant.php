<?php
session_start();

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once '../../includes/db.php';

$tenantId = (int)($_GET['tenant_id'] ?? 0);
$entityType = $_GET['entity_type'] ?? 'client';

if (!$tenantId) {
    exit(json_encode(['success' => false, 'message' => 'Tenant ID required']));
}

if ($entityType === 'client') {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.client_type, ct.currency,
               COUNT(DISTINCT ct.id) AS txn_count
        FROM clients c
        JOIN client_transactions ct ON ct.client_id = c.id AND ct.tenant_id = c.tenant_id
        WHERE c.tenant_id = ?
        GROUP BY c.id, c.name, c.client_type, ct.currency
        HAVING txn_count > 0
        ORDER BY c.name ASC, ct.currency ASC
    ");
    $stmt->execute([$tenantId]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
    exit(json_encode(['success' => true, 'items' => $items]));

} elseif ($entityType === 'supplier') {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.currency, s.supplier_type,
               COUNT(DISTINCT st.id) AS txn_count
        FROM suppliers s
        JOIN supplier_transactions st ON st.supplier_id = s.id AND st.tenant_id = s.tenant_id
        WHERE s.tenant_id = ?
        GROUP BY s.id, s.name, s.currency, s.supplier_type
        HAVING txn_count > 0
        ORDER BY s.name ASC
    ");
    $stmt->execute([$tenantId]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
    exit(json_encode(['success' => true, 'items' => $items]));

} elseif ($entityType === 'main_account') {
    $stmt = $pdo->prepare("
        SELECT ma.id, ma.name, ma.account_type, mat.currency,
               COUNT(DISTINCT mat.id) AS txn_count
        FROM main_account ma
        JOIN main_account_transactions mat ON mat.main_account_id = ma.id AND mat.tenant_id = ma.tenant_id
        WHERE ma.tenant_id = ?
        GROUP BY ma.id, ma.name, ma.account_type, mat.currency
        HAVING txn_count > 0
        ORDER BY ma.name ASC, mat.currency ASC
    ");
    $stmt->execute([$tenantId]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
    exit(json_encode(['success' => true, 'items' => $items]));

} else {
    exit(json_encode(['success' => false, 'message' => 'Invalid entity type']));
}
