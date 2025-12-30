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
    header('Location: manage_system_revenue.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Invalid revenue ID');
    }

    // Get revenue details
    $stmt = $pdo->prepare("SELECT id, revenue_type, amount FROM system_revenue WHERE id = ?");
    $stmt->execute([$id]);
    $revenue = $stmt->fetch();

    if (!$revenue) {
        throw new Exception('Revenue record not found');
    }

    // Delete revenue
    $stmt = $pdo->prepare("DELETE FROM system_revenue WHERE id = ?");
    $stmt->execute([$id]);

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'delete_system_revenue', 'system_revenue', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Deleted revenue: {$revenue['revenue_type']} (${amount})"
    ]);

    header('Location: manage_system_revenue.php?success=Revenue+record+deleted+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error deleting system revenue: " . $e->getMessage());
    header('Location: manage_system_revenue.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
