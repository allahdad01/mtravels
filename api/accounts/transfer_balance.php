<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once('../../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
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
    $pdo->beginTransaction();

    // Get from account balance
    $fromAccountStmt = $pdo->prepare("SELECT * FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $fromAccountStmt->bindParam(1, $fromAccountId, PDO::PARAM_INT);
    $fromAccountStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $fromAccountStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $fromAccountStmt->execute();
    $fromAccount = $fromAccountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fromAccount) {
        throw new Exception("Source account not found");
    }

    // Get to account balance
    $toAccountStmt = $pdo->prepare("SELECT * FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $toAccountStmt->bindParam(1, $toAccountId, PDO::PARAM_INT);
    $toAccountStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $toAccountStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $toAccountStmt->execute();
    $toAccount = $toAccountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$toAccount) {
        throw new Exception("Destination account not found");
    }

    // Check if source account has sufficient balance
    $fromBalanceField = strtolower($fromCurrency) . '_balance';
    if (!isset($fromAccount[$fromBalanceField]) || $fromAccount[$fromBalanceField] < $amount) {
        throw new Exception("Insufficient balance in source account");
    }



    // Normalize currency codes
    $fromCurrencyNorm = strtoupper($fromCurrency) === 'EURO' ? 'EUR' : strtoupper($fromCurrency);
    $toCurrencyNorm = strtoupper($toCurrency) === 'EURO' ? 'EUR' : strtoupper($toCurrency);

    // Calculate converted amount based on currency pairs
    $convertedAmount = 0;

    $dividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD'];
    $pairKey = "{$fromCurrencyNorm}->{$toCurrencyNorm}";

    if ($fromCurrencyNorm === $toCurrencyNorm) {
        $convertedAmount = $amount;
    } elseif (in_array($pairKey, $dividePairs)) {
        $convertedAmount = $amount / $exchangeRate;
    } else {
        $convertedAmount = $amount * $exchangeRate;
    }

    // Update source account balance
    $updateFromStmt = $pdo->prepare("UPDATE main_account SET {$fromBalanceField} = {$fromBalanceField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateFromStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateFromStmt->bindParam(2, $fromAccountId, PDO::PARAM_INT);
    $updateFromStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateFromStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateFromStmt->execute();

    // Update destination account balance
    $toBalanceField = strtolower($toCurrency) . '_balance';
    $updateToStmt = $pdo->prepare("UPDATE main_account SET {$toBalanceField} = {$toBalanceField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateToStmt->bindParam(1, $convertedAmount, PDO::PARAM_STR);
    $updateToStmt->bindParam(2, $toAccountId, PDO::PARAM_INT);
    $updateToStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateToStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateToStmt->execute();

    // Use normalized currency codes for transaction insertion
    $fromCurrency = $fromCurrencyNorm;
    $toCurrency = $toCurrencyNorm;

    // Record transaction for source account (debit)
    $fromTransactionStmt = $pdo->prepare("
        INSERT INTO main_account_transactions (
            main_account_id, type, amount, currency, description,
            transaction_of, reference_id, balance, tenant_id, branch_id

        ) VALUES (?, 'debit', ?, ?, ?, 'transfer', ?, ?, ?, ?)
    ");
    $fromBalance = $fromAccount[$fromBalanceField] - $amount;
    $fromTransactionStmt->bindParam(1, $fromAccountId, PDO::PARAM_INT);
    $fromTransactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $fromTransactionStmt->bindParam(3, $fromCurrency, PDO::PARAM_STR);
    $fromTransactionStmt->bindParam(4, $description, PDO::PARAM_STR);
    $fromTransactionStmt->bindParam(5, $toAccountId, PDO::PARAM_INT);
    $fromTransactionStmt->bindParam(6, $fromBalance, PDO::PARAM_STR);
    $fromTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $fromTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $fromTransactionStmt->execute();

    // Record transaction for destination account (credit)
    $toTransactionStmt = $pdo->prepare("
        INSERT INTO main_account_transactions (
            main_account_id, type, amount, currency, description,
            transaction_of, reference_id, balance, tenant_id, branch_id

        ) VALUES (?, 'credit', ?, ?, ?, 'transfer', ?, ?, ?, ?)
    ");
    $toBalance = $toAccount[$toBalanceField] + $convertedAmount;
    $toTransactionStmt->bindParam(1, $toAccountId, PDO::PARAM_INT);
    $toTransactionStmt->bindParam(2, $convertedAmount, PDO::PARAM_STR);
    $toTransactionStmt->bindParam(3, $toCurrency, PDO::PARAM_STR);
    $toTransactionStmt->bindParam(4, $description, PDO::PARAM_STR);
    $toTransactionStmt->bindParam(5, $fromAccountId, PDO::PARAM_INT);
    $toTransactionStmt->bindParam(6, $toBalance, PDO::PARAM_STR);
    $toTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $toTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $toTransactionStmt->execute();

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
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)

        VALUES (?, 'transfer', 'main_account_transactions', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

    $new_values_json = json_encode($new_values);
    $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(2, $fromAccountId, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $activity_log_stmt->execute();

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Transfer completed successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?> 