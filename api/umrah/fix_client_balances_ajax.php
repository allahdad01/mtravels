<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$where = ['ct.tenant_id = ?'];
$params = [$tenant_id];
if ($clientId) { $where[] = 'ct.client_id = ?'; $params[] = $clientId; }
$whereClause = implode(' AND ', $where);

$clientStmt = $pdo->prepare("
    SELECT DISTINCT ct.client_id, ct.currency, ct.tenant_id, ct.branch_id
    FROM client_transactions ct
    WHERE {$whereClause}
    ORDER BY ct.client_id");
$clientStmt->execute($params);
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$clients) { echo json_encode(['message' => 'No clients found', 'fixed' => 0]); exit; }

$fixed = 0;
$results = [];

foreach ($clients as $client) {
    $cid = $client['client_id'];
    $cur = $client['currency'];
    $tid = $client['tenant_id'];
    $bid = $client['branch_id'];

    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance, created_at, transaction_of, description
        FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id ASC");
    $txnStmt->execute([$cid, $cur, $tid, $bid]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$txns) continue;

    $balanceField = strtoupper($cur) === 'USD' ? 'usd_balance' : 'afs_balance';
    $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$cid, $tid, $bid]);
    $masterBalance = (float)$balStmt->fetchColumn();

    $startBalance = $masterBalance;
    for ($i = count($txns) - 1; $i >= 0; $i--) {
        $absAmt = abs((float)$txns[$i]['amount']);
        if (strtolower($txns[$i]['type']) === 'credit') {
            $startBalance = round($startBalance - $absAmt, 3);
        } else {
            $startBalance = round($startBalance + $absAmt, 3);
        }
    }

    $running = $startBalance;
    $updates = [];
    $mismatches = 0;

    foreach ($txns as $txn) {
        $absAmt = abs((float)$txn['amount']);
        if (strtolower($txn['type']) === 'credit') {
            $running = round($running + $absAmt, 3);
        } else {
            $running = round($running - $absAmt, 3);
        }
        if (abs((float)$txn['balance'] - $running) > 0.001) {
            $mismatches++;
            $updates[] = ['id' => $txn['id'], 'old' => (float)$txn['balance'], 'new' => $running];
        }
    }

    $masterWrong = abs($masterBalance - $running) > 0.001;

    if ($mismatches === 0 && !$masterWrong) continue;

    if (!$dryRun) {
        foreach ($updates as $u) {
            $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$u['new'], $u['id'], $tid, $bid]);
        }
        $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$running, $cid, $tid, $bid]);
    }

    $fixed++;
    $results[] = [
        'client_id' => $cid,
        'currency' => $cur,
        'transactions_fixed' => $mismatches,
        'master_old' => $masterBalance,
        'master_new' => $running,
        'master_synced' => $masterWrong,
        'updates' => $updates,
    ];
}

echo json_encode([
    'dry_run' => $dryRun,
    'fixed' => $fixed,
    'results' => $results,
]);
