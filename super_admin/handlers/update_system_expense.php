<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

require_once '../../includes/db.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $payment_method = $_POST['payment_method'] ?? null;
    $reference_number = $_POST['reference_number'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (!$id || !$category_id || !$date || !$description || $amount <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Missing or invalid fields']));
    }

    $stmt = $pdo->prepare("
        UPDATE system_expenses 
        SET category_id = ?, date = ?, description = ?, amount = ?, currency = ?, 
            payment_method = ?, reference_number = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $success = $stmt->execute([
        $category_id, $date, $description, $amount, $currency,
        $payment_method, $reference_number, $notes, $id
    ]);

    if ($success) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
            VALUES (?, 'update_expense', 'system_expense', ?, ?, ?, NOW())
        ");
        $audit_stmt->execute([$_SESSION['user_id'], $id, "Updated expense: $description", $ip_address]);

        exit(json_encode(['success' => true, 'message' => 'Expense updated successfully']));
    } else {
        exit(json_encode(['success' => false, 'message' => 'Database error']));
    }

} catch (Exception $e) {
    error_log("Update expense error: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}
?>
