<?php
session_start();

// Session timeout validation
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

// CSRF validation - use hash_equals to prevent timing attacks
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

require_once '../../includes/db.php';

try {
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        exit(json_encode(['success' => false, 'message' => 'Invalid expense ID']));
    }

    // Get expense to delete file if exists
    $stmt = $pdo->prepare("SELECT receipt_file FROM system_expenses WHERE id = ?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        exit(json_encode(['success' => false, 'message' => 'Expense not found']));
    }

    // Delete file if exists
    if ($expense['receipt_file']) {
        $filepath = '../../' . $expense['receipt_file'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    // Delete expense
    $stmt = $pdo->prepare("DELETE FROM system_expenses WHERE id = ?");
    $stmt->execute([$id]);

    // Log to audit
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
        VALUES (?, 'delete_expense', 'system_expense', ?, 'Deleted expense', ?, NOW())
    ");
    $audit_stmt->execute([$_SESSION['user_id'], $id, $ip_address]);

    exit(json_encode(['success' => true, 'message' => 'Expense deleted successfully']));

} catch (Exception $e) {
    error_log("Delete expense error: " . $e->getMessage());
    // Never expose error details to client
    exit(json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']));
}
?>
