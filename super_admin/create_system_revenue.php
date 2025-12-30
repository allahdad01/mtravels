<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_system_revenue.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    // Validate input
    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $revenue_type = trim($_POST['revenue_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'USD');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $status = trim($_POST['status'] ?? 'completed');
    $description = trim($_POST['description'] ?? null);
    $reference_id = trim($_POST['reference_id'] ?? null);
    $notes = trim($_POST['notes'] ?? null);

    // Validate required fields
    if ($tenant_id <= 0 || empty($revenue_type) || $amount <= 0 || empty($payment_date)) {
        throw new Exception('Missing required fields');
    }

    // Validate date format
    if (!strtotime($payment_date)) {
        throw new Exception('Invalid date format');
    }

    // Verify tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid tenant selected');
    }

    // Insert revenue record
    $stmt = $pdo->prepare("
        INSERT INTO system_revenue (
            tenant_id, revenue_type, amount, currency, payment_date,
            status, description, reference_id, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $notes
    ]);

    if (!$success) {
        throw new Exception('Failed to create revenue record');
    }

    // Log action
    $revenue_id = $pdo->lastInsertId();
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'create_system_revenue', 'system_revenue', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $revenue_id,
        "Created revenue: {$revenue_type} (${amount})"
    ]);

    header('Location: manage_system_revenue.php?success=Revenue+record+created+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error creating system revenue: " . $e->getMessage());
    header('Location: manage_system_revenue.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
