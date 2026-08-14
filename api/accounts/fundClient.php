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

// Payment currency: what currency is the client paying in
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

// Currency → main_account balance field mapping
$balanceFields = [
    'USD'    => 'usd_balance',
    'AFS'    => 'afs_balance',
    'EUR'    => 'euro_balance',
    'DARHAM' => 'darham_balance',
    'SAR'    => 'sar_balance',
];

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
if (empty($paymentCurrency) || !isset($balanceFields[$paymentCurrency])) {
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

// Validate exchange rate when currencies differ
if ($balanceCurrency !== $paymentCurrency && $exchangeRate <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid exchange rate is required when payment and balance currencies differ']);
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

// Fetch main account details (all currency balances)
$mainAccountQuery = "SELECT id, name, usd_balance, afs_balance, euro_balance, darham_balance, sar_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?";
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
    // Calculate the amount to credit to the client balance (balance currency)
    if ($balanceCurrency === $paymentCurrency) {
        $amountToCredit = $paymentAmount;
    } elseif ($balanceCurrency === 'USD') {
        // 1 USD = X <payment> → USD = payment / X
        $amountToCredit = $paymentAmount / $exchangeRate;
    } elseif ($paymentCurrency === 'USD') {
        // 1 USD = X AFS → AFS = USD × X
        $amountToCredit = $paymentAmount * $exchangeRate;
    } else {
        // 1 AFS = X <payment> → AFS = payment / X
        $amountToCredit = $paymentAmount / $exchangeRate;
    }

    // Update client balance (balance currency column)
    $clientField = $balanceFields[$balanceCurrency];
    $newClientBalance = $clientAccount[$clientField] + $amountToCredit;
    $updateClientStmt = $pdo->prepare("UPDATE clients SET $clientField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateClientStmt->bindParam(1, $newClientBalance, PDO::PARAM_STR);
    $updateClientStmt->bindParam(2, $clientId, PDO::PARAM_INT);
    $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateClientStmt->execute();

    // Update main account (credit the payment currency balance)
    $mainField = $balanceFields[$paymentCurrency];
    $newMainBalance = $mainAccount[$mainField] + $paymentAmount;
    $updateMainStmt = $pdo->prepare("UPDATE main_account SET $mainField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateMainStmt->bindParam(1, $newMainBalance, PDO::PARAM_STR);
    $updateMainStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
    $updateMainStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateMainStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateMainStmt->execute();

    // Record client transaction (in balance currency)
    $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, exchange_rate, tenant_id, branch_id)
                                    VALUES (?, 'Credit', ?, ?, ?, 'fund', ?, ?, ?, ?, ?, ?)");
    $transactionStmt->bindParam(1, $clientId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $balanceCurrency, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $amountToCredit, PDO::PARAM_STR);
    $transactionStmt->bindParam(4, $newClientBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $fullRemark, PDO::PARAM_STR);
    $transactionStmt->bindParam(6, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $transactionStmt->bindParam(8, $exchangeRate, PDO::PARAM_STR);
    $transactionStmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(10, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();
    $lastInsertId = $pdo->lastInsertId();

    // Record main account transaction (in payment currency)
    $rateText = ($balanceCurrency !== $paymentCurrency) ? " (rate: 1 " . $balanceCurrency . " = " . $exchangeRate . " " . $paymentCurrency . ")" : "";
    $mainTransactionRemarks = "Client: $clientName, Paid $paymentAmount $paymentCurrency (= " . round($amountToCredit, 2) . " $balanceCurrency) for account funding, processed by: $username, Remarks: $remarks";
    $mainTransactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, transaction_of, reference_id, description, balance, receipt, currency, tenant_id, branch_id, created_by)
                                        VALUES (?, 'credit', ?, 'client_fund', ?, ?, ?, ?, ?, ?, ?, ?)");
    $mainTransactionStmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(2, $paymentAmount, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(3, $lastInsertId, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(4, $mainTransactionRemarks, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(5, $newMainBalance, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(6, $receipt, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(7, $paymentCurrency, PDO::PARAM_STR);
    $mainTransactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $mainTransactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $mainTransactionStmt->bindValue(10, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
    $mainTransactionStmt->execute();

    // Notification
    $notificationMessage = "Client: $clientName, Paid $paymentAmount $paymentCurrency for account funding (credited " . round($amountToCredit, 2) . " $balanceCurrency), processed by: $username, Remarks: $remarks";
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

    $pdo->commit();

    // Send WhatsApp notification for client fund (if configured)
    try {
        require_once '../../api/whatsapp/WhatsAppManager.php';
        $whatsappManager = new WhatsAppManager($tenant_id);
        $whatsapp_result = $whatsappManager->sendBookingNotification('client_fund', $lastInsertId);

        if ($whatsapp_result['success']) {
            error_log("WhatsApp notification sent for Client Fund ID: $lastInsertId");
        } else {
            error_log("WhatsApp notification failed for Client Fund ID: $lastInsertId - " . $whatsapp_result['message']);
        }
    } catch (Exception $e) {
        // Don't fail the operation if WhatsApp fails
        error_log("WhatsApp integration error for Client Fund ID: $lastInsertId - " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Client account funded successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to fund account: ' . $e->getMessage()]);
}

?>
