<?php
require_once '../../admin/security.php';
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
$branch_id = isset($_SESSION['branch_id']) ? $_SESSION['branch_id'] : null;


require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ CSRF Token Validation
    if (!verify_csrf_token()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
        exit;
    }
    
    $supplierId = $_POST['supplier_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $receipt = $_POST['receipt_number'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $currency = $_POST['supplier_currency'] ?? null;

    if (empty($supplierId) || empty($amount) || !is_numeric($amount) || $amount <= 0 || empty($currency)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided. Please check all fields.']);
        exit;
    }


    $pdo->beginTransaction();

    try {
        // Lock the supplier row to prevent race conditions
        $stmt = $pdo->prepare("SELECT balance, name FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ? FOR UPDATE");
        $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            throw new Exception("Supplier not found.");
        }
        $current_balance = $supplier['balance'];
        $supplierName = $supplier['name'];
        // Update supplier balance
        $new_balance = $current_balance + $amount;
        $stmt = $pdo->prepare("UPDATE suppliers SET balance = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_balance, PDO::PARAM_STR);
        $stmt->bindParam(2, $supplierId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
              throw new Exception("Failed to update supplier balance.");
        }

        // Insert transaction with complete remarks
        $completeRemarks = "Bonus added to supplier: $supplierName, processed by: $username, Remarks: $remarks";
        $transactionType = 'credit';
        $transactionOf = 'supplier_bonus';

        $stmt = $pdo->prepare("
            INSERT INTO supplier_transactions (
                supplier_id,
                transaction_type,
                amount,
                transaction_of,
                reference_id,
                remarks,
                balance,
                receipt,
                tenant_id,
                branch_id
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");
        
        $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
        $stmt->bindParam(2, $transactionType, PDO::PARAM_STR);
        $stmt->bindParam(3, $amount, PDO::PARAM_STR);
        $stmt->bindParam(4, $transactionOf, PDO::PARAM_STR);
        $stmt->bindParam(5, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $completeRemarks, PDO::PARAM_STR);
        $stmt->bindParam(7, $new_balance, PDO::PARAM_STR);
        $stmt->bindParam(8, $receipt, PDO::PARAM_STR);
        $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert supplier transaction.");
        }

        $lastInsertId = $pdo->lastInsertId();

        // Send notification to admin
        $notificationMessage = "Bonus of $amount $currency added to supplier: $supplierName, processed by: $username, Remarks: $remarks";
        $notificationQuery = "
            INSERT INTO notifications (
                transaction_id,
                transaction_type,
                tenant_id,
                branch_id,
                message,
                status,
                created_at
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ";

        $transaction_type = 'supplier_bonus';
        $status = 'Unread';
        $notificationStmt = $pdo->prepare($notificationQuery);
        $notificationStmt->bindParam(1, $lastInsertId, PDO::PARAM_INT);
        $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
        $notificationStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(5, $notificationMessage, PDO::PARAM_STR);
        $notificationStmt->bindParam(6, $status, PDO::PARAM_STR);
        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to send notification to admin.");
        }

        // Log the activity
        $old_values = json_encode([
            'supplier_id' => $supplierId,
            'supplier_balance' => $current_balance
        ]);
        
        $new_values = json_encode([
            'supplier_id' => $supplierId,
            'supplier_balance' => $new_balance,
            'amount' => $amount,
            'currency' => $currency,
            'remarks' => $remarks,
            'receipt_number' => $receipt
        ]);
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, tenant_id, branch_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, 'bonus', 'suppliers', ?, ?, ?, ?, ?, NOW())
        ");
        $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activityStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $activityStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $activityStmt->bindParam(4, $supplierId, PDO::PARAM_INT);
        $activityStmt->bindParam(5, $old_values, PDO::PARAM_STR);
        $activityStmt->bindParam(6, $new_values, PDO::PARAM_STR);
        $activityStmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $activityStmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $activityStmt->execute();

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Bonus added successfully!']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to add bonus: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
} 