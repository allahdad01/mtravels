<?php
/**
 * Fix client transaction running balances.
 *
 * Recalculates ALL running balances from scratch based on the transaction history,
 * then syncs the master client balance to match.
 *
 * Usage:
 *   php fix_client_balances.php --tenant 28 --client 70 --dry-run
 *   php fix_client_balances.php --tenant 28 --client 70
 *
 *   # Override the starting balance (balance before first transaction):
 *   php fix_client_balances.php --tenant 28 --client 70 --start-balance -1210 --dry-run
 *   php fix_client_balances.php --tenant 28 --client 70 --start-balance -1210
 *
 *   # Or override via first transaction's correct balance:
 *   php fix_client_balances.php --tenant 28 --client 70 --first-balance -2275 --dry-run
 */

$dryRun = in_array('--dry-run', $argv);
$targetClient = null;
$targetTenant = null;
$startBalanceOverride = null;
$firstBalanceOverride = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--client' && isset($argv[$i + 1])) $targetClient = (int)$argv[$i + 1];
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--start-balance' && isset($argv[$i + 1])) $startBalanceOverride = (float)$argv[$i + 1];
    if ($argv[$i] === '--first-balance' && isset($argv[$i + 1])) $firstBalanceOverride = (float)$argv[$i + 1];
}

require_once __DIR__ . '/../../includes/db.php';

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

    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance, created_at, transaction_of, description
        FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id ASC");
    $txnStmt->execute([$clientId, $currency, $tenantId, $branchId]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$txns) continue;

    $balanceField = strtoupper($currency) === 'USD' ? 'usd_balance' : 'afs_balance';
    $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$clientId, $tenantId, $branchId]);
    $masterBalance = (float)$balStmt->fetchColumn();

    // Determine starting balance (balance before the first transaction)
    if ($startBalanceOverride !== null && $targetClient == $clientId) {
        $startBalance = $startBalanceOverride;
    } elseif ($firstBalanceOverride !== null && $targetClient == $clientId) {
        // User gives us the correct balance OF the first transaction.
        // Reverse the first transaction to get the start balance.
        $firstTxn = $txns[0];
        $absAmt = abs((float)$firstTxn['amount']);
        if (strtolower($firstTxn['type']) === 'credit') {
            $startBalance = $firstBalanceOverride - $absAmt;
        } else {
            $startBalance = $firstBalanceOverride + $absAmt;
        }
    } else {
        // Auto-compute: reverse from master balance
        $startBalance = $masterBalance;
        for ($i = count($txns) - 1; $i >= 0; $i--) {
            $absAmt = abs((float)$txns[$i]['amount']);
            if (strtolower($txns[$i]['type']) === 'credit') {
                $startBalance = round($startBalance - $absAmt, 3);
            } else {
                $startBalance = round($startBalance + $absAmt, 3);
            }
        }
    }

    // Rebuild all running balances forward
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

    $masterWrong = abs($masterBalance - $running) > 0.001;

    if ($mismatches === 0 && !$masterWrong) continue;

    $totalMismatches += $mismatches;
    echo "========================================\n";
    echo "Client #{$clientId} | {$currency} | Tenant #{$tenantId} | Branch #{$branchId}\n";
    echo "Transactions: " . count($txns) . " | Mismatches: {$mismatches}\n";
    echo "Start balance: " . number_format($startBalance, 3) . "\n";
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

    if (!$dryRun) {
        $pdo->beginTransaction();
        try {
            foreach ($updates as $u) {
                $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$u['new'], $u['id'], $tenantId, $branchId]);
            }
            $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$running, $clientId, $tenantId, $branchId]);
            $pdo->commit();
            echo "  [APPLIED] {$mismatches} transaction(s) fixed, master balance synced to " . number_format($running, 3) . "\n\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  [ERROR] " . $e->getMessage() . "\n\n";
        }
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
