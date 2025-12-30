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
    header('Location: manage_system_expenses.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Invalid expense ID');
    }

    // Get expense details before deletion
    $stmt = $pdo->prepare("SELECT id, description, amount, receipt_file FROM system_expenses WHERE id = ?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        throw new Exception('Expense not found');
    }

    // Delete receipt file if exists
    if ($expense['receipt_file']) {
        $file_path = __DIR__ . '/../uploads/expenses/' . $expense['receipt_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete expense
    $stmt = $pdo->prepare("DELETE FROM system_expenses WHERE id = ?");
    $stmt->execute([$id]);

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'delete_system_expense', 'system_expenses', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Deleted expense: {$expense['description']} (${amount})"
    ]);

    header('Location: manage_system_expenses.php?success=Expense+deleted+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error deleting system expense: " . $e->getMessage());
    header('Location: manage_system_expenses.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
