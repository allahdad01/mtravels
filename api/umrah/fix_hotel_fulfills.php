<?php
/**
 * Insert missing hotel fulfillments for bookings 207-211.
 * Copies fulfillment structure from booking #201 (FUL #427) as template.
 *
 * Usage:
 *   php fix_hotel_fulfills.php --tenant 28 --branch 21 --dry-run
 *   php fix_hotel_fulfills.php --tenant 28 --branch 21
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php fix_hotel_fulfills.php --tenant <id> --branch <id> [--dry-run]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$sourceFulId = 427; // Working hotel fulfillment from booking #201
$missingBookings = [207, 208, 209, 210, 211];

// Get source fulfillment
$srcFulStmt = $pdo->prepare("
    SELECT f.* FROM umrah_fulfillments f WHERE f.id = ? AND f.tenant_id = ?
");
$srcFulStmt->execute([$sourceFulId, $targetTenant]);
$srcFul = $srcFulStmt->fetch(PDO::FETCH_ASSOC);
if (!$srcFul) { fwrite(STDERR, "Source FUL #{$sourceFulId} not found\n"); exit(1); }

// Get source hotel details
$srcHotelStmt = $pdo->prepare("SELECT * FROM umrah_hotel_fulfillments WHERE fulfillment_id = ? AND tenant_id = ?");
$srcHotelStmt->execute([$sourceFulId, $targetTenant]);
$srcHotel = $srcHotelStmt->fetch(PDO::FETCH_ASSOC);

echo "Source: FUL #{$sourceFulId} | supplier={$srcFul['supplier_id']} | cost={$srcFul['supplier_cost']} | status={$srcFul['status']}\n";
if ($srcHotel) {
    echo "  Hotel: id={$srcHotel['hotel_id']} | room={$srcHotel['room_type_id']} | in={$srcHotel['check_in']} | out={$srcHotel['check_out']} | nights={$srcHotel['nights']}\n";
}
echo "\n";

$inserted = 0;

foreach ($missingBookings as $bkId) {
    // Get hotel booking_service_id
    $bsStmt = $pdo->prepare("SELECT id FROM umrah_booking_services WHERE booking_id = ? AND service_type = 'hotel' AND tenant_id = ? AND branch_id = ? LIMIT 1");
    $bsStmt->execute([$bkId, $targetTenant, $targetBranch]);
    $bsId = $bsStmt->fetchColumn();
    if (!$bsId) { echo "Booking #{$bkId}: no hotel BS — SKIP\n"; continue; }

    // Get member info
    $bkStmt = $pdo->prepare("SELECT family_id, name FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $bkStmt->execute([$bkId, $targetTenant]);
    $bk = $bkStmt->fetch(PDO::FETCH_ASSOC);

    echo "Booking #{$bkId} ({$bk['name']}): BS #{$bsId} → ";

    if ($dryRun) { echo "WOULD INSERT\n"; $inserted++; continue; }

    $pdo->beginTransaction();
    try {
        // Insert fulfillment
        $fulStmt = $pdo->prepare("
            INSERT INTO umrah_fulfillments
                (tenant_id, branch_id, booking_service_id, fulfillment_type, status,
                 supplier_id, supplier_currency, supplier_cost, cost_amount, exchange_rate,
                 requested_date, planned_date, completed_date, notes, family_id, created_at)
            VALUES (?, ?, ?, 'hotel', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $fulStmt->execute([
            $targetTenant, $targetBranch, $bsId,
            $srcFul['status'], $srcFul['supplier_id'], $srcFul['supplier_currency'],
            $srcFul['supplier_cost'], $srcFul['cost_amount'], $srcFul['exchange_rate'],
            $srcFul['requested_date'], $srcFul['planned_date'], $srcFul['completed_date'],
            $srcFul['notes'], $bk['family_id']
        ]);
        $newFulId = $pdo->lastInsertId();

        // Insert hotel details
        if ($srcHotel) {
            $hStmt = $pdo->prepare("
                INSERT INTO umrah_hotel_fulfillments
                    (tenant_id, branch_id, fulfillment_id, hotel_id, room_type_id,
                     check_in, check_out, nights, nightly_rate, contract_id,
                     makkah_currency, makkah_cost, makkah_rate,
                     madinah_currency, madinah_cost, madinah_rate, extra_bed, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $hStmt->execute([
                $targetTenant, $targetBranch, $newFulId,
                $srcHotel['hotel_id'], $srcHotel['room_type_id'],
                $srcHotel['check_in'], $srcHotel['check_out'], $srcHotel['nights'],
                $srcHotel['nightly_rate'], $srcHotel['contract_id'],
                $srcHotel['makkah_currency'], $srcHotel['makkah_cost'], $srcHotel['makkah_rate'],
                $srcHotel['madinah_currency'], $srcHotel['madinah_cost'], $srcHotel['madinah_rate'],
                $srcHotel['extra_bed']
            ]);
        }

        $pdo->commit();
        echo "INSERTED FUL #{$newFulId}\n";
        $inserted++;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "[DRY RUN] Would insert {$inserted} hotel fulfillment(s).\n";
    echo "Run without --dry-run to apply.\n";
} else {
    echo "Inserted {$inserted} hotel fulfillment(s).\n";
    echo "\nNext: Re-save each fulfillment via the UI to create supplier transactions,\n";
    echo "      or the existing orphaned transactions (after cleanup) already cover the cost.\n";
}
