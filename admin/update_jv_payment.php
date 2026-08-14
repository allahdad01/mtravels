<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_security.php';
require_once 'security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'] ?? 'Unknown';

require_once '../includes/db.php';

// Payment currency → supplier currency conversion (rate is "1 <payment> = X <supplier>")
function jvSupplierConvert($paymentCurrency, $supplierCurrency, $amount, $rate) {
    if ($paymentCurrency === $supplierCurrency || $rate <= 0) {
        return $amount;
    }
    $pairFrom = ($paymentCurrency === 'DARHAM') ? 'AED' : $paymentCurrency;
    $pairTo = ($supplierCurrency === 'DARHAM') ? 'AED' : $supplierCurrency;
    $dividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
    return in_array("{$pairFrom}->{$pairTo}", $dividePairs) ? $amount / $rate : $amount * $rate;
}

// Payment currency → client balance currency conversion (rate is "1 <balance> = X <payment>")
function jvClientCredit($balanceCurrency, $paymentCurrency, $amount, $rate) {
    if ($balanceCurrency === $paymentCurrency) {
        return $amount;
    }
    $safeRate = $rate > 0 ? $rate : 1;
    if ($balanceCurrency === 'USD') {
        return $amount / $safeRate;     // 1 USD = X <payment>
    }
    if ($paymentCurrency === 'USD') {
        return $amount * $safeRate;     // 1 USD = X AFS
    }
    return $amount / $safeRate;         // 1 AFS = X <payment>
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header('Location: jv_payments.php');
    exit();
}

