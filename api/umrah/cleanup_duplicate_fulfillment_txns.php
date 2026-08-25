<?php
/**
 * Cleanup duplicate "Fulfillment for ..." supplier transactions.
 *
 * When the old remarks-based dedup failed (e.g. member label changed between
 * saves), re-saving created duplicate transactions. This script keeps only
 * the NEWEST transaction per member+supplier+service-type and deletes the
 * rest, then rebuilds running balances.
 *
 * Usage:
 *   php cleanup_duplicate_fulfillment_txns.php --dry-run   (preview only)
 *   php cleanup_duplicate_fulfillment_txns.php              (apply fixes)
 *   php cleanup_duplicate_fulfillment_txns.php --tenant 5   (specific tenant)
 *   php cleanup_duplicate_fulfillment_txns.php --supplier 3 (specific supplier)
 */

$dryRun  = in_array('--dry-run', $argv);
$targetTenant = null;
$targetSupplier = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) {
        $targetTenant = (int)$argv[$i + 1];
    }
    if ($argv[$i] === '--supplier' && isset($argv[$i + 1])) {
        $targetSupplier = (int)$argv[$i + 1];
    }
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

// 1. Find all main "Fulfillment for ..." transactions (not corrections).
$sql = "
    SELECT st.id, st.supplier_id, st.reference_id AS booking_id,
           st.transaction_type, st.amount, st.balance, st.branch_id, st.tenant_id,
           st.remarks
    FROM supplier_transactions st
    WHERE st.transaction_of = 'umrah'
      AND st.remarks LIKE 'Fulfillment for %'
      AND st.remarks NOT LIKE 'Fulfillment cost correction%'" .
      ($targetTenant ? " AND st.tenant_id = ?" : "") .
      ($targetSupplier ? " AND st.supplier_id = ?" : "");
$stmt = $pdo->prepare($sql);
$params = [];
if ($targetTenant) { $params[] = $targetTenant; }
if ($targetSupplier) { $params[] = $targetSupplier; }
$stmt->execute($params);
$allTxns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Group by supplier_id + booking_id + service_type (parsed from remarks).
$grouped = [];
foreach ($allTxns as $tx) {
    if (preg_match('/^Fulfillment for ([^:]+):/', $tx['remarks'], $m)) {
        $svcType = trim($m[1]);
    } else {
        $svcType = 'unknown';
    }
    $key = $tx['tenant_id'] . '|' . $tx['supplier_id'] . '|' . $tx['booking_id'] . '|' . $svcType;
    $grouped[$key][] = $tx;
}

// 3. Identify duplicates: groups with >1 transaction.
$duplicates = [];
foreach ($grouped as $key => $txns) {
    if (count($txns) <= 1) continue;
    // Sort by id DESC — keep the newest (highest id).
    usort($txns, function ($a, $b) { return $b['id'] - $a['id']; });
    $keep = $txns[0]; // newest
    $delete = array_slice($txns, 1); // older duplicates
    $duplicates[$key] = ['keep' => $keep, 'delete' => $delete];
}

if (empty($duplicates)) {
    echo "No duplicates found. Nothing to do.\n";
    exit(0);
}

// 4. Report what will be done.
$totalDelete = 0;
$affectedSuppliers = [];
foreach ($duplicates as $key => $d) {
    $totalDelete += count($d['delete']);
    $supId = $d['keep']['supplier_id'];
    $tid   = $d['keep']['tenant_id'];
    $bid   = $d['keep']['branch_id'];
    $affectedSuppliers[$supId . '|' . $tid . '|' . $bid] = ['supplier_id' => $supId, 'tenant_id' => $tid, 'branch_id' => $bid];
    echo "GROUP: supplier={$d['keep']['supplier_id']} booking={$d['keep']['booking_id']} " .
         "remarks=\"{$d['keep']['remarks']}\"\n";
    echo "  KEEP:  id={$d['keep']['id']} amount={$d['keep']['amount']} balance={$d['keep']['balance']}\n";
    foreach ($d['delete'] as $del) {
        echo "  DELETE: id={$del['id']} amount={$del['amount']} balance={$del['balance']}\n";
    }
}
echo "\nTotal: " . count($duplicates) . " groups, {$totalDelete} transactions to delete.\n";

if ($dryRun) {
    echo "\n[DRY RUN] No changes made.\n";
    exit(0);
}

// 5. Delete the duplicate transactions.
$deletedIds = [];
foreach ($duplicates as $d) {
    foreach ($d['delete'] as $del) {
        $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?")
            ->execute([(int)$del['id'], (int)$del['tenant_id']]);
        $deletedIds[] = (int)$del['id'];
        echo "Deleted transaction id={$del['id']} (amount={$del['amount']})\n";
    }
}

// 6. Rebuild running balances for each affected supplier from the earliest
//    deleted id (or the kept transaction's id if it's lower).
foreach ($affectedSuppliers as $sup) {
    $minId = PHP_INT_MAX;
    foreach ($duplicates as $d) {
        if ((int)$d['keep']['supplier_id'] !== $sup['supplier_id']) continue;
        if ((int)$d['keep']['tenant_id'] !== $sup['tenant_id']) continue;
        $minId = min($minId, (int)$d['keep']['id']);
        foreach ($d['delete'] as $del) {
            $minId = min($minId, (int)$del['id']);
        }
    }
    if ($minId < PHP_INT_MAX) {
        umrahRebuildRunningBalances($pdo, (int)$sup['tenant_id'], (int)$sup['branch_id'], (int)$sup['supplier_id'], $minId);
        echo "Rebuilt running balances for supplier={$sup['supplier_id']} from id={$minId}\n";
    }

    // Fix supplier's live balance = last transaction's balance.
    $lastStmt = $pdo->prepare("
        SELECT balance FROM supplier_transactions
        WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id DESC LIMIT 1");
    $lastStmt->execute([$sup['supplier_id'], $sup['tenant_id'], $sup['branch_id']]);
    $lastBalance = $lastStmt->fetchColumn();
    if ($lastBalance !== false) {
        $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?")
            ->execute([(float)$lastBalance, $sup['supplier_id'], $sup['tenant_id']]);
        echo "Updated supplier={$sup['supplier_id']} live balance to {$lastBalance}\n";
    }
}

echo "\nDone. {$totalDelete} duplicate transactions removed.\n";
