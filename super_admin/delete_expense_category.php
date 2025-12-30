<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

$csrf = $_GET['csrf'] ?? '';
if ($csrf !== $_SESSION['csrf_token']) {
    header('Location: manage_expense_categories.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Invalid category ID');
    }

    // Check if category has expenses
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_expenses WHERE category_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        throw new Exception('Cannot delete category with existing expenses');
    }

    // Get category details
    $stmt = $pdo->prepare("SELECT id, name FROM system_expense_categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        throw new Exception('Category not found');
    }

    // Delete category
    $stmt = $pdo->prepare("DELETE FROM system_expense_categories WHERE id = ?");
    $stmt->execute([$id]);

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'delete_expense_category', 'system_expense_categories', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Deleted expense category: {$category['name']}"
    ]);

    header('Location: manage_expense_categories.php?success=Category+deleted+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error deleting expense category: " . $e->getMessage());
    header('Location: manage_expense_categories.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
