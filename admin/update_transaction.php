<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$transactionId = $_POST['transaction_id'] ?? '';
$transactionType = $_POST['transaction_type'] ?? '';
$originalAmount = floatval($_POST['original_amount'] ?? 0);
$originalType = $_POST['original_type'] ?? '';
$newAmount = floatval($_POST['amount'] ?? 0);
$newType = $_POST['type'] ?? '';
$transactionDate = $_POST['transaction_date'] ?? '';
$currency = $_POST['currency'] ?? '';
$receipt = $_POST['receipt'] ?? '';
$description = $_POST['description'] ?? '';

// Validate required fields
if (empty($transactionId) || empty($transactionType) || $newAmount <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate transaction_date
$transaction_date = isset($_POST['transaction_date']) ? DbSecurity::validateInput($_POST['transaction_date'], 'date') : null;

// Validate type
$type = isset($_POST['type']) ? DbSecurity::validateInput($_POST['type'], 'string', ['maxlength' => 255]) : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate original_type
$original_type = isset($_POST['original_type']) ? DbSecurity::validateInput($_POST['original_type'], 'string', ['maxlength' => 255]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate transaction_type
$transaction_type = isset($_POST['transaction_type']) ? DbSecurity::validateInput($_POST['transaction_type'], 'string', ['maxlength' => 255]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    $conn->begin_transaction();
    
    // Get account information based on transaction type
    $accountId = null;
    $accountName = '';
    
    if ($transactionType === 'main') {
        // Get main account transaction details
        $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$transactionId, $tenant_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        $accountId = $transaction['main_account_id'];
        
        // Get account name
        $accountStmt = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
        $accountStmt->execute([$accountId, $tenant_id]);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
        $accountName = $account['name'] ?? 'Unknown Account';
        
        // Calculate the difference in amount
        $originalSignedAmount = $originalType === 'credit' ? $originalAmount : -$originalAmount;
        $newSignedAmount = $newType === 'credit' ? $newAmount : -$newAmount;
        $amountDifference = $newSignedAmount - $originalSignedAmount;
        
        // Update the transaction - removed created_at from the update
        $updateStmt = $pdo->prepare("
            UPDATE main_account_transactions 
            SET amount = ?, type = ?, currency = ?, receipt = ?, description = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $updateStmt->execute([$newAmount, $newType, $currency, $receipt, $description, $transactionId, $tenant_id]);
        
        // Get all transactions after this one to update balances
        $laterTransactionsStmt = $pdo->prepare("
            SELECT id, amount, type, balance, currency
            FROM main_account_transactions 
            WHERE main_account_id = ? AND tenant_id = ? AND
                  (created_at > ? OR 
                  (created_at = ? AND id > ?)) AND
                  currency = ?
            ORDER BY created_at ASC, id ASC
        ");
        $laterTransactionsStmt->execute([$accountId, $tenant_id, $transaction['created_at'], $transaction['created_at'], $transactionId, $currency]);
        $laterTransactions = $laterTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update the current transaction's balance
        $currentBalance = $transaction['balance'] + $amountDifference;
        $updateBalanceStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
        $updateBalanceStmt->execute([$currentBalance, $transactionId, $tenant_id]);
        
        // Update all subsequent transactions' balances
        foreach ($laterTransactions as $laterTransaction) {
            $newBalance = $laterTransaction['balance'] + $amountDifference;
            $updateBalanceStmt->execute([$newBalance, $laterTransaction['id'], $tenant_id]);
        }
        
        // Update the main account balance - using direct currency check
        if ($currency === 'USD') {
            $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
            $updateMainAccountStmt->execute([$amountDifference, $accountId, $tenant_id]);
        } elseif ($currency === 'AFS') {
            $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
            $updateMainAccountStmt->execute([$amountDifference, $accountId, $tenant_id]);
        } elseif ($currency === 'EURO') {
            $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance + ? WHERE id = ? AND tenant_id = ?");
            $updateMainAccountStmt->execute([$amountDifference, $accountId, $tenant_id]);
        } elseif ($currency === 'DARHAM') {
            $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance + ? WHERE id = ? AND tenant_id = ?");
            $updateMainAccountStmt->execute([$amountDifference, $accountId, $tenant_id]);
        }
        
    } elseif ($transactionType === 'supplier') {
        // Get supplier transaction details
        $stmt = $conn->prepare("SELECT * FROM supplier_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transactionId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        $supplierId = $transaction['supplier_id'];
        
        // Get supplier name and currency
        $supplierStmt = $conn->prepare("SELECT name, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $supplierStmt->bind_param("ii", $supplierId, $tenant_id);
        $supplierStmt->execute();
        $supplierResult = $supplierStmt->get_result();
        $supplier = $supplierResult->fetch_assoc();
        $accountName = $supplier['name'] ?? 'Unknown Supplier';
        $accountId = $supplierId;
        $currency = $supplier['currency'] ?? 'USD'; // Get currency from suppliers table
        
        // Calculate the difference in amount
        $originalSignedAmount = $originalType === 'Credit' ? $originalAmount : -$originalAmount;
        $newSignedAmount = $newType === 'credit' ? $newAmount : -$newAmount;
        $amountDifference = $newSignedAmount - $originalSignedAmount;
        
        // Update the transaction - transaction_date already removed
        $updateStmt = $conn->prepare("
            UPDATE supplier_transactions 
            SET amount = ?, transaction_type = ?, receipt = ?, remarks = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $updateStmt->bind_param("dsssi", $newAmount, $newType, $receipt, $description, $transactionId, $tenant_id);
        $updateStmt->execute();
        
        // Get all transactions after this one to update balances
        $laterTransactionsStmt = $conn->prepare("
            SELECT id, amount, transaction_type, balance 
            FROM supplier_transactions 
            WHERE supplier_id = ? AND tenant_id = ? AND
                  (transaction_date > ? OR 
                  (transaction_date = ? AND id > ?)) 
            ORDER BY transaction_date ASC, id ASC
        ");
        $laterTransactionsStmt->bind_param("issi", $supplierId, $tenant_id, $transaction['transaction_date'], $transaction['transaction_date'], $transactionId);
        $laterTransactionsStmt->execute();
        $laterTransactionsResult = $laterTransactionsStmt->get_result();
        $laterTransactions = $laterTransactionsResult->fetch_all(MYSQLI_ASSOC);
        
        // Update the current transaction's balance
        $currentBalance = $transaction['balance'] + $amountDifference;
        $updateBalanceStmt = $conn->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
        $updateBalanceStmt->bind_param("di", $currentBalance, $transactionId, $tenant_id);
        $updateBalanceStmt->execute();
        
        // Update all subsequent transactions' balances
        foreach ($laterTransactions as $laterTransaction) {
            $newBalance = $laterTransaction['balance'] + $amountDifference;
            $updateBalanceStmt->bind_param("di", $newBalance, $laterTransaction['id'], $tenant_id);
            $updateBalanceStmt->execute();
        }
        
        // Update the supplier balance
        $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?");
        $updateSupplierStmt->bind_param("di", $amountDifference, $supplierId, $tenant_id);
        $updateSupplierStmt->execute();
        
        // Also update the corresponding main account transaction if it exists
        // Get main transaction details by transaction_of and reference_id instead of main_transaction_id
        $mainTransStmt = $pdo->prepare("
            SELECT * FROM main_account_transactions 
            WHERE transaction_of = 'supplier_fund' AND reference_id = ? AND tenant_id = ?
        ");
        $mainTransStmt->execute([$transactionId, $tenant_id]);
        $mainTransaction = $mainTransStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mainTransaction) {
            // Calculate the new balance for the main transaction
            $mainType = $newType === 'credit' ? 'debit' : 'credit';
            $mainAmountDifference = -$amountDifference; // Opposite effect on main account
            $newMainBalance = $mainTransaction['balance'] + $mainAmountDifference;
            
            // Update main transaction including balance - removed created_at from the update
            $updateMainStmt = $pdo->prepare("
                UPDATE main_account_transactions 
                SET amount = ?, type = ?, description = ?, receipt = ?, balance = ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $updateMainStmt->execute([
                $newAmount, 
                $mainType, 
                "Fund transfer to supplier: $accountName", 
                $receipt,
                $newMainBalance,
                $mainTransaction['id'],
                $tenant_id
            ]);
            
            // Get all transactions after this one to update balances
            $laterMainTransStmt = $pdo->prepare("
                SELECT id, balance, currency 
                FROM main_account_transactions 
                WHERE main_account_id = ? AND tenant_id = ? AND
                      (created_at > ? OR 
                      (created_at = ? AND id > ?)) AND
                      currency = ?
                ORDER BY created_at ASC, id ASC
            ");
            $laterMainTransStmt->execute([$mainTransaction['main_account_id'], $tenant_id, $mainTransaction['created_at'], $mainTransaction['created_at'], $mainTransaction['id'], $currency]);
            $laterMainTransactions = $laterMainTransStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update all subsequent transactions' balances
            foreach ($laterMainTransactions as $laterMainTrans) {
                $newMainBalance = $laterMainTrans['balance'] + $mainAmountDifference;
                $updateMainBalanceStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                $updateMainBalanceStmt->execute([$newMainBalance, $laterMainTrans['id'], $tenant_id]);
            }
            
            // Update the main account balance - using direct currency check
            if ($currency === 'USD') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'AFS') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'EURO') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance - ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'DARHAM') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance - ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            }
        }
        
    } elseif ($transactionType === 'client') {
        // Get client transaction details
        $stmt = $conn->prepare("SELECT * FROM client_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transactionId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        $clientId = $transaction['client_id'];
        
        // Get client name
        $clientStmt = $conn->prepare("SELECT name FROM clients WHERE id = ? AND tenant_id = ?");
        $clientStmt->bind_param("ii", $clientId, $tenant_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $client = $clientResult->fetch_assoc();
        $accountName = $client['name'] ?? 'Unknown Client';
        $accountId = $clientId;
        
        // Calculate the difference in amount
        $originalSignedAmount = $originalType === 'credit' ? $originalAmount : -$originalAmount;
        $newSignedAmount = $newType === 'credit' ? $newAmount : -$newAmount;
        $amountDifference = $newSignedAmount - $originalSignedAmount;
        
        // Update the transaction - created_at already removed
        $updateStmt = $conn->prepare("
            UPDATE client_transactions 
            SET amount = ?, type = ?, currency = ?, receipt = ?, description = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $updateStmt->bind_param("dssssi", $newAmount, $newType, $currency, $receipt, $description, $transactionId, $tenant_id);
        $updateStmt->execute();
        
        // Get all transactions after this one to update balances
        $laterTransactionsStmt = $conn->prepare("
            SELECT id, amount, type, balance, currency 
            FROM client_transactions 
            WHERE client_id = ? AND tenant_id = ? AND
                  (created_at > ? OR 
                  (created_at = ? AND id > ?)) AND
                  currency = ?
            ORDER BY created_at ASC, id ASC
        ");
        $laterTransactionsStmt->bind_param("issisi", $clientId, $tenant_id, $transaction['created_at'], $transaction['created_at'], $transactionId, $currency);
        $laterTransactionsStmt->execute();
        $laterTransactionsResult = $laterTransactionsStmt->get_result();
        $laterTransactions = $laterTransactionsResult->fetch_all(MYSQLI_ASSOC);
        
        // Update the current transaction's balance
        $currentBalance = $transaction['balance'] + $amountDifference;
        $updateBalanceStmt = $conn->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
        $updateBalanceStmt->bind_param("di", $currentBalance, $transactionId, $tenant_id);
        $updateBalanceStmt->execute();
        
        // Update all subsequent transactions' balances
        foreach ($laterTransactions as $laterTransaction) {
            $newBalance = $laterTransaction['balance'] + $amountDifference;
            $updateBalanceStmt->bind_param("di", $newBalance, $laterTransaction['id'], $tenant_id);
            $updateBalanceStmt->execute();
        }
        
        // Update the client balance based on currency
        // Since there's no general 'balance' column, we need to update the specific currency balance
        if ($currency === 'USD') {
            $updateClientStmt = $conn->prepare("
                UPDATE clients 
                SET usd_balance = usd_balance + ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $updateClientStmt->bind_param("di", $amountDifference, $clientId, $tenant_id);
            $updateClientStmt->execute();
        } elseif ($currency === 'AFS') {
            $updateClientStmt = $conn->prepare("
                UPDATE clients 
                SET afs_balance = afs_balance + ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $updateClientStmt->bind_param("di", $amountDifference, $clientId, $tenant_id);
            $updateClientStmt->execute();
        }
        
        // Also update the corresponding main account transaction if it exists
        // Get main transaction details by transaction_of and reference_id instead of main_transaction_id
        $mainTransStmt = $pdo->prepare("
            SELECT * FROM main_account_transactions 
            WHERE transaction_of = 'client_fund' AND reference_id = ? AND tenant_id = ?
        ");
        $mainTransStmt->execute([$transactionId, $tenant_id]);
        $mainTransaction = $mainTransStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mainTransaction) {
            // Calculate the new balance for the main transaction
            $mainAmountDifference = $amountDifference; // Same effect on main account
            $newMainBalance = $mainTransaction['balance'] + $mainAmountDifference;
            
            // Update main transaction including balance - removed created_at from the update
            $updateMainStmt = $pdo->prepare("
                UPDATE main_account_transactions 
                SET amount = ?, type = ?, description = ?, balance = ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $updateMainStmt->execute([
                $newAmount, 
                $newType, 
                "Fund transfer from client: $accountName", 
                $newMainBalance,
                $mainTransaction['id'],
                $tenant_id
            ]);
            
            // Get all transactions after this one to update balances
            $laterMainTransStmt = $pdo->prepare("
                SELECT id, balance, currency 
                FROM main_account_transactions 
                WHERE main_account_id = ? AND tenant_id = ? AND
                      (created_at > ? OR 
                      (created_at = ? AND id > ?)) AND
                      currency = ?
                ORDER BY created_at ASC, id ASC
            ");
            $laterMainTransStmt->execute([$mainTransaction['main_account_id'], $tenant_id, $mainTransaction['created_at'], $mainTransaction['created_at'], $mainTransaction['id'], $currency]);
            $laterMainTransactions = $laterMainTransStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update all subsequent transactions' balances
            foreach ($laterMainTransactions as $laterMainTrans) {
                $newMainBalance = $laterMainTrans['balance'] + $mainAmountDifference;
                $updateMainBalanceStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                $updateMainBalanceStmt->execute([$newMainBalance, $laterMainTrans['id'], $tenant_id]);
            }
            
            // Update the main account balance - using direct currency check
            if ($currency === 'USD') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'AFS') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'EURO') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance + ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            } elseif ($currency === 'DARHAM') {
                $updateMainAccountStmt = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance + ? WHERE id = ? AND tenant_id = ?");
                $updateMainAccountStmt->execute([$amountDifference, $mainTransaction['main_account_id'], $tenant_id]);
            }
        }
    } else {
        throw new Exception('Invalid transaction type');
    }
    
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare old values
    $old_values = [
        'transaction_id' => $transactionId,
        'transaction_type' => $transactionType,
        'amount' => $originalAmount,
        'type' => $originalType,
        'date' => $transaction['created_at'] ?? $transaction['transaction_date'] ?? ''
    ];
    
    // Prepare new values
    $new_values = [
        'amount' => $newAmount,
        'type' => $newType,
        'date' => $transactionDate,
        'receipt' => $receipt,
        'description' => $description
    ];
    
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $activity_log_stmt->execute([
        $user_id,
        'update',
        ($transactionType === 'main' ? 'main_account_transactions' : 
         ($transactionType === 'supplier' ? 'supplier_transactions' : 'client_transactions')),
        $transactionId,
        json_encode($old_values),
        json_encode($new_values),
        $ip_address,
        $user_agent,
        $tenant_id
    ]);
    
    // Commit the transaction
    $pdo->commit();
    $conn->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Transaction updated successfully',
        'account_id' => $accountId,
        'account_name' => $accountName
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction
    $pdo->rollBack();
    $conn->rollback();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 