<?php
/**
 * Cleanup duplicate + orphaned supplier transactions.
 *
 * For duplicates:
 *   - If one txn is linked to a fulfillment → keep it, delete the other
 *   - If multiple linked → keep earliest linked, delete rest
 *   - If all orphaned → delete ALL (phantom debits with no fulfillment)
 *
 * Usage:
 *   php cleanup_duplicate_supplier_txns.php --tenant 28 --branch 21 --dry-run
 *   php cleanup_duplicate_supplier_txns.php --tenant 28 --branch 21
 *   php cleanup_duplicate_supplier_txns.php --tenant 28 --branch 21 --supplier 113 --dry-run
 *   php cleanup_duplicate_supplier_txns.php --tenant 28 --branch 21 --booking 201 --dry-run
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetBranch = null;
$targetSupplier = null;
$targetBooking = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
    if ($argv[$i] === '--supplier' && isset($argv[$i + 1])) $targetSupplier = (int)$argv[$i + 1];
    if ($argv[$i] === '--booking' && isset($argv[$i + 1])) $targetBooking = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php cleanup_duplicate_supplier_txns.php --tenant <id> --branch <id> [--supplier <id>] [--booking <id>] [--dry-run]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$where = ['st.transaction_of = ?', 'st.tenant_id = ?', 'st.branch_id = ?'];
$params = ['umrah', $targetTenant, $targetBranch];
if ($targetSupplier) { $where[] = 'st.supplier_id = ?'; $params[] = $targetSupplier; }
if ($targetBooking) { $where[] = 'st.reference_id = ?'; $params[] = $targetBooking; }
$whereClause = 'WHERE ' . implode(' AND ', $where);

// Find duplicate groups
$dupStmt = $pdo->prepare("
    SELECT st.supplier_id, st.reference_id, st.remarks, COUNT(*) as cnt,
           GROUP_CONCAT(st.id ORDER BY st.id ASC) as all_ids
    FROM supplier_transactions st
    {$whereClause}
    GROUP BY st.supplier_id, st.reference_id, st.remarks
    HAVING cnt > 1
");
$dupStmt->execute($params);
$groups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($groups) . " duplicate groups\n\n";

if (empty($groups)) {
    echo "No duplicates found. Nothing to do.\n";
    exit(0);
}

$totalDeleted = 0;
$affectedSupplierIds = [];
$deletedAmountsBySupplier = []; // Track total deleted debit amounts per supplier

foreach ($groups as $grp) {
    $allIds = array_map('intval', explode(',', $grp['all_ids']));
    $affectedSupplierIds[] = (int)$grp['supplier_id'];

    // Check which txn IDs are linked to a current fulfillment
    // Fulfillment link: supplier_transactions.remarks contains the fulfillment_type
    // and there's a umrah_fulfillment on a booking_service for this booking
    $linkedIds = [];
    foreach ($allIds as $txnId) {
        // Extract service_type from remarks: "Fulfillment for {type}: {member}"
        if (preg_match('/^Fulfillment for (\w+):/', $grp['remarks'], $m)) {
            $serviceType = $m[1];
            $linkStmt = $pdo->prepare("
                SELECT 1 FROM umrah_fulfillments f
                JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
                WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
                  AND f.status <> 'cancelled'
                  AND f.fulfillment_type = ?
                LIMIT 1
            ");
            $linkStmt->execute([$grp['reference_id'], $targetTenant, $targetTenant, $serviceType]);
            if ($linkStmt->fetchColumn()) {
                $linkedIds[] = $txnId;
            }
        }
    }

    // Decide what to delete
    if (!empty($linkedIds)) {
        // At least one linked → keep earliest linked, delete rest
        $keepId = min($linkedIds);
        $deleteIds = array_diff($allIds, [$keepId]);
        $linkInfo = count($linkedIds) . " linked, keeping #" . $keepId;
    } else {
        // All orphaned → delete ALL (phantom debits)
        $keepId = null;
        $deleteIds = $allIds;
        $linkInfo = "ALL ORPHANED — deleting all (phantom debits)";
    }

    echo "Supplier #{$grp['supplier_id']} | Booking #{$grp['reference_id']} | {$grp['remarks']}\n";
    echo "  Delete: " . implode(', ', array_map(function($id) { return "#$id"; }, $deleteIds)) . " | {$linkInfo}\n";

    $totalDeleted += count($deleteIds);
    if (!$dryRun && !empty($deleteIds)) {
        // Get amounts of transactions being deleted (to adjust supplier balance)
        $deletePlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $amtStmt = $pdo->prepare("
            SELECT amount, transaction_type FROM supplier_transactions
            WHERE id IN ($deletePlaceholders) AND tenant_id = ? AND branch_id = ?
        ");
        $amtStmt->execute(array_merge($deleteIds, [$targetTenant, $targetBranch]));
        $deletedTxns = $amtStmt->fetchAll(PDO::FETCH_ASSOC);

        $supId = $grp['supplier_id'];
        if (!isset($deletedAmountsBySupplier[$supId])) {
            $deletedAmountsBySupplier[$supId] = ['debit' => 0, 'credit' => 0];
        }
        foreach ($deletedTxns as $dt) {
            if (strtolower($dt['transaction_type']) === 'credit') {
                $deletedAmountsBySupplier[$supId]['credit'] += (float)$dt['amount'];
            } else {
                $deletedAmountsBySupplier[$supId]['debit'] += (float)$dt['amount'];
            }
        }

        // Delete the transactions
        $pdo->prepare("DELETE FROM supplier_transactions WHERE id IN ($deletePlaceholders) AND tenant_id = ? AND branch_id = ?")
            ->execute(array_merge($deleteIds, [$targetTenant, $targetBranch]));
    }
    echo "\n";
}

$affectedSupplierIds = array_unique($affectedSupplierIds);

// Rebuild running balances
if (!$dryRun && $totalDeleted > 0) {
    echo "Rebuilding running balances for " . count($affectedSupplierIds) . " supplier(s)...\n";

    foreach ($affectedSupplierIds as $supId) {
        $txns = $pdo->prepare("
            SELECT id, amount, transaction_type FROM supplier_transactions
            WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY id ASC
        ");
        $txns->execute([$supId, $targetTenant, $targetBranch]);
        $allTxns = $txns->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allTxns)) continue;

        // Reconstruct starting balance
        $firstBalStmt = $pdo->prepare("SELECT balance FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $firstBalStmt->execute([$allTxns[0]['id'], $targetTenant, $targetBranch]);
        $firstStoredBalance = (float)$firstBalStmt->fetchColumn();

        $firstAmt = (float)$allTxns[0]['amount'];
        if (strtolower($allTxns[0]['transaction_type']) === 'credit') {
            $running = round($firstStoredBalance - $firstAmt, 3);
        } else {
            $running = round($firstStoredBalance + $firstAmt, 3);
        }

        $updStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        foreach ($allTxns as $txn) {
            $amt = (float)$txn['amount'];
            if (strtolower($txn['transaction_type']) === 'credit') {
                $running = round($running + $amt, 3);
            } else {
                $running = round($running - $amt, 3);
            }
            $updStmt->execute([$running, $txn['id'], $targetTenant, $targetBranch]);
        }

        echo "  Supplier #$supId: rebuilt " . count($allTxns) . " transactions\n";

        // Update supplier.master balance: add back deleted debits, subtract deleted credits
        if (isset($deletedAmountsBySupplier[$supId])) {
            $netDeleted = $deletedAmountsBySupplier[$supId]['debit'] - $deletedAmountsBySupplier[$supId]['credit'];
            if ($netDeleted != 0) {
                // Check supplier type
                $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $typeStmt->execute([$supId, $targetTenant, $targetBranch]);
                $supType = $typeStmt->fetchColumn();

                if ($supType === 'External') {
                    // External suppliers: balance tracks what we owe them
                    // Debit transactions reduce balance (we owe more), credits increase it
                    // Removing debits = balance goes UP (add back)
                    $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$netDeleted, $supId, $targetTenant, $targetBranch]);

                    $newBalStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $newBalStmt->execute([$supId, $targetTenant, $targetBranch]);
                    $newBal = $newBalStmt->fetchColumn();
                    echo "  Supplier #$supId: balance adjusted +{$netDeleted} → {$newBal}\n";
                } else {
                    echo "  Supplier #$supId: Internal supplier, balance not tracked\n";
                }
            }
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "[DRY RUN] Found {$totalDeleted} transaction(s) to delete across " . count($affectedSupplierIds) . " supplier(s). No changes made.\n";
    echo "Run without --dry-run to apply.\n";
} else {
    echo "Deleted {$totalDeleted} transaction(s). Balances rebuilt for " . count($affectedSupplierIds) . " supplier(s).\n";
}
