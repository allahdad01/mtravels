<?php
/**
 * AJAX endpoint to move a family (and all its members) to another group.
 *
 * POST parameters:
 *   family_id       - the family to move (required)
 *   target_group_id - the destination group (required)
 *
 * The endpoint:
 *   1. Validates both records belong to the same tenant/branch
 *   2. Updates families.group_id
 *   3. Re-include all excluded services (fresh start in new group)
 *   4. Logs the activity
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/admin/includes/db_security.php';
require_once $base_path . '/admin/security.php';
require_once $base_path . '/includes/db.php';
require_once $base_path . '/includes/language_helpers.php';

enforce_auth();
require_permission('umrah.member_edit');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$userId    = $_SESSION['user_id'] ?? 0;
$userIp    = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$familyId      = intval($_POST['family_id'] ?? 0);
$targetGroupId = intval($_POST['target_group_id'] ?? 0);

if (!$familyId) {
    echo json_encode(['success' => false, 'message' => 'Family ID is required']);
    exit;
}
if (!$targetGroupId) {
    echo json_encode(['success' => false, 'message' => 'Target group is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch the current family
    $stmt = $pdo->prepare("SELECT * FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$familyId, $tenant_id, $branch_id]);
    $family = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$family) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Family not found']);
        exit;
    }

    $sourceGroupId = intval($family['group_id']);

    // Cannot move if already in the target group
    if ($sourceGroupId === $targetGroupId) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Family is already in this group']);
        exit;
    }

    // 2. Validate the target group exists and belongs to the same tenant/branch
    $grpStmt = $pdo->prepare("SELECT group_id, group_name FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
    $grpStmt->execute([$targetGroupId, $tenant_id, $branch_id]);
    $targetGroup = $grpStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetGroup) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Target group not found']);
        exit;
    }

    // 3. Update the family's group_id
    $updateStmt = $pdo->prepare("UPDATE families SET group_id = ? WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $updateStmt->execute([$targetGroupId, $familyId, $tenant_id, $branch_id]);

    // 4. Re-include all excluded services — the family starts fresh in the new group
    $pdo->prepare("UPDATE umrah_booking_services SET is_excluded = 0 WHERE is_excluded = 1 AND booking_id IN (SELECT booking_id FROM umrah_bookings WHERE family_id = ? AND tenant_id = ?)")
        ->execute([$familyId, $tenant_id]);

    // 5. Activity logging
    $oldValues = json_encode([
        'family_id' => $familyId,
        'group_id' => $sourceGroupId,
        'head_of_family' => $family['head_of_family']
    ]);
    $newValues = json_encode([
        'group_id' => $targetGroupId,
        'target_group_name' => $targetGroup['group_name']
    ]);

    $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update', 'families', ?, ?, ?, NOW(), ?, ?)
    ")->execute([$userId, $userIp, $userAgent, $familyId, $oldValues, $newValues, $tenant_id, $branch_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Family moved to ' . $targetGroup['group_name'] . ' successfully',
        'source_group_id' => $sourceGroupId,
        'target_group_id' => $targetGroupId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error moving family: ' . $e->getMessage()]);
}
