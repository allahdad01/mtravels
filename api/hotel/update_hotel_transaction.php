<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once('../../includes/db.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $bookingId = $_POST['booking_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDate = $_POST['payment_date'] ?? '';
    $newDescription = $_POST['payment_description'] ?? '';
    $transactionType = $_POST['transaction_type'] ?? 'payment';
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);
    
    // Map transaction type to credit/debit
    $dbTransactionType = ($transactionType === 'payment') ? 'credit' : 'debit';
    
    // Validate required fields

// Validate transaction_type
$transaction_type = isset($_POST['transaction_type']) ? DbSecurity::validateInput($_POST['transaction_type'], 'string', ['maxlength' => 255]) : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    if (!$transactionId || !$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or booking ID']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT amount, type, main_account_id, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        $type = $transaction['type'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        
        // Get hotel booking to determine currency
        $bookingStmt = $pdo->prepare("SELECT currency FROM hotel_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $bookingStmt->bindParam(1, $bookingId, PDO::PARAM_INT);
        $bookingStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $bookingStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $bookingStmt->execute();

        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("Hotel booking not found");
        }
        $currency = $booking['currency'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
        // Map currency codes to the correct database field names
        $currencyFieldMap = [
            'USD' => 'usd_balance',
            'AFS' => 'afs_balance',
            'EUR' => 'euro_balance',
            'DARHAM' => 'darham_balance'
        ];
        
        // Check if the currency is in our map
        if (!isset($currencyFieldMap[$currency])) {
            throw new Exception("Unknown currency: " . $currency);
        }
        
        // Get the correct field name
        $balanceField = $currencyFieldMap[$currency];
        
        // Check if transaction type has changed
        $typeChanged = ($type !== $dbTransactionType);
        
        // If transaction type changed, we need to reverse the original transaction and apply a new one
        if ($typeChanged) {
            // For a transaction that was credit and now is debit, we need to subtract 2x the original amount
            // For a transaction that was debit and now is credit, we need to add 2x the original amount
            $typeChangeAdjustment = ($type == 'credit') ? -2 * $originalAmount : 2 * $originalAmount;
            
            // Update subsequent transactions' balances
            $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ? 
                                     WHERE main_account_id = ? 
                                     AND id > ? 
                                     AND id != ?
                                     AND tenant_id = ?";
            $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bindParam(1, $typeChangeAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
            
            if (!$updateSubsequentStmt->execute()) {
                throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
            }
            
            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
            $stmt->bindParam(1, $typeChangeAdjustment, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance");
            }
        }
        
        // Update subsequent transactions' balances if amount changed
        if ($amountDifference != 0) {
            // For credit transactions, subsequent balances increase when amount increases
            // For debit transactions, subsequent balances decrease when amount increases
            $balanceAdjustment = ($dbTransactionType == 'credit') ? $amountDifference : -$amountDifference;
            
            $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ? 
                                     WHERE main_account_id = ? 
                                     AND id > ? 
                                     AND id != ?
                                     AND tenant_id = ?";
            $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);

            if (!$updateSubsequentStmt->execute()) {
                throw new Exception("Failed to update subsequent transactions");
            }

            // Get the current balance of the transaction
            $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
            $getCurrentBalanceStmt = $pdo->prepare($getCurrentBalanceQuery);
            $getCurrentBalanceStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);

            if (!$getCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to get current transaction balance");
            }

            $currentBalance = $getCurrentBalanceStmt->fetch(PDO::FETCH_ASSOC)['balance'];

            // Calculate new balance for this transaction
            $newBalance = $currentBalance + (($dbTransactionType == 'credit') ? $amountDifference : -$amountDifference);

            // Update the balance of the current transaction
            $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
            $updateCurrentBalanceStmt = $pdo->prepare($updateCurrentBalanceQuery);
            $updateCurrentBalanceStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
            $updateCurrentBalanceStmt->bindParam(2, $transactionId, PDO::PARAM_INT);
            $updateCurrentBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);

            if (!$updateCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to update current transaction balance");
            }
        }
        
        // Update the transaction
        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ?, type = ?, exchange_rate = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $newDate, PDO::PARAM_STR);
        $stmt->bindParam(4, $dbTransactionType, PDO::PARAM_STR);
        $stmt->bindParam(5, $exchange_rate, PDO::PARAM_STR);
        $stmt->bindParam(6, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction");
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && !$typeChanged && $mainAccountId) {
            // For credit transactions (received payments), increase balance if amount increases
            // For debit transactions (paid out), decrease balance if amount increases
            $balanceAdjustment = ($dbTransactionType == 'credit') ? $amountDifference : -$amountDifference;
            
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
            $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance");
            }
        }
        
        // If date changed, we need to reorder transactions and recalculate all balances
        if ($newDate != $originalDate) {
            // Get all transactions for this account, ordered by date
            $stmt = $pdo->prepare("SELECT id, amount, type, created_at
                                   FROM main_account_transactions
                                   WHERE main_account_id = ?
                                   AND tenant_id = ?
                                   ORDER BY created_at ASC, id ASC");
            $stmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Failed to retrieve transactions for reordering");
            }

            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recalculate running balance for all transactions
            $runningBalance = 0;
            foreach ($transactions as $tx) {
                $txAmount = floatval($tx['amount']);
                if ($tx['type'] == 'credit') {
                    $runningBalance += $txAmount;
                } else {
                    $runningBalance -= $txAmount;
                }

                // Update the balance for this transaction
                $updateStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                $updateStmt->bindParam(1, $runningBalance, PDO::PARAM_STR);
                $updateStmt->bindParam(2, $tx['id'], PDO::PARAM_INT);
                $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);

                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update transaction balance during reordering");
                }
            }
        }
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'transaction_id' => $transactionId,
            'booking_id' => $bookingId,
            'amount' => $originalAmount,
            'type' => $type,
            'created_at' => $originalDate,
            'description' => $transaction['description'] ?? '',
            'currency' => $currency
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'type' => $dbTransactionType,
            'description' => $newDescription,
            'created_at' => $newDate
        ];
        $action = 'update';
        $table_name = 'main_account_transactions';
        $record_id = $transactionId;
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Insert activity log
        $stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $action, PDO::PARAM_STR);
        $stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $stmt->bindParam(4, $record_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $old_values, PDO::PARAM_STR);
        $stmt->bindParam(6, $new_values, PDO::PARAM_STR);
        $stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 