<?php
session_start();
require_once('../../admin/security.php');
require_once('../../includes/db.php');

// Enforce authentication
enforce_auth();

// Check if the user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
$branch_id = isset($_SESSION['branch_id']) ? $_SESSION['branch_id'] : null;

// Check if the request is JSON or form data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    // Handle form data
    $data = $_POST;
}

// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Extract data from the request
$clientId = isset($data['client_id']) ? $data['client_id'] : null;
$clientName = isset($data['client_name']) ? $data['client_name'] : null;

// Balance currency: which balance are we updating (USD or AFS)
$balanceCurrency = isset($data['payment_currency']) ? $data['payment_currency'] : null;

// Payment currency: what currency is the client paying in (USD or AFS)
$paymentCurrency = isset($data['payment_currency_actual']) ? $data['payment_currency_actual'] : null;

// Amount: always in the payment currency
$paymentAmount = isset($data['total_amount']) ? (float)$data['total_amount'] : 0;

// Hidden amounts (for validation/records)
$usdAmount = isset($data['usd_amount']) ? (float)$data['usd_amount'] : 0;
$afsAmount = isset($data['afs_amount']) ? (float)$data['afs_amount'] : 0;

// Exchange rate (only if currencies differ)
$exchangeRate = isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : 1;

$remarks = isset($data['remarks']) ? $data['remarks'] : '';
$receipt = isset($data['receipt_number']) ? strval($data['receipt_number']) : '';
$mainAccountId = isset($data['main_account']) ? $data['main_account'] : null;

// Debug information (remove in production)
/*
error_log("Data received: " . print_r($data, true));
error_log("Client ID: $clientId");
error_log("Selected Currency: $selectedCurrency");
error_log("Total Amount: $totalAmount");
error_log("USD Amount: $usdAmount");
error_log("AFS Amount: $afsAmount");
error_log("Exchange Rate: $exchangeRate");
error_log("Receipt: $receipt");
error_log("Main Account ID: $mainAccountId");
*/

// Validate receipt
if (empty($receipt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receipt number is required']);
    exit;
}

// Validate client ID
if (empty($clientId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

// Validate balance currency (which balance to update)
if (empty($balanceCurrency) || !in_array($balanceCurrency, ['USD', 'AFS'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid balance currency is required']);
    exit;
}

// Validate payment currency (what they're paying with)
if (empty($paymentCurrency) || !in_array($paymentCurrency, ['USD', 'AFS'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid payment currency is required']);
    exit;
}

// Validate main account
if (empty($mainAccountId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Main account is required']);
    exit;
}

// Validate payment amount
if ($paymentAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than zero.']);
    exit;
}

// Fetch the client's account balances (USD and AFS) based on client ID
$clientAccountQuery = "SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($clientAccountQuery);
$stmt->bindParam(1, $clientId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clientAccount = $stmt->fetch(PDO::FETCH_ASSOC);
$clientName = $clientAccount['name'];

if (!$clientAccount) {
    echo json_encode(['success' => false, 'message' => 'Client account not found.']);
    exit;
}

// Fetch main account details
$mainAccountQuery = "SELECT id, name, usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?";
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

// Generate full remark with the user name, date, and custom message
$fullRemark = "Client: $clientName, Account funded by $username. Remarks: $remarks";
$timestamp = date('Y-m-d H:i:s');

$pdo->beginTransaction();

try {
    // Calculate the amount to credit to the balance
    $amountToCredit = $paymentAmount;
    
    // If currencies differ, convert payment currency to balance currency
    if ($balanceCurrency !== $paymentCurrency) {
        if ($balanceCurrency === 'USD' && $paymentCurrency === 'AFS') {
            // Client pays AFS → credit USD: divide by rate
            $amountToCredit = $paymentAmount / $exchangeRate;
        } else if ($balanceCurrency === 'AFS' && $paymentCurrency === 'USD') {
            // Client pays USD → credit AFS: multiply by rate
            $amountToCredit = $paymentAmount * $exchangeRate;
        }
    }
    
    // Determine which balance to update based on $balanceCurrency
    if ($balanceCurrency === 'USD') {
        // Update client USD balance
        $newUsdBalance = $clientAccount['usd_balance'] + $amountToCredit;
        $updateQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Update main account (deduct from payment currency, add to balance currency)
        if ($paymentCurrency === 'AFS') {
            // Client paid AFS, so deduct from main AFS and add to client USD
            $newMainAfsBalance = $mainAccount['afs_balance'] + $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            // Record client transaction
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, exchange_rate, tenant_id, branch_id)
                                            VALUES (?, 'Credit', 'USD', ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $amountToCredit, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newUsdBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record main account transaction
            $mainTransactionRemarks = "Client: $clientName, Paid ؋$paymentAmount AFS (= $$amountToCredit USD) for account funding, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS', ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        } else {
            // Client paid USD, so add to main account USD balance
            $newMainUsdBalance = $mainAccount['usd_balance'] + $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            // Record client transaction
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                            VALUES (?, 'Credit', 'USD', ?, ?, 'fund', ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newUsdBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record main account transaction
            $mainTransactionRemarks = "Client: $clientName, Paid \$$paymentAmount USD for account funding, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD', ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        }

        // Notification
        $notificationMessage = "Client: $clientName, Paid $paymentAmount $paymentCurrency for account funding (credited $$amountToCredit USD), processed by: $username, Remarks: $remarks";
        $transaction_type = 'client_fund';
        $status = 'Unread';
        $notificationQuery = "INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
                             VALUES (?, ?, ?, ?, NOW(), ?, ?)";
        $notificationStmt = $pdo->prepare($notificationQuery);
        $notificationStmt->bindParam(1, $lastInsertId, PDO::PARAM_INT);
        $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
        $notificationStmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $notificationStmt->bindParam(4, $status, PDO::PARAM_STR);
        $notificationStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $notificationStmt->execute();

    } else if ($balanceCurrency === 'AFS') {
        // Update client AFS balance
        $newAfsBalance = $clientAccount['afs_balance'] + $amountToCredit;
        $updateQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Update main account
        if ($paymentCurrency === 'USD') {
            // Client paid USD, so add to main USD and client AFS
            $newMainUsdBalance = $mainAccount['usd_balance'] + $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            // Record client transaction
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, exchange_rate, tenant_id, branch_id)
                                            VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $amountToCredit, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newAfsBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record main account transaction
            $mainTransactionRemarks = "Client: $clientName, Paid \$$paymentAmount USD (= ؋$amountToCredit AFS) for account funding, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD', ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        } else {
            // Client paid AFS, so add to main account AFS balance
            $newMainAfsBalance = $mainAccount['afs_balance'] + $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            // Record client transaction
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                            VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newAfsBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record main account transaction
            $mainTransactionRemarks = "Client: $clientName, Paid ؋$paymentAmount AFS for account funding, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS', ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        }

        // Notification
        $notificationMessage = "Client: $clientName, Paid $paymentAmount $paymentCurrency for account funding (credited ؋$amountToCredit AFS), processed by: $username, Remarks: $remarks";
        $transaction_type = 'client_fund';
        $status = 'Unread';
        $notificationQuery = "INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
                             VALUES (?, ?, ?, ?, NOW(), ?, ?)";
        $notificationStmt = $pdo->prepare($notificationQuery);
        $notificationStmt->bindParam(1, $lastInsertId, PDO::PARAM_INT);
        $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
        $notificationStmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $notificationStmt->bindParam(4, $status, PDO::PARAM_STR);
        $notificationStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $notificationStmt->execute();
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Client account funded successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to fund account: ' . $e->getMessage()]);
}

?>