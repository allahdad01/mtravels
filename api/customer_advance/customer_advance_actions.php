<?php
/**
 * Customer Advance API
 *
 * Actions:
 *   summary            (GET)   Overall summary cards
 *   get_customers      (GET)   List all customers with advances
 *   get_advances       (GET)   Advances for a customer
 *   get_payments       (GET)   Payment history for a customer
 *   record_advance     (POST)  Record a new advance (supplier gave money to customer)
 *   mark_supplier_paid (POST)  Mark advance as paid by agency to supplier
 *   record_payment     (POST)  Record incoming or outgoing payment
 *   delete_advance     (POST)  Delete advance (only if no payments)
 *   delete_payment     (POST)  Delete a payment record
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
require_once '../../includes/db.php';
enforce_auth();
require_permission('finance.expenses');

header('Content-Type: application/json');

$tenant_id  = (int) $_SESSION['tenant_id'];
$branch_id  = (int) $_SESSION['branch_id'];
$current_user = (int) ($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] === 'POST') && !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function logActivity(PDO $pdo, $userId, $tenantId, $branchId, $act, $recordId, $oldV, $newV) {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, ?, 'customer_advances', ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt->execute([$userId, $act, $recordId, $oldV, $newV, $ip, $ua, $tenantId, $branchId]);
}

function getBalanceColumn($currency) {
    switch ($currency) {
        case 'AFS':    return 'afs_balance';
        case 'EUR':    return 'euro_balance';
        case 'DARHAM': return 'darham_balance';
        case 'SAR':    return 'sar_balance';
        default:       return 'usd_balance';
    }
}

try {
    switch ($action) {

        /* ── Summary cards ── */
        case 'summary':
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';
            $date_cond = '';
            $params = [$tenant_id, $branch_id];
            if ($start_date) { $date_cond .= " AND ca.advance_date >= ?"; $params[] = $start_date; }
            if ($end_date) { $date_cond .= " AND ca.advance_date <= ?"; $params[] = $end_date; }

            $sql = "SELECT
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'outgoing'
                ) THEN ca.amount ELSE 0 END) AS total_owed_to_suppliers,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'outgoing'
                ) THEN ca.amount ELSE 0 END) AS total_paid_to_suppliers,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'incoming'
                ) THEN ca.amount ELSE 0 END) AS total_completed,
                COUNT(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'outgoing'
                ) THEN 1 END) AS owed_count,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'outgoing'
                ) THEN 1 END) AS paid_count,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'incoming'
                ) THEN 1 END) AS completed_count
                FROM customer_advances ca WHERE ca.tenant_id = ? AND ca.branch_id = ? $date_cond";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            $pay_params = [$tenant_id, $branch_id];
            $pay_date_cond = '';
            if ($start_date) { $pay_date_cond .= " AND cap.payment_date >= ?"; $pay_params[] = $start_date; }
            if ($end_date) { $pay_date_cond .= " AND cap.payment_date <= ?"; $pay_params[] = $end_date; }

            // Get incoming payments total (customer paid us) - converted_amount for advance balance tracking
            $inStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cap.converted_amount, cap.amount)), 0) AS total_incoming
                FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                WHERE cap.tenant_id = ? AND cap.branch_id = ? AND cap.type = 'incoming' $pay_date_cond");
            $inStmt->execute($pay_params);
            $totalIncoming = $inStmt->fetchColumn();

            // Get outgoing payments total (we paid supplier) - converted_amount for advance balance tracking
            $outStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cap.converted_amount, cap.amount)), 0) AS total_outgoing
                FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                WHERE cap.tenant_id = ? AND cap.branch_id = ? AND cap.type = 'outgoing' $pay_date_cond");
            $outStmt->execute($pay_params);
            $totalOutgoing = $outStmt->fetchColumn();

            // Get incoming grouped by payment currency (for main account display)
            $inCurStmt = $pdo->prepare("SELECT cap.currency, COALESCE(SUM(cap.amount), 0) AS total_incoming
                FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                WHERE cap.tenant_id = ? AND cap.branch_id = ? AND cap.type = 'incoming' $pay_date_cond
                GROUP BY cap.currency");
            $inCurStmt->execute($pay_params);
            $incoming = $inCurStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get outgoing grouped by payment currency (for main account display)
            $outCurStmt = $pdo->prepare("SELECT cap.currency, COALESCE(SUM(cap.amount), 0) AS total_outgoing
                FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                WHERE cap.tenant_id = ? AND cap.branch_id = ? AND cap.type = 'outgoing' $pay_date_cond
                GROUP BY cap.currency");
            $outCurStmt->execute($pay_params);
            $outgoing = $outCurStmt->fetchAll(PDO::FETCH_ASSOC);

            $summary['incoming'] = $incoming;
            $summary['outgoing'] = $outgoing;

            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        /* ── List all customers with advances ── */
        case 'get_customers':
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            $sql = "SELECT ca.customer_name, ca.customer_phone,
                COUNT(*) AS total_advances,
                SUM(ca.amount) AS total_amount,
                ca.currency,
                SUM(CASE WHEN ca.status = 'pending' THEN ca.amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN ca.status = 'paid_by_agency' THEN ca.amount ELSE 0 END) AS owed_amount,
                SUM(CASE WHEN ca.status = 'completed' THEN ca.amount ELSE 0 END) AS completed_amount,
                COUNT(CASE WHEN ca.status = 'pending' THEN 1 END) AS pending_count,
                COUNT(CASE WHEN ca.status = 'paid_by_agency' THEN 1 END) AS owed_count,
                COUNT(CASE WHEN ca.status = 'completed' THEN 1 END) AS completed_count,
                MIN(ca.advance_date) AS first_advance,
                MAX(ca.advance_date) AS last_advance
                FROM customer_advances ca
                WHERE ca.tenant_id = ? AND ca.branch_id = ?";
            $params = [$tenant_id, $branch_id];

            if ($search) {
                $sql .= " AND (ca.customer_name LIKE ? OR ca.customer_phone LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($status) {
                $sql .= " AND ca.status = ?";
                $params[] = $status;
            }
            if ($start_date) {
                $sql .= " AND ca.advance_date >= ?";
                $params[] = $start_date;
            }
            if ($end_date) {
                $sql .= " AND ca.advance_date <= ?";
                $params[] = $end_date;
            }

            $sql .= " GROUP BY ca.customer_name, ca.customer_phone, ca.currency ORDER BY MAX(ca.advance_date) DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($customers as &$c) {
                $c['total_amount'] = (float) $c['total_amount'];
                $c['pending_amount'] = (float) $c['pending_amount'];
                $c['owed_amount'] = (float) $c['owed_amount'];
                $c['completed_amount'] = (float) $c['completed_amount'];
            }
            unset($c);

            echo json_encode(['success' => true, 'customers' => $customers]);
            break;

        /* ── List all suppliers with advances ── */
        case 'get_suppliers':
            $sql = "SELECT supplier_name,
                COUNT(*) AS total_advances,
                SUM(amount) AS total_amount,
                GROUP_CONCAT(DISTINCT customer_name ORDER BY customer_name SEPARATOR ', ') AS customers
                FROM customer_advances
                WHERE tenant_id = ? AND branch_id = ?
                GROUP BY supplier_name
                ORDER BY supplier_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id]);
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($suppliers as &$s) {
                $s['total_amount'] = (float) $s['total_amount'];
            }
            unset($s);

            echo json_encode(['success' => true, 'suppliers' => $suppliers]);
            break;

        /* ── List all existing customer names ── */
        case 'get_customer_names':
            $search = trim($_GET['search'] ?? '');
            $sql = "SELECT DISTINCT customer_name, customer_phone
                FROM customer_advances
                WHERE tenant_id = ? AND branch_id = ?";
            $params = [$tenant_id, $branch_id];
            if ($search) {
                $sql .= " AND (customer_name LIKE ? OR customer_phone LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            $sql .= " ORDER BY customer_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'customers' => $customers]);
            break;

        /* ── Get customers for a specific supplier ── */
        case 'get_customers_by_supplier':
            $supplierName = trim($_GET['supplier_name'] ?? '');
            if (!$supplierName) throw new Exception('Supplier name is required');

            $sql = "SELECT customer_name, customer_phone,
                COUNT(*) AS total_advances,
                SUM(amount) AS total_amount,
                currency,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_amount
                FROM customer_advances
                WHERE tenant_id = ? AND branch_id = ? AND supplier_name = ?
                GROUP BY customer_name, customer_phone, currency
                ORDER BY customer_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $supplierName]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($customers as &$c) {
                $c['total_amount'] = (float) $c['total_amount'];
                $c['pending_amount'] = (float) $c['pending_amount'];
            }
            unset($c);

            echo json_encode(['success' => true, 'customers' => $customers]);
            break;

        /* ── Get advances for a customer ── */
        case 'get_advances':
            $customerName = trim($_GET['customer_name'] ?? '');
            $supplierName = trim($_GET['supplier_name'] ?? '');
            if (!$customerName) throw new Exception('Customer name is required');

            $sql = "SELECT * FROM customer_advances
                WHERE tenant_id = ? AND branch_id = ? AND customer_name = ?";
            $params = [$tenant_id, $branch_id, $customerName];

            if ($supplierName) {
                $sql .= " AND supplier_name = ?";
                $params[] = $supplierName;
            }

            $sql .= " ORDER BY advance_date DESC, created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($advances as &$a) {
                $a['amount'] = (float) $a['amount'];

                // Get payments for this advance
                $payStmt = $pdo->prepare("SELECT cap.*, u.name AS created_by_name, ma.name AS main_account_name
                    FROM customer_advance_payments cap
                    LEFT JOIN users u ON u.id = cap.created_by
                    LEFT JOIN main_account ma ON ma.id = cap.main_account_id
                    WHERE cap.advance_id = ? ORDER BY cap.payment_date DESC");
                $payStmt->execute([$a['id']]);
                $a['payments'] = $payStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($a['payments'] as &$p) {
                    $p['amount'] = (float) $p['amount'];
                }
                unset($p);
            }
            unset($a);

            echo json_encode(['success' => true, 'advances' => $advances]);
            break;

        /* ── Get payment history for a customer ── */
        case 'get_payments':
            $customerName = trim($_GET['customer_name'] ?? '');
            if (!$customerName) throw new Exception('Customer name is required');

            $sql = "SELECT cap.*, ca.customer_name, ca.supplier_name, u.name AS created_by_name, ma.name AS main_account_name
                FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                LEFT JOIN users u ON u.id = cap.created_by
                LEFT JOIN main_account ma ON ma.id = cap.main_account_id
                WHERE cap.tenant_id = ? AND cap.branch_id = ? AND ca.customer_name = ?
                ORDER BY cap.payment_date DESC, cap.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $customerName]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($payments as &$p) {
                $p['amount'] = (float) $p['amount'];
                $p['exchange_rate'] = isset($p['exchange_rate']) ? (float) $p['exchange_rate'] : 1;
                $p['converted_amount'] = isset($p['converted_amount']) ? ($p['converted_amount'] !== null ? (float) $p['converted_amount'] : null) : null;
            }
            unset($p);

            echo json_encode(['success' => true, 'payments' => $payments]);
            break;

        /* ── Record a new advance ── */
        case 'record_advance':
            $customerName  = trim($_POST['customer_name'] ?? '');
            $customerPhone = trim($_POST['customer_phone'] ?? '');
            $supplierName  = trim($_POST['supplier_name'] ?? '');
            $amount        = (float) ($_POST['amount'] ?? 0);
            $currency      = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $advanceDate   = trim($_POST['advance_date'] ?? date('Y-m-d'));
            $reason        = trim($_POST['reason'] ?? '');

            if (empty($customerName)) throw new Exception('Customer name is required');
            if (empty($supplierName)) throw new Exception('Supplier name is required');
            if ($amount <= 0) throw new Exception('Amount must be greater than 0');
            if (!in_array($currency, ['USD','AFS','EUR','DARHAM','SAR'], true)) throw new Exception('Invalid currency');

            $pdo->beginTransaction();

            $insStmt = $pdo->prepare("INSERT INTO customer_advances
                (tenant_id, branch_id, customer_name, customer_phone, supplier_name, amount, currency, advance_date, reason, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $insStmt->execute([$tenant_id, $branch_id, $customerName, $customerPhone, $supplierName, $amount, $currency, $advanceDate, $reason, $current_user]);
            $newId = (int) $pdo->lastInsertId();

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'record_advance', $newId, '{}',
                json_encode(['customer_name' => $customerName, 'supplier_name' => $supplierName, 'amount' => $amount, 'currency' => $currency], JSON_UNESCAPED_UNICODE));

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Umrah Hawala of {$currency} " . number_format($amount, 2) . " recorded for {$customerName}", 'id' => $newId]);
            break;

        /* ── Mark advance as paid by agency to supplier ── */
        case 'mark_supplier_paid':
            $advanceId = (int) ($_POST['advance_id'] ?? 0);
            if (!$advanceId) throw new Exception('Advance ID is required');

            $stmt = $pdo->prepare("SELECT * FROM customer_advances WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$advanceId, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Advance not found');
            if ($row['status'] !== 'pending') throw new Exception('Only pending hawala can be marked as paid');

            $pdo->beginTransaction();

            $updStmt = $pdo->prepare("UPDATE customer_advances SET status = 'paid_by_agency' WHERE id = ?");
            $updStmt->execute([$advanceId]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'mark_supplier_paid', $advanceId,
                json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
                json_encode(['status' => 'paid_by_agency'], JSON_UNESCAPED_UNICODE));

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Hawala marked as paid to supplier']);
            break;

        /* ── Record incoming or outgoing payment ── */
        case 'record_payment':
            $customerName  = trim($_POST['customer_name'] ?? '');
            $supplierName  = trim($_POST['supplier_name'] ?? '');
            $type          = trim($_POST['type'] ?? '');
            $amount        = (float) ($_POST['amount'] ?? 0);
            $currency      = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $exchangeRate  = (float) ($_POST['exchange_rate'] ?? 1);
            $mainAccountId = (int) ($_POST['main_account_id'] ?? 0);
            $paymentDate   = trim($_POST['payment_date'] ?? date('Y-m-d'));
            $referenceNumber = trim($_POST['reference_number'] ?? '');
            $description   = trim($_POST['description'] ?? '');

            if (empty($customerName)) throw new Exception('Customer name is required');
            if (empty($supplierName)) throw new Exception('Supplier name is required');
            if (!in_array($type, ['incoming', 'outgoing'], true)) throw new Exception('Payment type must be incoming or outgoing');
            if ($amount <= 0) throw new Exception('Amount must be greater than 0');
            if (!in_array($currency, ['USD','AFS','EUR','DARHAM','SAR'], true)) throw new Exception('Invalid currency');
            if (!$mainAccountId) throw new Exception('Main account is required');
            if ($exchangeRate <= 0) $exchangeRate = 1;

            // Find the advance for this customer/supplier - prefer oldest unpaid, fallback to latest
            if ($type === 'outgoing') {
                // For outgoing payments, find oldest advance that hasn't been paid to supplier
                $advStmt = $pdo->prepare("SELECT * FROM customer_advances
                    WHERE tenant_id = ? AND branch_id = ? AND customer_name = ? AND supplier_name = ? AND status = 'pending'
                    ORDER BY advance_date ASC, created_at ASC LIMIT 1");
                $advStmt->execute([$tenant_id, $branch_id, $customerName, $supplierName]);
                $advance = $advStmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$advance) {
                // Fallback: get latest advance
                $advStmt = $pdo->prepare("SELECT * FROM customer_advances
                    WHERE tenant_id = ? AND branch_id = ? AND customer_name = ? AND supplier_name = ?
                    ORDER BY advance_date DESC, created_at DESC LIMIT 1");
                $advStmt->execute([$tenant_id, $branch_id, $customerName, $supplierName]);
                $advance = $advStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$advance) throw new Exception('No hawala found for this customer/supplier combination');

            $advanceId = (int) $advance['id'];
            $advanceCurrency = $advance['currency'];

            // Calculate converted amount in advance currency
            $convertedAmount = null;
            if ($currency !== $advanceCurrency && $exchangeRate > 0) {
                $convertedAmount = $amount * $exchangeRate;
            }

            // Validate main account
            $maStmt = $pdo->prepare("SELECT id FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $maStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            if (!$maStmt->fetch()) throw new Exception('Main account not found');

            $pdo->beginTransaction();

            // Main account uses payment currency
            // If currencies differ: use converted_amount (in payment currency) for main account
            $mainAccountAmount = $amount;
            $mainAccountCurrency = $currency;
            if ($convertedAmount !== null && $currency !== $advanceCurrency) {
                // Amount is in advance currency, converted_amount is in payment currency
                $mainAccountAmount = $convertedAmount;
                $mainAccountCurrency = $currency; // payment currency
            }
            $balanceColumn = getBalanceColumn($mainAccountCurrency);

            if ($type === 'incoming') {
                // Customer pays us: CREDIT main account
                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $updateBalanceStmt->execute([$mainAccountAmount, $mainAccountId, $tenant_id, $branch_id]);
            } else {
                // We pay supplier: DEBIT main account
                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $updateBalanceStmt->execute([$mainAccountAmount, $mainAccountId, $tenant_id, $branch_id]);
            }

            // Get updated balance
            $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $balanceStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            $updatedBalance = $balanceStmt->fetchColumn();

            // Insert payment record with exchange rate
            $insStmt = $pdo->prepare("INSERT INTO customer_advance_payments
                (tenant_id, branch_id, advance_id, type, amount, currency, exchange_rate, converted_amount, main_account_id, payment_date, reference_number, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$tenant_id, $branch_id, $advanceId, $type, $amount, $currency, $exchangeRate, $convertedAmount, $mainAccountId, $paymentDate, $referenceNumber, $description, $current_user]);
            $paymentId = (int) $pdo->lastInsertId();

            // Record main account transaction
            $txnType = $type === 'incoming' ? 'credit' : 'debit';
            $txnDesc = $description ?: ($type === 'incoming'
                ? "Umrah hawala payment received from {$customerName}"
                : "Umrah hawala payment to supplier {$supplierName}");
            $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, receipt, tenant_id, branch_id, created_by, exchange_rate) VALUES (?, ?, ?, ?, ?, ?, 'umrah_hawala', ?, ?, ?, ?, ?, ?)");
            $txnStmt->execute([$mainAccountId, $txnType, $mainAccountAmount, $txnDesc, $updatedBalance, $mainAccountCurrency, $paymentId, $referenceNumber, $tenant_id, $branch_id, $current_user, $exchangeRate]);

            // Update advance status based on payment type
            if ($type === 'outgoing' && $advance['status'] === 'pending') {
                $pdo->prepare("UPDATE customer_advances SET status = 'paid_by_agency' WHERE id = ?")->execute([$advanceId]);
            }
            if ($type === 'incoming') {
                $pdo->prepare("UPDATE customer_advances SET status = 'completed' WHERE id = ?")->execute([$advanceId]);
            }

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'record_payment', $paymentId, '{}',
                json_encode(['customer_name' => $customerName, 'supplier_name' => $supplierName, 'type' => $type, 'amount' => $amount, 'currency' => $currency, 'exchange_rate' => $exchangeRate, 'converted_amount' => $convertedAmount, 'main_account_id' => $mainAccountId], JSON_UNESCAPED_UNICODE));

            $pdo->commit();

            $label = $type === 'incoming' ? 'received from customer' : 'paid to supplier';
            echo json_encode(['success' => true, 'message' => ucfirst($type) . " payment of {$currency} " . number_format($amount, 2) . " {$label}", 'id' => $paymentId]);
            break;

        /* ── Delete advance (only if no payments) ── */
        case 'delete_advance':
            $advanceId = (int) ($_POST['advance_id'] ?? 0);
            if (!$advanceId) throw new Exception('Advance ID is required');

            $stmt = $pdo->prepare("SELECT * FROM customer_advances WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$advanceId, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Advance not found');

            // Check for payments
            $payStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_advance_payments WHERE advance_id = ?");
            $payStmt->execute([$advanceId]);
            if ($payStmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete hawala with existing payments. Delete payments first.');
            }

            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM customer_advances WHERE id = ?");
            $delStmt->execute([$advanceId]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'delete_advance', $advanceId,
                json_encode($row, JSON_UNESCAPED_UNICODE), '{}');

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Hawala deleted']);
            break;

        /* ── Delete payment record ── */
        case 'delete_payment':
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            if (!$paymentId) throw new Exception('Payment ID is required');

            $stmt = $pdo->prepare("SELECT cap.*, ca.status AS advance_status, ca.currency AS advance_currency FROM customer_advance_payments cap
                JOIN customer_advances ca ON ca.id = cap.advance_id
                WHERE cap.id = ? AND cap.tenant_id = ? AND cap.branch_id = ?");
            $stmt->execute([$paymentId, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Payment not found');

            $pdo->beginTransaction();

            // Reverse main account balance
            if ($row['main_account_id']) {
                $maId = (int) $row['main_account_id'];
                // Use converted_amount if currencies differ (same logic as record)
                $reverseAmount = (float) $row['amount'];
                $reverseCurrency = $row['currency'];
                if ($row['converted_amount'] !== null && $row['currency'] !== $row['advance_currency']) {
                    $reverseAmount = (float) $row['converted_amount'];
                    $reverseCurrency = $row['currency']; // payment currency
                }
                $balanceColumn = getBalanceColumn($reverseCurrency);

                if ($row['type'] === 'incoming') {
                    // Was a credit, now DEBIT to reverse
                    $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateBalanceStmt->execute([$reverseAmount, $maId, $tenant_id, $branch_id]);
                } else {
                    // Was a debit, now CREDIT to reverse
                    $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateBalanceStmt->execute([$reverseAmount, $maId, $tenant_id, $branch_id]);
                }

                // Delete the main account transaction
                $delTxnStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah_hawala' AND main_account_id = ? AND tenant_id = ? AND branch_id = ?");
                $delTxnStmt->execute([$paymentId, $maId, $tenant_id, $branch_id]);
            }

            // Revert advance status based on remaining payments
            $checkStmt = $pdo->prepare("SELECT type FROM customer_advance_payments WHERE advance_id = ?");
            $checkStmt->execute([$row['advance_id']]);
            $remainingPayments = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('incoming', $remainingPayments)) {
                $pdo->prepare("UPDATE customer_advances SET status = 'completed' WHERE id = ?")->execute([$row['advance_id']]);
            } elseif (in_array('outgoing', $remainingPayments)) {
                $pdo->prepare("UPDATE customer_advances SET status = 'paid_by_agency' WHERE id = ?")->execute([$row['advance_id']]);
            } else {
                $pdo->prepare("UPDATE customer_advances SET status = 'pending' WHERE id = ?")->execute([$row['advance_id']]);
            }

            // Delete payment record
            $delStmt = $pdo->prepare("DELETE FROM customer_advance_payments WHERE id = ?");
            $delStmt->execute([$paymentId]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'delete_payment', $paymentId,
                json_encode($row, JSON_UNESCAPED_UNICODE), '{}');

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted and balances reversed']);
            break;

        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
