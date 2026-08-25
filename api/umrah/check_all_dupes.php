<?php
/**
 * Check for duplicate transactions across ALL suppliers.
 *
 * Usage:
 *   php check_all_dupes.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php check_all_dupes.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// Check all suppliers for duplicates
$suppliers = [
    112 => 'ticket',
    113 => 'hotel',
    114 => 'visa'
];

foreach ($suppliers as $supId => $type) {
    echo "=== Supplier #{$supId} ({$type}) ===\n";

    $dupStmt = $pdo->prepare("
        SELECT reference_id, remarks, COUNT(*) as cnt,
               GROUP_CONCAT(id ORDER BY id ASC) as all_ids,
               SUM(amount) as total_amt
        FROM supplier_transactions
        WHERE supplier_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
        GROUP BY reference_id, remarks
        HAVING cnt > 1
    ");
    $dupStmt->execute([$supId, $targetTenant, $targetBranch]);
    $dupes = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dupes)) {
        echo "  No duplicates found\n\n";
        continue;
    }

    echo "  Found " . count($dupes) . " duplicate groups:\n";
    foreach ($dupes as $d) {
        echo "    Booking #{$d['reference_id']} | {$d['remarks']} | {$d['cnt']} txns | total={$d['total_amt']} | IDs: {$d['all_ids']}\n";
    }

    // Also check for orphaned (no fulfillment)
    $orphanStmt = $pdo->prepare("
        SELECT st.id, st.reference_id, st.remarks, st.amount
        FROM supplier_transactions st
        WHERE st.supplier_id = ? AND st.transaction_of = 'umrah' AND st.tenant_id = ? AND st.branch_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM umrah_fulfillments f
              JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
              WHERE bs.booking_id = st.reference_id AND bs.tenant_id = st.tenant_id
                AND f.status <> 'cancelled'
                AND st.remarks LIKE CONCAT('%', f.fulfillment_type, '%')
          )
    ");
    $orphanStmt->execute([$supId, $targetTenant, $targetBranch]);
    $orphans = $orphanStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($orphans)) {
        echo "  Orphaned (no fulfillment): " . count($orphans) . "\n";
        foreach ($orphans as $o) {
            echo "    TXN #{$o['id']} | Booking #{$o['reference_id']} | {$o['remarks']} | amt={$o['amount']}\n";
        }
    }

    echo "\n";
}

// Also show supplier balances
echo "=== Supplier Balances ===\n";
$balStmt = $pdo->prepare("SELECT id, name, balance, supplier_type FROM suppliers WHERE id IN (112,113,114) AND tenant_id = ? AND branch_id = ?");
$balStmt->execute([$targetTenant, $targetBranch]);
foreach ($balStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    echo "  Supplier #{$b['id']} ({$b['name']}) [{$b['supplier_type']}]: balance={$b['balance']}\n";
}
