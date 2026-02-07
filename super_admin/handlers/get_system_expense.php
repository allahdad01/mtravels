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

require_once '../../includes/db.php';

try {
    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        exit(json_encode(['success' => false, 'message' => 'Invalid expense ID']));
    }

    $stmt = $pdo->prepare("
        SELECT se.*, sec.name as category_name
        FROM system_expenses se
        LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
        WHERE se.id = ?
    ");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        exit(json_encode(['success' => false, 'message' => 'Expense not found']));
    }

    header('Content-Type: application/json');
    exit(json_encode($expense));

} catch (Exception $e) {
    error_log("Get expense error: " . $e->getMessage());
    // Never expose error details to client
    exit(json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']));
}
?>
