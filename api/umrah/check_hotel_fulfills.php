<?php
/**
 * Check: Do bookings 207-211 have hotel fulfillments anywhere?
 * Also check if there are fulfillments on old (deleted) service IDs.
 *
 * Usage:
 *   php check_hotel_fulfills.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php check_hotel_fulfills.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$bookings = [207,208,209,210,211];

foreach ($bookings as $bkId) {
    echo "=== Booking #$bkId ===\n";

    // 1. Current booking services
    $bsStmt = $pdo->prepare("
        SELECT id, service_type, supplier_id, base_price, sold_price
        FROM umrah_booking_services
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id
    ");
    $bsStmt->execute([$bkId, $targetTenant, $targetBranch]);
    $services = $bsStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Current booking_services: " . count($services) . "\n";
    foreach ($services as $s) {
        echo "    BS #{$s['id']} | type={$s['service_type']}\n";
    }

    // 2. Fulfillments on current services
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
        echo "  Fulfillments on current services: " . count($fulfillments) . "\n";
        foreach ($fulfillments as $f) {
            echo "    FUL #{$f['id']} | type={$f['fulfillment_type']} | status={$f['status']} | supplier={$f['supplier_id']}\n";
        }
    }

    // 3. ALL fulfillments for this booking (via booking_services.booking_id)
    $allFulStmt = $pdo->prepare("
        SELECT f.id, f.fulfillment_type, f.status, f.supplier_id, f.booking_service_id,
               bs.service_type AS bs_type
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
        WHERE bs.booking_id = ? AND bs.tenant_id = ?
        ORDER BY f.id
    ");
    $allFulStmt->execute([$bkId, $targetTenant]);
    $allFulfills = $allFulStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  All fulfillments (via booking_id): " . count($allFulfills) . "\n";
    foreach ($allFulfills as $f) {
        echo "    FUL #{$f['id']} | type={$f['fulfillment_type']} | status={$f['status']} | bs_id={$f['booking_service_id']} ({$f['bs_type']})\n";
    }

    // 4. Check if there are orphaned fulfillments (on non-existent service IDs)
    $orphanFulStmt = $pdo->prepare("
        SELECT f.id, f.fulfillment_type, f.status, f.supplier_id, f.booking_service_id
        FROM umrah_fulfillments f
        WHERE f.tenant_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM umrah_booking_services bs
              WHERE bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
          )
        ORDER BY f.id
    ");
    $orphanFulStmt->execute([$targetTenant]);
    $orphanFulfills = $orphanFulStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($orphanFulfills)) {
        echo "  ORPHANED fulfillments (on deleted services): " . count($orphanFulfills) . "\n";
        foreach ($orphanFulfills as $f) {
            echo "    FUL #{$f['id']} | type={$f['fulfillment_type']} | status={$f['status']} | bs_id={$f['booking_service_id']}\n";
        }
    }

    // 5. Member name for reference
    $memStmt = $pdo->prepare("SELECT name, family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $memStmt->execute([$bkId, $targetTenant]);
    $mem = $memStmt->fetch(PDO::FETCH_ASSOC);
    echo "  Member: " . ($mem['name'] ?? 'UNKNOWN') . " (family_id=" . ($mem['family_id'] ?? 'NULL') . ")\n";

    echo "\n";
}
