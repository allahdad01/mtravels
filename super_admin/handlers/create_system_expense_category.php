<?php
session_start();

// Session timeout validation
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../../login.php');
    exit();
}

// CSRF validation - use hash_equals to prevent timing attacks
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'CSRF token validation failed';
    header('Location: ../system_expenses.php');
    exit();
}

require_once '../../includes/db.php';

try {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$name) {
        $_SESSION['error'] = 'Category name is required';
        header('Location: ../system_expenses.php');
        exit();
    }

    // Check if category exists
    $stmt = $pdo->prepare("SELECT id FROM system_expense_categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Category already exists';
        header('Location: ../system_expenses.php');
        exit();
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO system_expense_categories (name, description, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$name, $description ?: null]);

    $_SESSION['success'] = 'Category created successfully';

    // Log
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
        VALUES (?, 'create_category', 'system_expense_category', ?, ?, ?, NOW())
    ");
    $audit_stmt->execute([$_SESSION['user_id'], $pdo->lastInsertId(), "Created category: $name", $ip_address]);

    header('Location: ../system_expenses.php');

} catch (Exception $e) {
    error_log("Create category error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: ../system_expenses.php');
}
?>
