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
    header('Location: manage_system_expenses.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    // Validate input
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'USD');
    $description = trim($_POST['description'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? null);
    $reference_number = trim($_POST['reference_number'] ?? null);
    $notes = trim($_POST['notes'] ?? null);

    // Validate required fields
    if ($category_id <= 0 || empty($date) || $amount <= 0 || empty($description)) {
        throw new Exception('Missing required fields');
    }

    // Validate date format
    if (!strtotime($date)) {
        throw new Exception('Invalid date format');
    }

    // Verify category exists
    $stmt = $pdo->prepare("SELECT id FROM system_expense_categories WHERE id = ?");
    $stmt->execute([$category_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid category selected');
    }

    // Handle file upload
    $receipt_file = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['size'] > 0) {
        $file = $_FILES['receipt_file'];
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception('Invalid file type');
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception('File too large (max 5MB)');
        }

        // Create upload directory
        $upload_dir = __DIR__ . '/../uploads/expenses/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $receipt_file = 'expense_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $upload_path = $upload_dir . $receipt_file;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload file');
        }
    }

    // Insert expense
    $stmt = $pdo->prepare("
        INSERT INTO system_expenses (
            category_id, date, description, amount, currency,
            payment_method, reference_number, receipt_file, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $success = $stmt->execute([
        $category_id,
        $date,
        $description,
        $amount,
        $currency,
        $payment_method,
        $reference_number,
        $receipt_file,
        $notes,
        $_SESSION['user_id']
    ]);

    if (!$success) {
        throw new Exception('Failed to create expense');
    }

    // Log action
    $expense_id = $pdo->lastInsertId();
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'create_system_expense', 'system_expenses', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $expense_id,
        "Created expense: {$description} (${amount})"
    ]);

    header('Location: manage_system_expenses.php?success=Expense+created+successfully');
    exit();

} catch (Exception $e) {
    header('Location: manage_system_expenses.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
