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
    header('Location: manage_expense_categories.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? null);

    if (empty($name)) {
        throw new Exception('Category name is required');
    }

    if (strlen($name) > 100) {
        throw new Exception('Category name too long (max 100 characters)');
    }

    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM system_expense_categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        throw new Exception('Category with this name already exists');
    }

    // Insert category
    $stmt = $pdo->prepare("
        INSERT INTO system_expense_categories (name, description)
        VALUES (?, ?)
    ");

    $success = $stmt->execute([$name, $description]);

    if (!$success) {
        throw new Exception('Failed to create category');
    }

    $category_id = $pdo->lastInsertId();
    
    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'create_expense_category', 'system_expense_categories', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $category_id,
        "Created expense category: {$name}"
    ]);

    header('Location: manage_expense_categories.php?success=Category+created+successfully');
    exit();

} catch (Exception $e) {
    header('Location: manage_expense_categories.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
