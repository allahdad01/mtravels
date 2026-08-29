<?php
/**
 * One-time fix: Recalculate all client_transactions.running balance
 * and clients.usd_balance / afs_balance from scratch.
 *
 * This corrects corruption caused by:
 *   1. Race condition when "Save All" fired parallel member updates
 *   2. Negative debit amounts in recalculation (subtracted a negative = added)
 *
 * Usage:
 *   php fix_client_balances.php --tenant 28                  (dry-run, shows what would change)
 *   php fix_client_balances.php --tenant 28 --apply          (apply fixes)
 *   php fix_client_balances.php --tenant 28 --client 70      (single client, dry-run)
 *   php fix_client_balances.php --tenant 28 --client 70 --apply
 */

$apply = in_array('--apply', $argv);
$targetTenant = null;
$targetClient = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--client' && isset($argv[$i + 1])) $targetClient = (int)$argv[$i + 1];
}

if (!$targetTenant) {
    fwrite(STDERR, "Usage: php fix_client_balances.php --tenant <id> [--client <id>] [--apply]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

echo "=== Client Balance Fix Script ===\n";
echo "Tenant: {$targetTenant}" . ($targetClient ? ", Client: {$targetClient}" : "") . "\n";
echo "Mode: " . ($apply ? "APPLY" : "DRY RUN") . "\n\n";

// Get all regular clients that have transactions
$where = ['ct.tenant_id = ?', "c.client_type = 'regular'"];
$params = [$targetTenant];
if ($targetClient) { $where[] = 'ct.client_id = ?'; $params[] = $targetClient; }
$whereClause = implode(' AND ', $where);

$clientStmt = $pdo->prepare("
    SELECT DISTINCT ct.client_id, ct.currency, ct.branch_id
    FROM client_transactions ct
    JOIN clients c ON c.id = ct.client_id AND c.tenant_id = ct.tenant_id AND c.branch_id = ct.branch_id
    WHERE {$whereClause}
    ORDER BY ct.client_id, ct.currency
");
$clientStmt->execute($params);
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$clients) {
    echo "No regular clients with transactions found.\n";
    exit(0);
}

$totalFixed = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($clients as $client) {
    $clientId = $client['client_id'];
    $currency = $client['currency'];
    $branchId = $client['branch_id'];

    // Get all transactions for this client+currency ordered by id ASC
    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance, created_at, description
        FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id ASC
    ");
    $txnStmt->execute([$clientId, $currency, $targetTenant, $branchId]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($txns)) continue;

    // Get current master balance
    $balanceField = strtoupper($currency) === 'USD' ? 'usd_balance' : 'afs_balance';
    $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$clientId, $targetTenant, $branchId]);
    $masterBalance = (float)$balStmt->fetchColumn();

    // Recalculate running balance from scratch starting at 0
    $runningBalance = 0;
    $changes = [];
    $needsUpdate = false;

    foreach ($txns as $txn) {
        $amt = abs((float)$txn['amount']);
        if (strtolower($txn['type']) === 'credit') {
            $runningBalance = round($runningBalance + $amt, 3);
        } else {
            $runningBalance = round($runningBalance - $amt, 3);
        }

        $storedBalance = round((float)$txn['balance'], 3);
        if (abs($runningBalance - $storedBalance) > 0.001) {
            $needsUpdate = true;
            $changes[] = [
                'id' => $txn['id'],
                'old_balance' => $storedBalance,
                'new_balance' => $runningBalance,
                'type' => $txn['type'],
                'amount' => $txn['amount'],
            ];
        }
    }

    // The final running balance should match the master balance
    $masterNeedsUpdate = (abs($runningBalance - $masterBalance) > 0.001);

    if (!$needsUpdate && !$masterNeedsUpdate) {
        $totalSkipped++;
        continue;
    }

    // Get client name
    $nameStmt = $pdo->prepare("SELECT name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $nameStmt->execute([$clientId, $targetTenant, $branchId]);
    $clientName = $nameStmt->fetchColumn() ?: "Client #{$clientId}";

    echo "Client #{$clientId} ({$clientName}) [{$currency}]:\n";
    echo "  Master balance: " . number_format($masterBalance, 3) . " → should be: " . number_format($runningBalance, 3) . "\n";

    if (!empty($changes)) {
        echo "  Transaction balance fixes:\n";
        foreach ($changes as $ch) {
            echo "    ID={$ch['id']} ({$ch['type']}, amt={$ch['amount']}): "
               . number_format($ch['old_balance'], 3) . " → " . number_format($ch['new_balance'], 3) . "\n";
        }
    }

    $totalFixed++;

    if ($apply) {
        $pdo->beginTransaction();
        try {
            // Update each transaction's balance
            if (!empty($changes)) {
                $updStmt = $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                foreach ($changes as $ch) {
                    $updStmt->execute([$ch['new_balance'], $ch['id'], $targetTenant, $branchId]);
                }
            }

            // Update master balance
            if ($masterNeedsUpdate) {
                $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$runningBalance, $clientId, $targetTenant, $branchId]);
            }

            $pdo->commit();
            echo "  ✓ Applied\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
    } else {
        echo "  (dry run — not applied)\n";
    }
}

echo "\n=== Summary ===\n";
echo "Clients checked: " . count($clients) . "\n";
echo "Clients fixed: {$totalFixed}\n";
echo "Clients unchanged: {$totalSkipped}\n";
echo "Errors: {$totalErrors}\n";
if (!$apply && $totalFixed > 0) {
    echo "\nRun with --apply to apply these fixes.\n";
}
