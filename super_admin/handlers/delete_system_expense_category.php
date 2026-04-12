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
        exit(json_encode(['success' => false, 'message' => 'Invalid category ID']));
    }

    // Check if category has expenses
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_expenses WHERE category_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        exit(json_encode(['success' => false, 'message' => 'Cannot delete category with expenses']));
    }

    // Delete category
    $stmt = $pdo->prepare("DELETE FROM system_expense_categories WHERE id = ?");
    $stmt->execute([$id]);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
        VALUES (?, 'delete_category', 'system_expense_category', ?, 'Deleted category', ?, NOW())
    ");
    $audit_stmt->execute([$_SESSION['user_id'], $id, $ip_address]);

    exit(json_encode(['success' => true, 'message' => 'Category deleted successfully']));

} catch (Exception $e) {
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}
?>
