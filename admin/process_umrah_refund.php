<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');

// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];


// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$refund_type = isset($_POST['refund_type']) ? $_POST['refund_type'] : '';
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';

// Validate required fields
if (!$booking_id || !$refund_type || empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields'
    ]);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Check if the booking exists and get its details
    $bookingQuery = "SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($bookingQuery);
    $stmt->bind_param('ii', $booking_id, $tenant_id);
    $stmt->execute();
    $bookingResult = $stmt->get_result();
    
    if ($bookingResult->num_rows === 0) {
        throw new Exception('Umrah booking not found');
    }
    
    $booking = $bookingResult->fetch_assoc();
    $originalProfit = floatval($booking['profit']);
    $originalBase = floatval($booking['price']);
    $originalSold = floatval($booking['sold_price']);
    
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
        
        // New profit is proportional to amount kept
        $newProfit = $amountKept;
        
        // Calculate proportional refund to supplier based on refund amount
        $refundPercentage = $refund_amount / $originalSold;
        $refundToSupplier = $originalBase * $refundPercentage;
    }

    // Insert refund record
    $insertQuery = "INSERT INTO umrah_refunds (booking_id, refund_type, refund_amount, reason, currency, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('isdssd', $booking_id, $refund_type, $refund_amount, $reason, $currency, $tenant_id);
    $stmt->execute();
    
    // Get the ID of the newly inserted refund record
    $refund_id = $conn->insert_id;
    
    // Update booking profit
    $updateQuery = "UPDATE umrah_bookings SET profit = ?, due = '0', status = 'refunded' WHERE booking_id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newProfit, $booking_id, $tenant_id);
    $stmt->execute();

    // Get supplier details
    $stmt_check_balance = $conn->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt_check_balance->bind_param("ii", $booking['supplier'], $tenant_id);
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
        $updateSupplierStmt->bind_param("dii", $newSupplierBalance, $booking['supplier'], $tenant_id);
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Failed to update supplier balance");
        }

        // Record supplier transaction with balance
        $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
            (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id)
            VALUES (NOW(), ?, ?, ?, ?, 'credit', ?, 'umrah_refund', ?)");
        $supplierRemarks = "Refund for umrah booking #$booking_id - " . $reason;
        $insertSupplierTransactionStmt->bind_param("iiddsi", 
            $booking['supplier'],
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
            VALUES (NOW(), ?, ?, ?, 'credit', ?, 'umrah_refund', ?)");
        $supplierRemarks = "Refund for umrah booking #$booking_id - " . $reason;
        $insertSupplierTransactionStmt->bind_param("iidsi", 
            $booking['supplier'],
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
    $clientQuery->bind_param("ii", $booking['sold_to'], $tenant_id);
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
            $stmt->bind_param("dii", $newUsdBalance, $booking['sold_to'], $tenant_id);
        } else {
            $newAfsBalance = $clientResult['afs_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($updateClientQuery);
            $stmt->bind_param("dii", $newAfsBalance, $booking['sold_to'], $tenant_id);
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update client balance");
        }

        // Record client transaction
        $clientTransactionQuery = "INSERT INTO client_transactions 
            (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id)
            VALUES (?, 'Credit', ?, ?, ?, ?, 'umrah_refund', ?, NOW(), ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for umrah booking #$booking_id - $reason";
        $balance = ($currency === 'USD') ? $newUsdBalance : $newAfsBalance;
        $stmt->bind_param("iddssii", 
            $booking['sold_to'],
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
        // Record client transaction without balance for non-regular clients
        $clientTransactionQuery = "INSERT INTO client_transactions 
            (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id)
            VALUES (?, 'Credit', ?, ?, ?, 'umrah_refund', ?, NOW(), ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for umrah booking #$booking_id - $reason";
        $stmt->bind_param("idssii", 
            $booking['sold_to'],
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
        'message' => 'Umrah booking refund processed successfully',
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
?> 