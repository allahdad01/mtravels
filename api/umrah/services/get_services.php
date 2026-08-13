<?php
/**
 * Get Services API (Phase 37) — service master + dictionaries for
 * admin/umrah_catalog.php. Tenant-scoped read-only.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
umrah_require('service_manage');

$tenant_id = $_SESSION['tenant_id'];

require_once '../../../includes/db.php';

$svc = $pdo->prepare("
    SELECT s.id, s.name, s.code, s.description, s.is_active
    FROM umrah_services s
    WHERE s.tenant_id = ? ORDER BY s.name");
$svc->execute([$tenant_id]);

echo json_encode([
    'success'  => true,
    'services' => $svc->fetchAll(PDO::FETCH_ASSOC),
]);