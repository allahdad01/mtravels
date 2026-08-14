<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Detect AJAX requests so we can respond with JSON instead of redirecting
$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the username
$username = $_SESSION['name'] ?? 'Unknown';
$user_id = $_SESSION['user_id'];

// Database connection
require_once('../includes/db.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }
    $_SESSION['error'] = 'Invalid request method';
    header('Location: jv_payments.php');
    exit();
}

// Get form data
$clientId = intval($_POST['client_id'] ?? 0);
$supplierId = intval($_POST['supplier_id'] ?? 0);
$amount = floatval($_POST['total_amount'] ?? 0);
$currency = $_POST['currency'] ?? 'USD';
$balanceCurrency = $_POST['balance_currency'] ?? 'USD';
$remarks = $_POST['remarks'] ?? '';
$receipt = $_POST['receipt'] ?? '';
$jvName = $_POST['jv_name'] ?? 'Client-Supplier JV Payment';
$exchangeRate = floatval($_POST['exchange_rate'] ?? 0);
$supplierRate = floatval($_POST['supplier_rate'] ?? 0);

// Validate required fields
if ($clientId <= 0 || $supplierId <= 0 || $amount <= 0 || empty($receipt)) {
    if ($isAjax) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled out']);
        exit();
    }
    $_SESSION['error_message'] = 'All required fields must be filled out';
    header('Location: jv_payments.php');
    exit();
}

// Validate exchange_rate
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : 0;

// Validate jv_name
$jv_name = isset($_POST['jv_name']) ? DbSecurity::validateInput($_POST['jv_name'], 'string', ['maxlength' => 255]) : 'Client-Supplier JV Payment';

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : '';

// Validate remarks - keep original value if validation fails
$validated_remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : '';
if (!empty($validated_remarks)) {
    $remarks = $validated_remarks;
}

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : 'USD';

// Validate balance currency (client accounts only hold USD/AFS)
$balanceCurrency = isset($_POST['balance_currency']) ? DbSecurity::validateInput($_POST['balance_currency'], 'currency') : 'USD';
if (!in_array($balanceCurrency, ['USD', 'AFS'])) {
    $balanceCurrency = 'USD';
}

// Validate supplier rate (payment → supplier currency conversion)
$validatedSupplierRate = isset($_POST['supplier_rate']) ? DbSecurity::validateInput($_POST['supplier_rate'], 'float', ['min' => 0]) : 0;
if ($validatedSupplierRate > 0) {
    $supplierRate = $validatedSupplierRate;
}

// Validate total_amount
$total_amount = isset($_POST['total_amount']) ? DbSecurity::validateInput($_POST['total_amount'], 'float', ['min' => 0]) : 0;
if ($total_amount > 0) {
    $amount = $total_amount;
}

// Validate supplier_id
$supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : 0;
if ($supplier_id > 0) {
    $supplierId = $supplier_id;
}

