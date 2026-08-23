<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

enforce_auth();
require_permission('umrah.member_edit');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

require_once '../../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$bookingIds = $data['booking_ids'] ?? [];

if (empty($bookingIds) || !is_array($bookingIds)) {
    echo json_encode(['success' => false, 'message' => 'No members selected for deletion']);
    exit;
}

$bookingIds = array_map('intval', $bookingIds);
$bookingIds = array_filter($bookingIds, fn($id) => $id > 0);
$bookingIds = array_values($bookingIds);

if (empty($bookingIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid member IDs']);
    exit;
}

try {
    // Verify all bookings exist and belong to this tenant/branch
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $stmtVerify = $pdo->prepare("
        SELECT booking_id, status FROM umrah_bookings
        WHERE booking_id IN ($placeholders) AND tenant_id = ? AND branch_id = ?
    ");
    $verifyParams = array_merge($bookingIds, [$tenant_id, $branch_id]);
    $stmtVerify->execute($verifyParams);
    $existingBookings = $stmtVerify->fetchAll(PDO::FETCH_KEY_PAIR);

    $notFound = array_diff($bookingIds, array_keys($existingBookings));
    if (!empty($notFound)) {
        echo json_encode(['success' => false, 'message' => 'Some members were not found: ' . implode(', ', $notFound)]);
        exit;
    }

    // Check if any member has main account transactions
    $stmtMainTxn = $pdo->prepare("
        SELECT COUNT(*) FROM main_account_transactions mat
        JOIN umrah_transactions ut ON mat.reference_id = ut.id
        WHERE ut.umrah_booking_id IN ($placeholders)
          AND mat.transaction_of = 'umrah_transaction'
          AND mat.tenant_id = ? AND mat.branch_id = ?
    ");
    $stmtMainTxn->execute(array_merge($bookingIds, [$tenant_id, $branch_id]));
    if ((int)$stmtMainTxn->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Some members have main account transactions. Please delete those transactions first before deleting the members.']);
        exit;
    }

    // Check permission for active bookings
    foreach ($existingBookings as $bid => $status) {
        if ($status === 'active' && !user_can('umrah.delete')) {
            echo json_encode(['success' => false, 'message' => 'Only administrators can delete active members. Contact an admin.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    foreach ($bookingIds as $booking_id) {
        // Get booking details
        $stmtB = $pdo->prepare("
            SELECT ub.*, c.client_type FROM umrah_bookings ub
            JOIN clients c ON ub.sold_to = c.id
            WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
              AND c.tenant_id = ? AND c.branch_id = ?
        ");
        $stmtB->execute([$booking_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
        $booking = $stmtB->fetch(PDO::FETCH_ASSOC);
        if (!$booking) continue;

        $client_id = $booking['sold_to'];
        $currency = $booking['currency'];
        $client_type = $booking['client_type'];
        $mainAccountId = $booking['paid_to'];
        $isActiveStatus = ($booking['status'] === 'active');

        // Get umrah transaction IDs
        $stmtUtIds = $pdo->prepare("SELECT id FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtUtIds->execute([$booking_id, $tenant_id, $branch_id]);
        $umrahTxnIds = $stmtUtIds->fetchAll(PDO::FETCH_COLUMN);

        // ==================== Client transactions Type 1 ====================
        $stmtCt1 = $pdo->prepare("
            SELECT id, amount, type FROM client_transactions
            WHERE client_id = ? AND transaction_of = 'umrah' AND reference_id = ?
            AND tenant_id = ? AND branch_id = ? ORDER BY id ASC
        ");
        $stmtCt1->execute([$client_id, $booking_id, $tenant_id, $branch_id]);
        foreach ($stmtCt1->fetchAll(PDO::FETCH_ASSOC) as $ct) {
            $amount = abs($ct['amount']);
            if ($isActiveStatus && $client_type === 'regular') {
                $bf = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                $op = ($ct['type'] == 'debit') ? '+' : '-';
                $pdo->prepare("UPDATE client_transactions SET balance = balance $op ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$amount, $client_id, $ct['id'], $currency, $tenant_id, $branch_id]);
                $pdo->prepare("UPDATE clients SET $bf = $bf $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$amount, $client_id, $tenant_id, $branch_id]);
            }
            $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$ct['id'], $tenant_id, $branch_id]);
        }

        // ==================== Client transactions Type 2 ====================
        if (!empty($umrahTxnIds)) {
            $ph = implode(',', array_fill(0, count($umrahTxnIds), '?'));
            $stmtCt2 = $pdo->prepare("
                SELECT id, amount, type FROM client_transactions
                WHERE client_id = ? AND transaction_of = 'umrah_transaction'
                AND reference_id IN ($ph) AND tenant_id = ? AND branch_id = ? ORDER BY id ASC
            ");
            $stmtCt2->execute(array_merge([$client_id], $umrahTxnIds, [$tenant_id, $branch_id]));
            foreach ($stmtCt2->fetchAll(PDO::FETCH_ASSOC) as $ct) {
                $amount = abs($ct['amount']);
                if ($isActiveStatus && $client_type === 'regular') {
                    $bf = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                    $op = ($ct['type'] == 'debit') ? '+' : '-';
                    $pdo->prepare("UPDATE client_transactions SET balance = balance $op ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $client_id, $ct['id'], $currency, $tenant_id, $branch_id]);
                    $pdo->prepare("UPDATE clients SET $bf = $bf $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $client_id, $tenant_id, $branch_id]);
                }
                $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$ct['id'], $tenant_id, $branch_id]);
            }
        }

        // ==================== Supplier transactions ====================
        $stmtSup = $pdo->prepare("
            SELECT COALESCE(f.supplier_id, ubs.supplier_id) AS supplier_id
            FROM umrah_booking_services ubs
            LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
              AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
            WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?
              AND COALESCE(f.supplier_id, ubs.supplier_id) IS NOT NULL LIMIT 1
        ");
        $stmtSup->execute([$booking_id, $tenant_id, $branch_id]);
        $supRow = $stmtSup->fetch(PDO::FETCH_ASSOC);
        $supplier_id = $supRow ? intval($supRow['supplier_id']) : 0;

        if ($supplier_id > 0) {
            $stmtST = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtST->execute([$supplier_id, $tenant_id, $branch_id]);
            $supType = $stmtST->fetchColumn();

            $allSupTxns = [];
            $stmtSt1 = $pdo->prepare("SELECT id, amount, transaction_type FROM supplier_transactions WHERE supplier_id = ? AND transaction_of = 'umrah' AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtSt1->execute([$supplier_id, $booking_id, $tenant_id, $branch_id]);
            $allSupTxns = array_merge($allSupTxns, $stmtSt1->fetchAll(PDO::FETCH_ASSOC));

            if (!empty($umrahTxnIds)) {
                $ph2 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
                $stmtSt2 = $pdo->prepare("SELECT id, amount, transaction_type FROM supplier_transactions WHERE supplier_id = ? AND transaction_of = 'umrah_transaction' AND reference_id IN ($ph2) AND tenant_id = ? AND branch_id = ?");
                $stmtSt2->execute(array_merge([$supplier_id], $umrahTxnIds, [$tenant_id, $branch_id]));
                $allSupTxns = array_merge($allSupTxns, $stmtSt2->fetchAll(PDO::FETCH_ASSOC));
            }

            usort($allSupTxns, fn($a, $b) => $a['id'] <=> $b['id']);
            foreach ($allSupTxns as $st) {
                $amount = abs($st['amount']);
                if ($isActiveStatus && $supType === 'External') {
                    $op = ($st['transaction_type'] == 'Credit') ? '-' : '+';
                    $pdo->prepare("UPDATE supplier_transactions SET balance = balance $op ? WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $supplier_id, $st['id'], $tenant_id, $branch_id]);
                    $pdo->prepare("UPDATE suppliers SET balance = balance $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $supplier_id, $tenant_id, $branch_id]);
                }
                $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$st['id'], $tenant_id, $branch_id]);
            }
        }

        // ==================== Main account transactions ====================
        if ($mainAccountId && $mainAccountId > 0 && !empty($umrahTxnIds)) {
            $ph3 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
            $stmtMainTxns = $pdo->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.main_account_id
                FROM main_account_transactions mat
                WHERE mat.reference_id IN ($ph3) AND mat.transaction_of = 'umrah_transaction'
                AND mat.tenant_id = ? AND mat.branch_id = ? ORDER BY mat.id ASC
            ");
            $stmtMainTxns->execute(array_merge($umrahTxnIds, [$tenant_id, $branch_id]));
            foreach ($stmtMainTxns->fetchAll(PDO::FETCH_ASSOC) as $mt) {
                $amount = abs($mt['amount']);
                $main_type = strtolower($mt['type']);
                $main_currency = $mt['currency'];
                $actual_main_id = ($mt['main_account_id'] && $mt['main_account_id'] > 0) ? intval($mt['main_account_id']) : $mainAccountId;
                $bf = match($main_currency) {
                    'USD' => 'usd_balance', 'AFS' => 'afs_balance', 'EUR' => 'euro_balance',
                    'DARHAM' => 'darham_balance', 'SAR' => 'sar_balance', default => null
                };
                if ($bf) {
                    $op = ($main_type === 'credit') ? '-' : '+';
                    $pdo->prepare("UPDATE main_account_transactions SET balance = balance $op ? WHERE main_account_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $actual_main_id, $mt['id'], $main_currency, $tenant_id, $branch_id]);
                    $pdo->prepare("UPDATE main_account SET $bf = $bf $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $actual_main_id, $tenant_id, $branch_id]);
                }
                $pdo->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$mt['id'], $tenant_id, $branch_id]);
            }
        }

        // ==================== Notifications ====================
        if (!empty($umrahTxnIds)) {
            $ph4 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
            $pdo->prepare("DELETE FROM notifications WHERE transaction_id IN ($ph4) AND tenant_id = ? AND branch_id = ?")
                ->execute(array_merge($umrahTxnIds, [$tenant_id, $branch_id]));
        }

        // ==================== Umrah transactions ====================
        $pdo->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);

        // ==================== Fulfillments & services ====================
        $pdo->prepare("DELETE uf FROM umrah_fulfillments uf JOIN umrah_booking_services ubs ON uf.booking_service_id = ubs.id WHERE ubs.booking_id = ? AND uf.tenant_id = ?")
            ->execute([$booking_id, $tenant_id]);
        $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?")
            ->execute([$booking_id, $tenant_id]);

        // ==================== Refunds ====================
        $pdo->prepare("DELETE FROM umrah_refunds WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);

        // ==================== Booking ====================
        $pdo->prepare("DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);

        // Update family totals
        $family_id = $booking['family_id'];
        $pdo->prepare("
            UPDATE families SET
                total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ?),
                total_price = COALESCE((SELECT SUM(sold_price) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'), 0),
                total_paid = COALESCE((SELECT SUM(paid) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'), 0),
                total_due = COALESCE((SELECT SUM(due) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'), 0)
            WHERE family_id = ? AND tenant_id = ? AND branch_id = ?
        ")->execute([
            $family_id, $tenant_id, $branch_id,
            $family_id, $tenant_id, $branch_id,
            $family_id, $tenant_id, $branch_id,
            $family_id, $tenant_id, $branch_id,
            $family_id, $tenant_id, $branch_id
        ]);
    }

    $pdo->commit();

    // Activity log
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $old_values = json_encode(['booking_ids' => $bookingIds]);
    $pdo->prepare("
        INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'bulk_delete', 'umrah_bookings', ?, ?, '{}', ?, ?, NOW(), ?, ?)
    ")->execute([$user_id, $bookingIds[0], $old_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

    echo json_encode(['success' => true, 'message' => count($bookingIds) . ' member(s) deleted successfully']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
