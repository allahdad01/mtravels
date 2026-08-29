<?php
session_start();

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '../../includes/db.php';

$mode = $_POST['mode'] ?? 'scan';
$targetTenant = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
$targetEntity = !empty($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
$targetCurrency = !empty($_POST['entity_currency']) ? $_POST['entity_currency'] : null;
$currencyFilter = !empty($_POST['currency_filter']) ? $_POST['currency_filter'] : null;
$effectiveCurrency = $targetCurrency ?: $currencyFilter;
$entityType = $_POST['entity_type'] ?? 'client';

try {
    if ($entityType === 'client') {
        $results = processClients($pdo, $targetTenant, $targetEntity, $effectiveCurrency, $mode);
    } elseif ($entityType === 'supplier') {
        $results = processSuppliers($pdo, $targetTenant, $targetEntity, $mode);
    } elseif ($entityType === 'main_account') {
        $results = processMainAccounts($pdo, $targetTenant, $targetEntity, $effectiveCurrency, $mode);
    } else {
        exit(json_encode(['success' => false, 'message' => 'Invalid entity type']));
    }

    exit(json_encode([
        'success' => true,
        'mode' => $mode,
        'entity_type' => $entityType,
        'fixed' => $results['fixed'],
        'unchanged' => $results['unchanged'],
        'clients' => $results['items'],
    ]));

} catch (Exception $e) {
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}

function processClients($pdo, $tenantId, $targetClient, $targetCurrency, $mode) {
    $where = ['ct.tenant_id = ?', "c.client_type = 'regular'"];
    $params = [$tenantId];
    if ($targetClient) { $where[] = 'ct.client_id = ?'; $params[] = $targetClient; }
    if ($targetCurrency) { $where[] = 'ct.currency = ?'; $params[] = $targetCurrency; }
    $whereClause = implode(' AND ', $where);

    $clientStmt = $pdo->prepare("
        SELECT DISTINCT ct.client_id, ct.currency
        FROM client_transactions ct
        JOIN clients c ON c.id = ct.client_id AND c.tenant_id = ct.tenant_id
        WHERE {$whereClause}
        ORDER BY ct.client_id, ct.currency
    ");
    $clientStmt->execute($params);
    $entities = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $fixed = 0;
    $unchanged = 0;

    foreach ($entities as $entity) {
        $id = $entity['client_id'];
        $currency = $entity['currency'];

        $txnStmt = $pdo->prepare("
            SELECT id, amount, type, balance, description
            FROM client_transactions
            WHERE client_id = ? AND currency = ? AND tenant_id = ?
            ORDER BY id ASC
        ");
        $txnStmt->execute([$id, $currency, $tenantId]);
        $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($txns)) continue;

        $balanceField = strtoupper($currency) === 'USD' ? 'usd_balance' : 'afs_balance';
        $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ?");
        $balStmt->execute([$id, $tenantId]);
        $masterBalance = (float)$balStmt->fetchColumn();

        $nameStmt = $pdo->prepare("SELECT name FROM clients WHERE id = ? AND tenant_id = ?");
        $nameStmt->execute([$id, $tenantId]);
        $name = $nameStmt->fetchColumn() ?: "Client #{$id}";

        $result = recalcRunningBalance($txns, $masterBalance, $name, $id, $currency);

        if ($mode === 'apply' && ($result['needs_update'] || $result['master_needs_update'])) {
            $pdo->beginTransaction();
            try {
                if (!empty($result['txn_fixes'])) {
                    $updStmt = $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                    foreach ($result['txn_fixes'] as $ch) {
                        $updStmt->execute([$ch['new_balance'], $ch['id'], $tenantId]);
                    }
                }
                if ($result['master_needs_update']) {
                    $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$result['new_master'], $id, $tenantId]);
                }
                $pdo->commit();
                $result['applied'] = true;
                $fixed++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $result['error'] = $e->getMessage();
            }
        } else {
            if ($result['needs_update'] || $result['master_needs_update']) {
                $fixed++;
            } else {
                $unchanged++;
            }
        }

        $items[] = $result;
    }

    return ['items' => $items, 'fixed' => $fixed, 'unchanged' => $unchanged];
}

function processSuppliers($pdo, $tenantId, $targetSupplier, $mode) {
    $where = ['st.tenant_id = ?'];
    $params = [$tenantId];
    if ($targetSupplier) { $where[] = 'st.supplier_id = ?'; $params[] = $targetSupplier; }
    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT DISTINCT st.supplier_id
        FROM supplier_transactions st
        JOIN suppliers s ON s.id = st.supplier_id AND s.tenant_id = st.tenant_id
        WHERE {$whereClause}
        ORDER BY st.supplier_id
    ");
    $stmt->execute($params);
    $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $fixed = 0;
    $unchanged = 0;

    foreach ($entities as $entity) {
        $id = $entity['supplier_id'];

        $txnStmt = $pdo->prepare("
            SELECT id, amount, transaction_type AS type, balance, remarks AS description
            FROM supplier_transactions
            WHERE supplier_id = ? AND tenant_id = ?
            ORDER BY id ASC
        ");
        $txnStmt->execute([$id, $tenantId]);
        $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($txns)) continue;

        $balStmt = $pdo->prepare("SELECT balance, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $balStmt->execute([$id, $tenantId]);
        $row = $balStmt->fetch(PDO::FETCH_ASSOC);
        $masterBalance = (float)$row['balance'];
        $currency = $row['currency'] ?? 'USD';

        $nameStmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ? AND tenant_id = ?");
        $nameStmt->execute([$id, $tenantId]);
        $name = $nameStmt->fetchColumn() ?: "Supplier #{$id}";

        $result = recalcRunningBalance($txns, $masterBalance, $name, $id, $currency);

        if ($mode === 'apply' && ($result['needs_update'] || $result['master_needs_update'])) {
            $pdo->beginTransaction();
            try {
                if (!empty($result['txn_fixes'])) {
                    $updStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                    foreach ($result['txn_fixes'] as $ch) {
                        $updStmt->execute([$ch['new_balance'], $ch['id'], $tenantId]);
                    }
                }
                if ($result['master_needs_update']) {
                    $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$result['new_master'], $id, $tenantId]);
                }
                $pdo->commit();
                $result['applied'] = true;
                $fixed++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $result['error'] = $e->getMessage();
            }
        } else {
            if ($result['needs_update'] || $result['master_needs_update']) {
                $fixed++;
            } else {
                $unchanged++;
            }
        }

        $items[] = $result;
    }

    return ['items' => $items, 'fixed' => $fixed, 'unchanged' => $unchanged];
}

