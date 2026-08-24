<?php
/**
 * Cleanup legacy "Fulfillment cost correction for ..." supplier transactions.
 *
 * These correction rows were created by the old reconciliation logic.
 * The new logic updates the main transaction in-place instead.
 *
 * This script:
 *   1. Finds each correction row
 *   2. Finds the matching main "Fulfillment for ..." transaction
 *   3. Updates the main transaction to the CURRENT fulfillment cost
 *   4. Deletes the correction row
 *   5. Rebuilds all subsequent running balances
 *
 * Usage:
 *   php cleanup_correction_txns.php --dry-run   (preview only)
 *   php cleanup_correction_txns.php              (apply fixes)
 *   php cleanup_correction_txns.php --tenant 5   (specific tenant)
 */

$dryRun  = in_array('--dry-run', $argv);
$fixBalances = in_array('--fix-balances', $argv);
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

// --fix-balances mode: sync all supplier balances to their last transaction
if ($fixBalances) {
    $supSql = "SELECT id, tenant_id, branch_id, name, supplier_type, balance FROM suppliers WHERE status = 'active'";
    if ($targetSupplier) { $supSql .= " AND id = ?"; }
    elseif ($targetTenant) { $supSql .= " AND tenant_id = ?"; }
    $supSql .= " ORDER BY id";
    $supStmt = $pdo->prepare($supSql);
    if ($targetSupplier) { $supStmt->execute([$targetSupplier]); }
    elseif ($targetTenant) { $supStmt->execute([$targetTenant]); }
    else { $supStmt->execute(); }
    $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

    $fixed = 0;
    foreach ($suppliers as $sup) {
        $lastStmt = $pdo->prepare("
            SELECT balance FROM supplier_transactions
            WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY id DESC LIMIT 1");
        $lastStmt->execute([$sup['id'], $sup['tenant_id'], $sup['branch_id']]);
        $lastBalance = (float)($lastStmt->fetchColumn() ?: 0);

        if (abs($lastBalance - (float)$sup['balance']) < 0.001) continue;

        echo sprintf("Supplier #%-4d %-30s  balance: %s → %s\n",
            $sup['id'], $sup['name'],
            number_format((float)$sup['balance'], 2),
            number_format($lastBalance, 2));

        if (!$dryRun) {
            $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$lastBalance, $sup['id'], $sup['tenant_id']]);
        }
        $fixed++;
    }

    echo "\nTotal: {$fixed} supplier(s) with stale balance.\n";
    if ($dryRun) { echo "Run without --dry-run to apply.\n"; }
    exit(0);
}

// Find all correction transactions
$sql = "
    SELECT st.id, st.supplier_id, st.reference_id AS booking_id, st.transaction_type,
           st.amount, st.remarks, st.balance, st.branch_id, st.tenant_id
    FROM supplier_transactions st
    WHERE st.transaction_of = 'umrah'
      AND st.remarks LIKE 'Fulfillment cost correction for %'";
if ($targetTenant) {
    $sql .= " AND st.tenant_id = ?";
}
$sql .= " ORDER BY st.id";

$stmt = $pdo->prepare($sql);
if ($targetTenant) {
    $stmt->execute([$targetTenant]);
} else {
    $stmt->execute();
}
$corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$corrections) {
    echo "No correction transactions found.\n";
    exit(0);
}

echo "Found " . count($corrections) . " correction transaction(s).\n\n";

