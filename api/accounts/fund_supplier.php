<?php

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once('../../includes/db.php');
// Check if the user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
// Decode JSON payload from POST request
$data = $_POST;

// Validate and sanitize inputs
if (!isset($data['supplier_id'], $data['amount'], $data['remarks'], $data['receipt_number'], $data['main_account'], $data['payment_currency']) || !is_numeric($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit;
}

$supplierId = (int)$data['supplier_id'];
$amount = (float)$data['amount'];
$mainAccountId = (int)$data['main_account'];
$userRemarks = $data['remarks'];
$receiptNumber = $data['receipt_number'];
$paymentCurrency = strtoupper(trim($data['payment_currency'])); // USD or AFS
$exchangeRate = isset($data['exchange_rate']) && $data['exchange_rate'] !== '' ? (float)$data['exchange_rate'] : null; // USD → AFS


// Fetch supplier's currency and balance
$supplierQuery = "
    SELECT name, currency, balance
    FROM suppliers
    WHERE id = ? AND tenant_id = ? AND branch_id = ?
";
$supplierStmt = $pdo->prepare($supplierQuery);
$supplierStmt->bindParam(1, $supplierId, PDO::PARAM_INT);
$supplierStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$supplierStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$supplierStmt->execute();
$supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
    exit;
}
$supplierName = $supplier['name'];

// Fetch main account balances and name
$mainAccountQuery = "
    SELECT usd_balance, afs_balance, name
    FROM main_account
    WHERE id = ? AND tenant_id = ? AND branch_id = ?
";
$mainAccountStmt = $pdo->prepare($mainAccountQuery);
$mainAccountStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
$mainAccountStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$mainAccountStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$mainAccountStmt->execute();
$mainAccount = $mainAccountStmt->fetch(PDO::FETCH_ASSOC);

if (!$mainAccount) {
    echo json_encode(['success' => false, 'message' => 'Main account not found.']);
    exit;
}
$supplierCurrency = $supplier['currency']; // Supplier's currency (USD or AFS)

// Determine which main account balance to deduct based on PAYMENT currency
if ($paymentCurrency === 'USD') {
    $mainBalance = (float)$mainAccount['usd_balance'];
    $balanceField = 'usd_balance';
} elseif ($paymentCurrency === 'AFS') {
    $mainBalance = (float)$mainAccount['afs_balance'];
    $balanceField = 'afs_balance';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid payment currency.']);
    exit;
}

// Ensure sufficient funds in the main account (deducting in payment currency)
if ($mainBalance < $amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient funds in the main account.']);
    exit;
}

// Get main account name
$mainAccountName = $mainAccount['name'];



// Begin transaction
$pdo->beginTransaction();

try {
    // Deduct from the main account balance (USD or AFS)
    $mainUpdateQuery = "
        UPDATE main_account
        SET {$balanceField} = {$balanceField} - ?
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ";
    $mainUpdateStmt = $pdo->prepare($mainUpdateQuery);
    $mainUpdateStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $mainUpdateStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
    $mainUpdateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $mainUpdateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$mainUpdateStmt->execute()) {
        throw new Exception("Failed to update main account balance.");
    }

    // Calculate new main account balance
    $newMainBalance = $mainBalance - $amount;

// Compute amount to credit to supplier in SUPPLIER currency.
// When currencies differ, we require exchangeRate as USD → AFS.
$creditedAmount = $amount; // same currency default
if ($paymentCurrency !== $supplierCurrency) {
    if ($exchangeRate === null || $exchangeRate <= 0) {
        throw new Exception('Missing or invalid exchange rate.');
    }
    if ($paymentCurrency === 'USD' && $supplierCurrency === 'AFS') {
        // 100 USD at 70 => 7000 AFS
        $creditedAmount = $amount * $exchangeRate;
    } elseif ($paymentCurrency === 'AFS' && $supplierCurrency === 'USD') {
        // 7000 AFS at 70 => 100 USD
        $creditedAmount = $amount / $exchangeRate;
    }
}

