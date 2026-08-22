<?php
/**
 * Save Extra Bed API — creates/removes an extra bed pseudo-member for a family.
 *
 * POST action=add  → creates a new extra bed row in umrah_bookings + booking_services
 * POST action=remove → cancels the extra bed booking row + removes its fulfillment
 *
 * Returns { success, booking_id, name, ... } on create.
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id   = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$action     = $_POST['action'] ?? 'add';
$family_id  = (int)($_POST['family_id'] ?? 0);
$booking_service_id = (int)($_POST['booking_service_id'] ?? 0);

if (!$family_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'family_id is required.']);
    exit;
}

$pdo->beginTransaction();

try {
    if ($action === 'add') {
        // ---- Create extra bed pseudo-member in umrah_bookings ----------------
        // Get the family's context (package, sold_to, currency) from an existing member
        $ctxStmt = $pdo->prepare("
            SELECT ub.package_id, ub.sold_to, ub.paid_to, ub.currency, ub.exchange_rate
            FROM umrah_bookings ub
            WHERE ub.family_id = ? AND ub.tenant_id = ?
              AND ub.status NOT IN ('refunded', 'cancelled')
              AND ub.is_extra_bed = 0
            ORDER BY ub.booking_id LIMIT 1");
        $ctxStmt->execute([$family_id, $tenant_id]);
        $ctx = $ctxStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ctx) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No active family members found to derive context.']);
            exit;
        }

        // Count existing extra beds to generate a sequence number
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND is_extra_bed = 1 AND status NOT IN ('refunded','cancelled')");
        $cntStmt->execute([$family_id, $tenant_id]);
        $ebCount = (int)$cntStmt->fetchColumn();
        $ebName = 'Extra Bed ' . ($ebCount + 1);

        $ins = $pdo->prepare("
            INSERT INTO umrah_bookings (
                tenant_id, branch_id, family_id, package_id, sold_to, paid_to,
                name, fname, gfname, relation, dob, gender, room_type, is_extra_bed,
                price, sold_price, profit, paid, due, discount, received_bank_payment,
                currency, exchange_rate, status, created_by, remarks, entry_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, '', '', 'extra_bed', NULL, '', '', 1,
                      0, 0, 0, 0, 0, 0, 0,
                      ?, 1.0000, 'active', ?, '', CURDATE())");
        $ins->execute([
            $tenant_id, $branch_id, $family_id,
            $ctx['package_id'], $ctx['sold_to'], $ctx['paid_to'],
            $ebName,
            $ctx['currency'],
            $user_id
        ]);
        $newBookingId = (int)$pdo->lastInsertId();

        // If a booking_service_id was provided, create skeleton service lines
        // for the extra bed member (copy service structure from the source member).
        if ($booking_service_id) {
            $svcStmt = $pdo->prepare("
                SELECT bs.service_type, bs.service_id, bs.pricing_unit, bs.quantity,
                       bs.is_optional, bs.sold_price, bs.currency, bs.service_rate_id,
                       bs.price_snapshot
                FROM umrah_booking_services bs
                WHERE bs.id = ? AND bs.tenant_id = ?");
            $svcStmt->execute([$booking_service_id, $tenant_id]);
            $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);

            if ($svc) {
                $insSvc = $pdo->prepare("
                    INSERT INTO umrah_booking_services (
                        tenant_id, branch_id, booking_id, service_type, service_id,
                        pricing_unit, quantity, is_optional, base_price, sold_price,
                        profit, currency, service_rate_id, price_snapshot, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'pending')");
                $insSvc->execute([
                    $tenant_id, $branch_id, $newBookingId,
                    $svc['service_type'], $svc['service_id'],
                    $svc['pricing_unit'], $svc['quantity'], $svc['is_optional'],
                    $svc['currency'], $svc['service_rate_id'], $svc['price_snapshot']
                ]);
            }
        }

        // ---- Recalculate family totals -------------------------------------------
        recalcFamilyTotals($pdo, $tenant_id, $family_id);

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'booking_id' => $newBookingId,
            'name'       => $ebName,
            'message'    => 'Extra bed created.',
        ]);

    } elseif ($action === 'remove') {
        // ---- Remove extra bed pseudo-member ------------------------------------
        $extraBedBookingId = (int)($_POST['extra_bed_booking_id'] ?? 0);
        if (!$extraBedBookingId) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'extra_bed_booking_id is required.']);
            exit;
        }

        // Verify it belongs to this family and is an extra bed
        $chk = $pdo->prepare("SELECT booking_id, family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND family_id = ? AND is_extra_bed = 1");
        $chk->execute([$extraBedBookingId, $tenant_id, $family_id]);
        if (!$chk->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Extra bed not found.']);
            exit;
        }

        // Delete fulfillments for this extra bed's services
        $pdo->prepare("
            DELETE FROM umrah_fulfillment_details
            WHERE fulfillment_id IN (
                SELECT id FROM umrah_fulfillments WHERE booking_service_id IN (
                    SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
                ) AND tenant_id = ?
            )
        ")->execute([$extraBedBookingId, $tenant_id, $tenant_id]);

        $pdo->prepare("
            DELETE FROM umrah_fulfillments
            WHERE booking_service_id IN (
                SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
            ) AND tenant_id = ?
        ")->execute([$extraBedBookingId, $tenant_id, $tenant_id]);

        // Delete the extra bed's booking services
        $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?")
            ->execute([$extraBedBookingId, $tenant_id]);

        // Delete the extra bed booking row
        $pdo->prepare("DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND is_extra_bed = 1")
            ->execute([$extraBedBookingId, $tenant_id]);

        recalcFamilyTotals($pdo, $tenant_id, $family_id);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Extra bed removed.',
        ]);

    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }

} catch (\Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ---- Helper: recalculate family totals from active members -------------------
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
            total_due = ?
        WHERE family_id = ? AND tenant_id = ?
    ")->execute([
        $totals['member_count'],
        $totals['total_price'],
        $totals['total_paid'] + $totals['total_bank'],
        $totals['total_due'],
        $familyId, $tenantId
    ]);
}
