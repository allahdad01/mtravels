<?php
/**
 * AJAX endpoint to move a member (umrah_booking) from one family to another.
 *
 * POST parameters:
 *   booking_id  - the member to move (required)
 *   target_family_id - the destination family (required)
 *
 * The endpoint:
 *   1. Validates both records belong to the same tenant/branch
 *   2. Updates umrah_bookings.family_id
 *   3. Optionally updates date_change_umrah.family_id if records exist
 *   4. Recalculates denormalized totals for both source and destination families
 *   5. Logs the activity
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

$bookingId    = intval($_POST['booking_id'] ?? 0);
$targetFamilyId = intval($_POST['target_family_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}
if (!$targetFamilyId) {
    echo json_encode(['success' => false, 'message' => 'Target family ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch the current booking
    $stmt = $pdo->prepare("SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$bookingId, $tenant_id, $branch_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    $sourceFamilyId = intval($booking['family_id']);

    // Cannot move if already in the target family
    if ($sourceFamilyId === $targetFamilyId) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Member is already in this family']);
        exit;
    }

    // 2. Validate the target family exists and belongs to the same tenant/branch
    $famStmt = $pdo->prepare("SELECT family_id, head_of_family FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $famStmt->execute([$targetFamilyId, $tenant_id, $branch_id]);
    $targetFamily = $famStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetFamily) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Target family not found']);
        exit;
    }

    // 3. Update the booking's family_id
    $oldFamilyId = $sourceFamilyId;
    $updateStmt = $pdo->prepare("UPDATE umrah_bookings SET family_id = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $updateStmt->execute([$targetFamilyId, $bookingId, $tenant_id, $branch_id]);

    // 4. Update date_change_umrah.family_id if records exist for this booking
    $dcStmt = $pdo->prepare("UPDATE date_change_umrah SET family_id = ? WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $dcStmt->execute([$targetFamilyId, $bookingId, $tenant_id, $branch_id]);

    // 5. Re-include all excluded services — the new family starts fresh
    $pdo->prepare("UPDATE umrah_booking_services SET is_excluded = 0 WHERE booking_id = ? AND is_excluded = 1")
        ->execute([$bookingId]);

    // 5. Recalculate totals for both source and destination families
    recalcFamilyTotals($pdo, $tenant_id, $sourceFamilyId);
    recalcFamilyTotals($pdo, $tenant_id, $targetFamilyId);

    // 6. Activity logging
    $oldValues = json_encode([
        'family_id' => $oldFamilyId,
        'member_name' => $booking['name']
    ]);
    $newValues = json_encode([
        'family_id' => $targetFamilyId,
        'target_family_name' => $targetFamily['head_of_family']
    ]);

    $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update', 'umrah_bookings', ?, ?, ?, NOW(), ?, ?)
    ")->execute([$userId, $userIp, $userAgent, $bookingId, $oldValues, $newValues, $tenant_id, $branch_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Member moved successfully',
        'source_family_id' => $oldFamilyId,
        'target_family_id' => $targetFamilyId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error moving member: ' . $e->getMessage()]);
}

// ---- Helper: recalculate family totals from active/pending members ----
function recalcFamilyTotals($pdo, int $tenantId, int $familyId): void
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(sold_price), 0) AS total_price,
               COALESCE(SUM(paid), 0)       AS total_paid,
               COALESCE(SUM(received_bank_payment), 0) AS total_bank,
               COALESCE(SUM(due), 0)         AS total_due,
               COUNT(*) AS member_count
        FROM umrah_bookings
        WHERE family_id = ? AND tenant_id = ?
          AND status NOT IN ('refunded', 'cancelled')");
    $stmt->execute([$familyId, $tenantId]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("
        UPDATE families SET
            total_members = ?,
            total_price = ?,
            total_paid = ?,
            total_paid_to_bank = ?,
            total_due = ?
        WHERE family_id = ? AND tenant_id = ?
    ")->execute([
        $totals['member_count'],
        $totals['total_price'],
        $totals['total_paid'] + $totals['total_bank'],
        $totals['total_bank'],
        $totals['total_due'],
        $familyId, $tenantId
    ]);
}