// Add to the supplier's account balance in supplier currency
$supplierUpdateQuery = "
        UPDATE suppliers
        SET balance = balance + ?
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ";
$supplierUpdateStmt = $pdo->prepare($supplierUpdateQuery);
$supplierUpdateStmt->bindParam(1, $creditedAmount, PDO::PARAM_STR);
$supplierUpdateStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
$supplierUpdateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$supplierUpdateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$supplierUpdateStmt->execute()) {
        throw new Exception("Failed to update supplier account balance.");
    }

    // Log the transaction with detailed remarks
    // Build exchange narrative if currencies differ
    $exchangeNarrative = '';
    if ($paymentCurrency !== $supplierCurrency) {
        if ($paymentCurrency === 'USD' && $supplierCurrency === 'AFS') {
            $exchangeNarrative = ", paid {$amount} USD; exchange rate USD to AFS is {$exchangeRate} equal to " . number_format($creditedAmount, 2) . " AFS";
        } elseif ($paymentCurrency === 'AFS' && $supplierCurrency === 'USD') {
            $exchangeNarrative = ", paid {$amount} AFS; exchange rate USD to AFS is {$exchangeRate} equal to " . number_format($creditedAmount, 2) . " USD";
        }
    }
    $completeRemarks = "Supplier: $supplierName, Funded by main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
    $newBalance = $supplier['balance'] + $creditedAmount;
    
    // Insert into supplier_transactions
    $transactionQuery = "
        INSERT INTO supplier_transactions (
            supplier_id,
            transaction_type,
            amount,
            transaction_of,
            reference_id,
            remarks,
            balance,
            receipt,
            tenant_id,
            branch_id
        ) VALUES (
            ?,
            'credit',
            ?,
            'fund',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";
    $transactionStmt = $pdo->prepare($transactionQuery);
    $transactionStmt->bindParam(1, $supplierId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $creditedAmount, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(4, $completeRemarks, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $newBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(6, $receiptNumber, PDO::PARAM_STR);
    $transactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    if (!$transactionStmt->execute()) {
        throw new Exception("Failed to log the supplier transaction.");
    }
    $lastInsertId = $pdo->lastInsertId();

    // Insert into main_account_transaction
    $mainTransactionRemarks = "Supplier: $supplierName, Funded by main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
    $mainTransactionQuery = "
        INSERT INTO main_account_transactions (
            main_account_id,
            type,
            amount,
            transaction_of,
            reference_id,
            description,
            balance,
            currency,
            receipt,
            tenant_id,
            branch_id
        ) VALUES (
            ?,
            'debit',
            ?,
            'supplier_fund',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";
    $mainTransactionStmt = $pdo->prepare($mainTransactionQuery);
    $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(5, $newMainBalance, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(6, $paymentCurrency, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(7, $receiptNumber, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    if (!$mainTransactionStmt->execute()) {
        throw new Exception("Failed to log the main account transaction.");
    }
    //send notification to admin
    $notificationMessage = "Supplier: $supplierName, Funded $amount $paymentCurrency by main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
    $notificationQuery = "
        INSERT INTO notifications (
            transaction_id,
            transaction_type,
            message,
            status,
            created_at,
            tenant_id,
            branch_id
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            NOW(),
            ?,
            ?
        )
    ";

    $transaction_type = 'supplier_fund';
    $status = 'Unread';
    $notificationStmt = $pdo->prepare($notificationQuery);
    $notificationStmt->bindParam(1, $lastInsertId, PDO::PARAM_INT);
    $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
    $notificationStmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
    $notificationStmt->bindParam(4, $status, PDO::PARAM_STR);
    $notificationStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $notificationStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    if (!$notificationStmt->execute()) {
        throw new Exception("Failed to send notification to admin.");
    }

    // Commit transaction
    $pdo->commit();
    
    // Log the activity
    $old_values = json_encode([
        'supplier_id' => $supplierId,
        'supplier_balance' => $supplier['balance'],
        'main_account_id' => $mainAccountId,
        'main_account_balance' => $mainBalance
    ]);
    $new_values = json_encode([
        'supplier_id' => $supplierId,
        'supplier_balance' => $newBalance,
        'main_account_id' => $mainAccountId,
        'main_account_balance' => $newMainBalance,
        'amount' => $amount,
        'currency' => $supplierCurrency,
        'remarks' => $userRemarks,
        'receipt_number' => $receiptNumber
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'fund', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $activityStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Supplier account funded successfully.']);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

?>
