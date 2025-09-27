<?php

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/conn.php');
// Check if the user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
// Decode JSON payload from POST request
$data = json_decode(file_get_contents('php://input'), true);

// Validate and sanitize inputs
if (!isset($data['supplier_id'], $data['amount'], $data['remarks'], $data['receipt_number'], $data['main_account_id'], $data['payment_currency']) || !is_numeric($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data. Missing required fields.']);
    exit;
}

$supplierId = (int)$data['supplier_id'];
$amount = (float)$data['amount'];
$mainAccountId = (int)$data['main_account_id'];
$userRemarks = $data['remarks'];
$receiptNumber = $data['receipt_number'];
$paymentCurrency = strtoupper(trim($data['payment_currency'])); // USD or AFS
$exchangeRate = isset($data['exchange_rate']) && $data['exchange_rate'] !== '' ? (float)$data['exchange_rate'] : null; // USD → AFS

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Fetch supplier's currency and balance
$supplierQuery = "
    SELECT name, currency, balance 
    FROM suppliers
    WHERE id = ? and tenant_id = ?
";
$supplierStmt = $conn->prepare($supplierQuery);
$supplierStmt->bind_param('ii', $supplierId, $tenant_id);
$supplierStmt->execute();
$supplierResult = $supplierStmt->get_result();

if ($supplierResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
    exit;
}

$supplier = $supplierResult->fetch_assoc();
$supplierName = $supplier['name'];

// Fetch main account balances and name
$mainAccountQuery = "
    SELECT usd_balance, afs_balance, name 
    FROM main_account 
    WHERE id = ? and tenant_id = ?
";
$mainAccountStmt = $conn->prepare($mainAccountQuery);
$mainAccountStmt->bind_param('ii', $mainAccountId, $tenant_id);
$mainAccountStmt->execute();
$mainAccountResult = $mainAccountStmt->get_result();

if ($mainAccountResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Main account not found.']);
    exit;
}

$mainAccount = $mainAccountResult->fetch_assoc();
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
$conn->begin_transaction();

try {
    // Deduct from the main account balance (USD or AFS)
    $mainUpdateQuery = "
        UPDATE main_account 
        SET {$balanceField} = {$balanceField} + ? 
        WHERE id = ? and tenant_id = ?
    ";
    $mainUpdateStmt = $conn->prepare($mainUpdateQuery);
    $mainUpdateStmt->bind_param('di', $amount, $mainAccountId, $tenant_id);
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
        SET balance = balance - ? 
        WHERE id = ? and tenant_id = ?
    ";
$supplierUpdateStmt = $conn->prepare($supplierUpdateQuery);
$supplierUpdateStmt->bind_param('di', $creditedAmount, $supplierId, $tenant_id);
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
    $completeRemarks = "Supplier: $supplierName, Withdrawn to main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
    $newBalance = $supplier['balance'] - $creditedAmount;
    
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
            tenant_id
        ) VALUES (
            ?, 
            'debit', 
            ?, 
            'fund_withdrawal', 
            ?, 
            ?, 
            ?,
            ?,
            ?
        )
    ";
    $transactionStmt = $conn->prepare($transactionQuery);
    $transactionStmt->bind_param('idssss', 
        $supplierId, 
        $creditedAmount, 
        $user_id, 
        $completeRemarks,
        $newBalance,
        $receiptNumber,
        $tenant_id
    );
    if (!$transactionStmt->execute()) {
        throw new Exception("Failed to log the supplier transaction.");
    }
    $lastInsertId = $transactionStmt->insert_id;

    // Insert into main_account_transaction
    $mainTransactionRemarks = "Supplier: $supplierName, Withdrawn to main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
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
            tenant_id
        ) VALUES (
            ?,
            'credit',
            ?,
            'supplier_fund_withdrawal',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";
    $mainTransactionStmt = $conn->prepare($mainTransactionQuery);
    $mainTransactionStmt->bind_param('idisdss',
        $mainAccountId,
        $amount,
        $lastInsertId,
        $mainTransactionRemarks,
        $newMainBalance,
        $paymentCurrency,
        $receiptNumber,
        $tenant_id
    );
    if (!$mainTransactionStmt->execute()) {
        throw new Exception("Failed to log the main account transaction.");
    }
    //send notification to admin
    $notificationMessage = "Supplier: $supplierName, Withdrawn $amount $paymentCurrency to main account: $mainAccountName, processed by: $username, Remarks: $userRemarks$exchangeNarrative";
    $notificationQuery = "
        INSERT INTO notifications (
            transaction_id,
            transaction_type,
            message,
            status,
            created_at,
            tenant_id
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            NOW(),
            ?
        )
    ";

    $transaction_type = 'supplier_fund_withdrawal';
    $status = 'Unread';
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $notificationMessage, $status, $tenant_id);
    if (!$notificationStmt->execute()) {
        throw new Exception("Failed to send notification to admin.");
    }

    // Commit transaction
    $conn->commit();
    
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
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'fund', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->bind_param("iissssi", $user_id, $supplierId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $activityStmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Supplier account withdrawn successfully.']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>
