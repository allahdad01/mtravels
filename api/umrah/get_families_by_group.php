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

if (!isset($_GET['group_id']) || empty($_GET['group_id'])) {
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit;
}

$groupId = intval($_GET['group_id']);

try {
    $stmt = $pdo->prepare("
        SELECT
            f.family_id,
            f.head_of_family,
            f.contact,
            f.address,
            f.total_members,
            f.package_type,
            f.tazmin,
            f.visa_status,
            COUNT(ub.booking_id) as actual_member_count,
            COALESCE(SUM(CASE WHEN ub.status = 'active' THEN ub.sold_price ELSE 0 END), 0) as total_price,
            COALESCE(SUM(CASE WHEN ub.status = 'active' THEN ub.paid ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN ub.status = 'active' THEN ub.due ELSE 0 END), 0) as total_due,
            c.name as client_name
        FROM families f
        LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id AND ub.tenant_id = f.tenant_id AND ub.branch_id = f.branch_id
        LEFT JOIN clients c ON c.id = ub.sold_to
        WHERE f.group_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        GROUP BY f.family_id
        ORDER BY f.head_of_family
    ");
    $stmt->execute([$groupId, $tenant_id, $branch_id]);
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'families' => $families
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
