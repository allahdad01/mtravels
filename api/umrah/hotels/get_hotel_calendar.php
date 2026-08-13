<?php
/**
 * Get Hotel Calendar API (Phase 25)
 * Room × date grid for one hotel (optionally one room type):
 * A = Available, R = Reserved, O = Occupied, B = Blocked.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];

require_once '../../../includes/db.php';
require_once __DIR__ . '/occupancy_helper.php';

$hotel_id = isset($_GET['hotel_id']) ? DbSecurity::validateInput($_GET['hotel_id'], 'int') : 0;
$room_type_id = isset($_GET['room_type_id']) && $_GET['room_type_id'] !== '' ? DbSecurity::validateInput($_GET['room_type_id'], 'int') : null;
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : date('Y-m-t');

if (!$hotel_id) {
    echo json_encode(['success' => false, 'message' => 'Hotel ID is required.']);
    exit;
}
if ($to <= $from) {
    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
    exit;
}
if ((strtotime($to) - strtotime($from)) > 92 * 86400) {
    echo json_encode(['success' => false, 'message' => 'Date range is limited to 90 days.']);
    exit;
}

// verify hotel belongs to tenant
$hStmt = $pdo->prepare("SELECT id, name FROM umrah_hotels WHERE id = ? AND tenant_id = ?");
$hStmt->execute([$hotel_id, $tenant_id]);
$hotel = $hStmt->fetch(PDO::FETCH_ASSOC);
if (!$hotel) {
    echo json_encode(['success' => false, 'message' => 'Hotel not found.']);
    exit;
}

$data = hotelCalendarData($pdo, $tenant_id, $hotel_id, $from, $to, $room_type_id);

echo json_encode([
    'success' => true,
    'hotel' => $hotel,
    'from' => $from,
    'to' => $to,
    'days' => $data['days'],
    'rooms' => $data['rooms'],
    'grid' => $data['grid'],
    'daily' => $data['daily'],
]);
