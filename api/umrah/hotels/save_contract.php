<?php
/**
 * Save Contract API (Phase 24)
 * Contract CRUD with its inventory (rooms under contract, with validity dates)
 * and rates (procurement cost per room type — Phase 17).
 *   action=save | toggle | delete
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
require_permission('umrah.hotel_manage');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../../includes/db.php';

$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string') : 'save';
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int') : 0;

if (!in_array($action, ['save', 'toggle', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$pdo->beginTransaction();
try {
    if ($action === 'toggle') {
        $row = $pdo->prepare("SELECT status FROM umrah_hotel_contracts WHERE id = ? AND tenant_id = ?");
        $row->execute([$id, $tenant_id]);
        $cur = $row->fetchColumn();
        if ($cur === false) throw new Exception('Contract not found.');
        $new = $cur === 'active' ? 'inactive' : 'active';
        $upd = $pdo->prepare("UPDATE umrah_hotel_contracts SET status = ? WHERE id = ? AND tenant_id = ?");
        $upd->execute([$new, $id, $tenant_id]);
        umrah_audit($pdo, 'update', 'umrah_hotel_contracts', $id, ['status' => $cur], ['status' => $new]);
        echo json_encode(['success' => true, 'message' => 'Contract status updated.', 'status' => $new]);
        $pdo->commit();
        exit;
    }

    if ($action === 'delete') {
        if (!$id) throw new Exception('Contract ID required.');
        $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_contracts WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        $del = $pdo->prepare("DELETE FROM umrah_contract_hotels WHERE contract_id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        $del = $pdo->prepare("DELETE FROM umrah_hotel_contract_rates WHERE contract_id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        $del = $pdo->prepare("DELETE FROM umrah_hotel_contract_inventory WHERE contract_id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        $del = $pdo->prepare("DELETE FROM umrah_hotel_contracts WHERE id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        umrah_audit($pdo, 'delete', 'umrah_hotel_contracts', $id, $old, []);
        echo json_encode(['success' => true, 'message' => 'Contract deleted.']);
        $pdo->commit();
        exit;
    }

    // ---- save -------------------------------------------------------------
    $supplier_id = !empty($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int') : null;
    $contract_number = DbSecurity::validateInput($_POST['contract_number'] ?? '', 'string');
    $scope = in_array($_POST['scope'] ?? '', ['entire_hotel', 'floor', 'specific_rooms'], true) ? $_POST['scope'] : 'specific_rooms';
    $contract_type = in_array($_POST['contract_type'] ?? '', ['period', 'per_trip'], true) ? $_POST['contract_type'] : 'period';
    $contract_amount = (isset($_POST['contract_amount']) && $_POST['contract_amount'] !== '' && $_POST['contract_amount'] !== null)
        ? (float)DbSecurity::validateInput($_POST['contract_amount'], 'float') : null;
    $contract_currency = strtoupper(trim((string)($_POST['contract_currency'] ?? 'USD')));
    if (!in_array($contract_currency, ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'], true)) { $contract_currency = 'USD'; }
    if ($contract_type === 'per_trip' && ($contract_amount === null || $contract_amount <= 0)) {
        throw new Exception('Per-trip contracts require a contracted amount.');
    }
    $valid_from = !empty($_POST['valid_from']) ? DbSecurity::validateInput($_POST['valid_from'], 'string') : null;
    $valid_to = !empty($_POST['valid_to']) ? DbSecurity::validateInput($_POST['valid_to'], 'string') : null;
    $payment_terms = DbSecurity::validateInput($_POST['payment_terms'] ?? '', 'string');
    $notes = DbSecurity::validateInput($_POST['notes'] ?? '', 'string');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'expired'], true) ? $_POST['status'] : 'active';

    // inventory rooms (belonging to this hotel)
    $inventory_room_ids = [];
    if (!empty($_POST['inventory_room_ids']) && is_array($_POST['inventory_room_ids'])) {
        foreach ($_POST['inventory_room_ids'] as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) $inventory_room_ids[$rid] = $rid;
        }
    }
    $inv_from = !empty($_POST['inventory_valid_from']) ? DbSecurity::validateInput($_POST['inventory_valid_from'], 'string') : $valid_from;
    $inv_to = !empty($_POST['inventory_valid_to']) ? DbSecurity::validateInput($_POST['inventory_valid_to'], 'string') : $valid_to;

    // rates: [ {hotel_id, room_type_id, cost_price, cost_currency} ]
    $rates = [];
    if (!empty($_POST['rates']) && is_array($_POST['rates'])) {
        foreach ($_POST['rates'] as $r) {
            $rt_id = (int)($r['room_type_id'] ?? 0);
            $h_id = (int)($r['hotel_id'] ?? 0);
            if ($rt_id <= 0 || $h_id <= 0) continue;
            $rates[] = [
                'hotel_id'     => $h_id,
                'room_type_id' => $rt_id,
                'cost_currency'=> strtoupper(trim((string)($r['cost_currency'] ?? 'USD'))),
                'cost_price'   => !empty($r['cost_price']) ? (float)$r['cost_price'] : null,
            ];
        }
    }

    // contract hotels (multi — e.g. Makkah + Madinah under one contract)
    $hotel_ids = [];
    if (!empty($_POST['hotel_ids']) && is_array($_POST['hotel_ids'])) {
        foreach ($_POST['hotel_ids'] as $hid) {
            $hid = (int)$hid;
            if ($hid > 0) $hotel_ids[$hid] = $hid;
        }
    }
    if (!$contract_number) throw new Exception('Contract number is required.');
    if (!$hotel_ids) throw new Exception('At least one hotel is required.');
    $ph = implode(',', array_fill(0, count($hotel_ids), '?'));
    $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotels WHERE id IN ($ph) AND tenant_id = ?");
    $chk->execute(array_merge(array_values($hotel_ids), [$tenant_id]));
    if ((int)$chk->fetchColumn() !== count($hotel_ids)) throw new Exception('Some hotels do not belong to this tenant.');
    foreach ($rates as $r) {
        if (!isset($hotel_ids[(int)$r['hotel_id']])) throw new Exception('Rate hotel must be one of the contract hotels.');
    }

    if ($inventory_room_ids) {
        $ph = implode(',', array_fill(0, count($inventory_room_ids), '?'));
        $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_rooms WHERE id IN ($ph) AND hotel_id = ? AND tenant_id = ?");
        $chk->execute(array_merge(array_values($inventory_room_ids), [$hotel_id ?? 0, $tenant_id]));
        if ((int)$chk->fetchColumn() !== count($inventory_room_ids)) throw new Exception('Some inventory rooms do not belong to this hotel.');
    }

    if ($id) {
        $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_contracts WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        $upd = $pdo->prepare("UPDATE umrah_hotel_contracts SET supplier_id=?, contract_number=?, scope=?, contract_type=?,
                                     contract_amount=?, contract_currency=?, valid_from=?, valid_to=?, payment_terms=?, notes=?, status=?, updated_at=NOW()
                              WHERE id = ? AND tenant_id = ?");
        $upd->execute([$supplier_id, $contract_number, $scope, $contract_type, $contract_amount, $contract_currency,
                       $valid_from, $valid_to, $payment_terms, $notes, $status, $id, $tenant_id]);
        umrah_audit($pdo, 'update', 'umrah_hotel_contracts', $id, $old, [
            'hotel_ids' => array_values($hotel_ids), 'supplier_id' => $supplier_id, 'contract_number' => $contract_number,
            'scope' => $scope, 'contract_type' => $contract_type,
            'contract_amount' => $contract_amount, 'contract_currency' => $contract_currency,
            'valid_from' => $valid_from, 'valid_to' => $valid_to,
            'payment_terms' => $payment_terms, 'notes' => $notes, 'status' => $status,
            'rate_count' => count($rates),
        ]);
        $msg = 'Contract updated.';
    } else {
        $ins = $pdo->prepare("INSERT INTO umrah_hotel_contracts (tenant_id, branch_id, supplier_id,
                                    contract_number, scope, contract_type, contract_amount, contract_currency,
                                    valid_from, valid_to, payment_terms, notes, status, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$tenant_id, $branch_id, $supplier_id, $contract_number, $scope, $contract_type,
                       $contract_amount, $contract_currency, $valid_from, $valid_to, $payment_terms, $notes, $status, $user_id]);
        $id = (int)$pdo->lastInsertId();
        umrah_audit($pdo, 'add', 'umrah_hotel_contracts', $id, [], [
            'hotel_ids' => array_values($hotel_ids), 'supplier_id' => $supplier_id, 'contract_number' => $contract_number,
            'scope' => $scope, 'contract_type' => $contract_type,
            'contract_amount' => $contract_amount, 'contract_currency' => $contract_currency,
            'valid_from' => $valid_from, 'valid_to' => $valid_to,
            'payment_terms' => $payment_terms, 'notes' => $notes, 'status' => $status,
            'rate_count' => count($rates),
        ]);
        $msg = 'Contract created.';
    }

    // Replace contract hotels + rates
    $del = $pdo->prepare("DELETE FROM umrah_contract_hotels WHERE contract_id = ? AND tenant_id = ?");
    $del->execute([$id, $tenant_id]);
    $insCh = $pdo->prepare("INSERT INTO umrah_contract_hotels (tenant_id, branch_id, contract_id, hotel_id)
                            VALUES (?, ?, ?, ?)");
    foreach ($hotel_ids as $hid) {
        $insCh->execute([$tenant_id, $branch_id, $id, $hid]);
    }

    $del = $pdo->prepare("DELETE FROM umrah_hotel_contract_inventory WHERE contract_id = ? AND tenant_id = ?");
    $del->execute([$id, $tenant_id]);
    $insInv = $pdo->prepare("INSERT INTO umrah_hotel_contract_inventory (tenant_id, branch_id, contract_id, room_id, valid_from, valid_to, status)
                             VALUES (?, ?, ?, ?, ?, ?, 'active')");
    foreach ($inventory_room_ids as $rid) {
        $insInv->execute([$tenant_id, $branch_id, $id, $rid, $inv_from, $inv_to]);
    }

    $del = $pdo->prepare("DELETE FROM umrah_hotel_contract_rates WHERE contract_id = ? AND tenant_id = ?");
    $del->execute([$id, $tenant_id]);
    $insRate = $pdo->prepare("INSERT INTO umrah_hotel_contract_rates (tenant_id, branch_id, contract_id, hotel_id, room_type_id,
                                    cost_currency, cost_price, status)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
    foreach ($rates as $r) {
        $insRate->execute([$tenant_id, $branch_id, $id, $r['hotel_id'], $r['room_type_id'],
                           $r['cost_currency'], $r['cost_price']]);
    }

    echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
    $pdo->commit();
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
