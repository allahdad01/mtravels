<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../includes/db.php');
require_once('../../admin/security.php');
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_GET['family_ids']) || empty($_GET['family_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Family IDs are required']);
    exit;
}

$familyIds = array_map('intval', explode(',', $_GET['family_ids']));
$placeholders = implode(',', array_fill(0, count($familyIds), '?'));

try {
    // Get families
    $famStmt = $pdo->prepare("
        SELECT f.family_id, f.head_of_family, f.contact, f.address, f.tazmin, f.package_type, f.visa_status,
               c.name AS client_name
        FROM families f
        LEFT JOIN umrah_bookings ub ON ub.family_id = f.family_id AND ub.tenant_id = f.tenant_id AND ub.branch_id = f.branch_id AND ub.status != 'cancelled'
        LEFT JOIN clients c ON c.id = ub.sold_to
        WHERE f.family_id IN ($placeholders) AND f.tenant_id = ? AND f.branch_id = ?
        GROUP BY f.family_id
    ");
    $famParams = array_merge($familyIds, [$tenant_id, $branch_id]);
    $famStmt->execute($famParams);
    $families = $famStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get members for all selected families
    $memStmt = $pdo->prepare("
        SELECT
            ub.booking_id,
            ub.family_id,
            ub.name,
            ub.fname,
            ub.gfname,
            ub.relation,
            ub.dob,
            ub.passport_number,
            ub.passport_expiry,
            ub.entry_date,
            ub.flight_date,
            ub.return_date,
            ub.duration,
            ub.room_type,
            ub.price,
            ub.sold_price,
            ub.discount,
            ub.paid,
            ub.due,
            ub.currency,
            ub.status,
            ub.gender,
            ub.dob,
            f.head_of_family
        FROM umrah_bookings ub
        LEFT JOIN families f ON ub.family_id = f.family_id
        WHERE ub.family_id IN ($placeholders) AND ub.tenant_id = ? AND ub.branch_id = ? AND ub.status != 'cancelled'
        ORDER BY
            FIELD(ub.family_id, " . implode(',', $familyIds) . "),
            CASE WHEN ub.name = f.head_of_family THEN 0 ELSE 1 END,
            ub.booking_id ASC
    ");
    $memParams = array_merge($familyIds, [$tenant_id, $branch_id]);
    $memStmt->execute($memParams);
    $members = $memStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group members by family_id
    $grouped = [];
    foreach ($families as &$fam) {
        $fam['members'] = [];
        $grouped[$fam['family_id']] = &$fam;
    }
    foreach ($members as $mem) {
        if (isset($grouped[$mem['family_id']])) {
            $grouped[$mem['family_id']]['members'][] = $mem;
        }
    }

    echo json_encode([
        'success' => true,
        'families' => array_values($grouped)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
