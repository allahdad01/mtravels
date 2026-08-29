<?php
/**
 * owner_funds.php
 * API endpoint for admin-to-owner payment tracking.
 *
 * Actions:
 *   create   – Deduct from main account, record owner fund transaction
 *   list     – List all owner fund payments (with filters)
 *   summary  – Total paid per currency
 *   delete   – Delete a pending owner fund payment (reverse the deduction)
 *   get      – Get single transaction for editing
 *   update   – Update a transaction (adjust balances)
 *   statement – Get all transactions for a specific owner (for printing statement)
 */
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('finance.owner_funds');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$current_user = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {

        /* ────────────────────── CREATE ────────────────────── */
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            if (!verify_csrf_token()) {
                http_response_code(403);
                throw new Exception('Security validation failed.');
            }

            $mainAccountId = (int) ($_POST['main_account_id'] ?? 0);
            $amount        = (float) ($_POST['amount'] ?? 0);
            $currency      = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $ownerId       = trim($_POST['owner_id'] ?? '');
            $ownerName     = trim($_POST['owner_name'] ?? '');
            $description   = trim($_POST['description'] ?? '');
            $receiptNumber = trim($_POST['receipt_number'] ?? '');

            if ($mainAccountId <= 0) throw new Exception('Main account is required');
            if ($amount <= 0)         throw new Exception('Amount must be greater than zero');
            if (empty($description))  throw new Exception('Purpose / reason is required');

            // Determine if custom name or system user
            $isCustomOwner = ($ownerId === 'custom');
            if ($isCustomOwner) {
                if (empty($ownerName)) throw new Exception('Owner name is required');
                $resolvedOwnerId = null;
                $resolvedOwnerName = $ownerName;
            } else {
                $ownerIdInt = (int) $ownerId;
                if ($ownerIdInt <= 0) throw new Exception('Owner is required');
                // Verify owner exists
                $ownerStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND tenant_id = ?");
                $ownerStmt->execute([$ownerIdInt, $tenant_id]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$owner) throw new Exception('Owner not found');
                $resolvedOwnerId = $ownerIdInt;
                $resolvedOwnerName = $owner['name'];
            }

            $allowedCurrencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
            if (!in_array($currency, $allowedCurrencies, true)) {
                throw new Exception('Invalid currency');
            }

            // Verify main account exists and is active
            $acctStmt = $pdo->prepare("SELECT id FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'");
            $acctStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            if (!$acctStmt->fetch()) throw new Exception('Main account not found or inactive');

            // Determine balance column
            $balanceColumn = match($currency) {
                'AFS'     => 'afs_balance',
                'EUR'     => 'euro_balance',
                'DARHAM'  => 'darham_balance',
                'SAR'     => 'sar_balance',
                default   => 'usd_balance',
            };

            $pdo->beginTransaction();

            // Check sufficient balance
            $balStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $balStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            $currentBalance = (float) $balStmt->fetchColumn();

            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance. Available: " . number_format($currentBalance, 2) . " $currency");
            }

            // Deduct from main account
            $newBalance = $currentBalance - $amount;
            $updateStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateStmt->execute([$newBalance, $mainAccountId, $tenant_id, $branch_id]);

            // Insert main_account_transactions record
            // For custom names, store the name in description with a prefix
            $txnDescription = $isCustomOwner ? "[Owner: $resolvedOwnerName] $description" : $description;
            $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions 
                (main_account_id, type, amount, balance, currency, description, transaction_of, reference_id, receipt, tenant_id, branch_id, created_by)
                VALUES (?, 'debit', ?, ?, ?, ?, 'owner_fund', ?, ?, ?, ?, ?)");
            $txnStmt->execute([
                $mainAccountId,
                $amount,
                $newBalance,
                $currency,
                $txnDescription,
                $resolvedOwnerId,
                $receiptNumber ?: null,
                $tenant_id,
                $branch_id,
                $current_user
            ]);
            $transactionId = $pdo->lastInsertId();

            // Activity log
            $user_id = $current_user;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $new_values = json_encode([
                'main_account_id' => $mainAccountId,
                'amount' => $amount,
                'currency' => $currency,
                'owner_id' => $resolvedOwnerId,
                'owner_name' => $resolvedOwnerName,
                'description' => $description,
                'receipt_number' => $receiptNumber,
            ], JSON_UNESCAPED_UNICODE);

            $actStmt = $pdo->prepare("INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'add', 'main_account_transactions', ?, '{}', ?, ?, ?, NOW(), ?, ?)");
            $actStmt->execute([$user_id, $transactionId, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

            // Notification for admin/finance
            $notifMsg = sprintf(
                "Owner payment: %s %s paid to %s by %s. Purpose: %s",
                $currency, number_format($amount, 2), $resolvedOwnerName,
                ($_SESSION['name'] ?? 'Admin'), $description
            );
            $notifStmt = $pdo->prepare("INSERT INTO notifications 
                (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
                VALUES (?, 'owner_fund', ?, 'Unread', NOW(), ?, ?)");
            $notifStmt->execute([$transactionId, $notifMsg, $tenant_id, $branch_id]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'transaction_id' => $transactionId,
            ]);
            break;

        /* ────────────────────── LIST ────────────────────── */
        case 'list':
            $startDate = $_GET['start_date'] ?? '';
            $endDate   = $_GET['end_date'] ?? '';
            $currencyF = $_GET['currency'] ?? '';
            $search    = $_GET['search'] ?? '';
            $page      = max(1, (int) ($_GET['page'] ?? 1));
            $perPage   = 20;
            $offset    = ($page - 1) * $perPage;

            $where  = "WHERE mat.tenant_id = ? AND mat.branch_id = ? AND mat.transaction_of = 'owner_fund'";
            $params = [$tenant_id, $branch_id];

            if (!empty($startDate)) {
                $where .= " AND DATE(mat.created_at) >= ?";
                $params[] = $startDate;
            }
            if (!empty($endDate)) {
                $where .= " AND DATE(mat.created_at) <= ?";
                $params[] = $endDate;
            }
            if (!empty($currencyF) && in_array($currencyF, ['USD','AFS','EUR','DARHAM','SAR'], true)) {
                $where .= " AND mat.currency = ?";
                $params[] = $currencyF;
            }
            if (!empty($search)) {
                $where .= " AND (mat.description LIKE ? OR u_owner.name LIKE ? OR mat.receipt LIKE ? OR mat.description LIKE ?)";
                $s = "%$search%";
                $params[] = $s;
                $params[] = $s;
                $params[] = $s;
                $params[] = "%[Owner: $search%";
            }

            // Count
            $countSql = "SELECT COUNT(*) FROM main_account_transactions mat LEFT JOIN users u_owner ON u_owner.id = mat.reference_id $where";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $totalPages = max(1, ceil($total / $perPage));

            // Fetch
            $sql = "SELECT mat.id, mat.amount, mat.balance, mat.currency, mat.description,
                    mat.receipt, mat.created_at, mat.reference_id AS owner_id,
                    CASE 
                        WHEN mat.reference_id IS NOT NULL THEN u_owner.name
                        WHEN mat.description LIKE '[Owner: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(mat.description, '] ', 1), '[Owner: ', -1)
                        ELSE '—'
                    END AS owner_name,
                    CASE 
                        WHEN mat.description LIKE '[Owner: %' THEN TRIM(SUBSTRING(mat.description, LOCATE('] ', mat.description) + 2))
                        ELSE mat.description
                    END AS purpose,
                    u_admin.name AS admin_name,
                    ma.name AS account_name
                FROM main_account_transactions mat
                LEFT JOIN users u_owner ON u_owner.id = mat.reference_id
                LEFT JOIN users u_admin ON u_admin.id = mat.created_by
                LEFT JOIN main_account ma ON ma.id = mat.main_account_id
                $where
                ORDER BY mat.created_at DESC
                LIMIT $perPage OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $total,
                    'total_pages' => $totalPages,
                ],
            ]);
            break;

        /* ────────────────────── SUMMARY ────────────────────── */
        case 'summary':
            $sql = "SELECT mat.currency, 
                    COUNT(*) AS total_count,
                    SUM(mat.amount) AS total_amount
                FROM main_account_transactions mat
                WHERE mat.tenant_id = ? AND mat.branch_id = ? AND mat.transaction_of = 'owner_fund'
                GROUP BY mat.currency
                ORDER BY FIELD(mat.currency, 'USD','AFS','EUR','DARHAM','SAR')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $summary = [];
            foreach ($rows as $r) {
                $summary[$r['currency']] = [
                    'total_count'  => (int) $r['total_count'],
                    'total_amount' => (float) $r['total_amount'],
                ];
            }

            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        /* ────────────────────── DELETE ────────────────────── */
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            if (!verify_csrf_token()) {
                http_response_code(403);
                throw new Exception('Security validation failed.');
            }

            $txnId = (int) ($_POST['id'] ?? 0);
            if ($txnId <= 0) throw new Exception('Invalid transaction ID');

            // Fetch the transaction
            $fetchStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? AND transaction_of = 'owner_fund'");
            $fetchStmt->execute([$txnId, $tenant_id, $branch_id]);
            $txn = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$txn) throw new Exception('Transaction not found');

            // Determine balance column
            $balanceColumn = match($txn['currency']) {
                'AFS'     => 'afs_balance',
                'EUR'     => 'euro_balance',
                'DARHAM'  => 'darham_balance',
                'SAR'     => 'sar_balance',
                default   => 'usd_balance',
            };

            $pdo->beginTransaction();

            // Restore balance to main account
            $restoreStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $restoreStmt->execute([$txn['amount'], $txn['main_account_id'], $tenant_id, $branch_id]);

            // Delete the transaction
            $delStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $delStmt->execute([$txnId, $tenant_id, $branch_id]);

            // Recalculate subsequent balance column values on the same account + currency
            // All transactions after the deleted one need their balance corrected
            $fixStmt = $pdo->prepare("UPDATE main_account_transactions 
                SET balance = balance + ? 
                WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                  AND created_at > ? AND id > ?");
            $fixStmt->execute([
                $txn['amount'],
                $txn['main_account_id'],
                $txn['currency'],
                $tenant_id,
                $branch_id,
                $txn['created_at'],
                $txnId
            ]);

            // Activity log
            $user_id = $current_user;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $old_values = json_encode($txn, JSON_UNESCAPED_UNICODE);
            $actStmt = $pdo->prepare("INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'delete', 'main_account_transactions', ?, ?, '{}', ?, ?, NOW(), ?, ?)");
            $actStmt->execute([$user_id, $txnId, $old_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Payment deleted and balance restored']);
            break;

        /* ────────────────────── GET ────────────────────── */
        case 'get':
            $txnId = (int) ($_GET['id'] ?? 0);
            if ($txnId <= 0) throw new Exception('Invalid transaction ID');

            $sql = "SELECT mat.*,
                    CASE 
                        WHEN mat.reference_id IS NOT NULL THEN u_owner.name
                        WHEN mat.description LIKE '[Owner: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(mat.description, '] ', 1), '[Owner: ', -1)
                        ELSE '—'
                    END AS owner_name,
                    CASE 
                        WHEN mat.description LIKE '[Owner: %' THEN TRIM(SUBSTRING(mat.description, LOCATE('] ', mat.description) + 2))
                        ELSE mat.description
                    END AS purpose,
                    u_admin.name AS admin_name,
                    ma.name AS account_name
                FROM main_account_transactions mat
                LEFT JOIN users u_owner ON u_owner.id = mat.reference_id
                LEFT JOIN users u_admin ON u_admin.id = mat.created_by
                LEFT JOIN main_account ma ON ma.id = mat.main_account_id
                WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ? AND mat.transaction_of = 'owner_fund'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$txnId, $tenant_id, $branch_id]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$txn) throw new Exception('Payment not found');

            echo json_encode(['success' => true, 'transaction' => $txn]);
            break;

        /* ────────────────────── UPDATE ────────────────────── */
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            if (!verify_csrf_token()) {
                http_response_code(403);
                throw new Exception('Security validation failed.');
            }

            $txnId        = (int) ($_POST['id'] ?? 0);
            $mainAccountId = (int) ($_POST['main_account_id'] ?? 0);
            $newAmount    = (float) ($_POST['amount'] ?? 0);
            $currency     = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $ownerId      = trim($_POST['owner_id'] ?? '');
            $ownerName    = trim($_POST['owner_name'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $receiptNumber = trim($_POST['receipt_number'] ?? '');

            if ($txnId <= 0)             throw new Exception('Invalid transaction ID');
            if ($mainAccountId <= 0)     throw new Exception('Main account is required');
            if ($newAmount <= 0)         throw new Exception('Amount must be greater than zero');
            if (empty($description))     throw new Exception('Purpose / reason is required');

            // Fetch existing transaction
            $fetchStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? AND transaction_of = 'owner_fund'");
            $fetchStmt->execute([$txnId, $tenant_id, $branch_id]);
            $oldTxn = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldTxn) throw new Exception('Payment not found');

            // Determine owner
            $isCustomOwner = ($ownerId === 'custom');
            if ($isCustomOwner) {
                if (empty($ownerName)) throw new Exception('Owner name is required');
                $resolvedOwnerId = null;
                $resolvedOwnerName = $ownerName;
            } else {
                $ownerIdInt = (int) $ownerId;
                if ($ownerIdInt <= 0) throw new Exception('Owner is required');
                $ownerStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND tenant_id = ?");
                $ownerStmt->execute([$ownerIdInt, $tenant_id]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$owner) throw new Exception('Owner not found');
                $resolvedOwnerId = $ownerIdInt;
                $resolvedOwnerName = $owner['name'];
            }

            $allowedCurrencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
            if (!in_array($currency, $allowedCurrencies, true)) {
                throw new Exception('Invalid currency');
            }

            // Verify main account
            $acctStmt = $pdo->prepare("SELECT id FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'");
            $acctStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            if (!$acctStmt->fetch()) throw new Exception('Main account not found or inactive');

            $balanceColumn = match($currency) {
                'AFS'     => 'afs_balance',
                'EUR'     => 'euro_balance',
                'DARHAM'  => 'darham_balance',
                'SAR'     => 'sar_balance',
                default   => 'usd_balance',
            };

            $pdo->beginTransaction();

            // If amount or account changed, adjust balances
            $oldAmount = (float) $oldTxn['amount'];
            $amountDiff = $newAmount - $oldAmount;
            $accountChanged = ((int) $oldTxn['main_account_id'] !== $mainAccountId);
            $currencyChanged = ($oldTxn['currency'] !== $currency);

            if ($accountChanged || $currencyChanged) {
                // Restore old account
                $oldBalanceCol = match($oldTxn['currency']) {
                    'AFS'     => 'afs_balance',
                    'EUR'     => 'euro_balance',
                    'DARHAM'  => 'darham_balance',
                    'SAR'     => 'sar_balance',
                    default   => 'usd_balance',
                };
                $restoreStmt = $pdo->prepare("UPDATE main_account SET $oldBalanceCol = $oldBalanceCol + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $restoreStmt->execute([$oldAmount, $oldTxn['main_account_id'], $tenant_id, $branch_id]);

                // Deduct from new account
                $deductStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $deductStmt->execute([$newAmount, $mainAccountId, $tenant_id, $branch_id]);
            } elseif ($amountDiff != 0) {
                // Same account, just adjust by difference
                $adjustStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $adjustStmt->execute([$amountDiff, $mainAccountId, $tenant_id, $branch_id]);
            }

            // Get updated balance
            $balStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $balStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
            $newBalance = (float) $balStmt->fetchColumn();

            // Build description with owner prefix if custom
            $txnDescription = $isCustomOwner ? "[Owner: $resolvedOwnerName] $description" : $description;

            // Update the transaction
            $updateStmt = $pdo->prepare("UPDATE main_account_transactions SET 
                main_account_id = ?, amount = ?, balance = ?, currency = ?, description = ?,
                reference_id = ?, receipt = ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateStmt->execute([
                $mainAccountId, $newAmount, $newBalance, $currency,
                $txnDescription, $resolvedOwnerId, $receiptNumber ?: null,
                $txnId, $tenant_id, $branch_id
            ]);

            // Recalculate subsequent balance column values if amount or account changed
            if ($amountDiff != 0 || $accountChanged || $currencyChanged) {
                // For same account + currency: adjust subsequent balances by the difference
                // For account/currency change: fix old account's subsequent balances, then new account's
                if ($accountChanged || $currencyChanged) {
                    // Fix old account/currency subsequent balances (restore old amount)
                    $oldBalanceCol = match($oldTxn['currency']) {
                        'AFS'     => 'afs_balance',
                        'EUR'     => 'euro_balance',
                        'DARHAM'  => 'darham_balance',
                        'SAR'     => 'sar_balance',
                        default   => 'usd_balance',
                    };
                    $fixOldStmt = $pdo->prepare("UPDATE main_account_transactions 
                        SET balance = balance + ? 
                        WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                          AND created_at > ? AND id > ?");
                    $fixOldStmt->execute([
                        $oldAmount, $oldTxn['main_account_id'], $oldTxn['currency'],
                        $tenant_id, $branch_id, $oldTxn['created_at'], $txnId
                    ]);
                    // Fix new account/currency subsequent balances (deduct new amount)
                    $fixNewStmt = $pdo->prepare("UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                          AND created_at >= ? AND id > ?");
                    $fixNewStmt->execute([
                        $newAmount, $mainAccountId, $currency,
                        $tenant_id, $branch_id, $oldTxn['created_at'], $txnId
                    ]);
                } elseif ($amountDiff != 0) {
                    // Same account, same currency — adjust by difference
                    $fixStmt = $pdo->prepare("UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                          AND created_at > ? AND id > ?");
                    $fixStmt->execute([
                        $amountDiff, $mainAccountId, $currency,
                        $tenant_id, $branch_id, $oldTxn['created_at'], $txnId
                    ]);
                }
            }

            // Activity log
            $old_values = json_encode($oldTxn, JSON_UNESCAPED_UNICODE);
            $new_values = json_encode([
                'main_account_id' => $mainAccountId,
                'amount' => $newAmount,
                'currency' => $currency,
                'owner_id' => $resolvedOwnerId,
                'owner_name' => $resolvedOwnerName,
                'description' => $description,
                'receipt_number' => $receiptNumber,
            ], JSON_UNESCAPED_UNICODE);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $actStmt = $pdo->prepare("INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'update', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $actStmt->execute([$current_user, $txnId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
            break;

        /* ────────────────────── STATEMENT ────────────────────── */
        case 'statement':
            $ownerId = (int) ($_GET['owner_id'] ?? 0);
            $customName = trim($_GET['custom_name'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo   = trim($_GET['date_to'] ?? '');

            // owner_id=0 with custom_name means custom owner
            if ($ownerId <= 0 && $customName === '') throw new Exception('Owner is required');

            // Get owner info (system user or custom)
            $owner = ['id' => 0, 'name' => $customName];
            if ($ownerId > 0) {
                $ownerStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND tenant_id = ?");
                $ownerStmt->execute([$ownerId, $tenant_id]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$owner) throw new Exception('Owner not found');
            }

            // Build query
            $where = ["tenant_id = ?", "branch_id = ?", "transaction_of = 'owner_fund'"];
            $params = [$tenant_id, $branch_id];

            if ($ownerId > 0) {
                // System user: match by reference_id OR custom name in description
                $where[] = "(reference_id = ? OR description LIKE ?)";
                $params[] = $ownerId;
                $params[] = "%[Owner: {$owner['name']}]%";
            } else {
                // Custom name only: match description pattern
                $where[] = "description LIKE ?";
                $params[] = "%[Owner: $customName]%";
            }

            if ($dateFrom !== '') {
                $where[] = "created_at >= ?";
                $params[] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $where[] = "created_at <= ?";
                $params[] = $dateTo . ' 23:59:59';
            }

            $whereSql = implode(' AND ', $where);
            $txnStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE $whereSql ORDER BY created_at ASC");
            $txnStmt->execute($params);
            $transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by currency
            $grouped = [];
            foreach ($transactions as $t) {
                $grouped[$t['currency']][] = $t;
            }

            // Totals per currency
            $totals = [];
            foreach ($grouped as $cur => $txns) {
                $totals[$cur] = array_sum(array_column($txns, 'amount'));
            }

            echo json_encode([
                'success'      => true,
                'owner'        => $owner,
                'transactions' => $transactions,
                'totals'       => $totals,
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
