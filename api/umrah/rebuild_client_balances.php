<?php
/**
 * Rebuild client transaction running balances.
 *
 * Usage:
 *   php rebuild_client_balances.php --dry-run                    (preview)
 *   php rebuild_client_balances.php --client <id>                (one client)
 *   php rebuild_client_balances.php --tenant <id>                (all clients in tenant)
 *
 * DELETE after running on all environments.
 */

$dryRun = in_array('--dry-run', $argv);
$targetClient = null;
$targetTenant = null;
$overrideBalance = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--client' && isset($argv[$i + 1])) $targetClient = (int)$argv[$i + 1];
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--balance' && isset($argv[$i + 1])) $overrideBalance = (float)$argv[$i + 1];
}

require_once __DIR__ . '/../../includes/db.php';

// Find all affected clients
$where = [];
$params = [];
if ($targetClient) { $where[] = 'ct.client_id = ?'; $params[] = $targetClient; }
if ($targetTenant) { $where[] = 'ct.tenant_id = ?'; $params[] = $targetTenant; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$clientStmt = $pdo->prepare("
    SELECT DISTINCT ct.client_id, ct.currency, ct.tenant_id, ct.branch_id
    FROM client_transactions ct
    {$whereClause}
    ORDER BY ct.client_id");
$clientStmt->execute($params);
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;

foreach ($clients as $client) {
    $clientId = $client['client_id'];
    $currency = $client['currency'];
    $tenantId = $client['tenant_id'];
    $branchId = $client['branch_id'];

    // Fetch all transactions for this client+currency ordered by id
    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id ASC");
    $txnStmt->execute([$clientId, $currency, $tenantId, $branchId]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$txns) continue;

    // Get client master balance
    $balanceField = strtoupper($currency) === 'USD' ? 'usd_balance' : 'afs_balance';
    $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$clientId, $tenantId, $branchId]);
    $masterBalance = (float)$balStmt->fetchColumn();

    // If --balance override provided, use it instead of master balance
    // (master balance itself may be corrupted by cascade bug)
    $lastBalance = ($overrideBalance !== null && $targetClient == $clientId) ? $overrideBalance : $masterBalance;

    // Compute what the starting balance should be:
    // Start from last balance and work backward through all transactions
    $reverseRunning = $lastBalance;
    for ($i = count($txns) - 1; $i >= 0; $i--) {
        $absAmt = abs((float)$txns[$i]['amount']);
        if (strtolower($txns[$i]['type']) === 'credit') {
            $reverseRunning = round($reverseRunning - $absAmt, 3);
        } else {
            $reverseRunning = round($reverseRunning + $absAmt, 3);
        }
    }
    // $reverseRunning is what the balance should be BEFORE the first transaction
    $startBalance = $reverseRunning;

    // Now rebuild forward
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
            $updates[] = ['id' => $txn['id'], 'old' => $txn['balance'], 'new' => $running];
        }
    }

    $masterWrong = abs($masterBalance - $running) > 0.001;

    if ($mismatches === 0 && !$masterWrong) continue;

    echo "Client #{$clientId} ({$currency}, tenant {$tenantId}): {$mismatches} transaction mismatch(es)";
    if ($masterWrong) echo ", master balance: " . number_format($masterBalance, 3) . " → " . number_format($running, 3);
    echo "\n";
    foreach ($updates as $u) {
        printf("  txn #%-6d  balance: %12s → %12s\n", $u['id'], number_format($u['old'], 3), number_format($u['new'], 3));
    }

    if (!$dryRun) {
        foreach ($updates as $u) {
            $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$u['new'], $u['id'], $tenantId, $branchId]);
        }
        // Sync master balance to last transaction's correct balance
        $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$running, $clientId, $tenantId, $branchId]);
        echo "  [APPLIED] {$mismatches} transaction(s) fixed, master balance synced to " . number_format($running, 3) . "\n";
    } else {
        echo "  [DRY RUN] No changes made\n";
    }
    $fixed++;
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
echo "Total: {$fixed} client(s) with mismatched balances.\n";
if ($dryRun) {
    echo "Run without --dry-run to apply fixes.\n";
}
