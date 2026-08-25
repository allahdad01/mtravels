<?php
/**
 * Fix supplier_id on booking_services for bookings 207-211.
 *
 * Usage:
 *   php fix_supplier_ids.php --tenant 28 --branch 21 --dry-run
 *   php fix_supplier_ids.php --tenant 28 --branch 21
 */

$dryRun = in_array('--dry-run', $argv);
$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php fix_supplier_ids.php --tenant <id> --branch <id> [--dry-run]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// Supplier mapping (from working members)
$supplierMap = [
    'hotel'   => 113,
    'ticket'  => 112,
    'visa'    => 114,
    // transport: no supplier (Pending for everyone)
];

$bookings = [207, 208, 209, 210, 211];

$fixed = 0;

foreach ($bookings as $bkId) {
    echo "Booking #{$bkId}:\n";

    foreach ($supplierMap as $svcType => $supId) {
        $bsStmt = $pdo->prepare("
            SELECT id, supplier_id FROM umrah_booking_services
            WHERE booking_id = ? AND service_type = ? AND tenant_id = ? AND branch_id = ?
            LIMIT 1
        ");
        $bsStmt->execute([$bkId, $svcType, $targetTenant, $targetBranch]);
        $bs = $bsStmt->fetch(PDO::FETCH_ASSOC);

        if (!$bs) {
            echo "  {$svcType}: NOT FOUND — SKIP\n";
            continue;
        }

        $currentSup = $bs['supplier_id'] ?: 'NULL';
        $newSup = $supId;

        if ($bs['supplier_id'] == $supId) {
            echo "  {$svcType}: already supplier #{$supId} — OK\n";
            continue;
        }

        echo "  {$svcType}: BS #{$bs['id']} | supplier {$currentSup} → #{$supId}\n";

        if (!$dryRun) {
            $pdo->prepare("UPDATE umrah_booking_services SET supplier_id = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$supId, $bs['id'], $targetTenant, $targetBranch]);
            $fixed++;
        }
    }
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "[DRY RUN] Would fix {$fixed} supplier assignments.\n";
    echo "Run without --dry-run to apply.\n";
} else {
    echo "Fixed {$fixed} supplier assignments.\n";
}
