<?php
require_once '../security.php';
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;


require_once '../../includes/db.php';
require_once '../../includes/conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = $_POST['supplier_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $receipt = $_POST['receipt_number'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $currency = $_POST['supplier_currency'] ?? null;

    if (empty($supplierId) || empty($amount) || !is_numeric($amount) || $amount <= 0 || empty($currency)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided. Please check all fields.']);
        exit;
    }

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Lock the supplier row to prevent race conditions
        $stmt = $conn->prepare("SELECT balance, name FROM suppliers WHERE id = ? AND tenant_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $supplierId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Supplier not found.");
        }
        
        $supplier = $result->fetch_assoc();
        $current_balance = $supplier['balance'];
        $supplierName = $supplier['name'];
        $stmt->close();
        
        // Update supplier balance
        $new_balance = $current_balance + $amount;
        $stmt = $conn->prepare("UPDATE suppliers SET balance = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("did", $new_balance, $supplierId, $tenant_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
             throw new Exception("Failed to update supplier balance.");
        }
        $stmt->close();

        // Insert transaction with complete remarks
        $completeRemarks = "Bonus added to supplier: $supplierName, processed by: $username, Remarks: $remarks";
        $transactionType = 'credit';
        $transactionOf = 'supplier_bonus';

        $stmt = $conn->prepare("
            INSERT INTO supplier_transactions (
                supplier_id,
                transaction_type,
                amount,
                transaction_of,
                reference_id,
                remarks,
                balance,
                receipt,
                tenant_id
            ) VALUES (
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
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for transaction insertion: " . $conn->error);
        }
        
        $stmt->bind_param("issssssss", 
            $supplierId,
            $transactionType,
            $amount,
            $transactionOf,
            $user_id,
            $completeRemarks,
            $new_balance,
            $receipt,
            $tenant_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert supplier transaction.");
        }
        
        $lastInsertId = $stmt->insert_id;
        $stmt->close();

        // Send notification to admin
        $notificationMessage = "Bonus of $amount $currency added to supplier: $supplierName, processed by: $username, Remarks: $remarks";
        $notificationQuery = "
            INSERT INTO notifications (
                transaction_id,
                transaction_type,
                tenant_id,
                message,
                status,
                created_at
            ) VALUES (
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
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $tenant_id, $notificationMessage, $status);
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
        
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
            VALUES (?, ?, 'bonus', 'suppliers', ?, ?, ?, ?, ?, NOW())
        ");
        $activityStmt->bind_param("iisssss", $user_id, $tenant_id, $supplierId, $old_values, $new_values, $ip_address, $user_agent);
        $activityStmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bonus added successfully!']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Supplier bonus error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add bonus: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
} 