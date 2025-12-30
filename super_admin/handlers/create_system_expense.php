<?php
session_start();

// Verify super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// CSRF validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

require_once '../../includes/db.php';

try {
    // Validate inputs
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $payment_method = $_POST['payment_method'] ?? null;
    $reference_number = $_POST['reference_number'] ?? null;
    $notes = $_POST['notes'] ?? null;

    // Validation
    if (!$category_id || !$date || !$description || $amount <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Missing or invalid required fields']));
    }

    if (!in_array($currency, ['USD', 'AFS'])) {
        $currency = 'USD';
    }

    // Handle file upload
    $receipt_file = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['receipt_file'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if ($file['size'] > $max_size) {
            exit(json_encode(['success' => false, 'message' => 'File too large (max 5MB)']));
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            exit(json_encode(['success' => false, 'message' => 'Invalid file type']));
        }

        $upload_dir = '../../uploads/expenses/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = 'expense_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $receipt_file = 'uploads/expenses/' . $filename;
        }
    }

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO system_expenses 
        (category_id, date, description, amount, currency, payment_method, reference_number, receipt_file, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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

    if ($success) {
        // Log to audit
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
            VALUES (?, 'create_expense', 'system_expense', ?, ?, ?, NOW())
        ");
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $pdo->lastInsertId(),
            "Created expense: $description ($currency $amount)",
            $ip_address
        ]);

        exit(json_encode(['success' => true, 'message' => 'Expense created successfully', 'id' => $pdo->lastInsertId()]));
    } else {
        exit(json_encode(['success' => false, 'message' => 'Database error']));
    }

} catch (Exception $e) {
    error_log("Expense creation error: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}
?>
