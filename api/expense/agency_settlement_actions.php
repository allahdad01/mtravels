<?php
/**
 * Agency Expense Settlement API
 *
 * Actions:
 *   mark_expense       (POST)  Mark an expense as owed by the 2nd agency branch
 *   unmark_expense     (POST)  Remove an expense from agency settlement
 *   record_payment     (POST)  Record a payment received from the 2nd agency branch
 *   delete_payment     (POST)  Delete a pending payment record
 *   get_agency_list    (GET)   List all branches with settlement summaries
 *   get_settlements    (GET)   Get settlement records for a branch (with optional date filter)
 *   get_payments       (GET)   Get payment records for a branch
 *   summary            (GET)   Overall settlement summary for expense page
 *   category_summary   (GET)   Per-category settlement summary within date range
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

// CSRF on POST
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
        VALUES (?, ?, 'agency_expense_settlements', ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt->execute([$userId, $act, $recordId, $oldV, $newV, $ip, $ua, $tenantId, $branchId]);
}

try {
    switch ($action) {

        /* ── Mark an expense as for the 2nd agency branch ── */
        case 'mark_expense':
            $expenseId      = (int) ($_POST['expense_id'] ?? 0);
            $agencyBranchId = (int) ($_POST['agency_branch_id'] ?? 0);
            $agencyName     = trim($_POST['agency_name'] ?? '');
            $amountOwed     = (float) ($_POST['amount_owed'] ?? 0);
            $currency       = strtoupper(trim($_POST['currency'] ?? 'USD'));

            if (!$expenseId)       throw new Exception('Expense ID is required');
            if (!$agencyBranchId && empty($agencyName))  throw new Exception('Branch or agency name is required');
            if ($amountOwed <= 0)  throw new Exception('Amount must be greater than 0');
            if (!in_array($currency, ['USD','AFS','EUR','DARHAM','SAR'], true)) throw new Exception('Invalid currency');

            // Verify expense exists and belongs to current tenant/branch
            $expStmt = $pdo->prepare("SELECT id, amount, currency FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $expStmt->execute([$expenseId, $tenant_id, $branch_id]);
            $expense = $expStmt->fetch(PDO::FETCH_ASSOC);
            if (!$expense) throw new Exception('Expense not found');

            if ($amountOwed > (float)$expense['amount']) {
                throw new Exception('Amount owed cannot exceed the expense amount');
            }

            // Check if already linked
            $dupStmt = $pdo->prepare("SELECT id FROM agency_expense_settlements WHERE expense_id = ? AND tenant_id = ? AND branch_id = ?");
            $dupStmt->execute([$expenseId, $tenant_id, $branch_id]);
            if ($dupStmt->fetch()) {
                throw new Exception('This expense is already linked to a branch');
            }

            // If custom name provided, resolve or use it
            if (empty($agencyBranchId) && !empty($agencyName)) {
                // Check if a settlement with this name already exists to group with
                $existingStmt = $pdo->prepare("SELECT agency_branch_id FROM agency_expense_settlements
                    WHERE tenant_id = ? AND branch_id = ? AND agency_name = ? AND agency_branch_id IS NULL LIMIT 1");
                $existingStmt->execute([$tenant_id, $branch_id, $agencyName]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            }

            $pdo->beginTransaction();

            $insStmt = $pdo->prepare("INSERT INTO agency_expense_settlements
                (tenant_id, branch_id, agency_branch_id, agency_name, expense_id, amount_owed, currency, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insStmt->execute([
                $tenant_id,
                $branch_id,
                $agencyBranchId ?: null,
                $agencyName ?: null,
                $expenseId,
                $amountOwed,
                $currency
            ]);
            $newId = (int) $pdo->lastInsertId();

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'mark_expense', $newId, '{}',
                json_encode(['expense_id' => $expenseId, 'agency_branch_id' => $agencyBranchId ?: null, 'agency_name' => $agencyName, 'amount_owed' => $amountOwed, 'currency' => $currency], JSON_UNESCAPED_UNICODE));

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Expense linked to agency', 'id' => $newId]);
            break;

        /* ── Remove an expense from agency settlement ── */
        case 'unmark_expense':
            $settlementId = (int) ($_POST['settlement_id'] ?? 0);
            if (!$settlementId) throw new Exception('Settlement ID is required');

            $stmt = $pdo->prepare("SELECT * FROM agency_expense_settlements WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$settlementId, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Settlement record not found');

            $pdo->beginTransaction();

            if ($row['status'] !== 'pending') {
                throw new Exception('Cannot unlink an expense that has been partially or fully settled. Remove the payment first.');
            }

            $delStmt = $pdo->prepare("DELETE FROM agency_expense_settlements WHERE id = ?");
            $delStmt->execute([$settlementId]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'unmark_expense', $settlementId,
                json_encode($row, JSON_UNESCAPED_UNICODE), '{}');

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Expense unlinked from branch']);
            break;

        /* ── Record a payment received from an agency ── */
        case 'record_payment':
            $agencyBranchId = (int) ($_POST['agency_branch_id'] ?? 0);
            $agencyName     = trim($_POST['agency_name'] ?? '');
            $amount         = (float) ($_POST['amount'] ?? 0);
            $currency       = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $paymentDate    = trim($_POST['payment_date'] ?? date('Y-m-d'));
            $description    = trim($_POST['description'] ?? '');
            $referenceNumber = trim($_POST['reference_number'] ?? '');
            $mainAccountId  = (int) ($_POST['main_account_id'] ?? 0);
            $exchangeRate   = isset($_POST['exchange_rate']) && $_POST['exchange_rate'] !== '' ? (float) $_POST['exchange_rate'] : null;

            if (!$agencyBranchId && empty($agencyName)) throw new Exception('Branch or agency name is required');
            if ($amount <= 0)     throw new Exception('Amount must be greater than 0');
            if (!in_array($currency, ['USD','AFS','EUR','DARHAM','SAR'], true)) throw new Exception('Invalid currency');
            if (!$mainAccountId)  throw new Exception('Main account is required');

            // Validate main account exists
            $maStmt = $pdo->prepare("SELECT id FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $maStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            if (!$maStmt->fetch()) throw new Exception('Main account not found');

            $pdo->beginTransaction();

            // Determine balance column based on currency
            $balanceColumn = 'usd_balance';
            switch ($currency) {
                case 'AFS':    $balanceColumn = 'afs_balance'; break;
                case 'EUR':    $balanceColumn = 'euro_balance'; break;
                case 'DARHAM': $balanceColumn = 'darham_balance'; break;
                case 'SAR':    $balanceColumn = 'sar_balance'; break;
            }

            // Credit the main account (money received)
            $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateBalanceStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);

            // Get updated balance
            $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $balanceStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            $updatedBalance = $balanceStmt->fetchColumn();

            // Insert payment record
            $insStmt = $pdo->prepare("INSERT INTO agency_payments
                (tenant_id, branch_id, agency_branch_id, agency_name, amount, currency, exchange_rate, payment_date, description, reference_number, main_account_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$tenant_id, $branch_id, $agencyBranchId ?: null, $agencyName ?: null, $amount, $currency, $exchangeRate, $paymentDate, $description, $referenceNumber, $mainAccountId, $current_user]);
            $paymentId = (int) $pdo->lastInsertId();

            // Record main account transaction
            $txnDesc = $description ?: "Payment from agency (Settlement #{$paymentId})";
            $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, receipt, tenant_id, branch_id, created_by) VALUES (?, ?, ?, ?, ?, ?, 'expense', ?, ?, ?, ?, ?)");
            $txnStmt->execute([$mainAccountId, 'credit', $amount, $txnDesc, $updatedBalance, $currency, $paymentId, $referenceNumber, $tenant_id, $branch_id, $current_user]);

            // FIFO: settle oldest pending expenses first
            $settlementAmount = $amount;
            $settlementCurrency = $currency;

            // Determine settlement currency from pending expenses
            $currStmt = $pdo->prepare("SELECT DISTINCT currency FROM agency_expense_settlements
                WHERE tenant_id = ? AND branch_id = ? AND ((agency_branch_id = ?) OR (agency_branch_id IS NULL AND agency_name = ?)) AND status IN ('pending','partial')");
            $currStmt->execute([$tenant_id, $branch_id, $agencyBranchId ?: 0, $agencyName]);
            $pendingCurrencies = $currStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($pendingCurrencies)) {
                $settlementCurrency = $pendingCurrencies[0];

                if ($currency !== $settlementCurrency) {
                    if ($exchangeRate && $exchangeRate > 0) {
                        $currencies = [$currency, $settlementCurrency];
                        $anchor = $settlementCurrency;
                        if (in_array('USD', $currencies)) $anchor = 'USD';
                        elseif (in_array('EUR', $currencies)) $anchor = 'EUR';
                        elseif (in_array('DARHAM', $currencies) || in_array('AED', $currencies)) $anchor = 'AED';
                        elseif (in_array('AFS', $currencies)) $anchor = 'AFS';

                        if ($anchor === $currency) {
                            $settlementAmount = $amount * $exchangeRate;
                        } else {
                            $settlementAmount = $amount / $exchangeRate;
                        }
                    } else {
                        $settlementAmount = 0;
                    }
                }
            }

            $remaining = $settlementAmount;
            $pendingStmt = $pdo->prepare("SELECT id, amount_owed FROM agency_expense_settlements
                WHERE tenant_id = ? AND branch_id = ? AND ((agency_branch_id = ?) OR (agency_branch_id IS NULL AND agency_name = ?)) AND currency = ? AND status IN ('pending','partial')
                ORDER BY created_at ASC");
            $pendingStmt->execute([$tenant_id, $branch_id, $agencyBranchId ?: 0, $agencyName, $settlementCurrency]);
            $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pending as $p) {
                if ($remaining <= 0) break;
                $owed = (float) $p['amount_owed'];
                if ($remaining >= $owed) {
                    $updStmt = $pdo->prepare("UPDATE agency_expense_settlements SET status = 'settled', amount_owed = 0 WHERE id = ?");
                    $updStmt->execute([$p['id']]);
                    $remaining -= $owed;
                } else {
                    $newOwed = $owed - $remaining;
                    $updStmt = $pdo->prepare("UPDATE agency_expense_settlements SET amount_owed = ?, status = 'partial' WHERE id = ?");
                    $updStmt->execute([$newOwed, $p['id']]);
                    $remaining = 0;
                }
            }

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'record_payment', $paymentId, '{}',
                json_encode(['agency_branch_id' => $agencyBranchId ?: null, 'agency_name' => $agencyName, 'amount' => $amount, 'currency' => $currency, 'exchange_rate' => $exchangeRate, 'main_account_id' => $mainAccountId, 'payment_date' => $paymentDate], JSON_UNESCAPED_UNICODE));

            $pdo->commit();

            $settledAmount = $settlementAmount - $remaining;
            $displayAgency = $agencyBranchId ? "branch #{$agencyBranchId}" : "'{$agencyName}'";
            $msg = "Payment of {$currency} " . number_format($amount, 2) . " recorded to main account for {$displayAgency}. " . number_format($settledAmount, 2) . " settled against pending expenses.";
            if ($remaining > 0) {
                $msg .= " " . number_format($remaining, 2) . " has no pending expenses to settle.";
            }

            echo json_encode(['success' => true, 'message' => $msg, 'id' => $paymentId]);
            break;

        /* ── Delete a payment record ── */
        case 'delete_payment':
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            if (!$paymentId) throw new Exception('Payment ID is required');

            $stmt = $pdo->prepare("SELECT * FROM agency_payments WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$paymentId, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Payment not found');

            $pdo->beginTransaction();

            // Reverse main account credit (debit the money back)
            if ($row['main_account_id']) {
                $maId = (int) $row['main_account_id'];
                $reverseAmount = (float) $row['amount'];
                $reverseCurrency = $row['currency'];

                $balanceColumn = 'usd_balance';
                switch ($reverseCurrency) {
                    case 'AFS':    $balanceColumn = 'afs_balance'; break;
                    case 'EUR':    $balanceColumn = 'euro_balance'; break;
                    case 'DARHAM': $balanceColumn = 'darham_balance'; break;
                    case 'SAR':    $balanceColumn = 'sar_balance'; break;
                }

                // Debit the main account
                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $updateBalanceStmt->execute([$reverseAmount, $maId, $tenant_id, $branch_id]);

                // Get updated balance
                $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $balanceStmt->execute([$maId, $tenant_id, $branch_id]);
                $updatedBalance = $balanceStmt->fetchColumn();

                // Delete the main account transaction record
                $delTxnStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND main_account_id = ? AND tenant_id = ? AND branch_id = ?");
                $delTxnStmt->execute([$paymentId, $maId, $tenant_id, $branch_id]);
            }

            // Reverse the FIFO settlement
            $reverseAmount = (float) $row['amount'];

            // If payment had an exchange rate and different currency, convert back to settlement currency
            if ($row['exchange_rate'] && $row['exchange_rate'] > 0) {
                $currStmt = $pdo->prepare("SELECT DISTINCT currency FROM agency_expense_settlements
                    WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id = ? AND status IN ('settled','partial')");
                $currStmt->execute([$tenant_id, $branch_id, $row['agency_branch_id']]);
                $settledCurrencies = $currStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($settledCurrencies)) {
                    $settlementCurrency = $settledCurrencies[0];
                    if ($row['currency'] !== $settlementCurrency) {
                        $currencies = [$row['currency'], $settlementCurrency];
                        $anchor = $settlementCurrency;
                        if (in_array('USD', $currencies)) $anchor = 'USD';
                        elseif (in_array('EUR', $currencies)) $anchor = 'EUR';
                        elseif (in_array('DARHAM', $currencies) || in_array('AED', $currencies)) $anchor = 'AED';
                        elseif (in_array('AFS', $currencies)) $anchor = 'AFS';

                        $exchangeRate = (float) $row['exchange_rate'];
                        if ($anchor === $row['currency']) {
                            $reverseAmount = $reverseAmount * $exchangeRate;
                        } else {
                            $reverseAmount = $reverseAmount / $exchangeRate;
                        }
                    }
                }
            }

            $settledStmt = $pdo->prepare("SELECT id, amount_owed FROM agency_expense_settlements
                WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id = ? AND currency = ? AND status IN ('settled','partial')
                ORDER BY created_at DESC");
            $settledStmt->execute([$tenant_id, $branch_id, $row['agency_branch_id'], $row['currency']]);
            $settled = $settledStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($settled as $s) {
                if ($reverseAmount <= 0) break;
                $currentOwed = (float) $s['amount_owed'];
                if ($currentOwed > 0) {
                    $restoreAmount = min($reverseAmount, $currentOwed);
                    $updStmt = $pdo->prepare("UPDATE agency_expense_settlements SET amount_owed = amount_owed + ?, status = 'pending' WHERE id = ?");
                    $updStmt->execute([$restoreAmount, $s['id']]);
                    $reverseAmount -= $restoreAmount;
                } else {
                    $expAmount = 0;
                    $origStmt = $pdo->prepare("SELECT e.amount FROM agency_expense_settlements aes
                        JOIN expenses e ON e.id = aes.expense_id WHERE aes.id = ?");
                    $origStmt->execute([$s['id']]);
                    $expAmount = (float) $origStmt->fetchColumn();
                    if ($expAmount > 0 && $reverseAmount > 0) {
                        $restoreAmount = min($reverseAmount, $expAmount);
                        $updStmt = $pdo->prepare("UPDATE agency_expense_settlements SET amount_owed = ?, status = 'partial' WHERE id = ?");
                        $updStmt->execute([$restoreAmount, $s['id']]);
                        $reverseAmount -= $restoreAmount;
                    }
                }
            }

            $delStmt = $pdo->prepare("DELETE FROM agency_payments WHERE id = ?");
            $delStmt->execute([$paymentId]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'delete_payment', $paymentId,
                json_encode($row, JSON_UNESCAPED_UNICODE), '{}');

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted, main account reversed, and settlements reversed']);
            break;

        /* ── List all branches/agencies with settlement summaries ── */
        case 'get_agency_list':
            $sql = "SELECT aes.agency_branch_id, COALESCE(br.name, aes.agency_name) AS name,
                COALESCE(SUM(CASE WHEN aes.status IN ('pending','partial') THEN aes.amount_owed ELSE 0 END), 0) AS total_remaining,
                COUNT(CASE WHEN aes.status = 'pending' THEN 1 END) AS pending_count,
                COUNT(CASE WHEN aes.status = 'partial' THEN 1 END) AS partial_count,
                COUNT(CASE WHEN aes.status = 'settled' THEN 1 END) AS settled_count,
                COUNT(*) AS total_expenses
                FROM agency_expense_settlements aes
                LEFT JOIN branches br ON br.id = aes.agency_branch_id
                WHERE aes.tenant_id = ? AND aes.branch_id = ?
                GROUP BY aes.agency_branch_id, aes.agency_name, br.name
                ORDER BY total_remaining DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id]);
            $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payment totals per agency
            foreach ($agencies as &$ag) {
                if ($ag['agency_branch_id']) {
                    $payStmt = $pdo->prepare("SELECT currency, SUM(amount) AS total_paid FROM agency_payments
                        WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id = ? GROUP BY currency");
                    $payStmt->execute([$tenant_id, $branch_id, $ag['agency_branch_id']]);
                    $ag['payments'] = $payStmt->fetchAll(PDO::FETCH_ASSOC);

                    $owedStmt = $pdo->prepare("SELECT currency, SUM(amount_owed) AS remaining FROM agency_expense_settlements
                        WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id = ? AND status IN ('pending','partial')
                        GROUP BY currency");
                    $owedStmt->execute([$tenant_id, $branch_id, $ag['agency_branch_id']]);
                    $ag['remaining_by_currency'] = $owedStmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Custom name: match by agency_name WHERE agency_branch_id IS NULL
                    $payStmt = $pdo->prepare("SELECT currency, SUM(amount) AS total_paid FROM agency_payments
                        WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id IS NULL AND agency_name = ? GROUP BY currency");
                    $payStmt->execute([$tenant_id, $branch_id, $ag['name']]);
                    $ag['payments'] = $payStmt->fetchAll(PDO::FETCH_ASSOC);

                    $owedStmt = $pdo->prepare("SELECT currency, SUM(amount_owed) AS remaining FROM agency_expense_settlements
                        WHERE tenant_id = ? AND branch_id = ? AND agency_branch_id IS NULL AND agency_name = ? AND status IN ('pending','partial')
                        GROUP BY currency");
                    $owedStmt->execute([$tenant_id, $branch_id, $ag['name']]);
                    $ag['remaining_by_currency'] = $owedStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            unset($ag);

            echo json_encode(['success' => true, 'agencies' => $agencies]);
            break;

        /* ── Get settlement records for an agency ── */
        case 'get_settlements':
            $agencyBranchId = (int) ($_GET['agency_branch_id'] ?? 0);
            $agencyName     = trim($_GET['agency_name'] ?? '');
            $startDate      = $_GET['startDate'] ?? '';
            $endDate        = $_GET['endDate'] ?? '';

            $sql = "SELECT aes.*, e.date AS expense_date, e.description AS expense_description, e.amount AS expense_amount,
                    ec.name AS category_name, esc.name AS sub_category_name,
                    COALESCE(br.name, aes.agency_name) AS agency_display_name
                    FROM agency_expense_settlements aes
                    JOIN expenses e ON e.id = aes.expense_id
                    LEFT JOIN branches br ON br.id = aes.agency_branch_id
                    LEFT JOIN expense_categories ec ON e.category_id = ec.id
                    LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
                    WHERE aes.tenant_id = ? AND aes.branch_id = ?";
            $params = [$tenant_id, $branch_id];

            if ($agencyBranchId) {
                $sql .= " AND aes.agency_branch_id = ?";
                $params[] = $agencyBranchId;
            } elseif ($agencyName) {
                $sql .= " AND aes.agency_branch_id IS NULL AND aes.agency_name = ?";
                $params[] = $agencyName;
            } else {
                throw new Exception('Branch ID or agency name is required');
            }

            if ($startDate && $endDate) {
                $sql .= " AND e.date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }
            $sql .= " ORDER BY e.date DESC, aes.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($settlements as &$s) {
                $s['amount_owed']   = (float) $s['amount_owed'];
                $s['expense_amount'] = (float) $s['expense_amount'];
            }
            unset($s);

            echo json_encode(['success' => true, 'settlements' => $settlements]);
            break;

        /* ── Get payment records for an agency ── */
        case 'get_payments':
            $agencyBranchId = (int) ($_GET['agency_branch_id'] ?? 0);
            $agencyName     = trim($_GET['agency_name'] ?? '');

            $sql = "SELECT ap.*, u.name AS created_by_name, ma.name AS main_account_name
                    FROM agency_payments ap
                    LEFT JOIN users u ON u.id = ap.created_by
                    LEFT JOIN main_account ma ON ma.id = ap.main_account_id
                    WHERE ap.tenant_id = ? AND ap.branch_id = ?";
            $params = [$tenant_id, $branch_id];

            if ($agencyBranchId) {
                $sql .= " AND ap.agency_branch_id = ?";
                $params[] = $agencyBranchId;
            } elseif ($agencyName) {
                $sql .= " AND ap.agency_branch_id IS NULL AND ap.agency_name = ?";
                $params[] = $agencyName;
            } else {
                throw new Exception('Branch ID or agency name is required');
            }

            $sql .= " ORDER BY ap.payment_date DESC, ap.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($payments as &$p) {
                $p['amount'] = (float) $p['amount'];
                $p['exchange_rate'] = $p['exchange_rate'] !== null ? (float) $p['exchange_rate'] : null;
            }
            unset($p);

            echo json_encode(['success' => true, 'payments' => $payments]);
            break;

        /* ── Summary for expense page (per-branch totals) ── */
        case 'summary':
            $sql = "SELECT aes.agency_branch_id, br.name AS branch_name, aes.currency,
                SUM(CASE WHEN aes.status IN ('pending','partial') THEN aes.amount_owed ELSE 0 END) AS remaining,
                COUNT(CASE WHEN aes.status = 'pending' THEN 1 END) AS pending_count,
                COUNT(CASE WHEN aes.status = 'partial' THEN 1 END) AS partial_count,
                COUNT(CASE WHEN aes.status = 'settled' THEN 1 END) AS settled_count
                FROM agency_expense_settlements aes
                JOIN branches br ON br.id = aes.agency_branch_id
                WHERE aes.tenant_id = ? AND aes.branch_id = ?
                GROUP BY aes.agency_branch_id, br.name, aes.currency";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id]);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        /* ── Per-category settlement summary within date range ── */
        case 'category_summary':
            $startDate = $_GET['startDate'] ?? date('Y-m-01');
            $endDate   = $_GET['endDate'] ?? date('Y-m-t');
            $agencyBranchId = (int) ($_GET['agency_branch_id'] ?? 0);

            $sql = "SELECT e.category_id, ec.name AS category_name,
                SUM(aes.amount_owed) AS total_owed_initial,
                SUM(CASE WHEN aes.status IN ('pending','partial') THEN aes.amount_owed ELSE 0 END) AS total_remaining,
                COUNT(*) AS total_expenses,
                COUNT(CASE WHEN aes.status = 'settled' THEN 1 END) AS settled_count
                FROM agency_expense_settlements aes
                JOIN expenses e ON e.id = aes.expense_id
                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                WHERE aes.tenant_id = ? AND aes.branch_id = ?
                AND e.date BETWEEN ? AND ?";
            $params = [$tenant_id, $branch_id, $startDate, $endDate];

            if ($agencyBranchId) {
                $sql .= " AND aes.agency_branch_id = ?";
                $params[] = $agencyBranchId;
            }
            $sql .= " GROUP BY e.category_id, ec.name ORDER BY ec.name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $catSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($catSummary as &$cs) {
                $cs['total_owed_initial'] = (float) $cs['total_owed_initial'];
                $cs['total_remaining']    = (float) $cs['total_remaining'];
            }
            unset($cs);

            echo json_encode(['success' => true, 'category_summary' => $catSummary]);
            break;

        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
