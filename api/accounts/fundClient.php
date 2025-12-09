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
$clientId = isset($data['client_id']) ? $data['client_id'] : (isset($data['clientId']) ? $data['clientId'] : null);
$clientName = isset($data['client_name']) ? $data['client_name'] : (isset($data['clientName']) ? $data['clientName'] : null);
$selectedCurrency = isset($data['payment_currency']) ? $data['payment_currency'] : (isset($data['selectedCurrency']) ? $data['selectedCurrency'] : null);
$totalAmount = isset($data['total_amount']) ? (float)$data['total_amount'] : (isset($data['totalAmount']) ? (float)$data['totalAmount'] : 0);
$usdAmount = isset($data['usd_amount']) ? (float)$data['usd_amount'] : (isset($data['usdAmount']) ? (float)$data['usdAmount'] : 0);
$afsAmount = isset($data['afs_amount']) ? (float)$data['afs_amount'] : (isset($data['afsAmount']) ? (float)$data['afsAmount'] : 0);
$exchangeRate = isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : (isset($data['exchangeRate']) ? (float)$data['exchangeRate'] : 0);
$remarks = isset($data['remarks']) ? $data['remarks'] : '';
$receipt = isset($data['receipt_number']) ? strval($data['receipt_number']) : (isset($data['receiptNumber']) ? strval($data['receiptNumber']) : '');
$mainAccountId = isset($data['main_account']) ? $data['main_account'] : (isset($data['mainAccountId']) ? $data['mainAccountId'] : null);

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

// Validate currency
if (empty($selectedCurrency)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Currency is required']);
    exit;
}

