<?php
/**
 * Save Package API (Phase 36)
 * Master data CRUD for packages and their service lines:
 *   entity=package : action=save | toggle | delete
 *   entity=line    : action=save | delete
 * Price-engine preview values (selling/base per unit) are resolved by
 * get_packages.php; this endpoint persists structure only.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
require_permission('umrah.package_manage');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../../includes/db.php';

$entity = isset($_POST['entity']) ? DbSecurity::validateInput($_POST['entity'], 'string') : '';
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string') : 'save';
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int') : 0;

if (!in_array($entity, ['package', 'line'], true) || !in_array($action, ['save', 'toggle', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid entity or action.']);
    exit;
}

$pdo->beginTransaction();
try {
    $packageExists = function (int $pid) use ($pdo, $tenant_id): bool {
        $s = $pdo->prepare("SELECT 1 FROM umrah_packages WHERE id = ? AND tenant_id = ?");
        $s->execute([$pid, $tenant_id]);
        return (bool)$s->fetchColumn();
    };

    if ($entity === 'package') {
        $name = DbSecurity::validateInput($_POST['name'] ?? '', 'string');
        $code = DbSecurity::validateInput($_POST['code'] ?? '', 'string');
        $description = DbSecurity::validateInput($_POST['description'] ?? '', 'string');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($action === 'delete') {
            if (!$id) throw new Exception('Package ID required.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE package_id = ? AND tenant_id = ?");
            $chk->execute([$id, $tenant_id]);
            if ((int)$chk->fetchColumn() > 0) throw new Exception('Package has bookings — deactivate it instead of deleting.');
            $oldRow = $pdo->prepare("SELECT * FROM umrah_packages WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$old) throw new Exception('Package not found.');
            $del = $pdo->prepare("DELETE FROM umrah_package_services WHERE package_id = ? AND tenant_id = ?");
            $del->execute([$id, $tenant_id]);
            $del = $pdo->prepare("DELETE FROM umrah_packages WHERE id = ? AND tenant_id = ?");
            $del->execute([$id, $tenant_id]);
            umrah_audit($pdo, 'delete', 'umrah_packages', $id, $old, []);
            echo json_encode(['success' => true, 'message' => 'Package deleted.']);
            $pdo->commit();
            exit;
        }

        if ($action === 'toggle') {
            $row = $pdo->prepare("SELECT status FROM umrah_packages WHERE id = ? AND tenant_id = ?");
            $row->execute([$id, $tenant_id]);
            $cur = $row->fetchColumn();
            if ($cur === false) throw new Exception('Package not found.');
            $new = $cur === 'active' ? 'inactive' : 'active';
            $upd = $pdo->prepare("UPDATE umrah_packages SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $upd->execute([$new, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_packages', $id, ['status' => $cur], ['status' => $new]);
            echo json_encode(['success' => true, 'message' => 'Package status updated.', 'status' => $new]);
            $pdo->commit();
            exit;
        }

        if (!$name) throw new Exception('Package name is required.');
        if (!$code) throw new Exception('Package code is required.');
        $dup = $pdo->prepare("SELECT COUNT(*) FROM umrah_packages WHERE code = ? AND tenant_id = ? AND id <> ?");
        $dup->execute([$code, $tenant_id, $id]);
        if ((int)$dup->fetchColumn() > 0) throw new Exception('Package code already exists for this tenant.');

        if ($id) {
            if (!$packageExists($id)) throw new Exception('Package not found.');
            $oldRow = $pdo->prepare("SELECT * FROM umrah_packages WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $upd = $pdo->prepare("UPDATE umrah_packages SET name=?, code=?, description=?, status=?, updated_at=NOW()
                                  WHERE id = ? AND tenant_id = ?");
            $upd->execute([$name, $code, $description, $status, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_packages', $id, $old, [
                'name' => $name, 'code' => $code, 'description' => $description, 'status' => $status,
            ]);
            echo json_encode(['success' => true, 'message' => 'Package updated.', 'id' => $id]);
        } else {
            $ins = $pdo->prepare("INSERT INTO umrah_packages (tenant_id, branch_id, name, code, description, status, created_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$tenant_id, $branch_id, $name, $code, $description, $status, $user_id]);
            $id = (int)$pdo->lastInsertId();
            umrah_audit($pdo, 'add', 'umrah_packages', $id, [], [
                'name' => $name, 'code' => $code, 'description' => $description, 'status' => $status,
            ]);
            echo json_encode(['success' => true, 'message' => 'Package created.', 'id' => $id]);
        }
        $pdo->commit();
        exit;
    }

    // entity === 'line'
    $package_id = DbSecurity::validateInput($_POST['package_id'] ?? 0, 'int');
    $service_id = DbSecurity::validateInput($_POST['service_id'] ?? 0, 'int');
    $hotel_id = !empty($_POST['hotel_id']) ? DbSecurity::validateInput($_POST['hotel_id'], 'int') : null;
    $room_type_id = !empty($_POST['room_type_id']) ? DbSecurity::validateInput($_POST['room_type_id'], 'int') : null;
    $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : 1.0;
    $is_required = !empty($_POST['is_required']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? (int)$_POST['sort_order'] : 0;

    if ($action === 'delete') {
        if (!$id) throw new Exception('Line ID required.');
        $oldRow = $pdo->prepare("SELECT * FROM umrah_package_services WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$old) throw new Exception('Package line not found.');
        $del = $pdo->prepare("DELETE FROM umrah_package_services WHERE id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        umrah_audit($pdo, 'delete', 'umrah_package_services', $id, $old, []);
        echo json_encode(['success' => true, 'message' => 'Package line removed.']);
        $pdo->commit();
        exit;
    }

    if (!$package_id || !$packageExists($package_id)) throw new Exception('Package not found for this tenant.');
    if (!$service_id) throw new Exception('Service is required.');
    $svc = $pdo->prepare("SELECT 1 FROM umrah_services WHERE id = ? AND tenant_id = ? AND is_active = 1");
    $svc->execute([$service_id, $tenant_id]);
    if (!$svc->fetchColumn()) throw new Exception('Service not found or inactive for this tenant.');
    if ($quantity <= 0) throw new Exception('Quantity must be greater than zero.');
    if ($hotel_id) {
        $h = $pdo->prepare("SELECT 1 FROM umrah_hotels WHERE id = ? AND tenant_id = ? AND status = 'active'");
        $h->execute([$hotel_id, $tenant_id]);
        if (!$h->fetchColumn()) throw new Exception('Hotel not found or inactive for this tenant.');
    }
    if ($room_type_id) {
        $rt = $pdo->prepare("SELECT 1 FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ? AND status = 'active'");
        $rt->execute([$room_type_id, $tenant_id]);
        if (!$rt->fetchColumn()) throw new Exception('Room type not found or inactive for this tenant.');
    }
    if (!$sort_order) {
        $mx = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM umrah_package_services WHERE package_id = ? AND tenant_id = ?");
        $mx->execute([$package_id, $tenant_id]);
        $sort_order = (int)$mx->fetchColumn();
    }

    if ($id) {
        $oldRow = $pdo->prepare("SELECT * FROM umrah_package_services WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$old) throw new Exception('Package line not found.');
        $upd = $pdo->prepare("UPDATE umrah_package_services
                              SET package_id=?, service_id=?, hotel_id=?, room_type_id=?, quantity=?,
                                  is_required=?, sort_order=?, updated_at=NOW()
                              WHERE id = ? AND tenant_id = ?");
        $upd->execute([$package_id, $service_id, $hotel_id, $room_type_id, $quantity, $is_required, $sort_order, $id, $tenant_id]);
        umrah_audit($pdo, 'update', 'umrah_package_services', $id, $old, [
            'package_id' => $package_id, 'service_id' => $service_id, 'hotel_id' => $hotel_id,
            'room_type_id' => $room_type_id, 'quantity' => $quantity, 'is_required' => $is_required, 'sort_order' => $sort_order,
        ]);
        echo json_encode(['success' => true, 'message' => 'Package line updated.', 'id' => $id]);
    } else {
        $ins = $pdo->prepare("INSERT INTO umrah_package_services
                              (tenant_id, branch_id, package_id, service_id, hotel_id, room_type_id, quantity, is_required, sort_order)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$tenant_id, $branch_id, $package_id, $service_id, $hotel_id, $room_type_id, $quantity, $is_required, $sort_order]);
        $id = (int)$pdo->lastInsertId();
        umrah_audit($pdo, 'add', 'umrah_package_services', $id, [], [
            'package_id' => $package_id, 'service_id' => $service_id, 'hotel_id' => $hotel_id,
            'room_type_id' => $room_type_id, 'quantity' => $quantity, 'is_required' => $is_required, 'sort_order' => $sort_order,
        ]);
        echo json_encode(['success' => true, 'message' => 'Package line added.', 'id' => $id]);
    }
    $pdo->commit();
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}