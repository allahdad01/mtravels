<?php
/**
 * Payments Journal — ledger entity list for the per-tab filter dropdown.
 *
 * Returns every selectable account/party of a given ledger type so the
 * journal toolbar can offer "All <type>" plus each individual entity.
 *
 * GET params:
 *   type   required — main_account | client | supplier
 *
 * Roles: admin, finance.
 */

session_status() === PHP_SESSION_NONE && session_start();

require_once __DIR__ . '/../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$allowed_types = ['main_account', 'client', 'supplier'];
if (!in_array($type, $allowed_types, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

$items = [];

try {
    switch ($type) {
        case 'main_account':
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM main_account
                WHERE tenant_id = ? AND branch_id = ?
                ORDER BY name
            ");
            $stmt->execute([$tenant_id, $branch_id]);
            break;

        case 'client':
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM clients
                WHERE tenant_id = ? AND branch_id = ?
                ORDER BY name
            ");
            $stmt->execute([$tenant_id, $branch_id]);
            break;

        case 'supplier':
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM suppliers
                WHERE tenant_id = ? AND branch_id = ?
                ORDER BY name
            ");
            $stmt->execute([$tenant_id, $branch_id]);
            break;
    }

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $items[] = [
            'id'   => (int) $r['id'],
            'name' => trim($r['name']),
        ];
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Journal entities error: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'type' => $type, 'items' => $items]);
