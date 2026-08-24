<?php
/**
 * Cleanup orphaned supplier transactions for cancelled fulfillments.
 *
 * Usage:
 *   php cleanup_orphaned_supplier_txns.php --dry-run   (preview only)
 *   php cleanup_orphaned_supplier_txns.php              (apply fixes)
 *   php cleanup_orphaned_supplier_txns.php --tenant 5   (specific tenant)
 *
 * DELETE after running on all environments.
 */

$dryRun  = in_array('--dry-run', $argv);
$targetTenant = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) {
        $targetTenant = (int)$argv[$i + 1];
    }
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$cleaned = 0;

// Find all supplier transactions that look like "Fulfillment for {type}: {name}"
// and check if the corresponding fulfillment is cancelled
$txnStmt = $pdo->prepare("
    SELECT st.id, st.supplier_id, st.reference_id AS booking_id, st.transaction_type,
           st.amount, st.remarks, st.balance, st.branch_id, st.tenant_id
    FROM supplier_transactions st
    WHERE st.transaction_of = 'umrah'
      AND st.remarks LIKE 'Fulfillment for %'
      AND st.remarks NOT LIKE 'Fulfillment cost correction%'
      " . ($targetTenant ? "AND st.tenant_id = ?" : ""));
if ($targetTenant) {
    $txnStmt->execute([$targetTenant]);
} else {
    $txnStmt->execute();
}

$allTxns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($allTxns as $tx) {
    // Parse "Fulfillment for {service_type}: {member_name}"
    if (preg_match('/^Fulfillment for (.+): (.+)$/', $tx['remarks'], $m)) {
        $svcType   = $m[1];
        $memName   = $m[2];
        $key = $tx['tenant_id'] . '|' . $tx['booking_id'] . '|' . $svcType . '|' . $memName;
        $grouped[$key][] = $tx;
    }
}

foreach ($grouped as $key => $txns) {
    [$tenantId, $bookingId, $svcType, $memName] = explode('|', $key, 4);

    // Check: is the fulfillment for this service+member cancelled?
    $fStmt = $pdo->prepare("
        SELECT f.id, f.status, f.supplier_id, f.cost_amount
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
        JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id AND ub.tenant_id = bs.tenant_id
        WHERE f.tenant_id = ? AND ub.booking_id = ? AND bs.service_type = ? AND ub.name = ?
        ORDER BY f.id DESC LIMIT 1");
    $fStmt->execute([$tenantId, $bookingId, $svcType, $memName]);
    $fulfillment = $fStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fulfillment || $fulfillment['status'] !== 'cancelled') {
        continue; // Not cancelled — skip
    }

    // Get supplier from the transaction (fulfillment may have null supplier_id when cost=0)
    $txnSupplierId = $txns[0]['supplier_id'] ?? (int)($fulfillment['supplier_id'] ?? 0);

    // Check if there's a correction row too
    $corrRemark = "Fulfillment cost correction for {$svcType}: {$memName}";
    $corrStmt = $pdo->prepare("
        SELECT id, transaction_type, amount, remarks, balance
        FROM supplier_transactions
        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
          AND remarks = ? AND tenant_id = ?");
    $corrStmt->execute([$txnSupplierId, $bookingId, $corrRemark, $tenantId]);
    $corrTxns = $corrStmt->fetchAll(PDO::FETCH_ASSOC);

    $allDelete = array_merge($txns, $corrTxns);
    $deleteIds = array_column($allDelete, 'id');
    $deleteIds = array_unique($deleteIds);

    // Calculate net effect
    $net   = 0.0;
    $minId = PHP_INT_MAX;
    foreach ($allDelete as $dt) {
        $net += (strcasecmp((string)$dt['transaction_type'], 'Credit') === 0)
            ? -(float)$dt['amount'] : (float)$dt['amount'];
        $minId = min($minId, (int)$dt['id']);
    }

    // Get supplier info
    $supStmt = $pdo->prepare("SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?");
    $supStmt->execute([$txnSupplierId, $tenantId]);
    $supplier = $supStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $supplierName = $supplier['name'] ?? '?';
    $supplierType = $supplier['supplier_type'] ?? '?';
    $currentBalance = (float)($supplier['balance'] ?? 0);

    // Get branch_id from first transaction
    $branchId = $txns[0]['branch_id'];

    echo str_repeat('=', 70) . "\n";
    echo "Fulfillment #{$fulfillment['id']} | {$svcType}: {$memName} | Status: {$fulfillment['status']}\n";
    echo "Supplier: {$supplierName} (ID {$txnSupplierId}, {$supplierType})\n";
    echo "Fulfillment cost: " . number_format((float)$fulfillment['cost_amount'], 3) . "\n\n";

    echo "Transactions to DELETE:\n";
    printf("  %-5s %-8s %-10s %-8s  %-50s\n", 'ID', 'Type', 'Amount', 'Balance', 'Remarks');
    printf("  %-5s %-8s %-10s %-8s  %-50s\n", '---', '------', '--------', '--------', str_repeat('-', 50));
    foreach ($allDelete as $tx) {
        printf("  %-5d %-8s %-10s %-8s  %s\n",
            $tx['id'], $tx['transaction_type'],
            number_format((float)$tx['amount'], 3),
            number_format((float)$tx['balance'], 3),
            $tx['remarks']);
    }

    echo "\nNet effect: " . ($net >= 0 ? '+' : '') . number_format($net, 3) . "\n";
    echo "Supplier balance: " . number_format($currentBalance, 3) . " → " . number_format($currentBalance + $net, 3) . "\n";

    // Show running balance recalculation
        $postStmt = $pdo->prepare("
            SELECT id, transaction_type, amount, balance, remarks
            FROM supplier_transactions
            WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id >= ?
            ORDER BY id ASC");
        $postStmt->execute([$txnSupplierId, $tenantId, $branchId, $minId]);
    $remaining = $postStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($remaining) {
        $preStmt = $pdo->prepare("
            SELECT balance FROM supplier_transactions
            WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id < ?
            ORDER BY id DESC LIMIT 1");
        $preStmt->execute([$txnSupplierId, $tenantId, $branchId, $minId]);
        $prevBalance = $preStmt->fetchColumn();
        $prevBalance = $prevBalance !== false ? (float)$prevBalance : $currentBalance;

        echo "\nRunning balance recalc (from #{$minId}):\n";
        printf("  %-5s %-8s %-10s %-12s %-12s  %-40s\n", 'ID', 'Type', 'Amount', 'Old Bal', 'New Bal', 'Remarks');
        foreach ($remaining as $r) {
            $amt = (float)$r['amount'];
            $newBal = (strcasecmp((string)$r['transaction_type'], 'Credit') === 0)
                ? $prevBalance + $amt : $prevBalance - $amt;
            $deleted = in_array($r['id'], $deleteIds) ? ' ← DELETE' : '';
            printf("  %-5d %-8s %-10s %-12s %-12s  %s%s\n",
                $r['id'], $r['transaction_type'], number_format($amt, 3),
                number_format((float)$r['balance'], 3), number_format($newBal, 3),
                $r['remarks'], $deleted);
            $prevBalance = $newBal;
        }
    }

    if ($dryRun) {
        echo "\n[DRY RUN] No changes made.\n";
    } else {
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

        // Restore supplier balance
        if ($net != 0.0 && $supplierType === 'External') {
            $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                ->execute([$net, $txnSupplierId, $tenantId]);
        }

        // Delete transactions
        $pdo->prepare("DELETE FROM supplier_transactions WHERE id IN ({$placeholders})")
            ->execute(array_values($deleteIds));

        // Rebuild running balances
        umrahRebuildRunningBalances($pdo, $tenantId, $branchId, $txnSupplierId, $minId);

        echo "\n[APPLIED] Deleted " . count($deleteIds) . " txn(s), restored " . number_format($net, 3) . "\n";
    }

    $cleaned++;
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
echo "Total: {$cleaned} orphaned transaction set(s) found.\n";
if ($dryRun) {
    echo "Run without --dry-run to apply fixes.\n";
}
