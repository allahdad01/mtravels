<?php
/**
 * Insert opening balance transactions for clients.
 *
 * For clients with a non-zero starting balance (balance before their first
 * transaction), inserts an "Opening balance brought forward" transaction
 * as the first entry and rebuilds all running balances.
 *
 * Usage:
 *   php insert_opening_balances.php --tenant 28 --client 70 --dry-run
 *   php insert_opening_balances.php --tenant 28 --client 70
 *   php insert_opening_balances.php --tenant 28 --dry-run          (all clients)
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetClient = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--client' && isset($argv[$i + 1])) $targetClient = (int)$argv[$i + 1];
}

if (!$targetTenant) {
    fwrite(STDERR, "Usage: php insert_opening_balances.php --tenant <id> [--client <id>] [--dry-run]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// Get clients with transactions
$where = ['ct.tenant_id = ?'];
$params = [$targetTenant];
if ($targetClient) { $where[] = 'ct.client_id = ?'; $params[] = $targetClient; }
$whereClause = implode(' AND ', $where);

$clientStmt = $pdo->prepare("
    SELECT DISTINCT ct.client_id, ct.currency, ct.branch_id
    FROM client_transactions ct
    WHERE {$whereClause}
    ORDER BY ct.client_id
");
$clientStmt->execute($params);
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$clients) {
    echo "No clients found.\n";
    exit(0);
}

$inserted = 0;

foreach ($clients as $client) {
    $clientId = $client['client_id'];
    $currency = $client['currency'];
    $branchId = $client['branch_id'];

    // Get all transactions ordered by id ASC
    $txnStmt = $pdo->prepare("
        SELECT id, amount, type, balance, created_at, description
        FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id ASC
    ");
    $txnStmt->execute([$clientId, $currency, $targetTenant, $branchId]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($txns)) continue;

    // Get master balance
    $balanceField = strtoupper($currency) === 'USD' ? 'usd_balance' : 'afs_balance';
    $balStmt = $pdo->prepare("SELECT {$balanceField} FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$clientId, $targetTenant, $branchId]);
    $masterBalance = (float)$balStmt->fetchColumn();

    // Calculate starting balance: reverse from master balance
    $startBalance = $masterBalance;
    for ($i = count($txns) - 1; $i >= 0; $i--) {
        $absAmt = abs((float)$txns[$i]['amount']);
        if (strtolower($txns[$i]['type']) === 'credit') {
            $startBalance = round($startBalance - $absAmt, 3);
        } else {
            $startBalance = round($startBalance + $absAmt, 3);
        }
    }

    // Skip if no opening balance needed
    if (abs($startBalance) < 0.001) continue;

    // Get client name
    $nameStmt = $pdo->prepare("SELECT name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $nameStmt->execute([$clientId, $targetTenant, $branchId]);
    $clientName = $nameStmt->fetchColumn() ?: "Client #{$clientId}";

    echo "Client #{$clientId} ({$clientName}) [{$currency}]:\n";
    echo "  Master balance: " . number_format($masterBalance, 3) . "\n";
    echo "  First txn balance: " . number_format($txns[0]['balance'], 3) . "\n";
    echo "  Opening balance: " . number_format($startBalance, 3) . "\n";

    if ($startBalance < 0) {
        // Client owes us money → debit transaction
        $txnType = 'debit';
        $amount = abs($startBalance);
        $description = "Opening balance brought forward (amount owed from previous records)";
    } else {
        // We owe client money → credit transaction
        $txnType = 'credit';
        $amount = abs($startBalance);
        $description = "Opening balance brought forward (amount owed to client from previous records)";
    }

    // Get the lowest existing id for this client's transactions
    $minIdStmt = $pdo->prepare("
        SELECT MIN(id) FROM client_transactions
        WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
    ");
    $minIdStmt->execute([$clientId, $currency, $targetTenant, $branchId]);
    $minId = (int)$minIdStmt->fetchColumn();

    // Find the lowest available ID (scan backwards from min - 1)
    // Check globally across ALL clients (primary key is global)
    $newId = $minId - 1;
    while ($newId > 0) {
        $checkStmt = $pdo->prepare("SELECT id FROM client_transactions WHERE id = ?");
        $checkStmt->execute([$newId]);
        if (!$checkStmt->fetchColumn()) break;
        $newId--;
    }

    echo "  Insert: {$txnType} {$amount} {$currency}\n";
    echo "  First txn ID: {$minId} → New txn ID: {$newId}" . ($newId > 0 ? " (available)" : " (NO FREE ID!)") . "\n";

    if (!$dryRun) {
        if ($newId <= 0) {
            echo "  SKIPPED — no free ID available\n";
            continue;
        }

        $pdo->beginTransaction();
        try {
            // Insert opening balance transaction with explicit ID
            $insertStmt = $pdo->prepare("
                INSERT INTO client_transactions
                    (id, client_id, type, transaction_of, reference_id, amount, balance, currency,
                     description, created_at, tenant_id, branch_id)
                VALUES (?, ?, ?, 'fund', 0, ?, ?, ?, ?, '2020-01-01 00:00:00', ?, ?)
            ");
            $insertStmt->execute([
                $newId,
                $clientId,
                $txnType,
                $startBalance,
                $startBalance,
                $currency,
                $description,
                $targetTenant,
                $branchId
            ]);

            // Rebuild ALL running balances from scratch
            $allTxnStmt = $pdo->prepare("
                SELECT id, amount, type FROM client_transactions
                WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                ORDER BY id ASC
            ");
            $allTxnStmt->execute([$clientId, $currency, $targetTenant, $branchId]);
            $allTxns = $allTxnStmt->fetchAll(PDO::FETCH_ASSOC);

            // Set opening balance row's balance
            $updStmt = $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updStmt->execute([$startBalance, $newId, $targetTenant, $branchId]);

            // Start from opening balance, skip the opening balance txn itself
            $running = $startBalance;
            foreach ($allTxns as $txn) {
                if ((int)$txn['id'] === (int)$newId) continue; // skip opening balance row
                $absAmt = abs((float)$txn['amount']);
                if (strtolower($txn['type']) === 'credit') {
                    $running = round($running + $absAmt, 3);
                } else {
                    $running = round($running - $absAmt, 3);
                }
                $updStmt->execute([$running, $txn['id'], $targetTenant, $branchId]);
            }

            if (abs($running - $masterBalance) > 0.001) {
                echo "  WARNING: Rebuilt balance ($running) != master ($masterBalance) — syncing\n";
                $pdo->prepare("UPDATE clients SET {$balanceField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$running, $clientId, $targetTenant, $branchId]);
            }

            $pdo->commit();
            echo "  INSERTED TXN #{$newId} — all balances rebuilt\n";
            $inserted++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    } else {
        $inserted++;
    }
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "[DRY RUN] Would insert {$inserted} opening balance transaction(s).\n";
    echo "Run without --dry-run to apply.\n";
} else {
    echo "Inserted {$inserted} opening balance transaction(s).\n";
}