$cleaned = 0;
foreach ($corrections as $corr) {
    $tenantId  = (int)$corr['tenant_id'];
    $branchId  = (int)$corr['branch_id'];
    $supplierId = (int)$corr['supplier_id'];
    $bookingId = (int)$corr['booking_id'];

    // Parse the correction remark to get service type and member name
    // "Fulfillment cost correction for {type}: {name}"
    if (!preg_match('/^Fulfillment cost correction for (.+): (.+)$/', $corr['remarks'], $m)) {
        continue;
    }
    $svcType = $m[1];
    $memName = $m[2];

    $mainRemark = "Fulfillment for {$svcType}: {$memName}";

    // Find the main transaction
    $mainStmt = $pdo->prepare("
        SELECT id, amount FROM supplier_transactions
        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
          AND remarks = ? AND tenant_id = ? ORDER BY id LIMIT 1");
    $mainStmt->execute([$supplierId, $bookingId, $mainRemark, $tenantId]);
    $mainRow = $mainStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainRow) {
        echo "WARNING: No main transaction found for '{$mainRemark}', skipping.\n\n";
        continue;
    }

    // Get the current fulfillment cost for this booking + supplier
    $costStmt = $pdo->prepare("
        SELECT COALESCE(SUM(COALESCE(f.supplier_cost, f.cost_amount)), 0)
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON f.booking_service_id = bs.id
        WHERE bs.booking_id = ? AND f.supplier_id = ? AND f.tenant_id = ? AND f.status <> 'cancelled'");
    $costStmt->execute([$bookingId, $supplierId, $tenantId]);
    $targetCost = round((float)$costStmt->fetchColumn(), 3);

    $oldMainAmount = round((float)$mainRow['amount'], 3);
    $mainId = (int)$mainRow['id'];
    $corrId = (int)$corr['id'];

    // Get supplier info
    $supStmt = $pdo->prepare("SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?");
    $supStmt->execute([$supplierId, $tenantId]);
    $supplier = $supStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $supplierName = $supplier['name'] ?? '?';
    $supplierType = $supplier['supplier_type'] ?? '?';
    $currentBalance = (float)($supplier['balance'] ?? 0);

    echo str_repeat('-', 60) . "\n";
    echo "Supplier: {$supplierName} (ID {$supplierId}, {$supplierType})\n";
    echo "Booking ID: {$bookingId}\n";
    echo "Service: {$svcType} | Member: {$memName}\n\n";

    echo "Main transaction:\n";
    printf("  #%-5d Debit  %-10s  %s\n", $mainId, number_format($oldMainAmount, 3), $mainRemark);
    echo "Correction transaction:\n";
    printf("  #%-5d %-6s %-10s  %s\n", $corrId, $corr['transaction_type'], number_format((float)$corr['amount'], 3), $corr['remarks']);

    echo "\nCurrent fulfillment cost: " . number_format($targetCost, 3) . "\n";
    echo "Main transaction amount:   " . number_format($oldMainAmount, 3) . " → " . number_format($targetCost, 3) . "\n";

    if ($dryRun) {
        echo "\n[DRY RUN] No changes made.\n";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Update the main transaction to the current cost
            $pdo->prepare("UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$targetCost, $mainId, $tenantId]);

            // 2. Delete the correction row
            $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?")
                ->execute([$corrId, $tenantId]);

            // 3. Rebuild running balances from the main transaction
            umrahRebuildRunningBalances($pdo, $tenantId, $branchId, $supplierId, $mainId);

            // 4. Sync supplier's live balance to the last transaction's balance
            $lastBalStmt = $pdo->prepare("
                SELECT balance FROM supplier_transactions
                WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?
                ORDER BY id DESC LIMIT 1");
            $lastBalStmt->execute([$supplierId, $tenantId, $branchId]);
            $lastBalance = (float)($lastBalStmt->fetchColumn() ?: 0);
            if ($supplierType === 'External') {
                $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$lastBalance, $supplierId, $tenantId]);
            }

            $pdo->commit();
            echo "\n[APPLIED] Main #{$mainId}: " . number_format($oldMainAmount, 3) . " → " . number_format($targetCost, 3)
                . ", deleted correction #{$corrId}\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "\n[ERROR] " . $e->getMessage() . "\n";
        }
    }

    $cleaned++;
    echo "\n";
}

echo str_repeat('=', 60) . "\n";
echo "Total: {$cleaned} correction(s) processed.\n";
if ($dryRun) {
    echo "Run without --dry-run to apply fixes.\n";
}
