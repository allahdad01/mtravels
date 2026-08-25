<?php
/**
 * Debug: Show the full chain for orphaned bookings.
 * supplier_transactions → umrah_booking_services → umrah_fulfillments
 *
 * Usage:
 *   php debug_orphaned.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php debug_orphaned.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// All bookings with duplicate supplier transactions
$bookings = [201,202,203,204,207,208,209,210,211,212,213];

foreach ($bookings as $bkId) {
    echo "=== Booking #$bkId ===\n";

    // Supplier transactions
    $txnStmt = $pdo->prepare("
        SELECT id, supplier_id, remarks, amount, balance, transaction_date
        FROM supplier_transactions
        WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
        ORDER BY id
    ");
    $txnStmt->execute([$bkId, $targetTenant, $targetBranch]);
    $txns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Supplier transactions: " . count($txns) . "\n";
    foreach ($txns as $t) {
        echo "    TXN #{$t['id']} | {$t['remarks']} | amt={$t['amount']} | {$t['transaction_date']}\n";
    }

    // Booking services (current, after old update replaced them)
    $bsStmt = $pdo->prepare("
        SELECT id, service_type, supplier_id, base_price, sold_price
        FROM umrah_booking_services
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id
    ");
    $bsStmt->execute([$bkId, $targetTenant, $targetBranch]);
    $services = $bsStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Booking services: " . count($services) . "\n";
    foreach ($services as $s) {
        echo "    BS #{$s['id']} | type={$s['service_type']} | supplier={$s['supplier_id']} | base={$s['base_price']}\n";
    }

    // Fulfillments linked to current booking_services
    if (!empty($services)) {
        $bsIds = array_column($services, 'id');
        $bsPh = implode(',', array_fill(0, count($bsIds), '?'));
        $fulStmt = $pdo->prepare("
            SELECT f.id, f.fulfillment_type, f.status, f.supplier_id, f.booking_service_id
            FROM umrah_fulfillments f
            WHERE f.booking_service_id IN ($bsPh) AND f.tenant_id = ?
            ORDER BY f.id
        ");
        $fulStmt->execute(array_merge($bsIds, [$targetTenant]));
        $fulfillments = $fulStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Fulfillments (on current services): " . count($fulfillments) . "\n";
        foreach ($fulfillments as $f) {
            echo "    FUL #{$f['id']} | type={$f['fulfillment_type']} | status={$f['status']} | supplier={$f['supplier_id']} | bs_id={$f['booking_service_id']}\n";
        }
    }

    // Check: are there fulfillments on OLD (deleted) service IDs?
    // The old update code replaced services, so fulfillments might reference non-existent bs IDs
    $oldFulStmt = $pdo->prepare("
        SELECT f.id, f.fulfillment_type, f.status, f.supplier_id, f.booking_service_id
        FROM umrah_fulfillments f
        WHERE f.booking_service_id NOT IN (
            SELECT bs2.id FROM umrah_booking_services bs2
            WHERE bs2.booking_id = ? AND bs2.tenant_id = ?
        ) AND f.tenant_id = ?
        AND f.booking_service_id IN (
            SELECT bs3.id FROM umrah_booking_services bs3
            WHERE bs3.tenant_id = ?
        )
    ");
    // Actually, simpler: check fulfillments that reference THIS booking via the old service chain
    $oldFulStmt2 = $pdo->prepare("
        SELECT f.id, f.fulfillment_type, f.status, f.supplier_id, f.booking_service_id
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
        WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
        ORDER BY f.id
    ");
    $oldFulStmt2->execute([$bkId, $targetTenant, $targetTenant]);
    $allFulfills = $oldFulStmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "  All fulfillments (via booking_services.booking_id): " . count($allFulfills) . "\n";
    foreach ($allFulfills as $f) {
        echo "    FUL #{$f['id']} | type={$f['fulfillment_type']} | status={$f['status']} | bs_id={$f['booking_service_id']}\n";
    }

    echo "\n";
}
