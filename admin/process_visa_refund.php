<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
$user_id = $_SESSION['user_id'] ?? 0;
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');


// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$visa_id = isset($_POST['visa_id']) ? intval($_POST['visa_id']) : 0;
$refund_type = isset($_POST['refund_type']) ? $_POST['refund_type'] : '';
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';

// Validate required fields
if (!$visa_id || !$refund_type || empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields'
    ]);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Check if the visa exists and get its details
    $visaQuery = "SELECT * FROM visa_applications WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($visaQuery);
    $stmt->bind_param('ii', $visa_id, $tenant_id);
    $stmt->execute();
    $visaResult = $stmt->get_result();
    
    if ($visaResult->num_rows === 0) {
        throw new Exception('Visa application not found');
    }
    
    $visa = $visaResult->fetch_assoc();
    $originalProfit = floatval($visa['profit']);
    $originalBase = floatval($visa['base']);
    $originalSold = floatval($visa['sold']);
    
    // Calculate new profit based on refund type
    if ($refund_type === 'full') {
        // Full refund - set profit to zero and refund the total sold amount
        $newProfit = 0;
        $refund_amount = $originalSold; // Refund the total sold amount
        $refundToSupplier = $originalBase; // Full base amount refund to supplier
    } else {
        // Partial refund
        if ($refund_amount < 0) {
            throw new Exception('Invalid refund amount');
        }
        
        if ($refund_amount > $originalSold) {
            throw new Exception('Refund amount cannot be greater than sold amount');
        }

        // Calculate how much we're keeping (not refunding)
        $amountKept = $originalSold - $refund_amount;
        
        // New profit is simply the amount we keep
        $newProfit = $amountKept;
        
        // Calculate proportional refund to supplier based on refund amount
        $refundPercentage = $refund_amount / $originalSold;
        $refundToSupplier = $originalBase * $refundPercentage;
    }

    // Insert refund record
    $insertQuery = "INSERT INTO visa_refunds (visa_id, refund_type, refund_amount, reason, currency, processed_by, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('isdssii', $visa_id, $refund_type, $refund_amount, $reason, $currency, $user_id, $tenant_id);
    $stmt->execute();
    
    // Get the ID of the newly inserted refund record
    $refund_id = $conn->insert_id;
    
    // Update visa profit
    $updateQuery = "UPDATE visa_applications SET profit = ?, status = 'refunded' WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newProfit, $visa_id, $tenant_id);
    $stmt->execute();

     // Get supplier details
     $stmt_check_balance = $conn->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
     $stmt_check_balance->bind_param("ii", $visa['supplier'], $tenant_id);
     if (!$stmt_check_balance->execute()) {
         throw new Exception("Failed to fetch supplier details");
     }
     $supplierResult = $stmt_check_balance->get_result()->fetch_assoc();
     if (!$supplierResult) {
         throw new Exception("Supplier not found");
     }
     $current_balance = $supplierResult['balance'];
     $supplier_currency = $supplierResult['currency'];
     $supplier_name = $supplierResult['name'];
     $supplier_type = $supplierResult['supplier_type'];
     
     // Handle supplier balance and transaction for External suppliers
     if ($supplier_type === 'External') {
         // Convert refund amount if currencies differ
         $supplierRefundAmount = $refundToSupplier;
       
 
         // Update supplier balance
         $newSupplierBalance = $current_balance + $supplierRefundAmount;
         $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
         $updateSupplierStmt->bind_param("dii", $newSupplierBalance, $visa['supplier'], $tenant_id);
         if (!$updateSupplierStmt->execute()) {
             throw new Exception("Failed to update supplier balance");
         }
 
         // Record supplier transaction with balance
         $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
             (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id)
             VALUES (NOW(), ?, ?, ?, ?, 'credit', ?, 'visa_refund', ?)");
         $supplierRemarks = "Refund for visa application #$visa_id - " . $reason;
         $insertSupplierTransactionStmt->bind_param("iiddsi", 
             $visa['supplier'],
             $refund_id,
             $supplierRefundAmount,
             $newSupplierBalance,
             $supplierRemarks,
             $tenant_id
         );
     } else {
         // Record supplier transaction without balance for non-External suppliers
         $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
             (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id)
             VALUES (NOW(), ?, ?, ?, 'credit', ?, 'visa_refund', ?)");
         $supplierRemarks = "Refund for visa application #$visa_id - " . $reason;
         $insertSupplierTransactionStmt->bind_param("iidsi", 
             $visa['supplier'],
             $refund_id,
             $refundToSupplier,
             $supplierRemarks,
             $tenant_id
         );
     }
     if (!$insertSupplierTransactionStmt->execute()) {
         throw new Exception("Failed to record supplier transaction");
     }

    // Get client details and type
    $clientQuery = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ?");
    $clientQuery->bind_param("ii", $visa['sold_to'], $tenant_id);
    if (!$clientQuery->execute()) {
        throw new Exception("Failed to fetch client details");
    }
    $clientResult = $clientQuery->get_result()->fetch_assoc();
    if (!$clientResult) {
        throw new Exception("Client not found");
    }

    // Handle client balance for regular clients
    if ($clientResult['client_type'] === 'regular') {
        // Calculate the amount in the appropriate currency
        $refundInClientCurrency = $refund_amount;
       

        // Update client balance based on currency
        if ($currency === 'USD') {
            $newUsdBalance = $clientResult['usd_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($updateClientQuery);
            $stmt->bind_param("dii", $newUsdBalance, $visa['sold_to'], $tenant_id);
        } else {
            $newAfsBalance = $clientResult['afs_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($updateClientQuery);
            $stmt->bind_param("dii", $newAfsBalance, $visa['sold_to'], $tenant_id);
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update client balance");
        }

        // Record client transaction
        $clientTransactionQuery = "INSERT INTO client_transactions 
            (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id)
            VALUES (?, 'Credit', ?, ?, ?, ?, 'visa_refund', ?, NOW(), ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for visa application #$visa_id - $reason";
        $balance = ($currency === 'USD') ? $newUsdBalance : $newAfsBalance;
        $stmt->bind_param("iddssii", 
            $visa['sold_to'],
            $refundInClientCurrency,
            $balance,
            $currency,
            $clientTransactionDescription,
            $refund_id,
            $tenant_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    } else {
        // Record client transaction
        $clientTransactionQuery = "INSERT INTO client_transactions 
            (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id)
            VALUES (?, 'Credit', ?, ?, ?, 'visa_refund', ?, NOW(), ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for visa application #$visa_id - $reason";
       
        $stmt->bind_param("idssii", 
            $visa['sold_to'],
            $refund_amount,
        
            $currency,
            $clientTransactionDescription,
            $refund_id,
            $tenant_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Visa refund processed successfully',
        'refund_id' => $refund_id,
        'old_profit' => $originalProfit,
        'new_profit' => $newProfit
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing refund: ' . $e->getMessage()
    ]);
}

// Close the prepared statement and connection
if (isset($stmt)) {
    $stmt->close();
}
?> 