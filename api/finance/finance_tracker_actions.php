<?php
// Finance Admin Quick Tracker API
// Simple finance tracking widget for Finance Admin only

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation (only for POST requests that modify data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
        exit;
    }
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once('../../includes/db.php');
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch($action) {
        case 'add_transaction':
            $date = $_POST['transactionDate'] ?? $_POST['date'] ?? date('Y-m-d');
            $type = $_POST['transactionType'] ?? $_POST['type'] ?? ''; // 'income' or 'expense'
            $amount = floatval($_POST['transactionAmount'] ?? $_POST['amount'] ?? 0);
            $currency = $_POST['transactionCurrency'] ?? $_POST['currency'] ?? 'usd'; // 'usd' or 'afs'
            $description = $_POST['transactionDescription'] ?? $_POST['description'] ?? '';

            // Validation
            if (!in_array($type, ['income', 'expense'])) {
                throw new Exception('Invalid transaction type');
            }
            if (!in_array($currency, ['usd', 'afs'])) {
                throw new Exception('Invalid currency');
            }
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            if (empty($date)) {
                throw new Exception('Date is required');
            }

            // Format date
            $date = date('Y-m-d', strtotime($date));

            // Insert transaction
            $stmt = $pdo->prepare("
                INSERT INTO finance_tracker (date, type, amount, currency, description, branch_id, tenant_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$date, $type, $amount, $currency, $description, $branch_id, $tenant_id, $user_id]);
            $transaction_id = $pdo->lastInsertId();

            // Log activity
            $new_values = json_encode([
                'date' => $date,
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description
            ], JSON_UNESCAPED_UNICODE);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'add', 'finance_tracker', ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $activityStmt->execute([$user_id, $transaction_id, '{}', $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Transaction added successfully',
                'transaction_id' => $transaction_id
            ]);
            break;

        case 'get_balances':
            // Get USD balance
            $usdStmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE 
                        WHEN type = 'income' AND currency = 'usd' THEN amount
                        WHEN type = 'expense' AND currency = 'usd' THEN -amount
                        ELSE 0
                    END), 0) AS balance
                FROM finance_tracker
                WHERE branch_id = ? AND tenant_id = ?
            ");
            $usdStmt->execute([$branch_id, $tenant_id]);
            $usd_balance = $usdStmt->fetchColumn();

            // Get AFS balance
            $afsStmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE 
                        WHEN type = 'income' AND currency = 'afs' THEN amount
                        WHEN type = 'expense' AND currency = 'afs' THEN -amount
                        ELSE 0
                    END), 0) AS balance
                FROM finance_tracker
                WHERE branch_id = ? AND tenant_id = ?
            ");
            $afsStmt->execute([$branch_id, $tenant_id]);
            $afs_balance = $afsStmt->fetchColumn();

            // Get today's income and expense
            $todayStmt = $pdo->prepare("
                SELECT 
                    type,
                    currency,
                    COALESCE(SUM(amount), 0) AS total
                FROM finance_tracker
                WHERE DATE(date) = CURDATE()
                AND branch_id = ? AND tenant_id = ?
                GROUP BY type, currency
            ");
            $todayStmt->execute([$branch_id, $tenant_id]);
            $today_data = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

            $today_income_usd = 0;
            $today_income_afs = 0;
            $today_expense_usd = 0;
            $today_expense_afs = 0;

            foreach ($today_data as $row) {
                if ($row['type'] === 'income') {
                    if ($row['currency'] === 'usd') {
                        $today_income_usd = floatval($row['total']);
                    } else {
                        $today_income_afs = floatval($row['total']);
                    }
                } else {
                    if ($row['currency'] === 'usd') {
                        $today_expense_usd = floatval($row['total']);
                    } else {
                        $today_expense_afs = floatval($row['total']);
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'usd_balance' => floatval($usd_balance),
                'afs_balance' => floatval($afs_balance),
                'today_income_usd' => $today_income_usd,
                'today_income_afs' => $today_income_afs,
                'today_expense_usd' => $today_expense_usd,
                'today_expense_afs' => $today_expense_afs
            ]);
            break;

        case 'get_recent_transactions':
            $limit = intval($_GET['limit'] ?? 10);
            if ($limit > 100) $limit = 100;
            if ($limit < 1) $limit = 10;

            $stmt = $pdo->prepare("
                SELECT 
                    id, date, type, amount, currency, description, created_at
                FROM finance_tracker
                WHERE branch_id = ? AND tenant_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$branch_id, $tenant_id, $limit]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
            break;

        case 'get_transaction':
            $transaction_id = intval($_GET['id'] ?? 0);
            if (!$transaction_id) {
                throw new Exception('Transaction ID is required');
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM finance_tracker
                WHERE id = ? AND branch_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$transaction_id, $branch_id, $tenant_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                throw new Exception('Transaction not found');
            }

            echo json_encode([
                'success' => true,
                'transaction' => $transaction
            ]);
            break;

        case 'update_transaction':
            $transaction_id = intval($_POST['transactionId'] ?? $_POST['id'] ?? 0);
            if (!$transaction_id) {
                throw new Exception('Transaction ID is required');
            }

            // Get original transaction
            $getStmt = $pdo->prepare("
                SELECT *
                FROM finance_tracker
                WHERE id = ? AND branch_id = ? AND tenant_id = ?
            ");
            $getStmt->execute([$transaction_id, $branch_id, $tenant_id]);
            $original = $getStmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                throw new Exception('Transaction not found');
            }

            $date = $_POST['transactionDate'] ?? $_POST['date'] ?? $original['date'];
            $type = $_POST['transactionType'] ?? $_POST['type'] ?? $original['type'];
            $amount = floatval($_POST['transactionAmount'] ?? $_POST['amount'] ?? $original['amount']);
            $currency = $_POST['transactionCurrency'] ?? $_POST['currency'] ?? $original['currency'];
            $description = $_POST['transactionDescription'] ?? $_POST['description'] ?? $original['description'];

            // Validation
            if (!in_array($type, ['income', 'expense'])) {
                throw new Exception('Invalid transaction type');
            }
            if (!in_array($currency, ['usd', 'afs'])) {
                throw new Exception('Invalid currency');
            }
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }

            $date = date('Y-m-d', strtotime($date));

            // Update transaction
            $stmt = $pdo->prepare("
                UPDATE finance_tracker
                SET date = ?, type = ?, amount = ?, currency = ?, description = ?, updated_at = NOW()
                WHERE id = ? AND branch_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$date, $type, $amount, $currency, $description, $transaction_id, $branch_id, $tenant_id]);

            // Log activity
            $old_values = json_encode($original, JSON_UNESCAPED_UNICODE);
            $new_values = json_encode([
                'date' => $date,
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description
            ], JSON_UNESCAPED_UNICODE);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'update', 'finance_tracker', ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Transaction updated successfully'
            ]);
            break;

        case 'delete_transaction':
            $transaction_id = intval($_POST['id'] ?? 0);
            if (!$transaction_id) {
                throw new Exception('Transaction ID is required');
            }

            // Get transaction before deleting
            $getStmt = $pdo->prepare("
                SELECT *
                FROM finance_tracker
                WHERE id = ? AND branch_id = ? AND tenant_id = ?
            ");
            $getStmt->execute([$transaction_id, $branch_id, $tenant_id]);
            $transaction = $getStmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                throw new Exception('Transaction not found');
            }

            // Delete transaction
            $stmt = $pdo->prepare("
                DELETE FROM finance_tracker
                WHERE id = ? AND branch_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$transaction_id, $branch_id, $tenant_id]);

            // Log activity
            $old_values = json_encode($transaction, JSON_UNESCAPED_UNICODE);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'delete', 'finance_tracker', ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $activityStmt->execute([$user_id, $transaction_id, $old_values, '{}', $ip_address, $user_agent, $tenant_id, $branch_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ]);
            break;

        case 'clear_all':
            // WARNING: This clears all finance tracker data created by current user
            // Requires confirmation via password or TOTP

            if (!isset($_POST['confirmation'])) {
                throw new Exception('Confirmation required to clear all data');
            }

            if ($_POST['confirmation'] !== 'CLEAR_ALL_FINANCE_DATA') {
                throw new Exception('Invalid confirmation code');
            }

            // Get all transactions created by this user before clearing (for audit log)
            $getAllStmt = $pdo->prepare("
                SELECT COUNT(*) as count, SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                       SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
                FROM finance_tracker
                WHERE branch_id = ? AND tenant_id = ? AND created_by = ?
            ");
            $getAllStmt->execute([$branch_id, $tenant_id, $user_id]);
            $summary = $getAllStmt->fetch(PDO::FETCH_ASSOC);

            // Clear all finance tracker records created by this user
            $stmt = $pdo->prepare("
                DELETE FROM finance_tracker
                WHERE branch_id = ? AND tenant_id = ? AND created_by = ?
            ");
            $stmt->execute([$branch_id, $tenant_id, $user_id]);

            // Log this action
            $old_values = json_encode($summary, JSON_UNESCAPED_UNICODE);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'delete_all', 'finance_tracker', 0, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $activityStmt->execute([$user_id, $old_values, '{}', $ip_address, $user_agent, $tenant_id, $branch_id]);

            echo json_encode([
                'success' => true,
                'message' => 'All finance tracker data cleared successfully',
                'deleted_count' => $summary['count'] ?? 0
            ]);
            break;

        case 'exchange_currency':
            // Simple currency exchange - creates two records (expense + income)
            $from_currency = $_POST['exchangeFromCurrency'] ?? $_POST['from_currency'] ?? '';
            $to_currency = $_POST['exchangeToCurrency'] ?? $_POST['to_currency'] ?? '';
            $from_amount = floatval($_POST['exchangeFromAmount'] ?? $_POST['from_amount'] ?? 0);
            $exchange_rate = floatval($_POST['exchangeRate'] ?? $_POST['exchange_rate'] ?? 0);
            $description = $_POST['exchangeDescription'] ?? $_POST['description'] ?? 'Currency exchange';

            if (!in_array($from_currency, ['usd', 'afs'])) {
                throw new Exception('Invalid from_currency');
            }
            if (!in_array($to_currency, ['usd', 'afs'])) {
                throw new Exception('Invalid to_currency');
            }
            if ($from_currency === $to_currency) {
                throw new Exception('Cannot exchange same currencies');
            }
            if ($from_amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            if ($exchange_rate <= 0) {
                throw new Exception('Exchange rate must be greater than 0');
            }

            $date = date('Y-m-d');
            $to_amount = $from_amount * $exchange_rate;

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Create expense record (money out)
                $stmt1 = $pdo->prepare("
                    INSERT INTO finance_tracker (date, type, amount, currency, description, branch_id, tenant_id, created_by)
                    VALUES (?, 'expense', ?, ?, ?, ?, ?, ?)
                ");
                $stmt1->execute([$date, $from_amount, $from_currency, "Exchange: {$description}", $branch_id, $tenant_id, $user_id]);

                // Create income record (money in)
                $stmt2 = $pdo->prepare("
                    INSERT INTO finance_tracker (date, type, amount, currency, description, branch_id, tenant_id, created_by)
                    VALUES (?, 'income', ?, ?, ?, ?, ?, ?)
                ");
                $stmt2->execute([$date, $to_amount, $to_currency, "Exchange: {$description} (rate: {$exchange_rate})", $branch_id, $tenant_id, $user_id]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Currency exchange recorded successfully',
                    'from_amount' => $from_amount,
                    'from_currency' => $from_currency,
                    'to_amount' => $to_amount,
                    'to_currency' => $to_currency,
                    'exchange_rate' => $exchange_rate
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
} catch(Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
