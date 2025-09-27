<?php
session_start();
require_once('../includes/conn.php');
// Check if the user is logged in
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Check if the request is JSON or form data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    // Handle form data
    $data = $_POST;
}
$tenant_id = $_SESSION['tenant_id'];
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

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
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
$clientAccountQuery = "SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? and tenant_id = ?";
$stmt = $conn->prepare($clientAccountQuery);
$stmt->bind_param('ii', $clientId, $tenant_id);
$stmt->execute();
$clientAccount = $stmt->get_result()->fetch_assoc();
$clientName = $clientAccount['name'];

if (!$clientAccount) {
    echo json_encode(['success' => false, 'message' => 'Client account not found.']);
    exit;
}

// Fetch main account details
$mainAccountQuery = "SELECT id, name, usd_balance, afs_balance FROM main_account WHERE id = ? and tenant_id = ?";
$mainAccountStmt = $conn->prepare($mainAccountQuery);
$mainAccountStmt->bind_param('ii', $mainAccountId, $tenant_id);
$mainAccountStmt->execute();
$mainAccount = $mainAccountStmt->get_result()->fetch_assoc();

if (!$mainAccount) {
    echo json_encode(['success' => false, 'message' => 'Main account not found.']);
    exit;
}

// Generate full remark with the user name, date, and custom message
$fullRemark = "Client: $clientName, Account funded by $username. Remarks: $remarks";
$timestamp = date('Y-m-d H:i:s');