$paymentId = intval($_POST['id'] ?? 0);
$jv_name = $_POST['jv_name'] ?? '';
$total_amount = floatval($_POST['total_amount'] ?? 0);
$currency = $_POST['currency'] ?? '';
$balanceCurrency = $_POST['balance_currency'] ?? '';
$receipt = $_POST['receipt'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$exchange_rate = isset($_POST['exchange_rate']) && $_POST['exchange_rate'] !== '' ? floatval($_POST['exchange_rate']) : 0;
$supplierRate = isset($_POST['supplier_rate']) && $_POST['supplier_rate'] !== '' ? floatval($_POST['supplier_rate']) : 0;

if ($paymentId <= 0 || $total_amount <= 0 || empty($currency) || empty($receipt) ||
    !in_array($currency, ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR']) ||
    !in_array($balanceCurrency, ['USD', 'AFS'])) {
    $_SESSION['error_message'] = 'All required fields must be filled out';
    header('Location: jv_payments.php');
    exit();
}

$pdo->beginTransaction();

try {
    // Get original payment
    $stmt = $pdo->prepare("SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$paymentId, $tenant_id, $branch_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    $oldAmount = floatval($payment['total_amount']);
    $oldPaymentCurrency = $payment['currency'];
    $oldBalanceCurrency = $payment['balance_currency'] ?: $oldPaymentCurrency;
    $oldExchangeRate = floatval($payment['exchange_rate'] ?? 0);
    $oldSupplierRate = (isset($payment['supplier_rate']) && $payment['supplier_rate'] > 0) ? floatval($payment['supplier_rate']) : floatval($payment['exchange_rate'] ?? 0);
    $clientId = $payment['client_id'];
    $supplierId = $payment['supplier_id'];

    // Get client and supplier
    $clientStmt = $pdo->prepare("SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $clientStmt->execute([$clientId, $tenant_id, $branch_id]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

    $supplierStmt = $pdo->prepare("SELECT name, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $supplierStmt->execute([$supplierId, $tenant_id, $branch_id]);
    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

    if (!$client || !$supplier) {
        throw new Exception('Client or supplier not found');
    }

    $supplierCurrency = $supplier['currency'];

    // Require rates only when the respective currencies differ
    if ($balanceCurrency !== $currency && $exchange_rate <= 0) {
        throw new Exception('A valid exchange rate is required when client balance and payment currencies differ');
    }
    if ($currency !== $supplierCurrency && $supplierRate <= 0) {
        throw new Exception('A valid supplier exchange rate is required when payment and supplier currencies differ');
    }

    // Client amounts credited to the balance currency
    $oldClientCredit = jvClientCredit($oldBalanceCurrency, $oldPaymentCurrency, $oldAmount, $oldExchangeRate);
    $newClientCredit = jvClientCredit($balanceCurrency, $currency, $total_amount, $exchange_rate);

    // Supplier amounts in the supplier's own currency
    $oldSupplierAmount = jvSupplierConvert($oldPaymentCurrency, $supplierCurrency, $oldAmount, $oldSupplierRate);
    $newSupplierAmount = jvSupplierConvert($currency, $supplierCurrency, $total_amount, $supplierRate);

    // Calculate diffs
    $amountDiff = $newClientCredit - $oldClientCredit;
    $supplierAmountDiff = $newSupplierAmount - $oldSupplierAmount;

    // 1. Update jv_payments record
    $updateStmt = $pdo->prepare("UPDATE jv_payments SET jv_name = ?, total_amount = ?, exchange_rate = ?, supplier_rate = ?, currency = ?, balance_currency = ?, receipt = ?, remarks = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateStmt->execute([$jv_name, $total_amount, $exchange_rate ?: 0, $supplierRate, $currency, $balanceCurrency, $receipt, $remarks, $paymentId, $tenant_id, $branch_id]);

    // 2. Update client master balance (old credit reversed from old column, new credit applied to new column)
    $oldClientField = ($oldBalanceCurrency === 'USD') ? 'usd_balance' : 'afs_balance';
    $newClientField = ($balanceCurrency === 'USD') ? 'usd_balance' : 'afs_balance';

    if ($oldClientField === $newClientField) {
        $updateClientStmt = $pdo->prepare("UPDATE clients SET {$newClientField} = {$newClientField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientStmt->execute([$amountDiff, $clientId, $tenant_id, $branch_id]);
    } else {
        $updateClientOldStmt = $pdo->prepare("UPDATE clients SET {$oldClientField} = {$oldClientField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientOldStmt->execute([$oldClientCredit, $clientId, $tenant_id, $branch_id]);
        $updateClientNewStmt = $pdo->prepare("UPDATE clients SET {$newClientField} = {$newClientField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientNewStmt->execute([$newClientCredit, $clientId, $tenant_id, $branch_id]);
    }

    // 3. Find and update client transaction
    $clientTransStmt = $pdo->prepare("SELECT id, balance FROM client_transactions WHERE client_id = ? AND transaction_of = 'jv_payment' AND reference_id IN (SELECT id FROM jv_transactions WHERE jv_payment_id = ?) AND tenant_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $clientTransStmt->execute([$clientId, $paymentId, $tenant_id, $branch_id]);
    $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);

    if ($clientTrans) {
        $clientTransId = $clientTrans['id'];
        // Update the transaction amount, currency and balance
        $updateClientTransStmt = $pdo->prepare("UPDATE client_transactions SET amount = ?, currency = ?, balance = balance - ? + ?, description = CONCAT('Updated: ', description) WHERE id = ?");
        $updateClientTransStmt->execute([$newClientCredit, $balanceCurrency, $oldClientCredit, $newClientCredit, $clientTransId]);

        // Subsequent transactions in the OLD balance currency: the old credit no longer precedes them
        $updateSubClientOldStmt = $pdo->prepare("UPDATE client_transactions SET balance = balance - ? WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ? AND id > ?");
        $updateSubClientOldStmt->execute([$oldClientCredit, $clientId, $oldBalanceCurrency, $tenant_id, $branch_id, $clientTransId]);

        // Subsequent transactions in the NEW balance currency: the new credit now precedes them
        $updateSubClientNewStmt = $pdo->prepare("UPDATE client_transactions SET balance = balance + ? WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ? AND id > ?");
        $updateSubClientNewStmt->execute([$newClientCredit, $clientId, $balanceCurrency, $tenant_id, $branch_id, $clientTransId]);
    }

    // 4. Update supplier master balance
    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateSupplierStmt->execute([$supplierAmountDiff, $supplierId, $tenant_id, $branch_id]);

    // 5. Find and update supplier transaction
    $supplierTransStmt = $pdo->prepare("SELECT id, balance FROM supplier_transactions WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND reference_id IN (SELECT id FROM jv_transactions WHERE jv_payment_id = ?) AND tenant_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $supplierTransStmt->execute([$supplierId, $paymentId, $tenant_id, $branch_id]);
    $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);

    if ($supplierTrans) {
        $supplierTransId = $supplierTrans['id'];
        // Update the transaction amount and balance
        $updateSupplierTransStmt = $pdo->prepare("UPDATE supplier_transactions SET amount = ?, balance = balance + ?, remarks = CONCAT('Updated: ', remarks) WHERE id = ?");
        $updateSupplierTransStmt->execute([$newSupplierAmount, $supplierAmountDiff, $supplierTransId]);

        // Update subsequent supplier transactions
        $updateSubSupplierStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = balance + ? WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id > ? ORDER BY id ASC");
        $updateSubSupplierStmt->execute([$supplierAmountDiff, $supplierId, $tenant_id, $branch_id, $supplierTransId]);
    }

    // 6. Activity log
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = [
        'jv_name' => $payment['jv_name'],
        'total_amount' => $oldAmount,
        'currency' => $payment['currency'],
        'balance_currency' => $oldBalanceCurrency,
        'exchange_rate' => $oldExchangeRate,
        'supplier_rate' => $oldSupplierRate,
        'client_credit' => $oldClientCredit,
        'supplier_amount' => $oldSupplierAmount,
        'receipt' => $payment['receipt'],
        'remarks' => $payment['remarks'],
    ];

    $new_values = [
        'jv_name' => $jv_name,
        'total_amount' => $total_amount,
        'currency' => $currency,
        'balance_currency' => $balanceCurrency,
        'exchange_rate' => $exchange_rate,
        'supplier_rate' => $supplierRate,
        'client_credit' => $newClientCredit,
        'supplier_amount' => $newSupplierAmount,
        'receipt' => $receipt,
        'remarks' => $remarks,
    ];

    $activity_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id) VALUES (?, 'update', 'jv_payments', ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $activity_stmt->execute([$user_id, $paymentId, json_encode($old_values), json_encode($new_values), $ip_address, $user_agent, $tenant_id, $branch_id]);

    $pdo->commit();
    $_SESSION['success_message'] = 'JV payment updated successfully. Client and supplier balances adjusted.';

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error updating JV payment: ' . $e->getMessage();
}

header('Location: jv_payments.php');
exit();
