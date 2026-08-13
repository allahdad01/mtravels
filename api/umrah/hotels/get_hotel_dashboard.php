<?php
/**
 * Get Hotel Dashboard API (Phase 24)
 * Data for the dedicated hotel-management area: overview stats, per-hotel
 * occupancy matrix by room type, recent hotel stays, and the master
 * reference data (hotels, room types, rooms, contracts, suppliers).
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../../includes/db.php';
require_once __DIR__ . '/occupancy_helper.php';

$today = date('Y-m-d');
$in14 = date('Y-m-d', strtotime('+14 days'));

// ---- Hotels ------------------------------------------------------------------
$hStmt = $pdo->prepare("SELECT h.id, h.supplier_id, s.name AS supplier_name,
                               h.name, h.saudi_name, h.city, h.location, h.address,
                               h.star_rating, h.contact, h.status, h.notes
                        FROM umrah_hotels h
                        LEFT JOIN suppliers s ON s.id = h.supplier_id
                        WHERE h.tenant_id = ? ORDER BY h.name");
$hStmt->execute([$tenant_id]);
$hotels = $hStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Room types (global) + rooms -----------------------------------------------------
$tStmt = $pdo->prepare("SELECT id, name, max_occupancy, bed_type, status
                        FROM umrah_hotel_room_types WHERE tenant_id = ? ORDER BY name");
$tStmt->execute([$tenant_id]);
$roomTypes = $tStmt->fetchAll(PDO::FETCH_ASSOC);

// per-type counts + per-hotel room/type counts (for tables)
$typeCounts = [];
$hotelCounts = [];
$hotelTypeCounts = [];
$cntStmt = $pdo->prepare("SELECT hotel_id, room_type_id, COUNT(*) c FROM umrah_hotel_rooms WHERE tenant_id = ? GROUP BY hotel_id, room_type_id");
$cntStmt->execute([$tenant_id]);
foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $typeCounts[$row['room_type_id']] = (int)$row['c'];
    $hotelCounts[$row['hotel_id']] = ($hotelCounts[$row['hotel_id']] ?? 0) + (int)$row['c'];
    $hotelTypeCounts[$row['hotel_id']] = ($hotelTypeCounts[$row['hotel_id']] ?? 0) + 1;
}
foreach ($roomTypes as &$rt) {
    $rt['room_count'] = $typeCounts[$rt['id']] ?? 0;
}
unset($rt);
foreach ($hotels as &$h) {
    $h['room_count'] = $hotelCounts[$h['id']] ?? 0;
    $h['room_type_count'] = $hotelTypeCounts[$h['id']] ?? 0;
}
unset($h);

$rStmt = $pdo->prepare("SELECT id, hotel_id, room_type_id, room_number, floor, status
                        FROM umrah_hotel_rooms WHERE tenant_id = ? ORDER BY hotel_id, room_type_id, room_number");
$rStmt->execute([$tenant_id]);
$rooms = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Contracts (with inventory + rates) -----------------------------------------
$cStmt = $pdo->prepare("SELECT id, hotel_id, supplier_id, contract_number, scope, contract_type,
                               contract_amount, contract_currency, valid_from, valid_to, payment_terms, notes, status
                        FROM umrah_hotel_contracts WHERE tenant_id = ? ORDER BY valid_from DESC, id DESC");
$cStmt->execute([$tenant_id]);
$contracts = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$invStmt = $pdo->prepare("SELECT id, contract_id, room_id, valid_from, valid_to
                          FROM umrah_hotel_contract_inventory WHERE tenant_id = ?");
$invStmt->execute([$tenant_id]);
$inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

$chStmt = $pdo->prepare("SELECT contract_id, hotel_id FROM umrah_contract_hotels WHERE tenant_id = ?");
$chStmt->execute([$tenant_id]);
$contractHotels = $chStmt->fetchAll(PDO::FETCH_ASSOC);

$rtStmt = $pdo->prepare("SELECT id, contract_id, hotel_id, room_type_id, cost_currency, cost_price
                         FROM umrah_hotel_contract_rates WHERE tenant_id = ?");
$rtStmt->execute([$tenant_id]);
$rates = $rtStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Overview stats ---------------------------------------------------------------
$stats = [
    'hotels'      => count(array_filter($hotels, fn($h) => $h['status'] === 'active')),
    'room_types'  => count(array_filter($roomTypes, fn($t) => $t['status'] === 'active')),
    'rooms'       => count(array_filter($rooms, fn($r) => $r['status'] === 'active')),
    'contracts'   => count(array_filter($contracts, fn($c) => $c['status'] === 'active')),
    'stays_today' => 0,
    'on_demand_today' => 0,
    'upcoming'    => 0,
];
// friendly aliases used by the UI stat cards
$stats['total_hotels'] = $stats['hotels'];
$stats['total_room_types'] = $stats['room_types'];
$stats['total_rooms'] = $stats['rooms'];
$stats['active_contracts'] = $stats['contracts'];

// stays today / upcoming / on-demand
$stayStmt = $pdo->prepare("
    SELECT hf.id, hf.hotel_id, hf.contract_id, hf.check_in, hf.check_out, f.status
    FROM umrah_hotel_fulfillments hf
    INNER JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
        AND f.tenant_id = ? AND f.status <> 'cancelled'
    WHERE hf.tenant_id = ?");
$stayStmt->execute([$tenant_id, $tenant_id]);
foreach ($stayStmt->fetchAll(PDO::FETCH_ASSOC) as $stay) {
    if ($stay['check_in'] <= $today && $today < $stay['check_out']) {
        $stats['stays_today']++;
        if (!$stay['contract_id']) $stats['on_demand_today']++;
    }
    if ($stay['check_in'] > $today && $stay['check_in'] <= $in14) {
        $stats['upcoming']++;
    }
}

// ---- Occupancy matrix (today) per hotel × room type -------------------------------
$occupancy = [];
$roomsByHotel = [];
foreach ($rooms as $r) $roomsByHotel[$r['hotel_id']][] = $r;
$typeById = [];
foreach ($roomTypes as $t) $typeById[$t['id']] = $t;
foreach ($hotels as $hotel) {
    $typeIds = [];
    foreach ($roomsByHotel[$hotel['id']] ?? [] as $r) $typeIds[$r['room_type_id']] = true;
    foreach (array_keys($typeIds) as $tid) {
        $t = $typeById[$tid] ?? null;
        if (!$t) continue;
        $o = hotelOccupancyForDate($pdo, $tenant_id, $hotel['id'], $today, (int)$tid);
        $occupancy[] = [
            'hotel_id' => $hotel['id'],
            'hotel_name' => $hotel['name'],
            'room_type_id' => $t['id'],
            'room_type_name' => $t['name'],
            'total'     => $o['total'] ?? 0,
            'occupied'  => $o['occupied'] ?? 0,
            'reserved'  => $o['reserved'] ?? 0,
            'available' => $o['available'] ?? 0,
            'blocked'   => $o['blocked'] ?? 0,
        ];
    }
}

// ---- Recent hotel stays (last 50) ----------------------------------------------------
$recent = [];
$recStmt = $pdo->prepare("
    SELECT hf.id, hf.hotel_id, h.name AS hotel_name, hf.room_id, hf.room_type_id,
           r.room_number, rt.name AS room_type_name,
           hf.check_in, hf.check_out, hf.nights, hf.nightly_rate, hf.currency, hf.cost_amount,
           hf.contract_id, f.status AS fulfill_status, f.fulfillment_type,
           b.name AS member_name, b.booking_id, b.family_id
    FROM umrah_hotel_fulfillments hf
    INNER JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id AND f.tenant_id = ?
    LEFT JOIN umrah_hotels h ON hf.hotel_id = h.id
    LEFT JOIN umrah_hotel_rooms r ON hf.room_id = r.id
    LEFT JOIN umrah_hotel_room_types rt ON hf.room_type_id = rt.id
    LEFT JOIN umrah_booking_services bs ON f.booking_service_id = bs.id
    LEFT JOIN umrah_bookings b ON bs.booking_id = b.booking_id
    WHERE hf.tenant_id = ?
    ORDER BY hf.check_in DESC, hf.id DESC
    LIMIT 50");
$recStmt->execute([$tenant_id, $tenant_id]);
$recent = $recStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Suppliers (for contract form) ----------------------------------------------------
$supStmt = $pdo->prepare("SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$supStmt->execute([$tenant_id]);
$suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

// helper maps for contract enrichment (after suppliers are loaded)
$hotelById = [];
foreach ($hotels as $h) $hotelById[$h['id']] = $h;
$supplierById = [];
foreach ($suppliers as $s) $supplierById[$s['id']] = $s;
foreach ($contracts as &$c) {
    $linked = array_values(array_filter($contractHotels, fn($ch) => (int)$ch['contract_id'] === (int)$c['id']));
    $c['hotels'] = array_map(function ($ch) use ($hotelById) {
        return [
            'id'   => (int)$ch['hotel_id'],
            'name' => $hotelById[$ch['hotel_id']]['name'] ?? null,
            'city' => $hotelById[$ch['hotel_id']]['city'] ?? null,
        ];
    }, $linked);
    $c['hotel_ids'] = array_map(fn($x) => $x['id'], $c['hotels']);
    $c['hotel_names'] = implode(', ', array_filter(array_map(fn($x) => $x['name'], $c['hotels'])));
    $c['supplier_name'] = $supplierById[$c['supplier_id']]['name'] ?? null;
    $c['inventory'] = array_values(array_filter($inventory, fn($i) => (int)$i['contract_id'] === (int)$c['id']));
    $c['rates'] = array_values(array_filter($rates, fn($r) => (int)$r['contract_id'] === (int)$c['id']));
    $c['inventory_count'] = count($c['inventory']);
    $c['rate_count'] = count($c['rates']);
}
unset($c);

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'hotels' => $hotels,
    'room_types' => $roomTypes,
    'rooms' => $rooms,
    'contracts' => $contracts,
    'inventory' => $inventory,
    'rates' => $rates,
    'occupancy' => $occupancy,
    'recent_stays' => $recent,
    'suppliers' => $suppliers,
]);
