<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

enforce_auth();
require_permission('umrah.member_create');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

require_once '../../includes/db.php';

$group_id = isset($_POST['group_id']) ? DbSecurity::validateInput($_POST['group_id'], 'int', ['min' => 1]) : null;

if (empty($group_id)) {
    echo json_encode(['success' => false, 'message' => 'Group id is required']);
    exit;
}

try {
    // Verify group exists
    $stmt = $pdo->prepare("SELECT group_id, group_name FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
    $stmt->execute([$group_id, $tenant_id, $branch_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit;
    }

    // Get all families in this group
    $stmtFamilies = $pdo->prepare("SELECT family_id FROM families WHERE group_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtFamilies->execute([$group_id, $tenant_id, $branch_id]);
    $familyIds = $stmtFamilies->fetchAll(PDO::FETCH_COLUMN);

    if (empty($familyIds)) {
        // No families, just delete the group
        $stmtDel = $pdo->prepare("DELETE FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
        $stmtDel->execute([$group_id, $tenant_id, $branch_id]);
        echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
        exit;
    }

    // Check if ALL members in the group are pending (only pending members can be deleted)
    $familyPlaceholders = implode(',', array_fill(0, count($familyIds), '?'));
    $stmtNonPending = $pdo->prepare("
        SELECT COUNT(*) FROM umrah_bookings ub
        WHERE ub.family_id IN ($familyPlaceholders)
          AND ub.tenant_id = ? AND ub.branch_id = ?
          AND ub.status != 'pending'
    ");
    $stmtNonPending->execute(array_merge($familyIds, [$tenant_id, $branch_id]));
    $nonPendingCount = (int)$stmtNonPending->fetchColumn();

    if ($nonPendingCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete group. All members must be in pending status. Please process or remove active members first.'
        ]);
        exit;
    }

    // Check if any member has main account transactions
    $stmtMainTxn = $pdo->prepare("
        SELECT COUNT(*) FROM main_account_transactions mat
        JOIN umrah_transactions ut ON mat.reference_id = ut.id
        JOIN umrah_bookings ub ON ut.umrah_booking_id = ub.booking_id
        WHERE ub.family_id IN ($familyPlaceholders)
          AND mat.transaction_of = 'umrah_transaction'
          AND mat.tenant_id = ? AND mat.branch_id = ?
          AND ub.tenant_id = ? AND ub.branch_id = ?
    ");
    $stmtMainTxn->execute(array_merge($familyIds, [$tenant_id, $branch_id, $tenant_id, $branch_id]));
    if ((int)$stmtMainTxn->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete group. Some members have main account transactions. Please delete those transactions first.'
        ]);
        exit;
    }

    // Safe to cascade delete
    $pdo->beginTransaction();

    // Get all member booking IDs for this group
    $stmtMembers = $pdo->prepare("
        SELECT ub.booking_id, ub.sold_to, ub.paid_to, ub.currency, ub.status, c.client_type
        FROM umrah_bookings ub
        JOIN families f ON ub.family_id = f.family_id
        JOIN clients c ON ub.sold_to = c.id
        WHERE f.group_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
          AND f.tenant_id = ? AND f.branch_id = ?
          AND c.tenant_id = ? AND c.branch_id = ?
    ");
    $stmtMembers->execute([$group_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($members as $member) {
        $booking_id = $member['booking_id'];
        $client_id = $member['sold_to'];
        $currency = $member['currency'];
        $client_type = $member['client_type'];
        $mainAccountId = $member['paid_to'];
        $isActiveStatus = ($member['status'] === 'active');

        // ==================== Delete client transactions (Type 1: booking-level) ====================
        $stmtCt1 = $pdo->prepare("
            SELECT id, amount, type FROM client_transactions
            WHERE client_id = ? AND transaction_of = 'umrah' AND reference_id = ?
            AND tenant_id = ? AND branch_id = ? ORDER BY id ASC
        ");
        $stmtCt1->execute([$client_id, $booking_id, $tenant_id, $branch_id]);
        $clientTxns1 = $stmtCt1->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clientTxns1 as $ct) {
            $amount = abs($ct['amount']);
            if ($isActiveStatus && $client_type === 'regular') {
                $balanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                $op = ($ct['type'] == 'debit') ? '+' : '-';
                $pdo->prepare("UPDATE client_transactions SET balance = balance $op ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$amount, $client_id, $ct['id'], $currency, $tenant_id, $branch_id]);
                $pdo->prepare("UPDATE clients SET $balanceField = $balanceField $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$amount, $client_id, $tenant_id, $branch_id]);
            }
            $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$ct['id'], $tenant_id, $branch_id]);
        }

        // ==================== Delete client transactions (Type 2: payment-level) ====================
        $stmtUtIds = $pdo->prepare("SELECT id FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtUtIds->execute([$booking_id, $tenant_id, $branch_id]);
        $umrahTxnIds = $stmtUtIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($umrahTxnIds)) {
            $ph2 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
            $stmtCt2 = $pdo->prepare("
                SELECT id, amount, type FROM client_transactions
                WHERE client_id = ? AND transaction_of = 'umrah_transaction'
                AND reference_id IN ($ph2) AND tenant_id = ? AND branch_id = ? ORDER BY id ASC
            ");
            $p2 = array_merge([$client_id], $umrahTxnIds, [$tenant_id, $branch_id]);
            $stmtCt2->execute($p2);
            $clientTxns2 = $stmtCt2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientTxns2 as $ct) {
                $amount = abs($ct['amount']);
                if ($isActiveStatus && $client_type === 'regular') {
                    $balanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                    $op = ($ct['type'] == 'debit') ? '+' : '-';
                    $pdo->prepare("UPDATE client_transactions SET balance = balance $op ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $client_id, $ct['id'], $currency, $tenant_id, $branch_id]);
                    $pdo->prepare("UPDATE clients SET $balanceField = $balanceField $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $client_id, $tenant_id, $branch_id]);
                }
                $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$ct['id'], $tenant_id, $branch_id]);
            }
        }

        // ==================== Delete supplier transactions ====================
        // Get supplier_id
        $stmtSup = $pdo->prepare("
            SELECT COALESCE(f.supplier_id, ubs.supplier_id) AS supplier_id
            FROM umrah_booking_services ubs
            LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
              AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
            WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?
              AND COALESCE(f.supplier_id, ubs.supplier_id) IS NOT NULL
            LIMIT 1
        ");
        $stmtSup->execute([$booking_id, $tenant_id, $branch_id]);
        $supRow = $stmtSup->fetch(PDO::FETCH_ASSOC);
        $supplier_id = $supRow ? intval($supRow['supplier_id']) : 0;

        if ($supplier_id > 0) {
            // Supplier type
            $stmtST = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtST->execute([$supplier_id, $tenant_id, $branch_id]);
            $supType = $stmtST->fetchColumn();

            $allSupTxns = [];

            // Type 1: booking-level
            $stmtSt1 = $pdo->prepare("
                SELECT id, amount, transaction_type FROM supplier_transactions
                WHERE supplier_id = ? AND transaction_of = 'umrah' AND reference_id = ?
                AND tenant_id = ? AND branch_id = ?
            ");
            $stmtSt1->execute([$supplier_id, $booking_id, $tenant_id, $branch_id]);
            $allSupTxns = array_merge($allSupTxns, $stmtSt1->fetchAll(PDO::FETCH_ASSOC));

            // Type 2: payment-level
            if (!empty($umrahTxnIds)) {
                $ph3 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
                $stmtSt2 = $pdo->prepare("
                    SELECT id, amount, transaction_type FROM supplier_transactions
                    WHERE supplier_id = ? AND transaction_of = 'umrah_transaction'
                    AND reference_id IN ($ph3) AND tenant_id = ? AND branch_id = ?
                ");
                $p3 = array_merge([$supplier_id], $umrahTxnIds, [$tenant_id, $branch_id]);
                $stmtSt2->execute($p3);
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

        // ==================== Delete main account transactions ====================
        if ($mainAccountId && $mainAccountId > 0 && !empty($umrahTxnIds)) {
            $ph4 = implode(',', array_fill(0, count($umrahTxnIds), '?'));
            $stmtMainTxns = $pdo->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.main_account_id
                FROM main_account_transactions mat
                WHERE mat.reference_id IN ($ph4) AND mat.transaction_of = 'umrah_transaction'
                AND mat.tenant_id = ? AND mat.branch_id = ?
                ORDER BY mat.id ASC
            ");
            $p4 = array_merge($umrahTxnIds, [$tenant_id, $branch_id]);
            $stmtMainTxns->execute($p4);
            $mainTxns = $stmtMainTxns->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mainTxns as $mt) {
                $amount = abs($mt['amount']);
                $main_type = strtolower($mt['type']);
                $main_currency = $mt['currency'];
                $actual_main_id = ($mt['main_account_id'] && $mt['main_account_id'] > 0) ? intval($mt['main_account_id']) : $mainAccountId;

                $balanceField = match($main_currency) {
                    'USD' => 'usd_balance', 'AFS' => 'afs_balance', 'EUR' => 'euro_balance',
                    'DARHAM' => 'darham_balance', 'SAR' => 'sar_balance', default => null
                };

                if ($balanceField) {
                    $op = ($main_type === 'credit') ? '-' : '+';
                    $pdo->prepare("UPDATE main_account_transactions SET balance = balance $op ? WHERE main_account_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $actual_main_id, $mt['id'], $main_currency, $tenant_id, $branch_id]);
                    $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField $op ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$amount, $actual_main_id, $tenant_id, $branch_id]);
                }

                $pdo->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$mt['id'], $tenant_id, $branch_id]);
            }
        }

        // ==================== Delete notifications ====================
        $pdo->prepare("
            DELETE FROM notifications
            WHERE transaction_id IN (SELECT id FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?)
            AND tenant_id = ? AND branch_id = ?
        ")->execute([$booking_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);

        // ==================== Delete umrah transactions ====================
        $pdo->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);

        // ==================== Delete fulfillments and services ====================
        $pdo->prepare("
            DELETE uf FROM umrah_fulfillments uf
            JOIN umrah_booking_services ubs ON uf.booking_service_id = ubs.id
            WHERE ubs.booking_id = ? AND uf.tenant_id = ?
        ")->execute([$booking_id, $tenant_id]);

        $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?")
            ->execute([$booking_id, $tenant_id]);

        // ==================== Delete refund records ====================
        $pdo->prepare("DELETE FROM umrah_refunds WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);

        // ==================== Delete the booking ====================
        $pdo->prepare("DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?")
            ->execute([$booking_id, $tenant_id, $branch_id]);
    }

    // ==================== Delete all families in group ====================
    $pdo->prepare("DELETE FROM families WHERE group_id = ? AND tenant_id = ? AND branch_id = ?")
        ->execute([$group_id, $tenant_id, $branch_id]);

    // ==================== Delete the group ====================
    $pdo->prepare("DELETE FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)")
        ->execute([$group_id, $tenant_id, $branch_id]);

    $pdo->commit();

    // Activity log
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $old_values = json_encode(['group_id' => $group_id, 'group_name' => $group['group_name'], 'families_count' => count($familyIds), 'members_count' => count($members)]);
    $pdo->prepare("
        INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'umrah_groups', ?, ?, '{}', ?, ?, NOW(), ?, ?)
    ")->execute([$user_id, $group_id, $old_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

    echo json_encode(['success' => true, 'message' => 'Group, families, and all members deleted successfully']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
