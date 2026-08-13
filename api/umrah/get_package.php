<?php
/**
 * Get Package API — resolves an umrah package into sellable service lines
 * for the Add Member / Add Booking modal.
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

$package_id = isset($_GET['package_id']) ? DbSecurity::validateInput($_GET['package_id'], 'int') : 0;

if (!$package_id) {
    echo json_encode(['success' => false, 'message' => 'Package ID is required.']);
    exit;
}

// ---- Package header ------------------------------------------------------
$pkgStmt = $pdo->prepare("SELECT id, tenant_id, name, code, description, status
    FROM umrah_packages
    WHERE id = ? AND tenant_id = ? AND status = 'active'");
$pkgStmt->execute([$package_id, $tenant_id]);
$package = $pkgStmt->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    echo json_encode(['success' => false, 'message' => 'Package not found or inactive.']);
    exit;
}

// ---- Package lines --------------------------------------------------------
$linesStmt = $pdo->prepare("
    SELECT ps.*, s.name AS service_name, s.code AS service_code, s.pricing_unit,
           c.name AS category_name,
           h.name AS hotel_name, rt.name AS room_type_name, mp.name AS meal_plan_name
    FROM umrah_package_services ps
    JOIN umrah_services s ON ps.service_id = s.id
    LEFT JOIN umrah_service_categories c ON s.category_id = c.id
    LEFT JOIN umrah_hotels h ON ps.hotel_id = h.id
    LEFT JOIN umrah_hotel_room_types rt ON ps.room_type_id = rt.id
    LEFT JOIN umrah_hotel_meal_plans mp ON ps.meal_plan_id = mp.id
    WHERE ps.package_id = ? AND ps.tenant_id = ? AND ps.is_active = 1
    ORDER BY ps.sort_order, ps.id");
$linesStmt->execute([$package_id, $tenant_id]);
$lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

// Map price-engine category to the legacy service_type enum used by
// umrah_booking_services (ticket/visa/hotel/transport/all + combos).
// Falls back to the service code/name when the category is missing.
function mapServiceType(?string $categoryName, ?string $serviceCode = null, ?string $serviceName = null): string
{
    $map = [
        'hotel'    => 'hotel',
        'flight'   => 'ticket',
        'ticket'   => 'ticket',
        'transport'=> 'transport',
        'visa'     => 'visa',
        'ziyarat'  => 'all',
        'meal'     => 'all',
        'other'    => 'all',
    ];
    foreach ([$categoryName, $serviceCode, $serviceName] as $candidate) {
        if ($candidate === null || trim((string)$candidate) === '') {
            continue;
        }
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$candidate));
        if (isset($map[$key])) {
            return $map[$key];
        }
    }
    return 'all';
}

// ---- Build the payload -----------------------------------------------------
$resultLines = [];
foreach ($lines as $line) {

    $qty = (float)$line['quantity'] > 0 ? (float)$line['quantity'] : 1.0;

    $resultLines[] = [
        'package_service_id' => (int)$line['id'],
        'service_id'         => (int)$line['service_id'],
        'service_name'       => $line['service_name'],
        'service_code'       => $line['service_code'],
        'pricing_unit'       => $line['pricing_unit'] ?: 'per_person',
        'service_type'       => mapServiceType($line['category_name'] ?? null, $line['service_code'] ?? null, $line['service_name'] ?? null),
        'quantity'           => $qty,
        'is_required'        => (int)($line['is_required'] ?? 1),
        'hotel_id'           => !empty($line['hotel_id']) ? (int)$line['hotel_id'] : null,
        'room_type_id'       => !empty($line['room_type_id']) ? (int)$line['room_type_id'] : null,
        'hotel_name'         => $line['hotel_name'],
        'room_type_name'     => $line['room_type_name'],
        'meal_plan_name'     => $line['meal_plan_name'],
        'supplier'           => null,
        'base_currency'      => null,
        'base_per_unit'      => null,
        'exchange_rate'      => null,
    ];
}

$totals = ['base' => 0.0, 'selling' => 0.0, 'currency' => 'USD'];

echo json_encode([
    'success' => true,
    'package' => [
        'id'          => (int)$package['id'],
        'name'        => $package['name'],
        'code'        => $package['code'],
        'description' => $package['description'],
    ],
    'lines'   => $resultLines,
    'totals'  => $totals,
]);