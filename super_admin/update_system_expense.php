<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_system_expenses.php?error=Invalid+CSRF+token');
    exit();
}

require_once '../includes/db.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'USD');
    $description = trim($_POST['description'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? null);
    $reference_number = trim($_POST['reference_number'] ?? null);
    $notes = trim($_POST['notes'] ?? null);

    if ($id <= 0 || $category_id <= 0 || empty($date) || $amount <= 0) {
        throw new Exception('Missing or invalid required fields');
    }

    // Verify expense exists
    $stmt = $pdo->prepare("SELECT id, receipt_file FROM system_expenses WHERE id = ?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        throw new Exception('Expense not found');
    }

    // Handle new file upload
    $receipt_file = $expense['receipt_file'];
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['size'] > 0) {
        $file = $_FILES['receipt_file'];
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception('Invalid file type');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large (max 5MB)');
        }

        // Delete old file
        if ($receipt_file) {
            $old_path = __DIR__ . '/../uploads/expenses/' . $receipt_file;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }

        // Upload new file
        $upload_dir = __DIR__ . '/../uploads/expenses/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $receipt_file = 'expense_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $upload_path = $upload_dir . $receipt_file;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload file');
        }
    }

    // Update expense
    $stmt = $pdo->prepare("
        UPDATE system_expenses
        SET category_id = ?, date = ?, description = ?, amount = ?,
            currency = ?, payment_method = ?, reference_number = ?,
            receipt_file = ?, notes = ?
        WHERE id = ?
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
        $id
    ]);

    if (!$success) {
        throw new Exception('Failed to update expense');
    }

    // Log action
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'update_system_expense', 'system_expenses', ?, ?)
    ");
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Updated expense: {$description} (${amount})"
    ]);

    header('Location: manage_system_expenses.php?success=Expense+updated+successfully');
    exit();

} catch (Exception $e) {
    error_log("Error updating system expense: " . $e->getMessage());
    header('Location: manage_system_expenses.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