// Validate main account
if (empty($mainAccountId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Main account is required']);
    exit;
}


// Validate amounts
if ($totalAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total amount must be greater than zero.']);
    exit;
}

if ($usdAmount <= 0 && $afsAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'At least one payment amount must be greater than zero.']);
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
    // Calculate total payment in selected currency
    $totalPaymentInSelectedCurrency = 0;
    $afsInUsd = 0;
    $usdInAfs = 0;
    
    if ($selectedCurrency === 'USD') {
        // Convert AFS payment to USD
        $afsInUsd = $exchangeRate > 0 ? $afsAmount / $exchangeRate : 0;
        $totalPaymentInSelectedCurrency = $usdAmount + $afsInUsd;
        
        // Validate total payment matches the total amount
        if (abs($totalAmount - $totalPaymentInSelectedCurrency) > 0.01) {
            echo json_encode(['success' => false, 'message' => 'The sum of USD and AFS payments must equal the total amount.']);
            exit;
        }
        
        // Update client USD balance with the total payment
        $newUsdBalance = $clientAccount['usd_balance'] + $totalAmount;
        $updateQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // If there's a USD portion, update main account USD balance and record transactions
        if ($usdAmount > 0) {
            $newMainUsdBalance = $mainAccount['usd_balance'] + $usdAmount;
            $updateMainUsdQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainUsdStmt = $pdo->prepare($updateMainUsdQuery);
            $mainUsdStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainUsdStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainUsdStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainUsdStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainUsdStmt->execute();

            // Record client transaction for the USD payment
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,exchange_rate, tenant_id, branch_id)
                                            VALUES (?, 'Credit', 'USD', ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $usdAmount, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newUsdBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record USD main account transaction
            $mainUsdTransactionRemarks = "Client: $clientName, Received $usdAmount USD for client account funding, processed by: $username, Remarks: $remarks";
            $mainUsdTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency, tenant_id, branch_id)
                                                  VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD', ?, ?)");
            $mainUsdTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(2, $usdAmount, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(4, $mainUsdTransactionRemarks, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainUsdTransactionStmt->execute();


            //notification
            $notificationMessage = "Client: $clientName, Paid $usdAmount USD for client account funding, processed by: $username, Remarks: $remarks";
            $transaction_type = 'client_fund';
            $status = 'Unread';
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
        }


        // If there's an AFS portion, update main account AFS balance
        if ($afsAmount > 0) {
            // Update main account AFS balance
            $newMainAfsBalance = $mainAccount['afs_balance'] + $afsAmount;
            $updateMainAfsQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainAfsStmt = $pdo->prepare($updateMainAfsQuery);
            $mainAfsStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainAfsStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainAfsStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainAfsStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainAfsStmt->execute();
            // Record client transaction for the AFS payment
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,exchange_rate, tenant_id, branch_id)
            VALUES (?, 'Credit', 'USD', ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $afsInUsd, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newUsdBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record AFS main account transaction
            $mainAfsTransactionRemarks = "Client: $clientName, Received $afsAmount AFS (equivalent to $afsInUsd USD) for client account funding, processed by: $username, Remarks: $remarks";
            $mainAfsTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency, tenant_id, branch_id)
                                                 VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS', ?, ?)");
            $mainAfsTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainAfsTransactionStmt->bindParam(2, $afsAmount, PDO::PARAM_STR);
            $mainAfsTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainAfsTransactionStmt->bindParam(4, $mainAfsTransactionRemarks, PDO::PARAM_STR);
            $mainAfsTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
            $mainAfsTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainAfsTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainAfsTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainAfsTransactionStmt->execute();

            //notification
            $notificationMessage = "Client: $clientName, Paid $afsInUsd USD for client account funding, processed by: $username, Remarks: $remarks";
            $transaction_type = 'client_fund';
            $status = 'Unread';
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
        }
    } else if ($selectedCurrency === 'AFS') {
        // Convert USD payment to AFS
        $usdInAfs = $usdAmount * $exchangeRate;
        $totalPaymentInSelectedCurrency = $usdInAfs + $afsAmount;
        
        // Validate total payment matches the total amount
        if (abs($totalAmount - $totalPaymentInSelectedCurrency) > 0.01) {
            echo json_encode(['success' => false, 'message' => 'The sum of USD and AFS payments must equal the total amount.']);
            exit;
        }
        
        // Update client AFS balance with the total payment
        $newAfsBalance = $clientAccount['afs_balance'] + $totalAmount;
        $updateQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update main account USD balance with the USD portion
        if ($usdAmount > 0) {
            $newMainUsdBalance = $mainAccount['usd_balance'] + $usdAmount;
            $updateMainUsdQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainUsdStmt = $pdo->prepare($updateMainUsdQuery);
            $mainUsdStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainUsdStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainUsdStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainUsdStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainUsdStmt->execute();

            // Record client transaction for the USD payment
            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,exchange_rate, tenant_id, branch_id)
                                             VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $usdInAfs, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newAfsBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            // Record USD main account transaction
            $mainUsdTransactionRemarks = "Client: $clientName, Received $usdAmount USD (equivalent to $usdInAfs AFS) for client account funding, processed by: $username, Remarks: $remarks";
            $mainUsdTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency, tenant_id, branch_id)
                                                 VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD', ?, ?)");
            $mainUsdTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(2, $usdAmount, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(4, $mainUsdTransactionRemarks, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainUsdTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainUsdTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainUsdTransactionStmt->execute();

            //notification
            $notificationMessage = "Client: $clientName, Paid $usdInAfs AFS for client account funding, processed by: $username, Remarks: $remarks";
            $transaction_type = 'client_fund';
            $status = 'Unread';
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
        }
        
        // Update main account AFS balance with the AFS portion
        if ($afsAmount > 0) {
            $newMainAfsBalance = $mainAccount['afs_balance'] + $afsAmount;
            $updateMainAfsQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainAfsStmt = $pdo->prepare($updateMainAfsQuery);
            $mainAfsStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainAfsStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainAfsStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainAfsStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainAfsStmt->execute();
        }

        // Record client transaction for the total AFS payment
        $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                        VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?, ?)");
        $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
        $transactionStmt->bindParam(2, $afsAmount, PDO::PARAM_STR);
        $transactionStmt->bindParam(3, $newAfsBalance, PDO::PARAM_STR);
        $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
        $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
        $transactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $transactionStmt->execute();
        $lastInsertId = $pdo->lastInsertId();

        // Record AFS main account transaction
        $mainAfsTransactionRemarks = "Client: $clientName, Received $afsAmount AFS for client account funding, processed by: $username, Remarks: $remarks";
        $mainAfsTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency, tenant_id, branch_id)
                                            VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS', ?, ?)");
        $mainAfsTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
        $mainAfsTransactionStmt->bindParam(2, $afsAmount, PDO::PARAM_STR);
        $mainAfsTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
        $mainAfsTransactionStmt->bindParam(4, $mainAfsTransactionRemarks, PDO::PARAM_STR);
        $mainAfsTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
        $mainAfsTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
        $mainAfsTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $mainAfsTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $mainAfsTransactionStmt->execute();

        //notification
        $notificationMessage = "Client: $clientName, Paid $afsAmount AFS for client account funding, processed by: $username, Remarks: $remarks";
        $transaction_type = 'client_fund';
        $status = 'Unread';
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
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Client account funded successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to fund account: ' . $e->getMessage()]);
}

?>