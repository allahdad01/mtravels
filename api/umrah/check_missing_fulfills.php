<?php
/**
 * Check: Do bookings 207-211 have visa/ticket fulfillments?
 *
 * Usage:
 *   php check_missing_fulfills.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php check_missing_fulfills.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$bookings = [207, 208, 209, 210, 211];

foreach ($bookings as $bkId) {
    $memStmt = $pdo->prepare("SELECT name FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $memStmt->execute([$bkId, $targetTenant]);
    $name = $memStmt->fetchColumn();

    echo "=== Booking #{$bkId} ({$name}) ===\n";

    // Check fulfillment existence per service type
    foreach (['hotel', 'ticket', 'visa', 'transport'] as $svcType) {
        $fulStmt = $pdo->prepare("
            SELECT f.id, f.status, f.supplier_id
            FROM umrah_fulfillments f
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
            WHERE bs.booking_id = ? AND bs.service_type = ? AND bs.tenant_id = ? AND f.tenant_id = ?
              AND f.fulfillment_type = ?
            LIMIT 1
        ");
        $fulStmt->execute([$bkId, $svcType, $targetTenant, $targetTenant, $svcType]);
        $ful = $fulStmt->fetch(PDO::FETCH_ASSOC);

        // Also check if a txn exists
        $txnStmt = $pdo->prepare("
            SELECT id FROM supplier_transactions
            WHERE reference_id = ? AND transaction_of = 'umrah' AND remarks LIKE ? AND tenant_id = ? AND branch_id = ?
            LIMIT 1
        ");
        $txnStmt->execute([$bkId, "%Fulfillment for {$svcType}:%", $targetTenant, $targetBranch]);
        $hasTxn = $txnStmt->fetchColumn() ? 'YES' : 'NO';

        if ($ful) {
            echo "  {$svcType}: FUL #{$ful['id']} ({$ful['status']}, supplier={$ful['supplier_id']}) | txn: {$hasTxn}\n";
        } else {
            echo "  {$svcType}: NO FULFILLMENT | txn: {$hasTxn}\n";
        }
    }
    echo "\n";
}
