<?php
/**
 * Save Multi-Member Fulfillment API
 * Applies one fulfillment (supplier, cost, status, dates, typed details)
 * to the matching sold service line across every active member of a family
 * (or of all families in the source family's group).
 *
 * Matching is package-aware: members whose package does NOT contain the same
 * service (or whose skeleton line is a different variant — different hotel /
 * room / airline) are skipped, never overwritten with mismatched data.
 * Lines whose fulfillment is already past the open phase are skipped too
 * (their cost is frozen).
 *
 * POST:
 *   booking_service_id  int    source line (its booking/family drives the scope)
 *   family_id           int    optional; defaults to the source booking's family
 *   scope               string optional; 'group' applies to all families in the
 *                        source family's group (default 'family')
 *   + all fields accepted by save_fulfillment.php
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$booking_service_id = isset($_POST['booking_service_id']) ? DbSecurity::validateInput($_POST['booking_service_id'], 'int') : 0;

if (!$booking_service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking service is required.']);
    exit;
}

// ---- Resolve source line + scope (family) ---------------------------------
$srcStmt = $pdo->prepare("
    SELECT bs.id, bs.service_type, bs.service_id, bs.quantity, bs.is_optional, bs.price_snapshot,
           ub.booking_id, ub.family_id, ub.name AS member_name, ub.status AS booking_status
    FROM umrah_booking_services bs
    JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
    WHERE bs.id = ? AND bs.tenant_id = ?");
$srcStmt->execute([$booking_service_id, $tenant_id]);
$src = $srcStmt->fetch(PDO::FETCH_ASSOC);

if (!$src) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking service not found.']);
    exit;
}

$family_id = isset($_POST['family_id']) && $_POST['family_id'] !== '' ? (int)$_POST['family_id'] : (int)$src['family_id'];
if (!$family_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking has no family — multi-member fulfillment needs a family.']);
    exit;
}

$famOk = $pdo->prepare("SELECT 1 FROM families WHERE family_id = ? AND tenant_id = ?");
$famOk->execute([$family_id, $tenant_id]);
if (!$famOk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Family does not belong to this tenant.']);
    exit;
}

// ---- Scope: family (all members of one family) or group (all families in
// the source family's group, then all their members) ------------------------
$scope = (isset($_POST['scope']) && $_POST['scope'] === 'group') ? 'group' : 'family';

$targetFamilies = [$family_id];
$familyCount = 1;
if ($scope === 'group') {
    $grpStmt = $pdo->prepare("SELECT group_id FROM families WHERE family_id = ? AND tenant_id = ?");
    $grpStmt->execute([$family_id, $tenant_id]);
    $group_id = (int)$grpStmt->fetchColumn();
    if ($group_id > 0) {
        $gfStmt = $pdo->prepare("SELECT family_id FROM families WHERE group_id = ? AND tenant_id = ?");
        $gfStmt->execute([$group_id, $tenant_id]);
        $targetFamilies = $gfStmt->fetchAll(PDO::FETCH_COLUMN);
        $familyCount = count($targetFamilies);
    }
}

// ---- Target member bookings (skip refunded/cancelled) ----------------------
$placeholders = implode(',', array_fill(0, count($targetFamilies), '?'));
$mbStmt = $pdo->prepare("
    SELECT booking_id, name FROM umrah_bookings
    WHERE family_id IN ($placeholders) AND tenant_id = ? AND status NOT IN ('refunded', 'cancelled')
    ORDER BY booking_id");
$mbStmt->execute(array_merge($targetFamilies, [$tenant_id]));
$members = $mbStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Package-aware matching --------------------------------------------------
// Different packages = different service lines. The source line's IDENTITY
// (catalog service id, or type + optional flag for skeleton lines) is matched
// against every target member. Typed guards keep variants apart: a member whose
// hotel line points at a different hotel/room (or a flight on a different
// airline) is skipped, not overwritten. Lines whose fulfillment is already past
// the open phase are skipped too (their cost is frozen).
$cat = resolveFulfillmentType($pdo, $tenant_id, (string)$src['service_type'], $src['service_id'], null);
$openFulfillmentStatuses = ['pending', 'requested', 'assigned', 'not_assigned', 'reserved', 'not_applied', 'applied', 'processing', 'confirmed', 'ticketed', 'issued', 'not_ticketed'];

$srcIdentity = '';
$srcParams = [];
if ($src['service_id'] !== null) {
    $srcIdentity = 'bs.service_id = ?';
    $srcParams[] = (int)$src['service_id'];
} else {
    $srcIdentity = 'bs.service_type = ? AND bs.is_optional = ?';
    $srcParams[] = (string)$src['service_type'];
    $srcParams[] = (int)$src['is_optional'];
}

// Typed identity of the source line (from its latest fulfillment row, or the
// package-pinned hotel/room from the sale snapshot when never fulfilled).
$srcF = $pdo->prepare("
    SELECT f.status, f.fulfillment_type,
           hf.hotel_id, hf.room_type_id, ff.airline, ff.flight_number
    FROM umrah_fulfillments f
    LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = f.id
    LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
    WHERE f.booking_service_id = ? AND f.tenant_id = ? ORDER BY f.id DESC LIMIT 1");
$srcF->execute([$booking_service_id, $tenant_id]);
$srcFul = $srcF->fetch(PDO::FETCH_ASSOC) ?: [];
$srcSnap = json_decode((string)($src['price_snapshot'] ?? ''), true) ?: [];
$srcHotelId = !empty($srcFul['hotel_id']) ? (int)$srcFul['hotel_id'] : (!empty($srcSnap['hotel_id']) ? (int)$srcSnap['hotel_id'] : null);
$srcRoomId  = !empty($srcFul['room_type_id']) ? (int)$srcFul['room_type_id'] : (!empty($srcSnap['room_type_id']) ? (int)$srcSnap['room_type_id'] : null);
$srcAirline = (string)($srcFul['airline'] ?? '');
$srcFlight  = (string)($srcFul['flight_number'] ?? '');

$srcCtx = [
    'cat' => $cat,
    'open' => $openFulfillmentStatuses,
    'hotel_id' => $srcHotelId,
    'room_type_id' => $srcRoomId,
    'airline' => $srcAirline,
    'flight_number' => $srcFlight,
];

// Candidate lines per member, with the target line's own latest fulfillment
// state attached (status, type, hotel/room identity, airline).
$candStmt = $pdo->prepare("
    SELECT bs.id, bs.price_snapshot,
           (SELECT f.status           FROM umrah_fulfillments f        WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_status,
           (SELECT f.fulfillment_type FROM umrah_fulfillments f        WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_type,
           (SELECT hf.hotel_id        FROM umrah_hotel_fulfillments hf JOIN umrah_fulfillments f ON f.id = hf.fulfillment_id WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_hotel,
           (SELECT hf.room_type_id    FROM umrah_hotel_fulfillments hf JOIN umrah_fulfillments f ON f.id = hf.fulfillment_id WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_room,
           (SELECT ff.airline         FROM umrah_flight_fulfillments ff JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_airline,
           (SELECT ff.flight_number   FROM umrah_flight_fulfillments ff JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id WHERE f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id ORDER BY f.id DESC LIMIT 1) AS t_flight
    FROM umrah_booking_services bs
    WHERE bs.booking_id = ? AND bs.tenant_id = ? AND " . $srcIdentity . "
    ORDER BY bs.id");

// Only fields that make sense for the source line's type are forwarded:
// common fulfillment fields always, typed keys only for hotel/flight lines.
$typedKeys = [
    'hotel'  => ['hotel_id', 'room_id', 'room_type_id', 'extra_bed', 'check_in', 'check_out', 'nights', 'nightly_rate', 'contract_id'],
    'flight' => ['ticket_number', 'pnr', 'airline', 'flight_type', 'flight_legs', 'flight_number', 'departure_city', 'arrival_city', 'departure_time', 'arrival_time', 'return_flight_number', 'return_departure_time', 'return_arrival_time'],
    'transport' => ['transport_vehicle', 'transport_trip_date'],
];
$commonKeys = ['supplier_id', 'status', 'supplier_currency', 'supplier_cost', 'exchange_rate', 'requested_date', 'planned_date', 'completed_date', 'notes'];
$pickFields = function(array $post, string $typeCat) use ($commonKeys, $typedKeys): array {
    $out = [];
    foreach ($commonKeys as $k) { $out[$k] = $post[$k] ?? ''; }
    foreach (($typedKeys[$typeCat] ?? []) as $k) { $out[$k] = $post[$k] ?? ''; }
    return $out;
};
$baseFields = $pickFields($_POST, $cat);
// Aggregate hotels with DIFFERENT member durations: each duration group
// posts its own stay list. hotel_groups maps duration key -> stays[];
// hotel_group_members maps duration key -> booking_ids.
$hotelGroups = null;
$hotelGroupMembers = [];
if ($cat === 'hotel') {
    if (isset($_POST['hotel_groups']) && is_string($_POST['hotel_groups']) && $_POST['hotel_groups'] !== '') {
        $decoded = json_decode($_POST['hotel_groups'], true);
        if (is_array($decoded)) { $hotelGroups = $decoded; }
    }
    if (isset($_POST['hotel_group_members']) && is_string($_POST['hotel_group_members']) && $_POST['hotel_group_members'] !== '') {
        $decoded = json_decode($_POST['hotel_group_members'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $dur => $ids) {
                $hotelGroupMembers[(string)$dur] = array_map('intval', is_array($ids) ? $ids : []);
            }
        }
    }
    // Group stay blocks carry the source member's fulfillment ids — every
    // target (including the source line) must create its own rows.
    if ($hotelGroups !== null) {
        foreach ($hotelGroups as $gKey => &$gStays) {
            if (!is_array($gStays)) { $gStays = []; continue; }
            $gStays = array_map(function($st) {
                if (is_array($st)) { $st['fulfillment_id'] = null; }
                return $st;
            }, $gStays);
        }
        unset($gStays);
    }
}

// Aggregate flights with DIFFERENT member durations: shared base fields plus
// per-duration-group overrides (PNR / outbound / return). flight_groups maps
// duration key -> fields; flight_group_members maps duration key -> booking_ids.
$flightGroups = null;
$flightGroupMembers = [];
if ($cat === 'flight') {
    if (isset($_POST['flight_groups']) && is_string($_POST['flight_groups']) && $_POST['flight_groups'] !== '') {
        $decoded = json_decode($_POST['flight_groups'], true);
        if (is_array($decoded)) { $flightGroups = $decoded; }
    }
    if (isset($_POST['flight_group_members']) && is_string($_POST['flight_group_members']) && $_POST['flight_group_members'] !== '') {
        $decoded = json_decode($_POST['flight_group_members'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $dur => $ids) {
                $flightGroupMembers[(string)$dur] = array_map('intval', is_array($ids) ? $ids : []);
            }
        }
    }
}

// Multi-hotel stays: forwarded to every matching sold service line. The
// source card's stay fulfillment_ids belong to the source member's booking
// only — targets must create their own rows (stay blocks are matched
// positionally, and orphaned ids would roll back the whole save).
$hotelStays = null;
if (isset($_POST['hotel_stays']) && is_string($_POST['hotel_stays']) && $_POST['hotel_stays'] !== '') {
    $decoded = json_decode($_POST['hotel_stays'], true);
    if (is_array($decoded)) {
        $hotelStays = array_map(function($st) {
            if (is_array($st)) { $st['fulfillment_id'] = null; }
            return $st;
        }, $decoded);
    }
}

$targets = [];
$skipReasons = [];
$noMatchMembers = 0;

foreach ($members as $member) {
    $candStmt->execute(array_merge([$member['booking_id'], $tenant_id], $srcParams));
    $cands = $candStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$cands) {
        $noMatchMembers++;
        continue;
    }
    foreach ($cands as $cand) {
        // Skip reasons: closed/frozen fulfillments, different hotel / room /
        // airline / flight (different package variant). Same rules as the
        // coverage counts shown in the fulfillment modal (get_fulfillments.php).
        // A line that was never fulfilled inherits the package-pinned variant
        // from its sale snapshot so different package hotels stay apart.
        $cSnap = json_decode((string)($cand['price_snapshot'] ?? ''), true) ?: [];
        $reason = fulfillment_variant_ok($srcCtx, [
            'status' => $cand['t_status'],
            'hotel_id' => !empty($cand['t_hotel']) ? (int)$cand['t_hotel'] : (!empty($cSnap['hotel_id']) ? (int)$cSnap['hotel_id'] : null),
            'room_type_id' => !empty($cand['t_room']) ? (int)$cand['t_room'] : (!empty($cSnap['room_type_id']) ? (int)$cSnap['room_type_id'] : null),
            'airline' => $cand['t_airline'],
            'flight_number' => $cand['t_flight'],
        ]);
        if ($reason !== null) {
            $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;
            continue;
        }
        $targets[] = ['booking_id' => (int)$member['booking_id'], 'name' => (string)$member['name'], 'line_id' => (int)$cand['id']];
    }
}

$applied = 0;
$errors = [];
// Extra-bed ownership: the same stay block is cloned to every member of a
// group, so the extra_bed flag would multiply (5 members -> "5 extra beds",
// inflating the room's capacity). Only the FIRST member that actually
// applies keeps the flag — one extra bed per room assignment, exactly as
// the UI's single per-block checkbox promises.
$extraBedGranted = [];
foreach ($targets as $target) {
    $mergedInput = array_merge($baseFields, [
        'tenant_id'          => $tenant_id,
        'branch_id'          => $branch_id,
        'user_id'            => $user_id,
        'booking_service_id' => $target['line_id'],
    ]);
    // Per-duration-group overlay: the target member's own group wins over the
    // shared fields (e.g. their PNR and their return flight for their duration).
    if ($flightGroups !== null) {
        $memberDur = null;
        foreach ($flightGroupMembers as $dur => $ids) {
            if (in_array($target['booking_id'], $ids, true)) { $memberDur = $dur; break; }
        }
        if ($memberDur !== null && isset($flightGroups[$memberDur]) && is_array($flightGroups[$memberDur])) {
            $overlay = array_filter($flightGroups[$memberDur], function ($v) {
                return $v !== null && $v !== '';
            });
            $mergedInput = array_merge($mergedInput, $overlay);
        }
    }
    // Per-group hotel stays: the shared (first-group) stay list is applied
    // first, then the member's own group's list replaces it entirely. Members
    // not covered by any stay block are skipped (their current state stays
    // untouched) — this is how per-family / per-member room assignment works.
    if ($hotelStays !== null) { $mergedInput['hotel_stays'] = $hotelStays; }
    if ($hotelGroups !== null) {
        $memberGroup = null;
        foreach ($hotelGroupMembers as $gKey => $ids) {
            if (in_array($target['booking_id'], $ids, true)) { $memberGroup = $gKey; break; }
        }
        if ($memberGroup === null || !isset($hotelGroups[$memberGroup]) || !is_array($hotelGroups[$memberGroup])) {
            $skipReasons['no room block'] = ($skipReasons['no room block'] ?? 0) + 1;
            continue;
        }
        $mergedInput['hotel_stays'] = $hotelGroups[$memberGroup];
    }
    $grantKey = $hotelGroups !== null ? ('g' . ($memberGroup ?? '')) : 'all';
    if (isset($mergedInput['hotel_stays']) && is_array($mergedInput['hotel_stays'])) {
        $keepExtra = !isset($extraBedGranted[$grantKey]);
        $extraBedGranted[$grantKey] = true;
        if (!$keepExtra) {
            foreach ($mergedInput['hotel_stays'] as &$st) {
                if (is_array($st)) { $st['extra_bed'] = 0; }
            }
            unset($st);
        }
    }
    $result = fulfillment_save($pdo, $mergedInput);
    if ($result['success']) {
        $applied++;
    } else {
        $errors[] = ['member' => $target['name'], 'message' => $result['message']];
    }
}

$skipCount = array_sum($skipReasons);
$skipped = $skipCount + $noMatchMembers;

$skipParts = [];
foreach ($skipReasons as $label => $n) {
    $skipParts[] = $n . ' ' . $label;
}
if ($noMatchMembers > 0) {
    $skipParts[] = $noMatchMembers . ' without this service (different package)';
}

$scopeLabel = $scope === 'group'
    ? ' across ' . $familyCount . ' famil' . ($familyCount === 1 ? 'y' : 'ies') . ' in the group'
    : ' in the family';

echo json_encode([
    'success' => true,
    'scope' => $scope,
    'message' => 'Fulfillment applied to ' . $applied . ' sold service line(s)'
        . $scopeLabel
        . ($skipParts ? ', skipped: ' . implode(', ', $skipParts) : '')
        . ($errors ? ', ' . count($errors) . ' error(s)' : ''),
    'applied' => $applied,
    'skipped' => $skipped,
    'skip_reasons' => $skipReasons,
    'errors'  => $errors,
]);