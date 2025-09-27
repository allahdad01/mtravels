<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
require_once('../includes/conn.php');
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$fromAccountId = $data['fromAccount'];
$fromCurrency = $data['fromCurrency'];
$toAccountId = $data['toAccount'];
$toCurrency = $data['toCurrency'];
$amount = floatval($data['amount']);
$exchangeRate = floatval($data['exchangeRate']);
$description = $data['description'] ?? 'Balance transfer';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get from account balance
    $fromAccountStmt = $conn->prepare("SELECT * FROM main_account WHERE id = ? AND tenant_id = ?");
    $fromAccountStmt->bind_param("ii", $fromAccountId, $tenant_id);
    $fromAccountStmt->execute();
    $fromAccount = $fromAccountStmt->get_result()->fetch_assoc();
    $fromAccountStmt->close();

    if (!$fromAccount) {
        throw new Exception("Source account not found");
    }

    // Get to account balance
    $toAccountStmt = $conn->prepare("SELECT * FROM main_account WHERE id = ? AND tenant_id = ?");
    $toAccountStmt->bind_param("ii", $toAccountId, $tenant_id);
    $toAccountStmt->execute();
    $toAccount = $toAccountStmt->get_result()->fetch_assoc();
    $toAccountStmt->close();

    if (!$toAccount) {
        throw new Exception("Destination account not found");
    }

    // Check if source account has sufficient balance
    $fromBalanceField = strtolower($fromCurrency) . '_balance';
    if (!isset($fromAccount[$fromBalanceField]) || $fromAccount[$fromBalanceField] < $amount) {
        throw new Exception("Insufficient balance in source account");
    }



    // Calculate converted amount based on currency pairs
    $convertedAmount = 0;
    
    // When converting from AFS to other currencies
    if ($fromCurrency === 'AFS') {
        if ($toCurrency === 'USD') {
            $convertedAmount = $amount / $exchangeRate; // AFS to USD: divide
        } elseif ($toCurrency === 'DARHAM') {
            $convertedAmount = $amount / $exchangeRate; // AFS to DARHAM: divide
        } elseif ($toCurrency === 'EURO') {
            $convertedAmount = $amount / $exchangeRate; // AFS to EUR: divide
        } else {
            $convertedAmount = $amount; // Same currency
        }
    }
    // When converting to AFS from other currencies
    elseif ($toCurrency === 'AFS') {
        if ($fromCurrency === 'USD') {
            $convertedAmount = $amount * $exchangeRate; // USD to AFS: multiply
        } elseif ($fromCurrency === 'DARHAM') {
            $convertedAmount = $amount * $exchangeRate; // DARHAM to AFS: multiply
        } elseif ($fromCurrency === 'EURO') {
            $convertedAmount = $amount * $exchangeRate; // EUR to AFS: multiply
        } else {
            $convertedAmount = $amount; // Same currency
        }
    }
    // For other currency pairs (non-AFS)
    else {
        $convertedAmount = $amount * $exchangeRate; // Default: multiply
    }

    // Update source account balance
    $updateFromStmt = $conn->prepare("UPDATE main_account SET {$fromBalanceField} = {$fromBalanceField} - ? WHERE id = ? AND tenant_id = ?");
    $updateFromStmt->bind_param("dii", $amount, $fromAccountId, $tenant_id);
    $updateFromStmt->execute();
    $updateFromStmt->close();

    // Update destination account balance
    $toBalanceField = strtolower($toCurrency) . '_balance';
    $updateToStmt = $conn->prepare("UPDATE main_account SET {$toBalanceField} = {$toBalanceField} + ? WHERE id = ? AND tenant_id = ?");
    $updateToStmt->bind_param("dii", $convertedAmount, $toAccountId, $tenant_id);
    $updateToStmt->execute();
    $updateToStmt->close();

    // Normalize euro currency to EUR before transaction insertion
    $fromCurrency = (strtolower($fromCurrency) === 'euro') ? 'EUR' : $fromCurrency;
    $toCurrency = (strtolower($toCurrency) === 'euro') ? 'EUR' : $toCurrency;

    // Record transaction for source account (debit)
    $fromTransactionStmt = $conn->prepare("
        INSERT INTO main_account_transactions (
            main_account_id, type, amount, currency, description, 
            transaction_of, reference_id, balance, tenant_id

        ) VALUES (?, 'debit', ?, ?, ?, 'transfer', ?, ?, ?)
    ");
    $fromBalance = $fromAccount[$fromBalanceField] - $amount;
    $fromTransactionStmt->bind_param(
        "idssiis", 
        $fromAccountId, 
        $amount, 
        $fromCurrency, 
        $description,
        $toAccountId,
        $fromBalance,
        $tenant_id
    );
    $fromTransactionStmt->execute();
    $fromTransactionStmt->close();

    // Record transaction for destination account (credit)
    $toTransactionStmt = $conn->prepare("
        INSERT INTO main_account_transactions (
            main_account_id, type, amount, currency, description, 
            transaction_of, reference_id, balance, tenant_id

        ) VALUES (?, 'credit', ?, ?, ?, 'transfer', ?, ?, ?)
    ");
    $toBalance = $toAccount[$toBalanceField] + $convertedAmount;
    $toTransactionStmt->bind_param(
        "idssiis", 
        $toAccountId, 
        $convertedAmount, 
        $toCurrency, 
        $description,
        $fromAccountId,
        $toBalance,
        $tenant_id
    );
    $toTransactionStmt->execute();
    $toTransactionStmt->close();

    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare new values data
    $new_values = [
        'from_account_id' => $fromAccountId,
        'from_account_name' => $fromAccount['name'],
        'from_currency' => $fromCurrency,
        'to_account_id' => $toAccountId,
        'to_account_name' => $toAccount['name'],
        'to_currency' => $toCurrency,
        'amount' => $amount,
        'converted_amount' => $convertedAmount,
        'exchange_rate' => $exchangeRate,
        'description' => $description
    ];
    
    // Insert activity log
    $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 

        VALUES (?, 'transfer', 'main_account_transactions', ?, '{}', ?, ?, ?, NOW(), ?)");
    
    $new_values_json = json_encode($new_values);
    $activity_log_stmt->bind_param("iissss", $user_id, $fromTransactionId, $new_values_json, $ip_address, $user_agent, $tenant_id);
    $activity_log_stmt->execute();
    $activity_log_stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Transfer completed successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?> 