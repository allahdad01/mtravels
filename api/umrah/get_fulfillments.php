<?php
/**
 * Get Fulfillments API — sold services of a member with their current
 * fulfillment state (Phase 19-21), plus reference data for the modal:
 * suppliers, status catalog per group, hotels, room types, and suggested
 * supplier costs for pre-fill.
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

// Deduplicate sold-service rows (a hotel service can carry several
// fulfillment rows — one per hotel stay — so the row join produces one
// row per fulfillment). Each record keeps its first (cost-bearing) row and
// every extra fulfillment becomes an entry in the record's hotel_stays[].
function attachHotelStays(array $rows): array
{
    $map = [];
    foreach ($rows as $ln) {
        $bs = (int)$ln['booking_service_id'];
        if (!isset($map[$bs])) {
            $ln['hotel_stays'] = [];
            $map[$bs] = $ln;
        }
        if (!empty($ln['fulfillment_id'])) {
            $map[$bs]['hotel_stays'][] = [
                'fulfillment_id' => (int)$ln['fulfillment_id'],
                'status'         => (string)$ln['fulfill_status'],
                'hotel_id'       => !empty($ln['hotel_id']) ? (int)$ln['hotel_id'] : null,
                'contract_id'    => !empty($ln['contract_id']) ? (int)$ln['contract_id'] : null,
                'room_id'        => !empty($ln['room_id']) ? (int)$ln['room_id'] : null,
                'room_type_id'   => !empty($ln['room_type_id']) ? (int)$ln['room_type_id'] : null,
                'extra_bed'      => !empty($ln['extra_bed']) ? 1 : 0,
                'check_in'       => $ln['check_in'],
                'check_out'      => $ln['check_out'],
                'nights'         => $ln['nights'] !== null ? (int)$ln['nights'] : null,
                'nightly_rate'   => $ln['nightly_rate'] !== null ? (float)$ln['nightly_rate'] : null,
            ];
        }
    }
    return array_values($map);
}

// Enrich hotel services with per-city cost fields (makkah_* / madinah_*).
// Per-city costs are stored in umrah_fulfillment_details on the first
// fulfillment row of each hotel service. Keys: city_makkah_currency,
// city_makkah_cost, city_makkah_rate, city_makkah_cost_amount (and madinah).
function enrichHotelCityCosts(array &$services, $pdo, int $tenant_id): void
{
    // Collect first fulfillment_id for each hotel service
    $hotelFids = [];
    foreach ($services as $sv) {
        $st = strtolower((string)($sv['service_type'] ?? ''));
        $cat = strtolower((string)($sv['category_name'] ?? ''));
        if ($st !== 'hotel' && $cat !== 'hotel') continue;
        if (empty($sv['fulfillment_id'])) continue;
        $hotelFids[(int)$sv['fulfillment_id']] = (int)$sv['booking_service_id'];
    }
    if (!$hotelFids) return;

    // Batch-query per-city detail keys for all fulfillment IDs
    $ph = implode(',', array_fill(0, count($hotelFids), '?'));
    $stmt = $pdo->prepare("
        SELECT fd.fulfillment_id, fd.detail_key, fd.detail_value
        FROM umrah_fulfillment_details fd
        WHERE fd.fulfillment_id IN ($ph) AND fd.tenant_id = ?
          AND fd.detail_key LIKE 'city_%'");
    $stmt->execute(array_merge(array_keys($hotelFids), [$tenant_id]));

    // Map: fulfillment_id => detail_key => value
    $detailMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $detailMap[(int)$row['fulfillment_id']][$row['detail_key']] = (string)$row['detail_value'];
    }

    // Enrich each hotel service with per-city fields
    foreach ($services as &$sv) {
        $fid = (int)($sv['fulfillment_id'] ?? 0);
        if (!isset($detailMap[$fid])) continue;
        $d = $detailMap[$fid];
        $sv['makkah_currency']    = $d['city_makkah_currency'] ?? 'USD';
        $sv['makkah_cost']        = $d['city_makkah_cost'] !== '' ? $d['city_makkah_cost'] : null;
        $sv['makkah_rate']        = $d['city_makkah_rate'] !== '' ? $d['city_makkah_rate'] : null;
        $sv['makkah_cost_amount'] = $d['city_makkah_cost_amount'] !== '' ? $d['city_makkah_cost_amount'] : null;
        $sv['madinah_currency']    = $d['city_madinah_currency'] ?? 'USD';
        $sv['madinah_cost']        = $d['city_madinah_cost'] !== '' ? $d['city_madinah_cost'] : null;
        $sv['madinah_rate']        = $d['city_madinah_rate'] !== '' ? $d['city_madinah_rate'] : null;
        $sv['madinah_cost_amount'] = $d['city_madinah_cost_amount'] !== '' ? $d['city_madinah_cost_amount'] : null;
    }
    unset($sv);
}

// Enrich extra bed pseudo-members with their stored cost/currency data from
// umrah_fulfillment_details so the UI can pre-fill cost, rate, sold fields.
function enrichExtraBedCosts(array &$services, $pdo, int $tenant_id): void
{
    // Collect booking_ids of extra bed members from member_breakdown
    $ebBookingIds = [];
    foreach ($services as $sv) {
        if (empty($sv['member_breakdown'])) continue;
        foreach ($sv['member_breakdown'] as &$m) {
            if (!empty($m['is_extra_bed'])) {
                $ebBookingIds[] = (int)$m['booking_id'];
            }
        }
        unset($m);
    }
    if (!$ebBookingIds) return;

    // Find the fulfillment_id for each extra bed member's hotel service
    $ph = implode(',', array_fill(0, count($ebBookingIds), '?'));
    $fStmt = $pdo->prepare("
        SELECT bs.booking_id, f.id AS fulfillment_id
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
        WHERE bs.booking_id IN ($ph) AND bs.tenant_id = ?
          AND LOWER(bs.service_type) = 'hotel'
        GROUP BY bs.booking_id, f.id");
    $fStmt->execute(array_merge($ebBookingIds, [$tenant_id]));
    $fidMap = [];
    foreach ($fStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $fidMap[(int)$r['booking_id']] = (int)$r['fulfillment_id'];
    }
    if (!$fidMap) return;

    // Load fulfillment_details for all extra bed fulfillment IDs
    $fids = array_values($fidMap);
    $ph2 = implode(',', array_fill(0, count($fids), '?'));
    $dStmt = $pdo->prepare("
        SELECT fulfillment_id, detail_key, detail_value
        FROM umrah_fulfillment_details
        WHERE fulfillment_id IN ($ph2) AND tenant_id = ?
          AND (detail_key LIKE 'eb_%')");
    $dStmt->execute(array_merge($fids, [$tenant_id]));
    $detailMap = [];
    foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $detailMap[(int)$r['fulfillment_id']][$r['detail_key']] = (string)$r['detail_value'];
    }

    // Enrich extra bed members in member_breakdown
    foreach ($services as &$sv) {
        if (empty($sv['member_breakdown'])) continue;
        foreach ($sv['member_breakdown'] as &$m) {
            if (empty($m['is_extra_bed'])) continue;
            $bid = (int)$m['booking_id'];
            if (!isset($fidMap[$bid]) || !isset($detailMap[$fidMap[$bid]])) continue;
            $d = $detailMap[$fidMap[$bid]];
            $m['eb_currency'] = $d['eb_currency'] ?? '';
            $m['eb_cost'] = $d['eb_cost'] !== '' ? $d['eb_cost'] : null;
            $m['eb_rate'] = $d['eb_rate'] !== '' ? $d['eb_rate'] : null;
            $m['eb_cost_usd'] = $d['eb_cost_usd'] !== '' ? $d['eb_cost_usd'] : null;
        }
        unset($m);
    }
    unset($sv);
}

// Travel type of a member from their date of birth — the same thresholds
// used by the passenger manifest (infant < 2, child 2-11, adult otherwise).
// Unknown dates default to adult. Ticket costs are priced per type; infants
// receive no hotel/transport fulfillment, and visa applies to everyone alike.
function memberTravelType($dob)
{
    if (empty($dob) || $dob === '0000-00-00') {
        return 'adult';
    }
    $ts = strtotime($dob);
    if (!$ts) {
        return 'adult';
    }
    $age = (int)date('Y') - (int)date('Y', $ts) - ((int)date('md') < (int)date('md', $ts) ? 1 : 0);
    if ($age < 2) return 'infant';
    if ($age <= 11) return 'child';
    return 'adult';
}

$booking_id = isset($_GET['booking_id']) ? DbSecurity::validateInput($_GET['booking_id'], 'int') : 0;
$family_id  = isset($_GET['family_id'])  ? DbSecurity::validateInput($_GET['family_id'], 'int') : 0;
$group_id   = isset($_GET['group_id'])   ? DbSecurity::validateInput($_GET['group_id'], 'int') : 0;

// Scope resolution: booking (member) | family | group — pick the source
// booking; family/group views use a representative member's service lines
// (skeleton lines are identical across family members).
$scopeMode = 'member';
if ($booking_id) {
    $scopeMode = 'member';
} elseif ($family_id) {
    $scopeMode = 'family';
    $srcStmt = $pdo->prepare("
        SELECT booking_id FROM umrah_bookings
        WHERE family_id = ? AND tenant_id = ?
          AND status NOT IN ('refunded', 'cancelled')
        ORDER BY booking_id LIMIT 1");
    $srcStmt->execute([$family_id, $tenant_id]);
    $booking_id = (int)$srcStmt->fetchColumn();
} elseif ($group_id) {
    $scopeMode = 'group';
    $grpOk = $pdo->prepare("SELECT 1 FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
    $grpOk->execute([$group_id, $tenant_id, $branch_id]);
    if (!$grpOk->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Group not found.']);
        exit;
    }
    $srcStmt = $pdo->prepare("
        SELECT b.booking_id
        FROM umrah_bookings b
        JOIN families f ON f.family_id = b.family_id
        WHERE f.group_id = ? AND b.tenant_id = ?
          AND b.status NOT IN ('refunded', 'cancelled')
        ORDER BY b.booking_id LIMIT 1");
    $srcStmt->execute([$group_id, $tenant_id]);
    $booking_id = (int)$srcStmt->fetchColumn();
}

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => $scopeMode === 'member' ? 'Booking ID is required.' : 'No active members found for this ' . $scopeMode . '.']);
    exit;
}

// ---- Booking header ------------------------------------------------------
$bStmt = $pdo->prepare("
    SELECT booking_id, name, family_id, sold_price, discount, paid, due, currency
    FROM umrah_bookings
    WHERE booking_id = ? AND tenant_id = ?");
$bStmt->execute([$booking_id, $tenant_id]);
$booking = $bStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

// Group context of the booking's family (for group-scoped rendering)
$group_id_out = 0;
$group_name_out = '';
if (!empty($booking['family_id'])) {
    $gStmt = $pdo->prepare("
        SELECT f.group_id, g.group_name
        FROM families f
        LEFT JOIN umrah_groups g ON g.group_id = f.group_id
        WHERE f.family_id = ? AND f.tenant_id = ?");
    $gStmt->execute([$booking['family_id'], $tenant_id]);
    $gctx = $gStmt->fetch(PDO::FETCH_ASSOC);
    if ($gctx) {
        $group_id_out = (int)($gctx['group_id'] ?? 0);
        $group_name_out = (string)($gctx['group_name'] ?? '');
    }
}

// ---- Family/group scope: aggregate services across ALL members ------------
// Every package service present in the scope is listed once (grouped by
// catalog service id, or by type + optional flag for skeleton lines), with
// coverage counts = how many families/members would receive a bulk apply
// (same variant guard + open-status rules as save_multi_fulfillment.php).
$isAggregate = ($scopeMode === 'family' || $scopeMode === 'group');
$services = [];
$familyNames = []; // family_id => head_of_family (room-split headers)

if ($isAggregate) {
    $scopeFamilies = [];
    if ($scopeMode === 'family') {
        $scopeFamilies = [(int)$family_id];
    } else {
        $gfStmt = $pdo->prepare("SELECT family_id FROM families WHERE group_id = ? AND tenant_id = ?");
        $gfStmt->execute([$group_id, $tenant_id]);
        $scopeFamilies = $gfStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$scopeFamilies) {
        echo json_encode(['success' => false, 'message' => 'No families found for this ' . $scopeMode . '.']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($scopeFamilies), '?'));
    $mStmt = $pdo->prepare("
        SELECT booking_id, name, family_id, sold_price, discount, paid, due, currency, is_extra_bed
        FROM umrah_bookings
        WHERE family_id IN ($placeholders) AND tenant_id = ?
          AND status NOT IN ('refunded', 'cancelled')
        ORDER BY booking_id");
    $mStmt->execute(array_merge($scopeFamilies, [$tenant_id]));
    $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$members) {
        echo json_encode(['success' => false, 'message' => 'No active members found for this ' . $scopeMode . '.']);
        exit;
    }

    $fnStmt = $pdo->prepare("SELECT family_id, head_of_family FROM families WHERE family_id IN ($placeholders) AND tenant_id = ?");
    $fnStmt->execute(array_merge($scopeFamilies, [$tenant_id]));
    foreach ($fnStmt->fetchAll(PDO::FETCH_ASSOC) as $fm) {
        $familyNames[(int)$fm['family_id']] = (string)($fm['head_of_family'] ?? '');
    }

    $soldTotal = 0.0;
    $discountTotal = 0.0;
    $costTotal = 0.0;
    foreach ($members as $m) {
        $soldTotal += (float)$m['sold_price'];
        $discountTotal += (float)$m['discount'];
    }
    $familiesCountAll = count(array_unique(array_column($members, 'family_id')));
    $membersCountAll = count($members);

    $agStmt = $pdo->prepare("
        SELECT bs.id AS booking_service_id,
               bs.booking_id, ub.family_id, ub.name, ub.gender, ub.room_type, ub.duration, ub.dob,
               ub.is_extra_bed,
               bs.service_type, bs.service_id,
               bs.pricing_unit, bs.quantity, bs.is_optional,
               bs.base_price, bs.sold_price, bs.profit, bs.currency,
               bs.status AS sold_status,
               bs.price_snapshot,
               s.name AS service_name, c.name AS category_name,
               f.id AS fulfillment_id, f.status AS fulfill_status,
               f.supplier_id, f.supplier_currency, f.supplier_cost, f.exchange_rate,
               f.cost_amount, f.requested_date, f.planned_date, f.completed_date, f.notes,
               hf.hotel_id, hf.contract_id, hf.room_id, hf.room_type_id, hf.extra_bed,
               hf.check_in, hf.check_out, hf.nights, hf.nightly_rate,
               ff.ticket_number, ff.pnr, ff.airline, ff.flight_type, ff.flight_legs, ff.flight_number,
               ff.departure_city, ff.arrival_city, ff.departure_time, ff.arrival_time,
               ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time, ff.class
        FROM umrah_booking_services bs
        JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
        LEFT JOIN umrah_services s ON bs.service_id = s.id
        LEFT JOIN umrah_service_categories c ON s.category_id = c.id
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = bs.id
          AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = bs.id AND f2.tenant_id = bs.tenant_id)
        LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = f.id
        LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
        WHERE ub.family_id IN ($placeholders) AND ub.tenant_id = ?
          AND ub.status NOT IN ('refunded', 'cancelled')
        ORDER BY bs.booking_id, bs.id");
    $agStmt->execute(array_merge($scopeFamilies, [$tenant_id]));
    $scopeLines = attachHotelStays($agStmt->fetchAll(PDO::FETCH_ASSOC));

    $openSet = ['pending', 'requested', 'assigned', 'not_assigned', 'reserved', 'not_applied', 'applied', 'processing', 'confirmed', 'ticketed', 'issued', 'not_ticketed'];
    $grouped = [];
    foreach ($scopeLines as $ln) {
        $costTotal += (float)($ln['base_price'] ?? 0);
        $key = $ln['service_id'] !== null
            ? 's' . (int)$ln['service_id']
            : 't' . (string)$ln['service_type'] . '|' . (int)$ln['is_optional'];
        $grouped[$key][] = $ln;
    }

    foreach ($grouped as $lines) {
        // Representative line: prefer one with an open fulfillment (has the
        // deepest pre-fill), then any fulfillment, then the earliest line.
        $rep = null;
        $repScore = -1;
        foreach ($lines as $ln) {
            $hasF = !empty($ln['fulfillment_id']);
            $openF = $hasF && in_array((string)$ln['fulfill_status'], $openSet, true);
            $score = ($openF ? 2 : ($hasF ? 1 : 0));
            if ($score > $repScore || ($score === $repScore && (
                ($rep === null) ||
                ($ln['booking_id'] < $rep['booking_id']) ||
                ($ln['booking_id'] === $rep['booking_id'] && $ln['booking_service_id'] < $rep['booking_service_id'])
            ))) {
                $rep = $ln;
                $repScore = $score;
            }
        }

        // Package-pinned variant identity: a line that was never fulfilled
        // inherits the hotel/room it was sold with (price_snapshot) so the
        // bulk guard can tell two different package hotels apart even before
        // either is fulfilled.
        $repSnap = json_decode((string)($rep['price_snapshot'] ?? ''), true) ?: [];
        $cat = resolveFulfillmentType($pdo, $tenant_id, (string)$rep['service_type'], $rep['service_id'], $rep['category_name']);
        $srcCtx = [
            'cat' => $cat,
            'open' => $openSet,
            'hotel_id' => !empty($rep['hotel_id']) ? (int)$rep['hotel_id'] : (!empty($repSnap['hotel_id']) ? (int)$repSnap['hotel_id'] : null),
            'room_type_id' => !empty($rep['room_type_id']) ? (int)$rep['room_type_id'] : (!empty($repSnap['room_type_id']) ? (int)$repSnap['room_type_id'] : null),
            'airline' => (string)($rep['airline'] ?? ''),
            'flight_number' => (string)($rep['flight_number'] ?? ''),
        ];

        $usable = [];
        $usableFamilies = [];
        $skipBreak = [];
        foreach ($lines as $ln) {
            $lnSnap = json_decode((string)($ln['price_snapshot'] ?? ''), true) ?: [];
            // Infant members receive no hotel/transport fulfillment — their
            // package covers only ticket + visa costs, and the ticket card
            // asks for their own fare separately.
            if (($cat === 'hotel' || $cat === 'transport') && memberTravelType((string)($ln['dob'] ?? '')) === 'infant') {
                $skipBreak['infant (no ' . $cat . ')'] = ($skipBreak['infant (no ' . $cat . ')'] ?? 0) + 1;
                continue;
            }
            $reason = fulfillment_variant_ok($srcCtx, [
                'status' => $ln['fulfill_status'],
                'hotel_id' => !empty($ln['hotel_id']) ? (int)$ln['hotel_id'] : (!empty($lnSnap['hotel_id']) ? (int)$lnSnap['hotel_id'] : null),
                'room_type_id' => !empty($ln['room_type_id']) ? (int)$ln['room_type_id'] : (!empty($lnSnap['room_type_id']) ? (int)$lnSnap['room_type_id'] : null),
                'airline' => $ln['airline'],
                'flight_number' => $ln['flight_number'],
            ]);
            if ($reason === null) {
                $usable[(int)$ln['booking_id']] = 1;
                $usableFamilies[(int)$ln['family_id']] = 1;
            } else {
                $skipBreak[$reason] = ($skipBreak[$reason] ?? 0) + 1;
            }
        }

        $rep['is_aggregate'] = true;
        $rep['families_applicable'] = count($usableFamilies);
        $rep['members_count'] = count($lines);
        $rep['members_applicable'] = count($usable);
        $rep['coverage_skipped'] = count($lines) - count($usable);
        $rep['skip_breakdown'] = $skipBreak;
        // Card-level supplier from the representative's fulfillment — used as
        // a fallback for extra bed pseudo-members that don't have their own
        // supplier yet.
        $repCardSupplierId = !empty($rep['supplier_id']) ? (int)$rep['supplier_id'] : null;
        // Per-member flight breakdown (only for flight lines) — lets the card
        // split shared PNR / departure / return by each member's duration.
        $rep['member_breakdown'] = [];
        if ($cat === 'flight') {
            $bd = [];
            foreach ($lines as $ln) {
                $bid = (int)$ln['booking_id'];
                if (!isset($usable[$bid])) { continue; }
                $bd[] = [
                    'booking_id' => $bid,
                    'booking_service_id' => (int)$ln['booking_service_id'],
                    'fulfillment_id' => !empty($ln['fulfillment_id']) ? (int)$ln['fulfillment_id'] : null,
                    'name' => (string)($ln['name'] ?? ''),
                    'type' => memberTravelType((string)($ln['dob'] ?? '')),
                    'duration' => ($ln['duration'] !== null && $ln['duration'] !== '') ? (int)$ln['duration'] : null,
                    'pnr' => (string)($ln['pnr'] ?? ''),
                    'flight_number' => (string)($ln['flight_number'] ?? ''),
                    'departure_city' => (string)($ln['departure_city'] ?? ''),
                    'arrival_city' => (string)($ln['arrival_city'] ?? ''),
                    'departure_time' => (string)($ln['departure_time'] ?? ''),
                    'arrival_time' => (string)($ln['arrival_time'] ?? ''),
                    'return_flight_number' => (string)($ln['return_flight_number'] ?? ''),
                    'return_departure_time' => (string)($ln['return_departure_time'] ?? ''),
                    'return_arrival_time' => (string)($ln['return_arrival_time'] ?? ''),
                ];
            }
            $rep['member_breakdown'] = $bd;
        } elseif ($cat === 'hotel' && $rep['service_id'] !== null) {
            // Per-member hotel breakdown: seed one entry per usable member
            // (even pre-fulfillment), then attach their own fulfillment rows
            // (one row per hotel stay), so the card can keep each duration
            // group's stays separate from day one.
            $bd = [];
            foreach ($lines as $ln) {
                $bid = (int)$ln['booking_id'];
                if (!isset($usable[$bid])) { continue; }
                $bd[$bid] = [
                    'booking_id' => $bid,
                    'family_id'  => (int)($ln['family_id'] ?? 0),
                    'name' => (string)($ln['name'] ?? ''),
                    'type' => memberTravelType((string)($ln['dob'] ?? '')),
                    'gender' => (string)($ln['gender'] ?? ''),
                    'room_type' => (string)($ln['room_type'] ?? ''),
                    'duration' => ($ln['duration'] !== null && $ln['duration'] !== '') ? (int)$ln['duration'] : null,
                    'is_extra_bed' => !empty($ln['is_extra_bed']),
                    'supplier_id' => !empty($ln['supplier_id']) ? (int)$ln['supplier_id'] : $repCardSupplierId,
                    'cost' => $ln['base_price'] !== null ? (float)$ln['base_price'] : null,
                    'sold_price' => $ln['sold_price'] !== null ? (float)$ln['sold_price'] : null,
                    'profit' => $ln['profit'] !== null ? (float)$ln['profit'] : null,
                    'paid' => $ln['paid'] !== null ? (float)$ln['paid'] : null,
                    'stays' => [],
                ];
            }
            if ($bd) {
                $hStmt = $pdo->prepare("
                    SELECT ub.booking_id,
                           f.id AS fulfillment_id,
                           hf.hotel_id, hf.contract_id, hf.room_id, hf.room_type_id, hf.extra_bed,
                           hf.check_in, hf.check_out, hf.nights, hf.nightly_rate
                    FROM umrah_fulfillments f
                    JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                    JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
                    LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = f.id
                    WHERE bs.service_id = ? AND bs.tenant_id = ?
                      AND ub.family_id IN ($placeholders) AND ub.tenant_id = ?
                      AND ub.status NOT IN ('refunded', 'cancelled')
                    ORDER BY ub.booking_id, f.id");
                $hStmt->execute(array_merge([$rep['service_id'], $tenant_id], $scopeFamilies, [$tenant_id]));
                foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $bid = (int)$r['booking_id'];
                    if (!isset($bd[$bid])) { continue; }
                    $bd[$bid]['stays'][] = [
                        'fulfillment_id' => (int)$r['fulfillment_id'],
                        'hotel_id'       => !empty($r['hotel_id']) ? (int)$r['hotel_id'] : null,
                        'contract_id'    => !empty($r['contract_id']) ? (int)$r['contract_id'] : null,
                        'room_id'        => !empty($r['room_id']) ? (int)$r['room_id'] : null,
                        'room_type_id'   => !empty($r['room_type_id']) ? (int)$r['room_type_id'] : null,
                        'extra_bed'      => !empty($r['extra_bed']) ? 1 : 0,
                        'check_in'       => $r['check_in'],
                        'check_out'      => $r['check_out'],
                        'nights'         => $r['nights'] !== null ? (int)$r['nights'] : null,
                        'nightly_rate'   => $r['nightly_rate'] !== null ? (float)$r['nightly_rate'] : null,
                    ];
                }
            }
            $rep['member_breakdown'] = array_values($bd);
        }
        $services[] = $rep;
    }

    // Scope-level header figures (aggregate of every active member).
    $booking['sold_price'] = round($soldTotal, 2);
    $booking['discount'] = round($discountTotal, 2);
    $booking['cost_total'] = round($costTotal, 2);
    $booking['families_count'] = $familiesCountAll;
    $booking['members_count'] = $membersCountAll;
} else {
    // ---- Sold services + fulfillment state (single member) ------------------
    $sStmt = $pdo->prepare("
        SELECT bs.id AS booking_service_id,
               bs.service_type, bs.service_id,
               bs.pricing_unit, bs.quantity, bs.is_optional,
               bs.base_price, bs.sold_price, bs.profit, bs.currency,
               bs.status AS sold_status,
               bs.price_snapshot,
               s.name AS service_name, c.name AS category_name,
               ub.is_extra_bed,
               f.id AS fulfillment_id, f.status AS fulfill_status,
               f.supplier_id, f.supplier_currency, f.supplier_cost, f.exchange_rate,
               f.cost_amount, f.requested_date, f.planned_date, f.completed_date, f.notes,
               hf.hotel_id, hf.contract_id, hf.room_id, hf.room_type_id, hf.extra_bed,
               hf.check_in, hf.check_out, hf.nights, hf.nightly_rate,
               ff.ticket_number, ff.pnr, ff.airline, ff.flight_type, ff.flight_legs, ff.flight_number,
               ff.departure_city, ff.arrival_city, ff.departure_time, ff.arrival_time,
               ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time, ff.class
        FROM umrah_booking_services bs
        JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
        LEFT JOIN umrah_services s ON bs.service_id = s.id
        LEFT JOIN umrah_service_categories c ON s.category_id = c.id
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = bs.id
          AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = bs.id AND f2.tenant_id = bs.tenant_id)
        LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = f.id
        LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
        WHERE bs.booking_id = ? AND bs.tenant_id = ?
        ORDER BY bs.id");
    $sStmt->execute([$booking_id, $tenant_id]);
    $services = attachHotelStays($sStmt->fetchAll(PDO::FETCH_ASSOC));

    if (!empty($booking['family_id'])) {
        $fnStmt = $pdo->prepare("SELECT head_of_family FROM families WHERE family_id = ? AND tenant_id = ?");
        $fnStmt->execute([(int)$booking['family_id'], $tenant_id]);
        $familyNames[(int)$booking['family_id']] = (string)$fnStmt->fetchColumn();
    }
}

// ---- BRN (Booking Reference Number) optional procurement cost ---------------------
// One umrah_brn_costs row per booking. Member mode: the booking's row;
// family/group mode: every active member's row (the aggregate card pre-fills
// from the first row and the modal summary counts the whole sum).
$brnCosts = [];
$brnBookingIds = [];
if ($scopeMode === 'member') {
    $brnBookingIds = [$booking_id];
} else {
    $brnBookingIds = array_column($members, 'booking_id');
}
if ($brnBookingIds) {
    $brnPh = implode(',', array_fill(0, count($brnBookingIds), '?'));
    $brnStmt = $pdo->prepare("
        SELECT bc.id, bc.booking_id, ub.name, bc.supplier_id, bc.supplier_currency,
               bc.supplier_cost, bc.exchange_rate, bc.cost_amount, bc.notes
        FROM umrah_brn_costs bc
        JOIN umrah_bookings ub ON ub.booking_id = bc.booking_id
        WHERE bc.booking_id IN ($brnPh) AND bc.tenant_id = ?
        ORDER BY bc.booking_id");
    $brnStmt->execute(array_merge($brnBookingIds, [$tenant_id]));
    foreach ($brnStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $brnCosts[] = [
            'id' => (int)$r['id'],
            'booking_id' => (int)$r['booking_id'],
            'name' => (string)$r['name'],
            'supplier_id' => !empty($r['supplier_id']) ? (int)$r['supplier_id'] : null,
            'supplier_currency' => (string)($r['supplier_currency'] ?? ''),
            'supplier_cost' => $r['supplier_cost'] !== null ? (float)$r['supplier_cost'] : null,
            'exchange_rate' => $r['exchange_rate'] !== null ? (float)$r['exchange_rate'] : null,
            'cost_amount' => $r['cost_amount'] !== null ? (float)$r['cost_amount'] : null,
            'notes' => (string)($r['notes'] ?? ''),
        ];
    }
}
// BRN costs count into the scope-level cost figure shown in the modal summary
// (profit preview = sold - discount - costs).
if ($isAggregate) {
    $brnSum = array_sum(array_column($brnCosts, 'cost_amount'));
    $booking['cost_total'] = round((float)$booking['cost_total'] + $brnSum, 2);
}

// ---- Transport details (vehicle / trip date) from the generic detail store -------
$fIds = [];
foreach ($services as $sv) { if (!empty($sv['fulfillment_id'])) $fIds[(int)$sv['fulfillment_id']] = 1; }
if ($fIds) {
    $ph = implode(',', array_fill(0, count($fIds), '?'));
    $dStmt = $pdo->prepare("SELECT fulfillment_id, detail_key, detail_value
                            FROM umrah_fulfillment_details
                            WHERE fulfillment_id IN ($ph) AND detail_key IN ('vehicle', 'trip_date')");
    $dStmt->execute(array_keys($fIds));
    $dMap = [];
    foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $dMap[(int)$d['fulfillment_id']][$d['detail_key']] = $d['detail_value'];
    }
    foreach ($services as &$sv) {
        if (empty($sv['fulfillment_id'])) continue;
        $det = $dMap[(int)$sv['fulfillment_id']] ?? [];
        $sv['transport_vehicle'] = $det['vehicle'] ?? '';
        $sv['transport_trip_date'] = $det['trip_date'] ?? '';
    }
    unset($sv);
}

// ---- Flight legs: decode the JSON blob into an array for the card ---------
foreach ($services as &$sv) {
    $sv['flight_type'] = !empty($sv['flight_type']) ? $sv['flight_type'] : 'direct';
    $sv['flight_legs'] = (!empty($sv['flight_legs']) && is_string($sv['flight_legs']))
        ? (json_decode($sv['flight_legs'], true) ?: [])
        : [];
}
unset($sv);

// ---- Reference data ---------------------------------------------------------
$suppliers = [];
$supStmt = $pdo->prepare("SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$supStmt->execute([$tenant_id]);
$suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = [];
$stStmt = $pdo->prepare("SELECT status_group, code, label FROM umrah_service_statuses WHERE is_active = 1 ORDER BY status_group, sort_order");
$stStmt->execute();
foreach ($stStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statuses[$row['status_group']][] = ['code' => $row['code'], 'label' => $row['label']];
}

$hotels = [];
$hStmt = $pdo->prepare("SELECT id, name, city, supplier_id FROM umrah_hotels WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$hStmt->execute([$tenant_id]);
$hotels = $hStmt->fetchAll(PDO::FETCH_ASSOC);

$roomTypes = [];
$rtStmt = $pdo->prepare("SELECT id, name, max_occupancy FROM umrah_hotel_room_types WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$rtStmt->execute([$tenant_id]);
$roomTypes = $rtStmt->fetchAll(PDO::FETCH_ASSOC);

$rooms = [];
$rStmt = $pdo->prepare("SELECT id, hotel_id, room_type_id, room_number, floor FROM umrah_hotel_rooms WHERE tenant_id = ? AND status = 'active' ORDER BY room_number");
$rStmt->execute([$tenant_id]);
$rooms = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Per-service member counts (for contract amount splits) -----------------
// The split divisor is the number of ACTIVE members whose package actually
// includes the service — not every member of the family/group (members on
// other packages never consume this service). Standalone bookings (no
// family) fall back to the member itself.
$countScopeBookings = [];
if (!empty($booking['family_id'])) {
    if ($group_id_out > 0) {
        $cntStmt = $pdo->prepare("
            SELECT b.booking_id FROM umrah_bookings b
            JOIN families f ON f.family_id = b.family_id
            WHERE f.group_id = ? AND b.tenant_id = ?
              AND b.status NOT IN ('refunded', 'cancelled')");
        $cntStmt->execute([$group_id_out, $tenant_id]);
    } else {
        $cntStmt = $pdo->prepare("
            SELECT booking_id FROM umrah_bookings
            WHERE family_id = ? AND tenant_id = ?
              AND status NOT IN ('refunded', 'cancelled')");
        $cntStmt->execute([$booking['family_id'], $tenant_id]);
    }
    $countScopeBookings = $cntStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $countScopeBookings = [$booking_id];
}

$typeCounts = ['hotel' => 0, 'transport' => 0];
if ($countScopeBookings) {
    $ph = implode(',', array_fill(0, count($countScopeBookings), '?'));
    $tsStmt = $pdo->prepare("
        SELECT bs.booking_id,
               LOWER(COALESCE(c.name, '')) AS cat, LOWER(bs.service_type) AS st
        FROM umrah_booking_services bs
        JOIN umrah_bookings b ON b.booking_id = bs.booking_id
        LEFT JOIN umrah_services s ON s.id = bs.service_id
        LEFT JOIN umrah_service_categories c ON c.id = s.category_id
        WHERE bs.booking_id IN ($ph) AND b.tenant_id = ?
          AND b.status NOT IN ('refunded', 'cancelled')");
    $tsStmt->execute(array_merge($countScopeBookings, [$tenant_id]));
    $seenByType = ['hotel' => [], 'transport' => []];
    foreach ($tsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = '';
        if ($row['cat'] === 'hotel' || $row['st'] === 'hotel') $type = 'hotel';
        elseif ($row['cat'] === 'transport' || $row['st'] === 'transport') $type = 'transport';
        if ($type !== '') { $seenByType[$type][(int)$row['booking_id']] = 1; }
    }
    $typeCounts['hotel'] = count($seenByType['hotel']);
    $typeCounts['transport'] = count($seenByType['transport']);
}
$typeCounts['hotel'] = max(1, $typeCounts['hotel']);
$typeCounts['transport'] = max(1, $typeCounts['transport']);

$contracts = [];
$cStmt = $pdo->prepare("
    SELECT id, supplier_id, contract_number, contract_type, contract_amount, contract_currency,
           valid_from, valid_to
    FROM umrah_hotel_contracts
    WHERE tenant_id = ? AND status = 'active'");
$cStmt->execute([$tenant_id]);
$contracts = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$chStmt = $pdo->prepare("SELECT contract_id, hotel_id FROM umrah_contract_hotels WHERE tenant_id = ?");
$chStmt->execute([$tenant_id]);
$cHotels = $chStmt->fetchAll(PDO::FETCH_ASSOC);

$crStmt = $pdo->prepare("SELECT contract_id, hotel_id, room_type_id, cost_currency, cost_price
                         FROM umrah_hotel_contract_rates WHERE tenant_id = ?");
$crStmt->execute([$tenant_id]);
$cRates = $crStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contracts as &$c) {
    $c['hotels'] = array_values(array_map('intval', array_column(
        array_filter($cHotels, fn($ch) => (int)$ch['contract_id'] === (int)$c['id']), 'hotel_id')));
    $c['rates'] = array_values(array_filter($cRates, fn($r) => (int)$r['contract_id'] === (int)$c['id']));
    $c['per_member_cost'] = ($c['contract_type'] === 'per_trip' && $c['contract_amount'] !== null)
        ? round((float)$c['contract_amount'] / $typeCounts['hotel'], 3) : null;
    $c['member_count'] = $typeCounts['hotel'];
}
unset($c);

// ---- Enrich hotel services with per-city cost fields ----------------------
enrichHotelCityCosts($services, $pdo, $tenant_id);

// ---- Enrich extra bed members with stored cost data -----------------------
enrichExtraBedCosts($services, $pdo, $tenant_id);

// ---- Transport contracts (amount-based: amount / members with the service) ----
$transportContracts = [];
$tcStmt = $pdo->prepare("
    SELECT id, supplier_id, contract_number, contract_type, contract_amount, contract_currency, valid_from, valid_to
    FROM umrah_transport_contracts
    WHERE tenant_id = ? AND status = 'active'");
$tcStmt->execute([$tenant_id]);
foreach ($tcStmt->fetchAll(PDO::FETCH_ASSOC) as $tc) {
    $tc['per_member_cost'] = ($tc['contract_amount'] !== null)
        ? round((float)$tc['contract_amount'] / $typeCounts['transport'], 3) : null;
    $tc['member_count'] = $typeCounts['transport'];
    $transportContracts[] = $tc;
}

echo json_encode([
    'success' => true,
    'booking' => $booking,
    'scope' => $scopeMode,
    'group_id' => $group_id_out,
    'group_name' => $group_name_out,
    'services' => $services,
    'families' => $familyNames,
    'suppliers' => $suppliers,
    'brn_costs' => $brnCosts,
    'statuses' => $statuses,
    'hotels' => $hotels,
    'room_types' => $roomTypes,
    'rooms' => $rooms,
    'contracts' => $contracts,
    'transport_contracts' => $transportContracts,
]);
