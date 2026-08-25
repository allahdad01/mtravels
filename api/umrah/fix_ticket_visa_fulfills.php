<?php
/**
 * Insert missing ticket + visa fulfillments for bookings 207-211.
 * Copies from booking #201 (working ticket FUL #419, visa FUL #423).
 *
 * Usage:
 *   php fix_ticket_visa_fulfills.php --tenant 28 --branch 21 --dry-run
 *   php fix_ticket_visa_fulfills.php --tenant 28 --branch 21
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php fix_ticket_visa_fulfills.php --tenant <id> --branch <id> [--dry-run]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$missingBookings = [207, 208, 209, 210, 211];

// Source templates from booking #201
$templates = [
    'ticket' => ['ful_id' => 419, 'type' => 'flight'],
    'visa'   => ['ful_id' => 423, 'type' => 'visa'],
];

foreach ($templates as $svcType => &$tpl) {
    $fulStmt = $pdo->prepare("SELECT * FROM umrah_fulfillments WHERE id = ? AND tenant_id = ?");
    $fulStmt->execute([$tpl['ful_id'], $targetTenant]);
    $tpl['ful'] = $fulStmt->fetch(PDO::FETCH_ASSOC);

    // Get typed details
    if ($svcType === 'ticket') {
        $ffStmt = $pdo->prepare("SELECT * FROM umrah_flight_fulfillments WHERE fulfillment_id = ? AND tenant_id = ?");
        $ffStmt->execute([$tpl['ful_id'], $targetTenant]);
        $tpl['typed'] = $ffStmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($svcType === 'visa') {
        // Visa has no extra table
        $tpl['typed'] = null;
    }
}
unset($tpl);

echo "Templates loaded:\n";
foreach ($templates as $svcType => $tpl) {
    echo "  {$svcType}: FUL #{$tpl['ful_id']} | supplier={$tpl['ful']['supplier_id']} | status={$tpl['ful']['status']}\n";
}
echo "\n";

$inserted = 0;

foreach ($missingBookings as $bkId) {
    // Get member info
    $bkStmt = $pdo->prepare("SELECT name, family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $bkStmt->execute([$bkId, $targetTenant]);
    $bk = $bkStmt->fetch(PDO::FETCH_ASSOC);

    echo "Booking #{$bkId} ({$bk['name']}):\n";

    foreach ($templates as $svcType => $tpl) {
        // Get booking_service_id for this type
        $bsStmt = $pdo->prepare("
            SELECT id FROM umrah_booking_services
            WHERE booking_id = ? AND service_type = ? AND tenant_id = ? AND branch_id = ?
            LIMIT 1
        ");
        $bsStmt->execute([$bkId, $svcType, $targetTenant, $targetBranch]);
        $bsId = $bsStmt->fetchColumn();

        if (!$bsId) {
            echo "  {$svcType}: NO booking_service — SKIP\n";
            continue;
        }

        // Check if fulfillment already exists
        $existsStmt = $pdo->prepare("
            SELECT id FROM umrah_fulfillments
            WHERE booking_service_id = ? AND fulfillment_type = ? AND tenant_id = ?
            LIMIT 1
        ");
        $existsStmt->execute([$bsId, $tpl['ful']['fulfillment_type'], $targetTenant]);
        if ($existsStmt->fetchColumn()) {
            echo "  {$svcType}: FUL already exists — SKIP\n";
            continue;
        }

        echo "  {$svcType}: BS #{$bsId} → ";

        if ($dryRun) {
            echo "WOULD INSERT\n";
            $inserted++;
            continue;
        }

        $pdo->beginTransaction();
        try {
            // Insert fulfillment
            $ful = $tpl['ful'];
            $fulStmt = $pdo->prepare("
                INSERT INTO umrah_fulfillments
                    (tenant_id, branch_id, booking_service_id, fulfillment_type, status,
                     supplier_id, supplier_currency, supplier_cost, cost_amount, exchange_rate,
                     requested_date, planned_date, completed_date, notes, family_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $fulStmt->execute([
                $targetTenant, $targetBranch, $bsId,
                $ful['fulfillment_type'], $ful['status'],
                $ful['supplier_id'], $ful['supplier_currency'], $ful['supplier_cost'],
                $ful['cost_amount'], $ful['exchange_rate'],
                $ful['requested_date'], $ful['planned_date'], $ful['completed_date'],
                $ful['notes'], $bk['family_id']
            ]);
            $newFulId = $pdo->lastInsertId();

            // Insert typed details (flight only)
            if ($svcType === 'ticket' && $tpl['typed']) {
                $ff = $tpl['typed'];
                $ffStmt = $pdo->prepare("
                    INSERT INTO umrah_flight_fulfillments
                        (tenant_id, branch_id, fulfillment_id, airline, flight_number,
                         pnr, ticket_number, departure_city, arrival_city,
                         departure_time, arrival_time,
                         return_flight_number, return_departure_time, return_arrival_time, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $ffStmt->execute([
                    $targetTenant, $targetBranch, $newFulId,
                    $ff['airline'], $ff['flight_number'],
                    $ff['pnr'], $ff['ticket_number'], $ff['departure_city'], $ff['arrival_city'],
                    $ff['departure_time'], $ff['arrival_time'],
                    $ff['return_flight_number'], $ff['return_departure_time'], $ff['return_arrival_time']
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
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "[DRY RUN] Would insert {$inserted} fulfillment(s).\n";
    echo "Run without --dry-run to apply.\n";
} else {
    echo "Inserted {$inserted} fulfillment(s).\n";
    echo "Existing supplier transactions will be updated in-place by the fulfillment flow.\n";
}
