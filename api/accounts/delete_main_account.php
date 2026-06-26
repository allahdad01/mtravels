<?php
require_once '../../admin/security.php';
enforce_auth(['admin']);

require_once '../../includes/db.php';

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

$data = json_decode(file_get_contents("php://input"), true);

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
    exit;
}

$account_id = intval($data['id'] ?? 0);
if (!$account_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM main_account_transactions WHERE main_account_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$account_id, $tenant_id, $branch_id]);
    $txnCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($txnCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: $txnCount transaction(s) exist for this account. Remove them first."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$account_id, $tenant_id, $branch_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found or already deleted.']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $old_values = json_encode(['main_account_id' => $account_id]);

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'main_account', ?, ?, '{}', ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->execute([$user_id, $account_id, $old_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
