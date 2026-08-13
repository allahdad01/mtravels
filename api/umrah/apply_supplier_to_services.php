<?php
/**
 * Apply Supplier to Services API (Phase 34)
 * Bulk supplier assignment for a booking: updates the PLANNED supplier on one,
 * several, or all sold services of a booking. Per-service override afterwards
 * happens through save_fulfillment.php.
 *
 * POST:
 *   booking_id    int   (required) target booking (tenant/branch scoped)
 *   supplier_id   int   (required) new supplier (must be active, tenant-owned)
 *   service_type  str   (optional) only services with this service_type
 *   service_ids   int[] (optional) only these service ids
 *   csrf_token    str
 *
 * Responses: 200 success {success, updated}; 400/403/404 JSON failures.
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
umrah_require('fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int') : 0;
$supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int') : 0;
$service_type = isset($_POST['service_type']) && $_POST['service_type'] !== '' ? DbSecurity::validateInput($_POST['service_type'], 'string') : null;
$service_ids = isset($_POST['service_ids']) && is_array($_POST['service_ids'])
    ? array_values(array_unique(array_map('intval', array_filter($_POST['service_ids'], fn($v) => is_numeric($v)))))
    : [];

// ---- Phase 31-style integrity validation -----------------------------------
if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking is required.']);
    exit;
}
if (!$supplier_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Supplier is required.']);
    exit;
}

$bookingOk = $pdo->prepare("SELECT 1 FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))");
$bookingOk->execute([$booking_id, $tenant_id, $branch_id, $branch_id]);
if (!$bookingOk->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found for this tenant/branch.']);
    exit;
}

$supOk = $pdo->prepare("SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? AND status = 'active'");
$supOk->execute([$supplier_id, $tenant_id]);
if (!$supOk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Supplier is inactive or does not belong to this tenant.']);
    exit;
}

// ---- Scope: services of the booking (+ optional filters) --------------------
$where = "booking_id = ? AND tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))";
$params = [$booking_id, $tenant_id, $branch_id, $branch_id];
if ($service_type !== null) {
    $where .= " AND service_type = ?";
    $params[] = $service_type;
}
if (!empty($service_ids)) {
    $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
    $where .= " AND id IN ($placeholders)";
    $params = array_merge($params, $service_ids);
}

$pdo->beginTransaction();
try {
    // Snapshot current suppliers for the audit trail (before-state)
    $sel = $pdo->prepare("SELECT id, supplier_id FROM umrah_booking_services WHERE $where");
    $sel->execute($params);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

    $upd = $pdo->prepare("UPDATE umrah_booking_services SET supplier_id = ?, updated_at = NOW() WHERE $where");
    $upd->execute(array_merge([$supplier_id], $params));

    $changed = 0;
    foreach ($rows as $r) {
        if ((int)$r['supplier_id'] === $supplier_id) { continue; }
        $changed++;
        umrah_audit($pdo, 'update', 'umrah_booking_services', (int)$r['id'],
            ['supplier_id' => (int)$r['supplier_id']],
            ['supplier_id' => $supplier_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'updated' => $changed,
        'message' => 'Supplier applied to ' . $changed . ' service(s).',
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}