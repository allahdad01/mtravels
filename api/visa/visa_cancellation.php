<?php
// Include security module
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Database connection
require_once('../../includes/db.php');
require_once '../../includes/conn.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['visa_id']) || !isset($input['new_status']) || !isset($input['cancellation_reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$visa_id = intval($input['visa_id']);
$new_status = $conn->real_escape_string($input['new_status']);
$cancellation_reason = $conn->real_escape_string($input['cancellation_reason']);
$current_status = isset($input['current_status']) ? $conn->real_escape_string($input['current_status']) : '';

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if visa exists and belongs to current tenant
    $check_query = "SELECT supplier, sold_to, id, status, applicant_name, base, sold, profit, currency, tenant_id 
                   FROM visa_applications 
                   WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $visa_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Visa not found or access denied');
    }
    
    $visa = $result->fetch_assoc();
    $stmt->close();
    
    // Check if visa is already cancelled/rejected
    if (in_array(strtolower($visa['status']), ['cancelled', 'rejected', 'withdrawn'])) {
        throw new Exception('Visa is already ' . strtolower($visa['status']));
    }
    
    // Log the cancellation attempt
    // Prepare old and new values as JSON
    $log_old_values = json_encode(['status' => $visa['status']]);
    $log_new_values = json_encode([
        'status' => $new_status,
        'cancellation_reason' => $cancellation_reason,
        'cancelled_by' => $_SESSION['user_id'],
        'cancelled_at' => date('Y-m-d H:i:s')
    ]);
    
    // Get client IP address and user agent
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $log_query = "INSERT INTO activity_log 
                  (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                  VALUES (?, 'visa_cancellation', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?)";
    
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param("iissssi", 
        $_SESSION['user_id'],
        $visa_id,
        $log_old_values,
        $log_new_values,
        $ip_address,
        $user_agent,
        $tenant_id
    );
    $stmt->execute();
    $stmt->close();
    
    // Update visa status and set profit to 0 (like refund logic)
    $update_query = "UPDATE visa_applications 
                    SET status = ?, 
                        profit = 0,
                        remarks = CONCAT(COALESCE(remarks, ''), '\n[CANCELLED] ', ?, ' | ', NOW()),
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?";
    
    $cancellation_note = "Cancelled: " . $cancellation_reason;
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssii", $new_status, $cancellation_note, $visa_id, $tenant_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update visa status');
    }
    
    $stmt->close();
    
    // Handle supplier balance reversal for External suppliers
    if ($visa['base'] > 0) {
        // Get supplier details
        $supplierQuery = "SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($supplierQuery);
        $stmt->bind_param("ii", $visa['supplier'], $tenant_id);
        $stmt->execute();
        $supplierResult = $stmt->get_result();
        
        if ($supplierResult->num_rows > 0) {
            $supplier = $supplierResult->fetch_assoc();
            
            try {
                // Handle supplier balance for External suppliers (reverse the original payment)
                if ($supplier['supplier_type'] === 'External') {
                    // Reverse the original base amount
                    $supplierRefundAmount = $visa['base'];
                    
                    // Update supplier balance (add back the amount that was originally deducted)
                    $newSupplierBalance = $supplier['balance'] + $supplierRefundAmount;
                    $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
                    $updateSupplierStmt->bind_param("dii", $newSupplierBalance, $visa['supplier'], $tenant_id);
                    if (!$updateSupplierStmt->execute()) {
                        throw new Exception("Failed to update supplier balance");
                    }
                    
                    // Record supplier transaction
                    $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
                        (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id)
                        VALUES (NOW(), ?, ?, ?, ?, 'Credit', ?, 'visa_cancellation', ?)");
                    $supplierRemarks = "Cancellation reversal for visa application #$visa_id - " . $cancellation_reason;
                    $insertSupplierTransactionStmt->bind_param("iiddsi", 
                        $visa['supplier'],
                        $visa_id,
                        $supplierRefundAmount,
                        $newSupplierBalance,
                        $supplierRemarks,
                        $tenant_id
                    );
                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to record supplier transaction");
                    }
                } else {
                    // Record supplier transaction without balance for non-External suppliers
                    $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
                        (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id)
                        VALUES (NOW(), ?, ?, ?, 'Credit', ?, 'visa_cancellation', ?)");
                    $supplierRemarks = "Cancellation reversal for visa application #$visa_id - " . $cancellation_reason;
                    $insertSupplierTransactionStmt->bind_param("iidsi", 
                        $visa['supplier'],
                        $visa_id,
                        $visa['base'],
                        $supplierRemarks,
                        $tenant_id
                    );
                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to record supplier transaction");
                    }
                }
            } catch (Exception $e) {
                throw new Exception("Supplier balance reversal failed: " . $e->getMessage());
            }
        }
        $stmt->close();
    }

    // Handle client balance reversal for regular clients
    if ($visa['sold'] > 0) {
        // Get client details and type
        $clientQuery = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ?");
        $clientQuery->bind_param("ii", $visa['sold_to'], $tenant_id);
        $clientQuery->execute();
        $clientResult = $clientQuery->get_result();
        
        if ($clientResult->num_rows > 0) {
            $client = $clientResult->fetch_assoc();
            
            try {
                // Handle client balance for regular clients (reverse the original sale)
                if ($client['client_type'] === 'regular') {
                    // Calculate the amount in the appropriate currency
                    $refundInClientCurrency = $visa['sold'];
                   
                    // Update client balance based on currency (reverse the original sale)
                    if ($visa['currency'] === 'USD') {
                        $newUsdBalance = $client['usd_balance'] - $refundInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($updateClientQuery);
                        $stmt->bind_param("dii", $newUsdBalance, $visa['sold_to'], $tenant_id);
                    } else {
                        $newAfsBalance = $client['afs_balance'] - $refundInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($updateClientQuery);
                        $stmt->bind_param("dii", $newAfsBalance, $visa['sold_to'], $tenant_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update client balance");
                    }
                    
                    // Record client transaction (debit to reverse the original sale)
                    $clientTransactionQuery = "INSERT INTO client_transactions 
                        (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id)
                        VALUES (?, 'Debit', ?, ?, ?, ?, 'visa_cancellation', ?, NOW(), ?)";
                    $stmt = $conn->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Cancellation reversal for visa application #$visa_id - $cancellation_reason";
                    $balance = ($visa['currency'] === 'USD') ? $newUsdBalance : $newAfsBalance;
                    $stmt->bind_param("iddssii", 
                        $visa['sold_to'],
                        $refundInClientCurrency,
                        $balance,
                        $visa['currency'],
                        $clientTransactionDescription,
                        $visa_id,
                        $tenant_id
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to record client transaction");
                    }
                } else {
                    // Record client transaction for agency clients without balance
                    $clientTransactionQuery = "INSERT INTO client_transactions 
                        (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id)
                        VALUES (?, 'Debit', ?, ?, ?, 'visa_cancellation', ?, NOW(), ?)";
                    $stmt = $conn->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Cancellation reversal for visa application #$visa_id - $cancellation_reason";
                    $stmt->bind_param("idssii", 
                        $visa['sold_to'],
                        $visa['sold'],
                        $visa['currency'],
                        $clientTransactionDescription,
                        $visa_id,
                        $tenant_id
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to record client transaction");
                    }
                }
            } catch (Exception $e) {
                throw new Exception("Client balance reversal failed: " . $e->getMessage());
            }
        }
        $clientQuery->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Visa cancelled successfully with balance reversals and profit reset',
        'data' => [
            'visa_id' => $visa_id,
            'old_status' => $visa['status'],
            'new_status' => $new_status,
            'applicant_name' => $visa['applicant_name'],
            'old_profit' => $visa['profit'],
            'new_profit' => 0,

        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?>