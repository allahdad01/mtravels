<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    echo json_encode(['success' => false, 'message' => 'Session expired']); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

require_once '../includes/db.php';

$account_id = (int)($_GET['account_id'] ?? 0);
$currency = $_GET['currency'] ?? '';
$entity_type = $_GET['entity_type'] ?? 'main_account';

if ($account_id <= 0 || !in_array($currency, ['USD','AFS','EUR','DARHAM','SAR'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit();
}

try {
    // Get ALL IDs from the relevant table (id is a global auto-increment, not per-account)
    if ($entity_type === 'main_account') {
        $stmt = $pdo->query("SELECT id FROM main_account_transactions");
    } elseif ($entity_type === 'client') {
        $stmt = $pdo->query("SELECT id FROM client_transactions");
    } elseif ($entity_type === 'supplier') {
        $stmt = $pdo->query("SELECT id FROM supplier_transactions");
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid entity type']); exit();
    }

    $used_ids = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $used_ids[] = (int)$row['id'];
    }

    // Last 10 IDs for display (descending)
    $display_ids = $used_ids;
    rsort($display_ids);
    $display_ids = array_slice($display_ids, 0, 10);

    // Find first free ID: scan from 1 upward
    $used_set = array_flip($used_ids);
    $next_free_id = 1;
    $max_id = !empty($used_ids) ? max($used_ids) : 0;

    for ($i = 1; $i <= $max_id + 1; $i++) {
        if (!isset($used_set[$i])) {
            $next_free_id = $i;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'existing_ids' => $display_ids,
        'next_free_id' => $next_free_id,
        'currency' => $currency,
        'entity_type' => $entity_type,
        'account_id' => $account_id
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
