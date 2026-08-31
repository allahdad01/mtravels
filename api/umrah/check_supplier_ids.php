<?php
/**
 * Check supplier assignments for all members in the group.
 * Compare working members vs the 5 broken ones.
 *
 * Usage:
 *   php check_supplier_ids.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php check_supplier_ids.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

// Get all bookings in group with group_id (from family)
$groupStmt = $pdo->prepare("
    SELECT DISTINCT f.group_id
    FROM families f
    WHERE f.tenant_id = ? AND f.branch_id = ?
      AND f.family_id IN (86)
");
$groupStmt->execute([$targetTenant, $targetBranch]);
$groupId = $groupStmt->fetchColumn();

if (!$groupId) {
    echo "No group found for family 86\n";
    exit(1);
}

echo "Group ID: {$groupId}\n\n";

// Get all members in this group
$memStmt = $pdo->prepare("
    SELECT ub.booking_id, ub.name, f.head_of_family
    FROM umrah_bookings ub
    JOIN families f ON f.family_id = ub.family_id AND f.tenant_id = ub.tenant_id
    WHERE f.group_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
      AND COALESCE(ub.is_extra_bed, 0) = 0
      AND COALESCE(ub.is_extra_transport, 0) = 0
    ORDER BY f.family_id, ub.booking_id
");
$memStmt->execute([$groupId, $targetTenant, $targetBranch]);
$members = $memStmt->fetchAll(PDO::FETCH_ASSOC);

echo count($members) . " members in group\n\n";

// Get supplier assignments for each member's booking_services
$bsStmt = $pdo->prepare("
    SELECT bs.booking_id, bs.service_type, bs.supplier_id, bs.base_price, bs.sold_price,
           s.name AS supplier_name
    FROM umrah_booking_services bs
    LEFT JOIN suppliers s ON s.id = bs.supplier_id AND s.tenant_id = bs.tenant_id
    WHERE bs.booking_id = ? AND bs.tenant_id = ? AND bs.branch_id = ?
    ORDER BY bs.service_type
");

foreach ($members as $mem) {
    echo "Booking #{$mem['booking_id']} | {$mem['name']} | family: {$mem['head_of_family']}\n";
    $bsStmt->execute([$mem['booking_id'], $targetTenant, $targetBranch]);
    $services = $bsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as $s) {
        $supLabel = $s['supplier_id'] ? "#{$s['supplier_id']} ({$s['supplier_name']})" : "EMPTY";
        echo "  {$s['service_type']}: supplier={$supLabel} | base={$s['base_price']}\n";
    }
    echo "\n";
}