$conn->begin_transaction();

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
        $updateQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? and tenant_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('dii', $newUsdBalance, $clientId, $tenant_id);
        $stmt->execute();
        
        // Update main account USD balance with the USD portion
        $newMainUsdBalance = $mainAccount['usd_balance'] + $usdAmount;
        $updateMainUsdQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? and tenant_id = ?";
        $mainUsdStmt = $conn->prepare($updateMainUsdQuery);
        $mainUsdStmt->bind_param('dii', $newMainUsdBalance, $mainAccountId, $tenant_id);
        $mainUsdStmt->execute();
        
        // Record client transaction for the total USD payment
        $transactionStmt = $conn->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,exchange_rate,tenant_id)
                                        VALUES (?, 'Credit', 'USD', ?, ?, 'fund', ?, ?, ?, ?, ?)");
        $transactionStmt->bind_param('iddsssss', $clientId, $usdAmount, $newUsdBalance, $fullRemark, $user_id, $receipt, $exchangeRate, $tenant_id);
        $transactionStmt->execute();
        $lastInsertId = $transactionStmt->insert_id;
        
        // Record USD main account transaction
        $mainUsdTransactionRemarks = "Client: $clientName, Received $usdAmount USD for client account funding, processed by: $username, Remarks: $remarks";
        $mainUsdTransactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency,tenant_id)
                                            VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD',?)");
        $mainUsdTransactionStmt->bind_param('idisdss', $mainAccountId, $usdAmount, $lastInsertId, $mainUsdTransactionRemarks, $newMainUsdBalance, $receipt, $tenant_id);
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
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $notificationMessage, $status, $tenant_id);

        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to send notification to admin.");
        }
        

        // If there's an AFS portion, update main account AFS balance
        if ($afsAmount > 0) {
            // Update main account AFS balance
            $newMainAfsBalance = $mainAccount['afs_balance'] + $afsAmount;
            $updateMainAfsQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? and tenant_id = ?";
            $mainAfsStmt = $conn->prepare($updateMainAfsQuery);
            $mainAfsStmt->bind_param('dii', $newMainAfsBalance, $mainAccountId, $tenant_id);
            $mainAfsStmt->execute();
            // Record client transaction for the total USD payment
            $transactionStmt = $conn->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,exchange_rate,tenant_id)
            VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?, ?)");
            $transactionStmt->bind_param('iddsssss', $clientId, $afsAmount, $newUsdBalance, $fullRemark, $user_id, $receipt, $exchangeRate, $tenant_id);
            $transactionStmt->execute();
            $lastInsertId = $transactionStmt->insert_id;
            
            // Record AFS main account transaction
            $mainAfsTransactionRemarks = "Client: $clientName, Received $afsAmount AFS (equivalent to $afsInUsd USD) for client account funding, processed by: $username, Remarks: $remarks";
            $mainAfsTransactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency,tenant_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS',?)");
            $mainAfsTransactionStmt->bind_param('idisdss', $mainAccountId, $afsAmount, $lastInsertId, $mainAfsTransactionRemarks, $newMainAfsBalance, $receipt, $tenant_id);
            $mainAfsTransactionStmt->execute();

            //notification
            $notificationMessage = "Client: $clientName, Paid $afsAmount AFS (equivalent to $afsInUsd USD) for client account funding, processed by: $username, Remarks: $remarks";
            $transaction_type = 'client_fund';
            $status = 'Unread';
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
            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $notificationMessage, $status, $tenant_id);
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
        $updateQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? and tenant_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('dii', $newAfsBalance, $clientId, $tenant_id);
        $stmt->execute();
        
        // Update main account USD balance with the USD portion
        if ($usdAmount > 0) {
            $newMainUsdBalance = $mainAccount['usd_balance'] + $usdAmount;
            $updateMainUsdQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? and tenant_id = ?";
            $mainUsdStmt = $conn->prepare($updateMainUsdQuery);
            $mainUsdStmt->bind_param('dii', $newMainUsdBalance, $mainAccountId, $tenant_id);
            $mainUsdStmt->execute();
            
            // Record USD main account transaction
            $mainUsdTransactionRemarks = "Client: $clientName, Received $usdAmount USD (equivalent to $usdInAfs AFS) for client account funding, processed by: $username, Remarks: $remarks";
            $mainUsdTransactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency,tenant_id)
                                                VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'USD',?)");
            $mainUsdTransactionStmt->bind_param('idisdss', $mainAccountId, $usdAmount, $lastInsertId, $mainUsdTransactionRemarks, $newMainUsdBalance, $receipt, $tenant_id);
            $mainUsdTransactionStmt->execute();

            //notification
            $notificationMessage = "Client: $clientName, Paid $usdAmount USD (equivalent to $usdInAfs AFS) for client account funding, processed by: $username, Remarks: $remarks";
            $transaction_type = 'client_fund';
            $status = 'Unread';
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
            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $notificationMessage, $status, $tenant_id);
            if (!$notificationStmt->execute()) {
                throw new Exception("Failed to send notification to admin.");
            }
        }
        
        // Update main account AFS balance with the AFS portion
        if ($afsAmount > 0) {
            $newMainAfsBalance = $mainAccount['afs_balance'] + $afsAmount;
            $updateMainAfsQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? and tenant_id = ?";
            $mainAfsStmt = $conn->prepare($updateMainAfsQuery);
            $mainAfsStmt->bind_param('dii', $newMainAfsBalance, $mainAccountId, $tenant_id);
            $mainAfsStmt->execute();
        }
        
        // Record client transaction for the total AFS payment
        $transactionStmt = $conn->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt,tenant_id)
                                        VALUES (?, 'Credit', 'AFS', ?, ?, 'fund', ?, ?, ?, ?)");
        $transactionStmt->bind_param('iddssss', $clientId, $afsAmount, $newAfsBalance, $fullRemark, $user_id, $receipt, $tenant_id);
        $transactionStmt->execute();
        $lastInsertId = $transactionStmt->insert_id;
        
        // Record AFS main account transaction
        $mainAfsTransactionRemarks = "Client: $clientName, Received $afsAmount AFS for client account funding, processed by: $username, Remarks: $remarks";
        $mainAfsTransactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt,currency,tenant_id)
                                            VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, 'AFS',?)");
        $mainAfsTransactionStmt->bind_param('idisdss', $mainAccountId, $afsAmount, $lastInsertId, $mainAfsTransactionRemarks, $newMainAfsBalance, $receipt, $tenant_id);
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
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param('isssi', $lastInsertId, $transaction_type, $notificationMessage, $status, $tenant_id);
        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to send notification to admin.");
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Client account funded successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to fund account: ' . $e->getMessage()]);
}

$conn->close();
?>