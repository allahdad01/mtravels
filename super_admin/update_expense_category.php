<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_expense_categories.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? null);

    if ($id <= 0) {
        throw new Exception('Invalid category ID');
    }

    if (empty($name)) {
        throw new Exception('Category name is required');
    }

    // Verify category exists
    $stmt = $pdo->prepare("SELECT id FROM system_expense_categories WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('Category not found');
    }

    // Update category
    $stmt = $pdo->prepare("
        UPDATE system_expense_categories
        SET name = ?, description = ?
        WHERE id = ?
    ");

    $success = $stmt->execute([$name, $description, $id]);

    if (!$success) {
        throw new Exception('Failed to update category');
    }

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'update_expense_category', 'system_expense_categories', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Updated expense category: {$name}"
    ]);

    header('Location: manage_expense_categories.php?success=Category+updated+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error updating expense category: " . $e->getMessage());
    header('Location: manage_expense_categories.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
