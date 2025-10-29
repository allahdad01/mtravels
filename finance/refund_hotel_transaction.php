<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once('../includes/db.php');

// Set response headers
header('Content-Type: application/json');

// Check if required data is provided
if (!isset($_POST['transaction_id']) || !isset($_POST['booking_id']) || !isset($_POST['amount']) || !isset($_POST['currency']) || !isset($_POST['refund_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);

// Validate refund_date
$refund_date = isset($_POST['refund_date']) ? DbSecurity::validateInput($_POST['refund_date'], 'date') : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    exit();
}

// Get parameters
$transactionId = intval($_POST['transaction_id']);
$bookingId = intval($_POST['booking_id']);
$amount = floatval($_POST['amount']); // This should be a positive value
$currency = $_POST['currency'];
$refund_date = $_POST['refund_date'];

// Validate parameters
if ($transactionId <= 0 || $bookingId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Begin transaction for data consistency
    $pdo->beginTransaction();
    
    // First, get the original transaction to make sure it exists
    $stmt = $pdo->prepare("
        SELECT * FROM main_account_transactions 
        WHERE id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ?
    ");
    $stmt->execute([$transactionId, $bookingId, $tenant_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Check if this transaction is already a refund
    if ($transaction['type'] === 'refund') {
        throw new Exception('Cannot refund a refund transaction');
    }
    
    // Check if this transaction has already been refunded
    $checkRefundStmt = $pdo->prepare("
        SELECT COUNT(*) FROM main_account_transactions 
        WHERE description LIKE ? AND transaction_of = 'hotel' AND type = 'refund' AND tenant_id = ?
    ");
    $checkRefundStmt->execute(['%Refund for transaction #' . $transactionId . '%']);
    $refundCount = $checkRefundStmt->fetchColumn();
    
    if ($refundCount > 0) {
        throw new Exception('This transaction has already been refunded');
    }
    
    // Get the main_account_id from the original transaction
    $mainAccountId = $transaction['main_account_id'];
    
    // Get current main account balance
    $balanceQuery = $currency === 'USD' 
        ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ?" 
        : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ?";
    
    $stmtGetBalance = $pdo->prepare($balanceQuery);
    $stmtGetBalance->execute([$mainAccountId, $tenant_id]);
    $currentBalance = $stmtGetBalance->fetchColumn();
    
    // Calculate new main account balance (subtract for refund)
    $newBalance = $currentBalance - $amount;
    
    // Update main account balance
    $updateQuery = $currency === 'USD'
        ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ?"
        : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
    
    $stmtUpdateBalance = $pdo->prepare($updateQuery);
    if (!$stmtUpdateBalance->execute([$newBalance, $mainAccountId, $tenant_id])) {
        throw new Exception('Failed to update main account balance');
    }
    
    // Create a new refund transaction
    $now = date('Y-m-d H:i:s');
    $refund_description = "Refund for: " . ($transaction['description'] ? $transaction['description'] : "Transaction #" . $transactionId);
    $userId = $_SESSION['user_id'];
    
    // Insert the refund transaction
    $stmt = $pdo->prepare("
        INSERT INTO main_account_transactions 
        (main_account_id, reference_id, transaction_of, type, amount, balance, currency, description, created_by, created_at, tenant_id) 
        VALUES (?, ?, 'hotel', 'refund', ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $mainAccountId,
        $bookingId,
        -$amount, // Store as negative amount for refund
        $newBalance,
        $currency,
        $refund_description,
        $userId,
        $now,
        $tenant_id
    ]);
    
    $refundTransactionId = $pdo->lastInsertId();
    
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare new values data
    $new_values = [
        'transaction_id' => $refundTransactionId,
        'original_transaction_id' => $transactionId,
        'hotel_booking_id' => $bookingId,
        'main_account_id' => $mainAccountId,
        'amount' => -$amount,
        'currency' => $currency,
        'balance' => $newBalance,
        'description' => $refund_description,
        'refund_date' => $refund_date
    ];
    
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log 
        (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'add', 'main_account_transactions', ?, '{}', ?, ?, ?, NOW(), ?)");
    
    $new_values_json = json_encode($new_values);
    $activity_log_stmt->execute([$user_id, $refundTransactionId, $new_values_json, $ip_address, $user_agent, $tenant_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'transaction_id' => $refundTransactionId,
        'amount' => $amount,
        'currency' => $currency
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Error processing refund: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process refund: ' . $e->getMessage()
    ]);
}
?> 