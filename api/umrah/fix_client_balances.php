<?php
/**
 * Fix client transaction running balances.
 *
 * Recalculates ALL running balances from scratch based on the transaction history,
 * then syncs the master client balance to match.
 *
 * Usage:
 *   php fix_client_balances.php --dry-run                    (preview all clients)
 *   php fix_client_balances.php --client <id>                (one client)
 *   php fix_client_balances.php --tenant <id>                (all clients in tenant)
 *   php fix_client_balances.php --tenant <id> --client <id>  (specific client in tenant)
 */

$dryRun = in_array('--dry-run', $argv);
$targetClient = null;
$targetTenant = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--client' && isset($argv[$i + 1])) $targetClient = (int)$argv[$i + 1];
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
}

require_once __DIR__ . '/../../includes/db.php';

// Build query to find affected clients
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

if (!$clients) {
    echo "No clients found matching criteria.\n";
    exit(0);
}

$fixed = 0;
$totalMismatches = 0;

foreach ($clients as $client) {
    $clientId = $client['client_id'];
    $currency = $client['currency'];
    $tenantId = $client['tenant_id'];
    $branchId = $client['branch_id'];

    // Fetch ALL transactions for this client+currency ordered by id (chronological)
    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance, created_at, transaction_of, description
        FROM client_transactions
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

    // Step 1: Compute what the starting balance BEFORE the first transaction should be.
    // We know the final master balance is correct. Work backward from it.
    $startBalance = $masterBalance;
    for ($i = count($txns) - 1; $i >= 0; $i--) {
        $absAmt = abs((float)$txns[$i]['amount']);
        if (strtolower($txns[$i]['type']) === 'credit') {
            // Credit added to balance, so reverse: subtract
            $startBalance = round($startBalance - $absAmt, 3);
        } else {
            // Debit subtracted from balance, so reverse: add
            $startBalance = round($startBalance + $absAmt, 3);
        }
    }

    // Step 2: Rebuild all running balances forward from the computed start
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

        $oldBal = (float)$txn['balance'];
        if (abs($oldBal - $running) > 0.001) {
            $mismatches++;
            $updates[] = [
                'id'     => $txn['id'],
                'old'    => $oldBal,
                'new'    => $running,
                'date'   => $txn['created_at'],
                'type'   => $txn['type'],
                'amount' => $txn['amount'],
                'desc'   => $txn['description'],
            ];
        }
    }

    // Step 3: Check if master balance also needs syncing
    $masterWrong = abs($masterBalance - $running) > 0.001;

    if ($mismatches === 0 && !$masterWrong) continue;

    $totalMismatches += $mismatches;
    echo "========================================\n";
    echo "Client #{$clientId} | {$currency} | Tenant #{$tenantId} | Branch #{$branchId}\n";
    echo "Transactions: " . count($txns) . " | Mismatches: {$mismatches}\n";
    echo "Computed start balance: " . number_format($startBalance, 3) . "\n";
    echo "Master balance: " . number_format($masterBalance, 3);
    if ($masterWrong) {
        echo " → " . number_format($running, 3) . " (WILL SYNC)";
    } else {
        echo " (OK)";
    }
    echo "\n";

    if ($mismatches > 0) {
        echo "\n";
        echo str_pad('ID', 8) . str_pad('Type', 8) . str_pad('Amount', 12) . str_pad('Old Balance', 14) . str_pad('New Balance', 14) . "Description\n";
        echo str_repeat('-', 90) . "\n";
        foreach ($updates as $u) {
            echo str_pad($u['id'], 8)
                . str_pad($u['type'], 8)
                . str_pad(number_format($u['amount'], 3), 12)
                . str_pad(number_format($u['old'], 3), 14)
                . str_pad(number_format($u['new'], 3), 14)
                . substr($u['desc'], 0, 40) . "\n";
        }
    }
    echo "\n";

    // Apply fixes
    if (!$dryRun) {
        foreach ($updates as $u) {
            $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$u['new'], $u['id'], $tenantId, $branchId]);
        }
        // Sync master balance to the last correct running balance
        $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$running, $clientId, $tenantId, $branchId]);
        echo "  [APPLIED] {$mismatches} transaction(s) fixed, master balance synced to " . number_format($running, 3) . "\n\n";
    } else {
        echo "  [DRY RUN] No changes made\n\n";
    }
    $fixed++;
}

echo str_repeat('=', 70) . "\n";
echo "Total: {$fixed} client(s) with mismatched balances, {$totalMismatches} transaction(s) to fix.\n";
if ($dryRun) {
    echo "Run without --dry-run to apply fixes.\n";
} else {
    echo "All fixes applied.\n";
}
