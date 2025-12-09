<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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
    $pdo->beginTransaction();

    // Check if the booking exists and get its details
    $bookingQuery = "SELECT * FROM hotel_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($bookingQuery);
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Hotel booking not found');
    }
    $originalProfit = floatval($booking['profit']);
    $originalBase = floatval($booking['base_amount']);
    $originalSold = floatval($booking['sold_amount']);
    
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
    $insertQuery = "INSERT INTO hotel_refunds (booking_id, refund_type, refund_amount, reason, currency, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertQuery);
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $refund_type, PDO::PARAM_STR);
    $stmt->bindParam(3, $refund_amount, PDO::PARAM_STR);
    $stmt->bindParam(4, $reason, PDO::PARAM_STR);
    $stmt->bindParam(5, $currency, PDO::PARAM_STR);
    $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the ID of the newly inserted refund record
    $refund_id = $pdo->lastInsertId();
    
    // Update booking profit
    $updateQuery = "UPDATE hotel_bookings SET profit = ?, status = 'refunded' WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(1, $newProfit, PDO::PARAM_STR);
    $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get supplier details
    $stmt_check_balance = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_check_balance->bindParam(1, $booking['supplier_id'], PDO::PARAM_INT);
    $stmt_check_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_check_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_check_balance->execute()) {
        throw new Exception("Failed to fetch supplier details");
    }
    $supplierResult = $stmt_check_balance->fetch(PDO::FETCH_ASSOC);
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
        $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
        $updateSupplierStmt->bindParam(2, $booking['supplier_id'], PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Failed to update supplier balance");
        }

        // Record supplier transaction with balance
        $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
            VALUES (NOW(), ?, ?, ?, ?, 'credit', ?, 'hotel_refund', ?, ?)");
        $supplierRemarks = "Refund for hotel booking #$booking_id - " . $reason;
        $insertSupplierTransactionStmt->bindParam(1, $booking['supplier_id'], PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(2, $refund_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(3, $supplierRefundAmount, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(4, $newSupplierBalance, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(5, $supplierRemarks, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    } else {
        // Record supplier transaction without balance for non-External suppliers
        $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id, branch_id)
            VALUES (NOW(), ?, ?, ?, 'credit', ?, 'hotel_refund', ?, ?)");
        $supplierRemarks = "Refund for hotel booking #$booking_id - " . $reason;
        $insertSupplierTransactionStmt->bindParam(1, $booking['supplier_id'], PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(2, $refund_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(3, $refundToSupplier, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(4, $supplierRemarks, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    }
    if (!$insertSupplierTransactionStmt->execute()) {
        throw new Exception("Failed to record supplier transaction");
    }

    // Get client details and type
    $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $clientQuery->bindParam(1, $booking['sold_to'], PDO::PARAM_INT);
    $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$clientQuery->execute()) {
        throw new Exception("Failed to fetch client details");
    }
    $clientResult = $clientQuery->fetch(PDO::FETCH_ASSOC);
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
            $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateClientQuery);
            $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
            $stmt->bindParam(2, $booking['sold_to'], PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        } else {
            $newAfsBalance = $clientResult['afs_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateClientQuery);
            $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
            $stmt->bindParam(2, $booking['sold_to'], PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update client balance");
        }

        // Record client transaction
        $clientTransactionQuery = "INSERT INTO client_transactions
            (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
            VALUES (?, 'Credit', ?, ?, ?, ?, 'hotel_refund', ?, NOW(), ?, ?)";
        $stmt = $pdo->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for hotel booking #$booking_id - $reason";
        $balance = ($currency === 'USD') ? $newUsdBalance : $newAfsBalance;
        $stmt->bindParam(1, $booking['sold_to'], PDO::PARAM_INT);
        $stmt->bindParam(2, $refundInClientCurrency, PDO::PARAM_STR);
        $stmt->bindParam(3, $balance, PDO::PARAM_STR);
        $stmt->bindParam(4, $currency, PDO::PARAM_STR);
        $stmt->bindParam(5, $clientTransactionDescription, PDO::PARAM_STR);
        $stmt->bindParam(6, $refund_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    } else {
        // Record client transaction without balance for non-regular clients
        $clientTransactionQuery = "INSERT INTO client_transactions
            (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
            VALUES (?, 'Credit', ?, ?, ?, 'hotel_refund', ?, NOW(), ?, ?)";
        $stmt = $pdo->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for hotel booking #$booking_id - $reason";
        $stmt->bindParam(1, $booking['sold_to'], PDO::PARAM_INT);
        $stmt->bindParam(2, $refund_amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $clientTransactionDescription, PDO::PARAM_STR);
        $stmt->bindParam(5, $refund_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Hotel booking refund processed successfully',
        'refund_id' => $refund_id,
        'old_profit' => $originalProfit,
        'new_profit' => $newProfit
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing refund: ' . $e->getMessage()
    ]);
}
?> 