<?php
/**
 * Remove extra-bed hotel fulfillments and their supplier ledger rows.
 *
 * This intentionally preserves the extra-bed booking, its sold price and its
 * client transaction. It removes only the hotel procurement fulfillment/cost
 * and the matching supplier Debit/Credit correction rows, then restores the
 * supplier balance and rebuilds the supplier running-balance column.
 *
 * Usage (preview first):
 *   php cleanup_extra_bed_hotel_fulfillments.php --tenant 28 --supplier 113
 * Apply after reviewing the preview:
 *   php cleanup_extra_bed_hotel_fulfillments.php --tenant 28 --supplier 113 --apply
 */

$args = $argv ?? [];
$tenantId = 0;
$supplierId = 0;
$apply = in_array('--apply', $args, true);
for ($i = 1; $i < count($args); $i++) {
    if ($args[$i] === '--tenant' && isset($args[$i + 1])) $tenantId = (int)$args[++$i];
    if ($args[$i] === '--supplier' && isset($args[$i + 1])) $supplierId = (int)$args[++$i];
}
if ($tenantId <= 0 || $supplierId <= 0) {
    fwrite(STDERR, "Usage: php cleanup_extra_bed_hotel_fulfillments.php --tenant <id> --supplier <id> [--apply]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$fulfillmentStmt = $pdo->prepare("
    SELECT f.id, f.booking_service_id, bs.booking_id, b.name, f.cost_amount, f.supplier_cost
    FROM umrah_fulfillments f
    JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
    JOIN umrah_bookings b ON b.booking_id = bs.booking_id AND b.tenant_id = bs.tenant_id
    WHERE f.tenant_id = ? AND f.supplier_id = ? AND f.fulfillment_type = 'hotel'
      AND b.is_extra_bed = 1
    ORDER BY f.id");
$fulfillmentStmt->execute([$tenantId, $supplierId]);
$fulfillments = $fulfillmentStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$fulfillments) {
    echo "No extra-bed hotel fulfillments found for tenant {$tenantId}, supplier {$supplierId}.\n";
    exit(0);
}

$bookingIds = array_values(array_unique(array_map(fn($r) => (int)$r['booking_id'], $fulfillments)));
$bookingPh = implode(',', array_fill(0, count($bookingIds), '?'));
$txnStmt = $pdo->prepare("
    SELECT id, branch_id, reference_id, transaction_type, amount, balance, remarks
    FROM supplier_transactions
    WHERE tenant_id = ? AND supplier_id = ? AND transaction_of = 'umrah'
      AND reference_id IN ({$bookingPh})
      AND (remarks LIKE 'Fulfillment for hotel:%' OR remarks LIKE 'Fulfillment cost correction for hotel:%')
    ORDER BY id");
$txnStmt->execute(array_merge([$tenantId, $supplierId], $bookingIds));
$transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

$supplierStmt = $pdo->prepare("SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?");
$supplierStmt->execute([$supplierId, $tenantId]);
$supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
if (!$supplier) {
    fwrite(STDERR, "Supplier {$supplierId} does not belong to tenant {$tenantId}.\n");
    exit(1);
}

$net = 0.0;
$byBranch = [];
foreach ($transactions as $tx) {
    $effect = strcasecmp((string)$tx['transaction_type'], 'Credit') === 0
        ? -(float)$tx['amount'] : (float)$tx['amount'];
    $net += $effect;
    $branchId = (int)$tx['branch_id'];
    if (!isset($byBranch[$branchId])) $byBranch[$branchId] = PHP_INT_MAX;
    $byBranch[$branchId] = min($byBranch[$branchId], (int)$tx['id']);
}

echo ($apply ? "APPLY" : "DRY RUN") . ": tenant {$tenantId}, supplier {$supplierId} ({$supplier['name']})\n\n";
echo "Extra-bed hotel fulfillments to remove:\n";
foreach ($fulfillments as $f) {
    echo "  fulfillment #{$f['id']} | booking #{$f['booking_id']} | {$f['name']} | cost {$f['supplier_cost']}\n";
}
echo "\nSupplier transactions to remove:\n";
foreach ($transactions as $tx) {
    echo "  #{$tx['id']} | {$tx['transaction_type']} {$tx['amount']} | {$tx['remarks']}\n";
}
echo "\nSupplier ledger effect being removed: " . number_format($net, 3) . "\n";
echo "Supplier balance: " . number_format((float)$supplier['balance'], 3) . " -> "
    . number_format((float)$supplier['balance'] + $net, 3) . "\n";
echo "Extra-bed bookings and client transactions are preserved.\n";

if (!$apply) {
    echo "\nPreview only. Re-run with --apply after verifying these rows.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();
    $fulfillmentIds = array_values(array_unique(array_map(fn($r) => (int)$r['id'], $fulfillments)));
    $fulfillmentPh = implode(',', array_fill(0, count($fulfillmentIds), '?'));

    $pdo->prepare("DELETE FROM umrah_hotel_fulfillments WHERE fulfillment_id IN ({$fulfillmentPh})")
        ->execute($fulfillmentIds);
    $pdo->prepare("DELETE FROM umrah_fulfillment_details WHERE fulfillment_id IN ({$fulfillmentPh})")
        ->execute($fulfillmentIds);
    $pdo->prepare("DELETE FROM umrah_fulfillments WHERE id IN ({$fulfillmentPh}) AND tenant_id = ?")
        ->execute(array_merge($fulfillmentIds, [$tenantId]));

    // Recalculate the affected service and booking costs after removing the
    // hotel fulfillments, without changing the extra-bed sale/client amount.
    $serviceIds = array_values(array_unique(array_map(fn($r) => (int)$r['booking_service_id'], $fulfillments)));
    $remainingCostStmt = $pdo->prepare("SELECT COALESCE(SUM(cost_amount), 0) FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ?");
    $updateServiceStmt = $pdo->prepare("UPDATE umrah_booking_services SET base_price = ?, profit = sold_price - ? WHERE id = ? AND tenant_id = ?");
    foreach ($serviceIds as $serviceId) {
        $remainingCostStmt->execute([$serviceId, $tenantId]);
        $cost = (float)$remainingCostStmt->fetchColumn();
        $updateServiceStmt->execute([$cost, $cost, $serviceId, $tenantId]);
    }
    $bookingCostStmt = $pdo->prepare("SELECT COALESCE(SUM(base_price), 0) FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?");
    $bookingStmt = $pdo->prepare("SELECT sold_price, discount FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $updateBookingStmt = $pdo->prepare("UPDATE umrah_bookings SET price = ?, profit = ? WHERE booking_id = ? AND tenant_id = ?");
    foreach ($bookingIds as $bookingId) {
        $bookingCostStmt->execute([$bookingId, $tenantId]);
        $price = (float)$bookingCostStmt->fetchColumn();
        $bookingStmt->execute([$bookingId, $tenantId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC) ?: ['sold_price' => 0, 'discount' => 0];
        $updateBookingStmt->execute([$price, (float)$booking['sold_price'] - (float)$booking['discount'] - $price, $bookingId, $tenantId]);
    }

    if ($transactions) {
        $txnIds = array_column($transactions, 'id');
        $txnPh = implode(',', array_fill(0, count($txnIds), '?'));
        if ($supplier['supplier_type'] === 'External' && abs($net) > 0.00001) {
            $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                ->execute([$net, $supplierId, $tenantId]);
        }
        $pdo->prepare("DELETE FROM supplier_transactions WHERE id IN ({$txnPh}) AND tenant_id = ?")
            ->execute(array_merge($txnIds, [$tenantId]));
        foreach ($byBranch as $branchId => $firstDeletedId) {
            umrahRebuildRunningBalances($pdo, $tenantId, $branchId, $supplierId, $firstDeletedId);
        }
    }
    $pdo->commit();
    echo "\nApplied successfully.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Cleanup failed; no changes were committed: {$e->getMessage()}\n");
    exit(1);
}
