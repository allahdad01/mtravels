<?php
// Enable CORS for API requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include required files
require_once '../admin/security.php';
require_once '../includes/db.php';
require_once '../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['action']) || $data['action'] !== 'reapply_visa') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

if (!isset($data['visa_id']) || !isset($data['new_status']) || !isset($data['reapply_reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Validate and fetch visa data
    $visa_id = intval($data['visa_id']);
    $new_status = $data['new_status'];
    $reapply_reason = $data['reapply_reason'];
    $base_amount = floatval($data['base_amount']);
    $sold_amount = floatval($data['sold_amount']);
    $currency = $data['currency'];
    $original_profit = floatval($data['sold_amount']) - floatval($data['base_amount']);
    // Validate status
    if (!in_array($new_status, ['Pending', 'Approved'])) {
        throw new Exception('Invalid re-apply status');
    }

    // Get current visa data
    $visaQuery = "SELECT * FROM visa_applications WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($visaQuery);
    $stmt->bind_param("ii", $visa_id, $tenant_id);
    $stmt->execute();
    $visaResult = $stmt->get_result();
    
    if ($visaResult->num_rows === 0) {
        throw new Exception('Visa application not found');
    }
    
    $visa = $visaResult->fetch_assoc();
    $stmt->close();

    // Check if visa is actually cancelled
    if (!in_array(strtolower($visa['status']), ['cancelled', 'rejected', 'withdrawn'])) {
        throw new Exception('Only cancelled, rejected, or withdrawn visas can be re-applied');
    }

    // Update visa status and restore profit
    $update_query = "UPDATE visa_applications 
                    SET status = ?, 
                        profit = ?, 
                        remarks = CONCAT(COALESCE(remarks, ''), '\n[RE-APPLIED] ', ?, ' | ', NOW()),
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?";
    
    $reapply_note = "Re-applied: " . $reapply_reason;
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sdsii", $new_status, $original_profit, $reapply_note, $visa_id, $tenant_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update visa status');
    }
    
    $stmt->close();

    // Handle balance restorations (reverse the cancellation logic)
    
    // 1. Restore supplier balance for external suppliers
    if ($visa['base'] > 0) {
        // Get supplier details
        $supplierQuery = $conn->prepare("SELECT supplier_type, balance, name FROM suppliers WHERE id = ? AND tenant_id = ?");
        $supplierQuery->bind_param("ii", $visa['supplier'], $tenant_id);
        $supplierQuery->execute();
        $supplierResult = $supplierQuery->get_result();
        
        if ($supplierResult->num_rows > 0) {
            $supplier = $supplierResult->fetch_assoc();
            
            try {
                // For external suppliers, we need to deduct the base amount (reverse the cancellation)
                if ($supplier['supplier_type'] === 'External') {
                    // Update supplier balance (deduct the base amount)
                    $newBalance = $supplier['balance'] - $visa['base'];
                    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($updateSupplierQuery);
                    $stmt->bind_param("dii", $newBalance, $visa['supplier'], $tenant_id);
                    $stmt->execute();
                }
                
                // Record supplier transaction
                $supplierTransactionQuery = "INSERT INTO supplier_transactions 
                    (supplier_id, transaction_type, amount, balance, remarks, transaction_of, reference_id, tenant_id)
                    VALUES (?, 'Debit', ?, ?, ?, 'visa_sale', ?, ?)";
                $stmt = $conn->prepare($supplierTransactionQuery);
                $supplierTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
                $balance = ($supplier['supplier_type'] === 'External') ? $newBalance : $supplier['balance'];
                $stmt->bind_param("iddsii", 
                    $visa['supplier'],
                    $visa['base'],
                    $balance,
                    $supplierTransactionDescription,
                    $visa_id,
                    $tenant_id
                );
                if (!$stmt->execute()) {
                    throw new Exception("Failed to record supplier transaction");
                }
            } catch (Exception $e) {
                throw new Exception("Supplier balance restoration failed: " . $e->getMessage());
            }
        }
        $supplierQuery->close();
    }

    // 2. Restore client balance for regular clients (reverse the cancellation)
    if ($visa['sold'] > 0) {
        // Get client details and type
        $clientQuery = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ?");
        $clientQuery->bind_param("ii", $visa['sold_to'], $tenant_id);
        $clientQuery->execute();
        $clientResult = $clientQuery->get_result();
        
        if ($clientResult->num_rows > 0) {
            $client = $clientResult->fetch_assoc();
            
            try {
                // Handle client balance for regular clients (restore the original charge)
                if ($client['client_type'] === 'regular') {
                    // Calculate the amount in the appropriate currency
                    $restoreInClientCurrency = $visa['sold'];
                   
                    // Update client balance based on currency (restore the original charge)
                    if ($visa['currency'] === 'USD') {
                        $newUsdBalance = $client['usd_balance'] + $restoreInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($updateClientQuery);
                        $stmt->bind_param("dii", $newUsdBalance, $visa['sold_to'], $tenant_id);
                    } else {
                        $newAfsBalance = $client['afs_balance'] + $restoreInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($updateClientQuery);
                        $stmt->bind_param("dii", $newAfsBalance, $visa['sold_to'], $tenant_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update client balance");
                    }
                    
                    // Record client transaction (credit to restore the original charge)
                    $clientTransactionQuery = "INSERT INTO client_transactions 
                        (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id)
                        VALUES (?, 'debit', ?, ?, ?, ?, 'visa_sale', ?, NOW(), ?)";
                    $stmt = $conn->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
                    $balance = ($visa['currency'] === 'USD') ? $newUsdBalance : $newAfsBalance;
                    $stmt->bind_param("iddssii", 
                        $visa['sold_to'],
                        $restoreInClientCurrency,
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
                        VALUES (?, 'debit', ?, ?, ?, 'visa_sale', ?, NOW(), ?)";
                    $stmt = $conn->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
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
                throw new Exception("Client balance restoration failed: " . $e->getMessage());
            }
        }
        $clientQuery->close();
    }

    // 3. Log activity
    try {
        // Prepare old and new values as JSON
        $old_values = json_encode([
            'status' => $visa['status'],
            'profit' => $visa['profit'],
            'remarks' => $visa['remarks']
        ]);
        
        $new_values = json_encode([
            'status' => $new_status,
            'profit' => $original_profit,
            'remarks' => "Re-applied: " . $reapply_reason
        ]);
        
        // Get client IP address and user agent
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $logQuery = "INSERT INTO activity_log 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                    VALUES (?, 'visa_reapply', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($logQuery);
        $stmt->bind_param("iissssi", 
            $_SESSION['user_id'],
            $visa_id,
            $old_values,
            $new_values,
            $ip_address,
            $user_agent,
            $tenant_id
        );
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Don't fail the entire operation for activity log failure
        error_log("Activity log failed: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Visa re-applied successfully with balance restorations and profit restoration',
        'data' => [
            'visa_id' => $visa_id,
            'old_status' => $visa['status'],
            'new_status' => $new_status,
            'applicant_name' => $visa['applicant_name'],
            'old_profit' => $visa['profit'],
            'new_profit' => $original_profit,
            'balance_restorations' => [
                'supplier_amount' => $visa['base'],
                'client_amount' => $visa['sold'],
                'currency' => $visa['currency']
            ]
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>
