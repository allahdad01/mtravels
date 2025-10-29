<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



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
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: jv_payments.php');
    exit();
}

// Get form data
$clientId = intval($_POST['client_id'] ?? 0);
$supplierId = intval($_POST['supplier_id'] ?? 0);
$amount = floatval($_POST['total_amount'] ?? 0);
$currency = $_POST['currency'] ?? 'USD';
$remarks = $_POST['remarks'] ?? '';
$receipt = $_POST['receipt'] ?? '';
$jvName = $_POST['jv_name'] ?? 'Client-Supplier JV Payment';
$exchangeRate = floatval($_POST['exchange_rate'] ?? 0);

// Validate required fields
if ($clientId <= 0 || $supplierId <= 0 || $amount <= 0 || empty($receipt)) {
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
    $clientQuery = "SELECT c.name, c.usd_balance, c.afs_balance FROM clients c WHERE c.id = ? AND c.tenant_id = ?";
    $clientStmt = $pdo->prepare($clientQuery);
    $clientStmt->execute([$clientId, $tenant_id]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        throw new Exception('Client not found');
    }
    
    $clientName = $client['name'];
    
    // Get supplier details
    $supplierQuery = "SELECT s.name, s.balance, s.currency as supplier_currency FROM suppliers s WHERE s.id = ? AND s.tenant_id = ?";
    $supplierStmt = $pdo->prepare($supplierQuery);
    $supplierStmt->execute([$supplierId, $tenant_id]);
    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        throw new Exception('Supplier not found');
    }
    
    $supplierName = $supplier['name'];
    $supplierCurrency = $supplier['supplier_currency'];
    
    // Determine client balance field and check if sufficient balance exists
    if ($currency === 'USD') {
        $clientCurrentBalance = $client['usd_balance'];
        $clientBalanceField = 'usd_balance';
    } else {
        $clientCurrentBalance = $client['afs_balance'];
        $clientBalanceField = 'afs_balance';
    }
    
   
    
    // Calculate new client balance after deduction
    $clientNewBalance = $clientCurrentBalance + $amount;
    
    // Update client balance
    $updateClientQuery = "UPDATE clients SET {$clientBalanceField} = ? WHERE id = ? AND tenant_id = ?";
    $updateClientStmt = $pdo->prepare($updateClientQuery);
    $updateClientStmt->execute([$clientNewBalance, $clientId, $tenant_id]);
    
    // Calculate amount to add to supplier based on currencies
    $supplierAddAmount = $amount;
    if ($currency !== $supplierCurrency) {
        // Convert amount if currencies differ
        if ($currency === 'USD' && $supplierCurrency === 'AFS') {
            // Convert USD to AFS
            $supplierAddAmount = $amount * $exchangeRate;
        } else if ($currency === 'AFS' && $supplierCurrency === 'USD') {
            // Convert AFS to USD
            $supplierAddAmount = $amount / $exchangeRate;
        }
    }
    
    // Calculate new supplier balance
    $supplierCurrentBalance = $supplier['balance'];
    $supplierNewBalance = $supplierCurrentBalance + $supplierAddAmount;
    
    // Update supplier balance
    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?";
    $updateSupplierStmt = $pdo->prepare($updateSupplierQuery);
    $updateSupplierStmt->execute([$supplierNewBalance, $supplierId, $tenant_id]);
    
    // Insert into jv_payments table
    $insertJvQuery = "INSERT INTO jv_payments (
        jv_name, exchange_rate,
        total_amount, currency, receipt, remarks, created_by,
        client_id, supplier_id, tenant_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Set appropriate USD/AFS amounts
    $usdAmount = ($currency === 'USD') ? $amount : 0;
    $afsAmount = ($currency === 'AFS') ? $amount : 0;

    // For JV payments between client and supplier, we'll use 0 as main_account_id
    // since no main account is involved
    $mainAccountId = 0;

    $insertJvStmt = $pdo->prepare($insertJvQuery);
    $insertJvStmt->execute([
        $jvName, $exchangeRate, $amount, $currency, $receipt, $remarks, $user_id,
        $clientId, $supplierId, $tenant_id
    ]);
    
    $jvPaymentId = $pdo->lastInsertId();
    
    // Create the full remarks for transaction logs
    $clientRemark = "JV Payment: Client {$clientName} paid {$amount} {$currency} to supplier {$supplierName}. Receipt: {$receipt}. Processed by: {$username}. {$remarks}";
    $supplierRemark = "JV Payment: Received {$supplierAddAmount} {$supplierCurrency} from client {$clientName}. Processed by: {$username}. {$remarks}";
    
    // Record JV transaction
    $jvTransactionQuery = "INSERT INTO jv_transactions (
        jv_payment_id, transaction_type, amount, balance, currency,
        description, receipt, reference_id, tenant_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $jvTransactionStmt = $pdo->prepare($jvTransactionQuery);
    $jvTransactionStmt->execute([
        $jvPaymentId, 'Transfer', $amount, $amount, $currency,
        $remarks, $receipt, $clientId, $tenant_id
    ]);
    
    $jvTransactionId = $pdo->lastInsertId();
    
    // Record client transaction (debit)
    $clientTransactionQuery = "INSERT INTO client_transactions (
        client_id, type, amount, balance, currency,
        `description`, transaction_of, reference_id, receipt, tenant_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $clientTransactionStmt = $pdo->prepare($clientTransactionQuery);
    $clientTransactionStmt->execute([
        $clientId, 'credit', $amount, $clientNewBalance, $currency,
        $clientRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id
    ]);
    
    // Record supplier transaction (credit)
    $supplierTransactionQuery = "INSERT INTO supplier_transactions (
        supplier_id, transaction_type, amount, balance,
        remarks, transaction_of, reference_id, receipt, tenant_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $supplierTransactionStmt = $pdo->prepare($supplierTransactionQuery);
    $supplierTransactionStmt->execute([
        $supplierId, 'Credit', $supplierAddAmount, $supplierNewBalance,
        $supplierRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id
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
        'supplier_amount' => $supplierAddAmount,
        'currency' => $currency,
        'supplier_currency' => $supplierCurrency,
        'exchange_rate' => $exchangeRate,
        'receipt' => $receipt,
        'remarks' => $remarks
    ];
    
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

    $new_values_json = json_encode($new_values);
    $activity_log_stmt->execute([$user_id, 'add', 'jv_payments', $jvPaymentId, '{}', $new_values_json, $ip_address, $user_agent, $tenant_id]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = 'JV payment processed successfully. Client balance reduced and supplier balance increased.';
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: jv_payments.php');
exit(); 