function processMainAccounts($pdo, $tenantId, $targetAccount, $targetCurrency, $mode) {
    $where = ['mt.tenant_id = ?'];
    $params = [$tenantId];
    if ($targetAccount) { $where[] = 'mt.main_account_id = ?'; $params[] = $targetAccount; }
    if ($targetCurrency) { $where[] = 'mt.currency = ?'; $params[] = $targetCurrency; }
    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT DISTINCT mt.main_account_id, mt.currency
        FROM main_account_transactions mt
        JOIN main_account ma ON ma.id = mt.main_account_id AND ma.tenant_id = mt.tenant_id
        WHERE {$whereClause}
        ORDER BY mt.main_account_id, mt.currency
    ");
    $stmt->execute($params);
    $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $fixed = 0;
    $unchanged = 0;

    foreach ($entities as $entity) {
        $id = $entity['main_account_id'];
        $currency = $entity['currency'];

        $txnStmt = $pdo->prepare("
            SELECT id, amount, type, balance, description
            FROM main_account_transactions
            WHERE main_account_id = ? AND currency = ? AND tenant_id = ?
            ORDER BY id ASC
        ");
        $txnStmt->execute([$id, $currency, $tenantId]);
        $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($txns)) continue;

        $currencyKey = strtolower($currency);
        if ($currencyKey === 'darham') $currencyKey = 'darham';
        $balanceField = $currencyKey . '_balance';
        $balStmt = $pdo->prepare("SELECT {$balanceField} FROM main_account WHERE id = ? AND tenant_id = ?");
        $balStmt->execute([$id, $tenantId]);
        $masterBalance = (float)$balStmt->fetchColumn();

        $nameStmt = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
        $nameStmt->execute([$id, $tenantId]);
        $name = $nameStmt->fetchColumn() ?: "Main Account #{$id}";

        $result = recalcRunningBalance($txns, $masterBalance, $name, $id, $currency);

        if ($mode === 'apply' && ($result['needs_update'] || $result['master_needs_update'])) {
            $pdo->beginTransaction();
            try {
                if (!empty($result['txn_fixes'])) {
                    $updStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                    foreach ($result['txn_fixes'] as $ch) {
                        $updStmt->execute([$ch['new_balance'], $ch['id'], $tenantId]);
                    }
                }
                if ($result['master_needs_update']) {
                    $pdo->prepare("UPDATE main_account SET {$balanceField} = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$result['new_master'], $id, $tenantId]);
                }
                $pdo->commit();
                $result['applied'] = true;
                $fixed++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $result['error'] = $e->getMessage();
            }
        } else {
            if ($result['needs_update'] || $result['master_needs_update']) {
                $fixed++;
            } else {
                $unchanged++;
            }
        }

        $items[] = $result;
    }

    return ['items' => $items, 'fixed' => $fixed, 'unchanged' => $unchanged];
}

function recalcRunningBalance($txns, $masterBalance, $name, $id, $currency) {
    $runningBalance = 0;
    $changes = [];
    $allTxns = [];
    $needsUpdate = false;

    foreach ($txns as $txn) {
        $rawAmt = abs((float)$txn['amount']);
        if (strtolower($txn['type']) === 'credit') {
            $runningBalance = round($runningBalance + $rawAmt, 3);
        } else {
            $runningBalance = round($runningBalance - $rawAmt, 3);
        }

        $storedBalance = round((float)$txn['balance'], 3);
        $isCorrect = abs($runningBalance - $storedBalance) < 0.001;
        $diff = round($runningBalance - $storedBalance, 3);

        $entry = [
            'id' => (int)$txn['id'],
            'type' => $txn['type'],
            'amount' => (float)$txn['amount'],
            'old_balance' => $storedBalance,
            'new_balance' => $runningBalance,
            'correct' => $isCorrect,
            'diff' => $diff,
            'description' => $txn['description'] ?? '',
        ];
        $allTxns[] = $entry;

        if (!$isCorrect) {
            $needsUpdate = true;
            $changes[] = $entry;
        }
    }

    $masterNeedsUpdate = (abs($runningBalance - $masterBalance) > 0.001);

    return [
        'entity_id' => $id,
        'entity_name' => $name,
        'currency' => $currency,
        'old_master' => $masterBalance,
        'new_master' => $runningBalance,
        'total_txns' => count($txns),
        'all_txns' => $allTxns,
        'txn_fixes' => $changes,
        'needs_update' => $needsUpdate,
        'master_needs_update' => $masterNeedsUpdate,
        'applied' => false,
    ];
}
