<?php
/**
 * Get Packages API (Phase 36)
 * Management list: every package (any status) with its active service lines,
 * price-engine preview (latest best-matching rate / supplier cost), totals,
 * plus the lookup dictionaries the package editor needs.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
umrah_require('package_manage');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../../includes/db.php';

// ---- Packages -------------------------------------------------------------
$pkgs = $pdo->prepare("SELECT id, name, code, description, status FROM umrah_packages
    WHERE tenant_id = ? ORDER BY sort_order, name");
$pkgs->execute([$tenant_id]);
$packages = $pkgs->fetchAll(PDO::FETCH_ASSOC);

$linesStmt = $pdo->prepare("
    SELECT ps.*, s.name AS service_name, s.code AS service_code, s.pricing_unit,
           c.name AS category_name
    FROM umrah_package_services ps
    JOIN umrah_services s ON ps.service_id = s.id
    LEFT JOIN umrah_service_categories c ON s.category_id = c.id
    WHERE ps.package_id = ? AND ps.tenant_id = ? AND ps.is_active = 1
    ORDER BY ps.sort_order, ps.id");

$result = [];
foreach ($packages as $pkg) {
    $linesStmt->execute([$pkg['id'], $tenant_id]);
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
    $outLines = [];
    $totals = ['base' => 0.0, 'selling' => 0.0, 'currency' => 'USD'];
    foreach ($lines as $line) {
        $qty = (float)$line['quantity'] > 0 ? (float)$line['quantity'] : 1.0;
        $outLines[] = [
            'id'              => (int)$line['id'],
            'service_id'      => (int)$line['service_id'],
            'service_name'    => $line['service_name'],
            'service_code'    => $line['service_code'],
            'category_name'   => $line['category_name'],
            'pricing_unit'    => $line['pricing_unit'] ?: 'per_person',
            'quantity'        => $qty,
            'is_required'     => (int)$line['is_required'],
            'hotel_id'        => $line['hotel_id'] ? (int)$line['hotel_id'] : null,
            'room_type_id'    => $line['room_type_id'] ? (int)$line['room_type_id'] : null,
        ];
    }
    $totals['base'] = round($totals['base'], 2);

    $result[] = [
        'id'          => (int)$pkg['id'],
        'name'        => $pkg['name'],
        'code'        => $pkg['code'],
        'description' => $pkg['description'],
        'status'      => $pkg['status'],
        'lines'       => $outLines,
        'totals'      => $totals,
    ];
}

// ---- Dictionaries for the editor ------------------------------------------
$svc = $pdo->prepare("
    SELECT s.id, s.name, s.code, s.pricing_unit, s.category_id, c.name AS category_name
    FROM umrah_services s
    LEFT JOIN umrah_service_categories c ON s.category_id = c.id
    WHERE s.tenant_id = ? AND s.is_active = 1
    ORDER BY c.sort_order, s.name");
$svc->execute([$tenant_id]);

$cats = $pdo->prepare("SELECT id, name FROM umrah_service_categories WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, name");
$cats->execute([$tenant_id]);
$hotels = $pdo->prepare("SELECT id, name FROM umrah_hotels WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$hotels->execute([$tenant_id]);
$rts = $pdo->prepare("SELECT id, name FROM umrah_hotel_room_types WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$rts->execute([$tenant_id]);

echo json_encode([
    'success'  => true,
    'packages' => $result,
    'services' => $svc->fetchAll(PDO::FETCH_ASSOC),
    'categories' => $cats->fetchAll(PDO::FETCH_ASSOC),
    'hotels'   => $hotels->fetchAll(PDO::FETCH_ASSOC),
    'room_types' => $rts->fetchAll(PDO::FETCH_ASSOC),
]);