<?php
/**
 * Save Hotel API (Phase 24)
 * Master data CRUD for the hotel subsystem:
 *   entity=hotel | room_type | room
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

$entity = isset($_POST['entity']) ? DbSecurity::validateInput($_POST['entity'], 'string') : '';
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string') : 'save';
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int') : 0;

if (!in_array($entity, ['hotel', 'room_type', 'room'], true) || !in_array($action, ['save', 'toggle', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid entity or action.']);
    exit;
}

$pdo->beginTransaction();
try {
    if ($entity === 'hotel') {
        $name = DbSecurity::validateInput($_POST['name'] ?? '', 'string');
        $saudi_name = DbSecurity::validateInput($_POST['saudi_name'] ?? '', 'string');
        $city = DbSecurity::validateInput($_POST['city'] ?? '', 'string');
        $location = DbSecurity::validateInput($_POST['location'] ?? '', 'string');
        $address = DbSecurity::validateInput($_POST['address'] ?? '', 'string');
        $star_rating = DbSecurity::validateInput($_POST['star_rating'] ?? '', 'string');
        $contact = DbSecurity::validateInput($_POST['contact'] ?? '', 'string');
        $supplier_id = !empty($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int') : null;
        $notes = DbSecurity::validateInput($_POST['notes'] ?? '', 'string');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($action === 'delete') {
            if (!$id) throw new Exception('Hotel ID required.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_rooms WHERE hotel_id = ? AND tenant_id = ?");
            $chk->execute([$id, $tenant_id]);
            if ((int)$chk->fetchColumn() > 0) throw new Exception('Hotel has rooms — delete or move them first.');
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            // Room types are global — they are NOT deleted with the hotel.
            $del = $pdo->prepare("DELETE FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
            $del->execute([$id, $tenant_id]);
            umrah_audit($pdo, 'delete', 'umrah_hotels', $id, $old, []);
            echo json_encode(['success' => true, 'message' => 'Hotel deleted.']);
            $pdo->commit();
            exit;
        }

        if ($action === 'toggle') {
            $row = $pdo->prepare("SELECT status FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
            $row->execute([$id, $tenant_id]);
            $cur = $row->fetchColumn();
            if ($cur === false) throw new Exception('Hotel not found.');
            $new = $cur === 'active' ? 'inactive' : 'active';
            $upd = $pdo->prepare("UPDATE umrah_hotels SET status = ? WHERE id = ? AND tenant_id = ?");
            $upd->execute([$new, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotels', $id, ['status' => $cur], ['status' => $new]);
            echo json_encode(['success' => true, 'message' => 'Hotel status updated.', 'status' => $new]);
            $pdo->commit();
            exit;
        }

        if (!$name) throw new Exception('Hotel name is required.');
        if ($id) {
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $upd = $pdo->prepare("UPDATE umrah_hotels SET name=?, saudi_name=?, city=?, location=?, address=?,
                                         star_rating=?, contact=?, supplier_id=?, status=?, notes=?, updated_at=NOW()
                                  WHERE id = ? AND tenant_id = ?");
            $upd->execute([$name, $saudi_name, $city, $location, $address, $star_rating, $contact, $supplier_id, $status, $notes, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotels', $id, $old, [
                'name' => $name, 'saudi_name' => $saudi_name, 'city' => $city, 'location' => $location,
                'address' => $address, 'star_rating' => $star_rating, 'contact' => $contact,
                'supplier_id' => $supplier_id, 'status' => $status, 'notes' => $notes,
            ]);
            $msg = 'Hotel updated.';
        } else {
            $ins = $pdo->prepare("INSERT INTO umrah_hotels (tenant_id, branch_id, supplier_id, name, saudi_name, city,
                                    location, address, star_rating, contact, status, notes, created_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$tenant_id, $branch_id, $supplier_id, $name, $saudi_name, $city, $location, $address, $star_rating, $contact, $status, $notes, $user_id]);
            $id = (int)$pdo->lastInsertId();
            umrah_audit($pdo, 'add', 'umrah_hotels', $id, [], [
                'name' => $name, 'saudi_name' => $saudi_name, 'city' => $city, 'location' => $location,
                'address' => $address, 'star_rating' => $star_rating, 'contact' => $contact,
                'supplier_id' => $supplier_id, 'status' => $status, 'notes' => $notes,
            ]);
            $msg = 'Hotel created.';
        }
        echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
        $pdo->commit();
        exit;
    }

    if ($entity === 'room_type') {
        $name = DbSecurity::validateInput($_POST['name'] ?? '', 'string');
        $max_occupancy = !empty($_POST['max_occupancy']) ? (int)$_POST['max_occupancy'] : null;
        $bed_type = DbSecurity::validateInput($_POST['bed_type'] ?? '', 'string');
        $description = DbSecurity::validateInput($_POST['description'] ?? '', 'string');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($action === 'delete') {
            if (!$id) throw new Exception('Room type ID required.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_rooms WHERE room_type_id = ? AND tenant_id = ?");
            $chk->execute([$id, $tenant_id]);
            if ((int)$chk->fetchColumn() > 0) throw new Exception('Room type has rooms — delete them first.');
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $del = $pdo->prepare("DELETE FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ?");
            $del->execute([$id, $tenant_id]);
            umrah_audit($pdo, 'delete', 'umrah_hotel_room_types', $id, $old, []);
            echo json_encode(['success' => true, 'message' => 'Room type deleted.']);
            $pdo->commit();
            exit;
        }

        if ($action === 'toggle') {
            $row = $pdo->prepare("SELECT status FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ?");
            $row->execute([$id, $tenant_id]);
            $cur = $row->fetchColumn();
            if ($cur === false) throw new Exception('Room type not found.');
            $new = $cur === 'active' ? 'inactive' : 'active';
            $upd = $pdo->prepare("UPDATE umrah_hotel_room_types SET status = ? WHERE id = ? AND tenant_id = ?");
            $upd->execute([$new, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotel_room_types', $id, ['status' => $cur], ['status' => $new]);
            echo json_encode(['success' => true, 'message' => 'Room type status updated.', 'status' => $new]);
            $pdo->commit();
            exit;
        }

        if (!$name) throw new Exception('Room type name is required.');
        $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_room_types WHERE tenant_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id <> ?");
        $chk->execute([$tenant_id, $name, $id]);
        if ((int)$chk->fetchColumn() > 0) throw new Exception('A room type with this name already exists.');
        if ($id) {
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $upd = $pdo->prepare("UPDATE umrah_hotel_room_types SET name=?, max_occupancy=?, bed_type=?,
                                         description=?, status=?, updated_at=NOW()
                                  WHERE id = ? AND tenant_id = ?");
            $upd->execute([$name, $max_occupancy, $bed_type, $description, $status, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotel_room_types', $id, $old, [
                'name' => $name, 'max_occupancy' => $max_occupancy,
                'bed_type' => $bed_type, 'description' => $description, 'status' => $status,
            ]);
            $msg = 'Room type updated.';
        } else {
            $ins = $pdo->prepare("INSERT INTO umrah_hotel_room_types (tenant_id, branch_id, name,
                                        max_occupancy, bed_type, description, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$tenant_id, $branch_id, $name, $max_occupancy, $bed_type, $description, $status]);
            $id = (int)$pdo->lastInsertId();
            umrah_audit($pdo, 'add', 'umrah_hotel_room_types', $id, [], [
                'name' => $name, 'max_occupancy' => $max_occupancy,
                'bed_type' => $bed_type, 'description' => $description, 'status' => $status,
            ]);
            $msg = 'Room type created.';
        }
        echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
        $pdo->commit();
        exit;
    }

    if ($entity === 'room') {
        $hotel_id = DbSecurity::validateInput($_POST['hotel_id'] ?? 0, 'int');
        $room_type_id = DbSecurity::validateInput($_POST['room_type_id'] ?? 0, 'int');
        $room_number = DbSecurity::validateInput($_POST['room_number'] ?? '', 'string');
        $floor = DbSecurity::validateInput($_POST['floor'] ?? '', 'string');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'maintenance'], true) ? $_POST['status'] : 'active';
        $notes = DbSecurity::validateInput($_POST['notes'] ?? '', 'string');

        if ($action === 'delete') {
            if (!$id) throw new Exception('Room ID required.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_contract_inventory WHERE room_id = ?");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) throw new Exception('Room is in a contract inventory — remove it from the contract first.');
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_rooms WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $del = $pdo->prepare("DELETE FROM umrah_hotel_rooms WHERE id = ? AND tenant_id = ?");
            $del->execute([$id, $tenant_id]);
            umrah_audit($pdo, 'delete', 'umrah_hotel_rooms', $id, $old, []);
            echo json_encode(['success' => true, 'message' => 'Room deleted.']);
            $pdo->commit();
            exit;
        }

        if ($action === 'toggle') {
            $row = $pdo->prepare("SELECT status FROM umrah_hotel_rooms WHERE id = ? AND tenant_id = ?");
            $row->execute([$id, $tenant_id]);
            $cur = $row->fetchColumn();
            if ($cur === false) throw new Exception('Room not found.');
            $new = $cur === 'active' ? 'inactive' : ($cur === 'inactive' ? 'active' : $cur);
            $upd = $pdo->prepare("UPDATE umrah_hotel_rooms SET status = ? WHERE id = ? AND tenant_id = ?");
            $upd->execute([$new, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotel_rooms', $id, ['status' => $cur], ['status' => $new]);
            echo json_encode(['success' => true, 'message' => 'Room status updated.', 'status' => $new]);
            $pdo->commit();
            exit;
        }

        if (!$hotel_id || !$room_type_id) throw new Exception('Hotel and room type are required.');
        $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
        $chk->execute([$hotel_id, $tenant_id]);
        if (!(int)$chk->fetchColumn()) throw new Exception('Hotel not found.');
        $chk = $pdo->prepare("SELECT COUNT(*) FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ?");
        $chk->execute([$room_type_id, $tenant_id]);
        if (!(int)$chk->fetchColumn()) throw new Exception('Room type not found.');

        if (!$room_number) throw new Exception('Room number is required.');
        if ($id) {
            $oldRow = $pdo->prepare("SELECT * FROM umrah_hotel_rooms WHERE id = ? AND tenant_id = ?");
            $oldRow->execute([$id, $tenant_id]);
            $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $upd = $pdo->prepare("UPDATE umrah_hotel_rooms SET hotel_id=?, room_type_id=?, room_number=?, floor=?,
                                         status=?, notes=?, updated_at=NOW()
                                  WHERE id = ? AND tenant_id = ?");
            $upd->execute([$hotel_id, $room_type_id, $room_number, $floor, $status, $notes, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_hotel_rooms', $id, $old, [
                'hotel_id' => $hotel_id, 'room_type_id' => $room_type_id, 'room_number' => $room_number,
                'floor' => $floor, 'status' => $status, 'notes' => $notes,
            ]);
            $msg = 'Room updated.';
        } else {
            $ins = $pdo->prepare("INSERT INTO umrah_hotel_rooms (tenant_id, branch_id, hotel_id, room_type_id,
                                        room_number, floor, status, notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$tenant_id, $branch_id, $hotel_id, $room_type_id, $room_number, $floor, $status, $notes]);
            $id = (int)$pdo->lastInsertId();
            umrah_audit($pdo, 'add', 'umrah_hotel_rooms', $id, [], [
                'hotel_id' => $hotel_id, 'room_type_id' => $room_type_id, 'room_number' => $room_number,
                'floor' => $floor, 'status' => $status, 'notes' => $notes,
            ]);
            $msg = 'Room created.';
        }
        echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
        $pdo->commit();
        exit;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
