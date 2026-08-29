<?php
/**
 * Insert opening balance transactions for suppliers.
 *
 * For suppliers with a non-zero starting balance (balance before their first
 * transaction), inserts an "Opening balance brought forward" transaction
 * as the first entry and rebuilds all running balances.
 *
 * Usage:
 *   php insert_supplier_opening_balances.php --tenant 28 --supplier 114 --amount 400 --branch 21 --dry-run
 *   php insert_supplier_opening_balances.php --tenant 28 --supplier 114 --amount 400 --branch 21
 *   php insert_supplier_opening_balances.php --tenant 28 --supplier 114 --dry-run   (auto-calculate)
 *   php insert_supplier_opening_balances.php --tenant 28 --dry-run                  (all suppliers)
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetSupplier = null;
$manualAmount = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--supplier' && isset($argv[$i + 1])) $targetSupplier = (int)$argv[$i + 1];
    if ($argv[$i] === '--amount' && isset($argv[$i + 1])) $manualAmount = (float)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant) {
    fwrite(STDERR, "Usage: php insert_supplier_opening_balances.php --tenant <id> [--supplier <id>] [--amount <value>] [--branch <id>] [--dry-run]\n");
    exit(1);
}

if (!$targetBranch) {
    fwrite(STDERR, "Error: --branch is required.\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$where = ['st.tenant_id = ?'];
$params = [$targetTenant];
if ($targetSupplier) { $where[] = 'st.supplier_id = ?'; $params[] = $targetSupplier; }
$whereClause = implode(' AND ', $where);

$supplierStmt = $pdo->prepare("
    SELECT DISTINCT st.supplier_id
    FROM supplier_transactions st
    WHERE {$whereClause}
    ORDER BY st.supplier_id
");
$supplierStmt->execute($params);
$suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$suppliers) {
    echo "No suppliers found.\n";
    exit(0);
}

$inserted = 0;

foreach ($suppliers as $supplier) {
    $supplierId = $supplier['supplier_id'];

    $txnStmt = $pdo->prepare("
        SELECT id, amount, transaction_type, balance, transaction_date, remarks
        FROM supplier_transactions
        WHERE supplier_id = ? AND tenant_id = ?
        ORDER BY id ASC
    ");
    $txnStmt->execute([$supplierId, $targetTenant]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    $balStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
    $balStmt->execute([$supplierId, $targetTenant]);
    $masterBalance = (float)$balStmt->fetchColumn();

    if ($manualAmount !== null) {
        $startBalance = $manualAmount;
        $txnType = $startBalance >= 0 ? 'Credit' : 'Debit';
    } else {
        if (empty($txns)) continue;

        $startBalance = $masterBalance;
        for ($i = count($txns) - 1; $i >= 0; $i--) {
            $absAmt = abs((float)$txns[$i]['amount']);
            if (strtoupper($txns[$i]['transaction_type']) === 'CREDIT') {
                $startBalance = round($startBalance - $absAmt, 3);
            } else {
                $startBalance = round($startBalance + $absAmt, 3);
            }
        }

        if (abs($startBalance) < 0.001) continue;
    }

    $nameStmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ? AND tenant_id = ?");
    $nameStmt->execute([$supplierId, $targetTenant]);
    $supplierName = $nameStmt->fetchColumn() ?: "Supplier #{$supplierId}";

    echo "Supplier #{$supplierId} ({$supplierName}):\n";
    echo "  Master balance: " . number_format($masterBalance, 3) . "\n";
    if (empty($txns)) {
        echo "  No existing transactions.\n";
    } else {
        echo "  First txn balance: " . number_format($txns[0]['balance'], 3) . "\n";
    }
    echo "  Opening balance: " . number_format($startBalance, 3) . "\n";

    $amount = abs($startBalance);
    if ($txnType === 'Debit') {
        $remarks = "Opening balance brought forward (amount owed from previous records)";
    } else {
        $remarks = "Opening balance brought forward (amount owed to supplier from previous records)";
    }

    $minIdStmt = $pdo->prepare("
        SELECT MIN(id) FROM supplier_transactions
        WHERE supplier_id = ? AND tenant_id = ?
    ");
    $minIdStmt->execute([$supplierId, $targetTenant]);
    $minId = $minIdStmt->fetchColumn();

    if ($minId !== null) {
        $minId = (int)$minId;
        $newId = $minId - 1;
        while ($newId > 0) {
            $checkStmt = $pdo->prepare("SELECT id FROM supplier_transactions WHERE id = ?");
            $checkStmt->execute([$newId]);
            if (!$checkStmt->fetchColumn()) break;
            $newId--;
        }
    } else {
        $globalMinStmt = $pdo->prepare("SELECT MIN(id) FROM supplier_transactions");
        $globalMinStmt->execute();
        $globalMin = $globalMinStmt->fetchColumn();
        if ($globalMin !== null) {
            $newId = (int)$globalMin - 1;
            while ($newId > 0) {
                $checkStmt = $pdo->prepare("SELECT id FROM supplier_transactions WHERE id = ?");
                $checkStmt->execute([$newId]);
                if (!$checkStmt->fetchColumn()) break;
                $newId--;
            }
        } else {
            $newId = 1;
        }
    }

    echo "  Insert: {$txnType} {$amount}\n";
    echo "  First txn ID: " . ($minId ?? 'none') . " → New txn ID: {$newId}" . ($newId > 0 ? " (available)" : " (NO FREE ID!)") . "\n";

    if (!$dryRun) {
        if ($newId <= 0) {
            echo "  SKIPPED — no free ID available\n";
            continue;
        }

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO supplier_transactions
                    (id, supplier_id, transaction_type, transaction_of, reference_id, amount, balance,
                     remarks, transaction_date, tenant_id, branch_id)
                VALUES (?, ?, ?, 'fund', 0, ?, ?, ?, '2020-01-01 00:00:00', ?, 0)
            ");
            $insertStmt->execute([
                $newId,
                $supplierId,
                $txnType,
                $amount,
                $startBalance,
                $remarks,
                $targetTenant
            ]);

            $allTxnStmt = $pdo->prepare("
                SELECT id, amount, transaction_type FROM supplier_transactions
                WHERE supplier_id = ? AND tenant_id = ?
                ORDER BY id ASC
            ");
            $allTxnStmt->execute([$supplierId, $targetTenant]);
            $allTxns = $allTxnStmt->fetchAll(PDO::FETCH_ASSOC);

            $updStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
            $updStmt->execute([$startBalance, $newId, $targetTenant]);

            $running = $startBalance;
            foreach ($allTxns as $txn) {
                if ((int)$txn['id'] === (int)$newId) continue;
                $absAmt = abs((float)$txn['amount']);
                if (strtoupper($txn['transaction_type']) === 'CREDIT') {
                    $running = round($running + $absAmt, 3);
                } else {
                    $running = round($running - $absAmt, 3);
                }
                $updStmt->execute([$running, $txn['id'], $targetTenant]);
            }

            if (abs($running - $masterBalance) > 0.001) {
                echo "  WARNING: Rebuilt balance ($running) != master ($masterBalance) — syncing\n";
                $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$running, $supplierId, $targetTenant]);
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
