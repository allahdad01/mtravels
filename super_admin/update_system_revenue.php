<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_system_revenue.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $revenue_type = trim($_POST['revenue_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'USD');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $status = trim($_POST['status'] ?? 'completed');
    $description = trim($_POST['description'] ?? null);
    $reference_id = trim($_POST['reference_id'] ?? null);
    $notes = trim($_POST['notes'] ?? null);

    if ($id <= 0 || $tenant_id <= 0 || empty($revenue_type) || $amount <= 0) {
        throw new Exception('Missing or invalid required fields');
    }

    // Verify revenue exists
    $stmt = $pdo->prepare("SELECT id FROM system_revenue WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('Revenue record not found');
    }

    // Verify tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid tenant selected');
    }

    // Update revenue
    $stmt = $pdo->prepare("
        UPDATE system_revenue
        SET tenant_id = ?, revenue_type = ?, amount = ?, currency = ?,
            payment_date = ?, status = ?, description = ?,
            reference_id = ?, notes = ?
        WHERE id = ?
    ");

    $success = $stmt->execute([
        $tenant_id,
        $revenue_type,
        $amount,
        $currency,
        $payment_date,
        $status,
        $description,
        $reference_id,
        $notes,
        $id
    ]);

    if (!$success) {
        throw new Exception('Failed to update revenue record');
    }

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'update_system_revenue', 'system_revenue', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Updated revenue: {$revenue_type} (${amount})"
    ]);

    header('Location: manage_system_revenue.php?success=Revenue+record+updated+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error updating system revenue: " . $e->getMessage());
    header('Location: manage_system_revenue.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
