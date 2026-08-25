<?php
/**
 * Diagnostic: Check which supplier transactions are linked to fulfillments
 * vs orphaned, for the duplicate cleanup.
 *
 * Usage:
 *   php check_linked_txns.php --tenant 28 --branch 21 --supplier 113
 */

$targetTenant = null;
$targetBranch = null;
$targetSupplier = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
    if ($argv[$i] === '--supplier' && isset($argv[$i + 1])) $targetSupplier = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch || !$targetSupplier) {
    fwrite(STDERR, "Usage: php check_linked_txns.php --tenant <id> --branch <id> --supplier <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// Get all supplier transactions for this supplier
$txnStmt = $pdo->prepare("
    SELECT st.id, st.reference_id, st.remarks, st.amount, st.balance, st.transaction_date
    FROM supplier_transactions st
    WHERE st.transaction_of = 'umrah' AND st.tenant_id = ? AND st.branch_id = ?
      AND st.supplier_id = ?
    ORDER BY st.reference_id, st.id ASC
");
$txnStmt->execute([$targetTenant, $targetBranch, $targetSupplier]);
$allTxns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total transactions for supplier #{$targetSupplier}: " . count($allTxns) . "\n\n";

// Group by reference_id (booking_id)
$byBooking = [];
foreach ($allTxns as $t) {
    $byBooking[$t['reference_id']][] = $t;
}

foreach ($byBooking as $bookingId => $txns) {
    echo "=== Booking #{$bookingId} (" . count($txns) . " txns) ===\n";

    // Get fulfillments linked to this booking's services
    $fulfillStmt = $pdo->prepare("
        SELECT f.id AS fulfillment_id, f.fulfillment_type, f.status AS ful_status,
               f.booking_service_id, bs.service_type
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
        WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
    ");
    $fulfillStmt->execute([$bookingId, $targetTenant, $targetTenant]);
    $fulfillments = $fulfillStmt->fetchAll(PDO::FETCH_ASSOC);

    // Match each txn to a fulfillment
    foreach ($txns as $t) {
        $matched = false;
        foreach ($fulfillments as $f) {
            // Check if the txn remarks contains the fulfillment type
            if (stripos($t['remarks'], $f['fulfillment_type']) !== false) {
                $matched = true;
                echo "  TXN #{$t['id']} | amt={$t['amount']} | bal={$t['balance']} | {$t['transaction_date']}\n";
                echo "    -> LINKED to fulfillment #{$f['fulfillment_id']} ({$f['fulfillment_type']}, status={$f['ful_status']})\n";
                break;
            }
        }
        if (!$matched) {
            echo "  TXN #{$t['id']} | amt={$t['amount']} | bal={$t['balance']} | {$t['transaction_date']}\n";
            echo "    -> ORPHANED (no matching fulfillment)\n";
        }
    }
    echo "\n";
}
