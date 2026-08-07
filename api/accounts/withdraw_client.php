<?php
session_start();
require_once('../../admin/security.php');
require_once('../../includes/db.php');

enforce_auth();

$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
$branch_id = isset($_SESSION['branch_id']) ? $_SESSION['branch_id'] : null;

$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data = $_POST;
}

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$clientId = isset($data['client_id']) ? $data['client_id'] : null;
$balanceCurrency = isset($data['payment_currency']) ? $data['payment_currency'] : null;
$paymentCurrency = isset($data['payment_currency_actual']) ? $data['payment_currency_actual'] : null;
$paymentAmount = isset($data['total_amount']) ? (float)$data['total_amount'] : 0;
$usdAmount = isset($data['usd_amount']) ? (float)$data['usd_amount'] : 0;
$afsAmount = isset($data['afs_amount']) ? (float)$data['afs_amount'] : 0;
$exchangeRate = isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : 1;
$remarks = isset($data['remarks']) ? $data['remarks'] : '';
$receipt = isset($data['receipt_number']) ? strval($data['receipt_number']) : '';
$mainAccountId = isset($data['main_account']) ? $data['main_account'] : null;

if (empty($receipt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receipt number is required']);
    exit;
}

if (empty($clientId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

if (empty($balanceCurrency) || !in_array($balanceCurrency, ['USD', 'AFS'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid balance currency is required']);
    exit;
}

if (empty($paymentCurrency) || !in_array($paymentCurrency, ['USD', 'AFS'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid payment currency is required']);
    exit;
}

if (empty($mainAccountId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Main account is required']);
    exit;
}

if ($paymentAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Withdraw amount must be greater than zero.']);
    exit;
}

$clientAccountQuery = "SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($clientAccountQuery);
$stmt->bindParam(1, $clientId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clientAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$clientAccount) {
    echo json_encode(['success' => false, 'message' => 'Client account not found.']);
    exit;
}
$clientName = $clientAccount['name'];

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

if ($balanceCurrency === 'USD' && $clientAccount['usd_balance'] < $paymentAmount && $balanceCurrency === $paymentCurrency) {
    echo json_encode(['success' => false, 'message' => 'Insufficient USD balance in client account.']);
    exit;
}
if ($balanceCurrency === 'AFS' && $clientAccount['afs_balance'] < $paymentAmount && $balanceCurrency === $paymentCurrency) {
    echo json_encode(['success' => false, 'message' => 'Insufficient AFS balance in client account.']);
    exit;
}

$fullRemark = "Client: $clientName, Account withdrawn by $username. Remarks: $remarks";
$timestamp = date('Y-m-d H:i:s');

$pdo->beginTransaction();

try {
    $amountToDeduct = $paymentAmount;

    if ($balanceCurrency !== $paymentCurrency) {
        if ($balanceCurrency === 'USD' && $paymentCurrency === 'AFS') {
            $amountToDeduct = $paymentAmount / $exchangeRate;
        } else if ($balanceCurrency === 'AFS' && $paymentCurrency === 'USD') {
            $amountToDeduct = $paymentAmount * $exchangeRate;
        }
    }

    if ($balanceCurrency === 'USD') {
        $newUsdBalance = $clientAccount['usd_balance'] - $amountToDeduct;
        $updateQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($paymentCurrency === 'AFS') {
            $newMainAfsBalance = $mainAccount['afs_balance'] - $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, exchange_rate, tenant_id, branch_id)
                                            VALUES (?, 'debit', 'USD', ?, ?, 'client_withdrawal', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $amountToDeduct, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newUsdBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            $mainTransactionRemarks = "Client: $clientName, Withdrawn ؋$paymentAmount AFS (= -$" . number_format($amountToDeduct, 2) . " USD) from account, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id, created_by)
                                                VALUES (?, 'debit', ?, 'client_withdraw', ?, ?, ?, ?, 'AFS', ?, ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindValue(9, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        } else {
            $newMainUsdBalance = $mainAccount['usd_balance'] - $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                            VALUES (?, 'debit', 'USD', ?, ?, 'client_withdrawal', ?, ?, ?, ?, ?)");
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

            $mainTransactionRemarks = "Client: $clientName, Withdrawn \$$paymentAmount USD from account, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id, created_by)
                                                VALUES (?, 'debit', ?, 'client_withdraw', ?, ?, ?, ?, 'USD', ?, ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindValue(9, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        }

        $notificationMessage = "Client: $clientName, Withdrawn $paymentAmount $paymentCurrency from account (deducted $" . number_format($amountToDeduct, 2) . " USD), processed by: $username, Remarks: $remarks";
        $transaction_type = 'client_withdraw';
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
        $newAfsBalance = $clientAccount['afs_balance'] - $amountToDeduct;
        $updateQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($paymentCurrency === 'USD') {
            $newMainUsdBalance = $mainAccount['usd_balance'] - $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainUsdBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, exchange_rate, tenant_id, branch_id)
                                            VALUES (?, 'debit', 'AFS', ?, ?, 'client_withdrawal', ?, ?, ?, ?, ?, ?)");
            $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $transactionStmt->bindParam(2, $amountToDeduct, PDO::PARAM_STR);
            $transactionStmt->bindParam(3, $newAfsBalance, PDO::PARAM_STR);
            $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
            $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $transactionStmt->bindParam(7, $exchangeRate, PDO::PARAM_STR);
            $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $transactionStmt->execute();
            $lastInsertId = $pdo->lastInsertId();

            $mainTransactionRemarks = "Client: $clientName, Withdrawn \$$paymentAmount USD (= -؋" . number_format($amountToDeduct, 2) . " AFS) from account, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id, created_by)
                                                VALUES (?, 'debit', ?, 'client_withdraw', ?, ?, ?, ?, 'USD', ?, ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainUsdBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindValue(9, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        } else {
            $newMainAfsBalance = $mainAccount['afs_balance'] - $paymentAmount;
            $updateMainQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $mainStmt = $pdo->prepare($updateMainQuery);
            $mainStmt->bindParam(1, $newMainAfsBalance, PDO::PARAM_STR);
            $mainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainStmt->execute();

            $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                            VALUES (?, 'debit', 'AFS', ?, ?, 'client_withdrawal', ?, ?, ?, ?, ?)");
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

            $mainTransactionRemarks = "Client: $clientName, Withdrawn ؋$paymentAmount AFS from account, processed by: $username, Remarks: $remarks";
            $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id, created_by)
                                                VALUES (?, 'debit', ?, 'client_withdraw', ?, ?, ?, ?, 'AFS', ?, ?, ?)");
            $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(5, $newMainAfsBalance, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
            $mainTransactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $mainTransactionStmt->bindValue(9, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $mainTransactionStmt->execute();
        }

        $notificationMessage = "Client: $clientName, Withdrawn $paymentAmount $paymentCurrency from account (deducted ؋" . number_format($amountToDeduct, 2) . " AFS), processed by: $username, Remarks: $remarks";
        $transaction_type = 'client_withdraw';
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

    echo json_encode(['success' => true, 'message' => 'Client account withdrawn successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to withdraw from account: ' . $e->getMessage()]);
}