// Validate client_id
$client_id = isset($_POST['client_id']) ? DbSecurity::validateInput($_POST['client_id'], 'int', ['min' => 0]) : 0;
if ($client_id > 0) {
    $clientId = $client_id;
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Get client details
    $clientQuery = "SELECT c.name, c.usd_balance, c.afs_balance FROM clients c WHERE c.id = ? AND c.tenant_id = ? AND c.branch_id = ?";
    $clientStmt = $pdo->prepare($clientQuery);
    $clientStmt->execute([$clientId, $tenant_id, $branch_id]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        throw new Exception('Client not found');
    }
    
    $clientName = $client['name'];
    
    // Get supplier details
    $supplierQuery = "SELECT s.name, s.balance, s.currency as supplier_currency FROM suppliers s WHERE s.id = ? AND s.tenant_id = ? AND s.branch_id = ?";
    $supplierStmt = $pdo->prepare($supplierQuery);
    $supplierStmt->execute([$supplierId, $tenant_id, $branch_id]);
    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        throw new Exception('Supplier not found');
    }
    
    $supplierName = $supplier['name'];
    $supplierCurrency = $supplier['supplier_currency'];

    // Require the client exchange rate when balance and payment currencies differ
    if ($balanceCurrency !== $currency && $exchangeRate <= 0) {
        throw new Exception('A valid exchange rate is required when client balance and payment currencies differ');
    }

    // Require the supplier exchange rate when payment and supplier currencies differ
    if ($currency !== $supplierCurrency && $supplierRate <= 0) {
        throw new Exception('A valid supplier exchange rate is required when payment and supplier currencies differ');
    }

    // Determine client balance field from the selected balance currency
    $clientBalanceField = ($balanceCurrency === 'USD') ? 'usd_balance' : 'afs_balance';
    $clientCurrentBalance = $client[$clientBalanceField];

    // Amount credited to the client balance (payment currency → balance currency).
    // Same convention as fund client: rate is "1 <balance> = X <payment>"
    if ($balanceCurrency === $currency) {
        $clientCredit = $amount;                     // same currency
    } elseif ($balanceCurrency === 'USD') {
        $clientCredit = $amount / $exchangeRate;     // 1 USD = X <payment>
    } elseif ($currency === 'USD') {
        $clientCredit = $amount * $exchangeRate;     // 1 USD = X AFS
    } else {
        $clientCredit = $amount / $exchangeRate;     // 1 AFS = X <payment>
    }

    // Calculate new client balance
    $clientNewBalance = $clientCurrentBalance + $clientCredit;

    // Update client balance
    $updateClientQuery = "UPDATE clients SET {$clientBalanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $updateClientStmt = $pdo->prepare($updateClientQuery);
    $updateClientStmt->execute([$clientNewBalance, $clientId, $tenant_id, $branch_id]);

    // Amount to add to supplier (payment currency → supplier currency).
    // Rate is "1 <payment> = X <supplier currency>", same divide/multiply
    // convention used by balance transfers
    $pairFrom = ($currency === 'DARHAM') ? 'AED' : $currency;
    $pairTo = ($supplierCurrency === 'DARHAM') ? 'AED' : $supplierCurrency;
    $dividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];

    $supplierAddAmount = $amount;
    if ($currency !== $supplierCurrency) {
        $pairKey = "{$pairFrom}->{$pairTo}";
        $supplierAddAmount = in_array($pairKey, $dividePairs) ? $amount / $supplierRate : $amount * $supplierRate;
    }
    
    // Calculate new supplier balance
    $supplierCurrentBalance = $supplier['balance'];
    $supplierNewBalance = $supplierCurrentBalance + $supplierAddAmount;
    
    // Update supplier balance
    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $updateSupplierStmt = $pdo->prepare($updateSupplierQuery);
    $updateSupplierStmt->execute([$supplierNewBalance, $supplierId, $tenant_id, $branch_id]);
    
    // Insert into jv_payments table
    $insertJvQuery = "INSERT INTO jv_payments (
        jv_name, exchange_rate, supplier_rate,
        total_amount, currency, balance_currency, receipt, remarks, created_by,
        client_id, supplier_id, tenant_id, branch_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insertJvStmt = $pdo->prepare($insertJvQuery);
    $insertJvStmt->execute([
        $jvName, $exchangeRate, $supplierRate, $amount, $currency, $balanceCurrency,
        $receipt, $remarks, $user_id,
        $clientId, $supplierId, $tenant_id, $branch_id
    ]);
    
    $jvPaymentId = $pdo->lastInsertId();
    
    // Create the full remarks for transaction logs
    $clientRemark = "JV Payment: Client {$clientName} paid {$amount} {$currency} to supplier {$supplierName}. Credited {$clientCredit} {$balanceCurrency}. Receipt: {$receipt}. Processed by: {$username}. {$remarks}";
    $supplierRemark = "JV Payment: Received {$supplierAddAmount} {$supplierCurrency} from client {$clientName}. Processed by: {$username}. {$remarks}";
    
    // Record JV transaction
    $jvTransactionQuery = "INSERT INTO jv_transactions (
        jv_payment_id, transaction_type, amount, balance, currency,
        description, receipt, reference_id, tenant_id, branch_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $jvTransactionStmt = $pdo->prepare($jvTransactionQuery);
    $jvTransactionStmt->execute([
        $jvPaymentId, 'Transfer', $amount, $amount, $currency,
        $remarks, $receipt, $clientId, $tenant_id, $branch_id
    ]);
    
    $jvTransactionId = $pdo->lastInsertId();
    
    // Record client transaction (credit, in the balance currency)
    $clientTransactionQuery = "INSERT INTO client_transactions (
        client_id, type, amount, balance, currency,
        `description`, transaction_of, reference_id, receipt, tenant_id, branch_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $clientTransactionStmt = $pdo->prepare($clientTransactionQuery);
    $clientTransactionStmt->execute([
        $clientId, 'credit', $clientCredit, $clientNewBalance, $balanceCurrency,
        $clientRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id, $branch_id
    ]);
    
    // Record supplier transaction (credit)
    $supplierTransactionQuery = "INSERT INTO supplier_transactions (
        supplier_id, transaction_type, amount, balance,
        remarks, transaction_of, reference_id, receipt, tenant_id, branch_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $supplierTransactionStmt = $pdo->prepare($supplierTransactionQuery);
    $supplierTransactionStmt->execute([
        $supplierId, 'Credit', $supplierAddAmount, $supplierNewBalance,
        $supplierRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id, $branch_id
    ]);
    
    // Add activity logging
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare new values data
    $new_values = [
        'jv_payment_id' => $jvPaymentId,
        'jv_name' => $jvName,
        'client_id' => $clientId,
        'client_name' => $clientName,
        'supplier_id' => $supplierId,
        'supplier_name' => $supplierName,
        'amount' => $amount,
        'client_credit' => $clientCredit,
        'supplier_amount' => $supplierAddAmount,
        'currency' => $currency,
        'balance_currency' => $balanceCurrency,
        'supplier_currency' => $supplierCurrency,
        'exchange_rate' => $exchangeRate,
        'supplier_rate' => $supplierRate,
        'receipt' => $receipt,
        'remarks' => $remarks
    ];
    
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

    $new_values_json = json_encode($new_values);
    $activity_log_stmt->execute([$user_id, 'add', 'jv_payments', $jvPaymentId, '{}', $new_values_json, $ip_address, $user_agent, $tenant_id, $branch_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Send WhatsApp notification for JV payment (if configured)
    try {
        require_once '../api/whatsapp/WhatsAppManager.php';
        $whatsappManager = new WhatsAppManager($tenant_id);
        $whatsapp_result = $whatsappManager->sendBookingNotification('jv_payment', $jvPaymentId);
        
        if ($whatsapp_result['success']) {
            error_log("WhatsApp notification sent for JV Payment ID: $jvPaymentId");
        } else {
            error_log("WhatsApp notification failed for JV Payment ID: $jvPaymentId - " . $whatsapp_result['message']);
        }
    } catch (Exception $e) {
        // Don't fail the operation if WhatsApp fails
        error_log("WhatsApp integration error for JV Payment ID: $jvPaymentId - " . $e->getMessage());
    }
    
    $_SESSION['success_message'] = 'JV payment processed successfully. Client balance reduced and supplier balance increased.';
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'JV payment processed successfully',
        'jv_payment_id' => $jvPaymentId ?? null,
    ]);
    exit();
}

header('Location: jv_payments.php');
exit(); 