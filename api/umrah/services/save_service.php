<?php
/**
 * Service Master API — services.
 *   entity=service              action=save | toggle | delete
 * Every write runs inside one transaction with an umrah_audit row.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
require_permission('umrah.service_manage');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../../includes/db.php';

$entity = $_POST['entity'] ?? '';
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string') : 'save';
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int') : 0;

if (!in_array($entity, ['service'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid entity.']);
    exit;
}
if (!in_array($action, ['save', 'toggle', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$pdo->beginTransaction();
try {

    // ============================ SERVICE =================================
    if ($action === 'toggle') {
            $s = $pdo->prepare("SELECT is_active FROM umrah_services WHERE id = ? AND tenant_id = ?");
            $s->execute([$id, $tenant_id]);
            $cur = $s->fetchColumn();
            if ($cur === false) throw new Exception('Service not found.');
            $new = $cur ? 0 : 1;
            $pdo->prepare("UPDATE umrah_services SET is_active = ? WHERE id = ? AND tenant_id = ?")->execute([$new, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_services', $id, ['is_active' => $cur], ['is_active' => $new]);
            echo json_encode(['success' => true, 'message' => 'Service status updated.', 'is_active' => $new]);
            $pdo->commit();
            exit;
        }
        if ($action === 'delete') {
            if (!$id) throw new Exception('Service ID required.');
            $s = $pdo->prepare("SELECT COUNT(*) FROM umrah_booking_services ubs JOIN umrah_bookings ub ON ub.booking_id = ubs.booking_id WHERE ubs.service_id = ? AND ub.tenant_id = ?");
            $s->execute([$id, $tenant_id]);
            if ((int)$s->fetchColumn() > 0) throw new Exception('Service has sold bookings — deactivate it instead of deleting.');
            $s = $pdo->prepare("SELECT COUNT(*) FROM umrah_package_services WHERE service_id = ? AND tenant_id = ?");
            $s->execute([$id, $tenant_id]);
            if ((int)$s->fetchColumn() > 0) throw new Exception('Service is used by a package line — deactivate it instead of deleting.');
            $old = $pdo->prepare("SELECT * FROM umrah_services WHERE id = ? AND tenant_id = ?");
            $old->execute([$id, $tenant_id]);
            $row = $old->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$row) throw new Exception('Service not found.');
            $pdo->prepare("DELETE FROM umrah_services WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
            umrah_audit($pdo, 'delete', 'umrah_services', $id, $row, []);
            echo json_encode(['success' => true, 'message' => 'Service deleted.']);
            $pdo->commit();
            exit;
        }
        $name = trim(DbSecurity::validateInput($_POST['name'] ?? '', 'string'));
        $code = trim(DbSecurity::validateInput($_POST['code'] ?? '', 'string'));
        $description = trim(DbSecurity::validateInput($_POST['description'] ?? '', 'string'));
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $pricing_unit = 'per_person';
        $is_active = isset($_POST['is_active']) ? ((int)$_POST['is_active'] ? 1 : 0) : 1;
        if ($name === '') throw new Exception('Service name is required.');
        if ($category_id) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM umrah_service_categories WHERE id = ? AND tenant_id = ?");
            $s->execute([$category_id, $tenant_id]);
            if (!(int)$s->fetchColumn()) throw new Exception('Category not found for this tenant.');
        }
        if ($code !== '') {
            $s = $pdo->prepare("SELECT COUNT(*) FROM umrah_services WHERE tenant_id = ? AND code = ? AND (? IS NULL OR id != ?)");
            $s->execute([$tenant_id, $code, $id > 0 ? $id : null, $id > 0 ? $id : null]);
            if ((int)$s->fetchColumn() > 0) throw new Exception('A service with this code already exists for this tenant.');
        }
        if ($id) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM umrah_services WHERE id = ? AND tenant_id = ?");
            $s->execute([$id, $tenant_id]);
            if (!(int)$s->fetchColumn()) throw new Exception('Service not found.');
            $pdo->prepare("UPDATE umrah_services SET category_id = ?, name = ?, code = ?, description = ?, pricing_unit = ?, is_active = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$category_id, $name, $code ?: null, $description ?: null, $pricing_unit, $is_active, $id, $tenant_id]);
            umrah_audit($pdo, 'update', 'umrah_services', $id, [], ['name' => $name, 'code' => $code, 'pricing_unit' => $pricing_unit, 'is_active' => $is_active]);
        } else {
            $pdo->prepare("INSERT INTO umrah_services (tenant_id, branch_id, category_id, name, code, description, pricing_unit, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$tenant_id, $branch_id, $category_id, $name, $code ?: null, $description ?: null, $pricing_unit, $is_active, $user_id]);
            $id = (int)$pdo->lastInsertId();
            umrah_audit($pdo, 'create', 'umrah_services', $id, [], ['name' => $name, 'code' => $code, 'pricing_unit' => $pricing_unit]);
        }
        echo json_encode(['success' => true, 'message' => 'Service saved.', 'id' => $id]);
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