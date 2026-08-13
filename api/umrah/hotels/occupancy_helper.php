<?php
/**
 * Shared hotel occupancy engine (Phases 24-25).
 * Computes room × date states (A/R/O/B) and room-type occupancy counts.
 *
 * States per room per date (check-out day is free):
 *   O = Occupied    — a stay with a room assigned covers the date
 *   R = Reserved    — a stay covers the date at room-type level (no room assigned yet)
 *   B = Blocked     — room in maintenance
 *   A = Available   — otherwise
 */

if (!function_exists('hotelCalendarData')) {
function hotelCalendarData(PDO $pdo, int $tenantId, int $hotelId, string $from, string $to, ?int $roomTypeId = null): array
{
    $rooms = [];
    $rStmt = $pdo->prepare("
        SELECT r.id, r.room_type_id, r.room_number, r.floor, r.status,
               t.name AS room_type_name
        FROM umrah_hotel_rooms r
        LEFT JOIN umrah_hotel_room_types t ON r.room_type_id = t.id
        WHERE r.hotel_id = ? AND r.tenant_id = ?
          AND (r.room_type_id = ? OR ? IS NULL)
        ORDER BY t.name, r.room_number");
    $rStmt->execute([$hotelId, $tenantId, $roomTypeId, $roomTypeId]);
    $rooms = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    // Stays overlapping [from, to)
    $stays = [];
    $sStmt = $pdo->prepare("
        SELECT hf.room_id, hf.room_type_id, hf.check_in, hf.check_out,
               f.status AS fulfill_status, b.name AS member_name, b.booking_id,
               b.family_id, fam.head_of_family AS family_name,
               hf.nights, hf.nightly_rate, hf.currency
        FROM umrah_hotel_fulfillments hf
        INNER JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
            AND f.tenant_id = ? AND f.status <> 'cancelled'
        LEFT JOIN umrah_booking_services bs ON f.booking_service_id = bs.id
        LEFT JOIN umrah_bookings b ON bs.booking_id = b.booking_id
        LEFT JOIN families fam ON b.family_id = fam.family_id
        WHERE hf.hotel_id = ?
          AND hf.check_in IS NOT NULL AND hf.check_out IS NOT NULL
          AND hf.check_in < ? AND hf.check_out > ?
        ORDER BY hf.check_in");
    $sStmt->execute([$tenantId, $hotelId, $to, $from]);
    $stays = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    // Per date state per room
    $fromTs = strtotime($from);
    $toTs = strtotime($to);
    $days = [];
    for ($t = $fromTs; $t < $toTs; $t += 86400) {
        $days[] = date('Y-m-d', $t);
    }

    $grid = [];  // [room_id][date] => ['state','member','booking_id']
    $counts = []; // [room_type_id] => ['total','occupied','reserved','available']  (per date? -> per date index)
    $daily = []; // [date] => [room_type_id] => counts

    foreach ($days as $d) {
        $daily[$d] = [];
        foreach ($rooms as $room) {
            $rt = $room['room_type_id'];
            if (!isset($daily[$d][$rt])) {
                $daily[$d][$rt] = ['total' => 0, 'occupied' => 0, 'reserved' => 0, 'available' => 0, 'blocked' => 0];
            }
            if (strtolower($room['status']) === 'maintenance') {
                $grid[$room['id']][$d] = ['state' => 'B', 'member' => null, 'booking_id' => null];
                $daily[$d][$rt]['total']++;
                $daily[$d][$rt]['blocked']++;
                continue;
            }
            $daily[$d][$rt]['total']++;

            $state = 'A';
            $member = null;
            $family = null;
            $bkid = null;
            $checkIn = null;
            $checkOut = null;
            foreach ($stays as $stay) {
                if ($stay['check_in'] <= $d && $d < $stay['check_out']) {
                    if ($stay['room_id'] !== null && (int)$stay['room_id'] === (int)$room['id']) {
                        $state = 'O';
                        $member = $stay['member_name'];
                        $family = $stay['family_name'] ?? null;
                        $bkid = $stay['booking_id'];
                        $checkIn = $stay['check_in'];
                        $checkOut = $stay['check_out'];
                        break;
                    }
                    if ($stay['room_id'] === null && (int)$stay['room_type_id'] === (int)$room['room_type_id']) {
                        $state = 'R';
                        $member = $stay['member_name'];
                        $family = $stay['family_name'] ?? null;
                        $bkid = $stay['booking_id'];
                        $checkIn = $stay['check_in'];
                        $checkOut = $stay['check_out'];
                    }
                }
            }
            $grid[$room['id']][$d] = [
                'state' => $state,
                'member' => $member,
                'family' => $family,
                'booking_id' => $bkid,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ];
            if ($state === 'O') $daily[$d][$rt]['occupied']++;
            elseif ($state === 'R') $daily[$d][$rt]['reserved']++;
            else $daily[$d][$rt]['available']++;
        }
    }

    return [
        'rooms' => $rooms,
        'stays' => $stays,
        'days' => $days,
        'grid' => $grid,
        'daily' => $daily,
    ];
}
}

/**
 * Occupancy for a single date (used by the overview matrix).
 * Returns counts for one room type (total/occupied/reserved/available/blocked)
 * or, without a room type, the per-type map for that date.
 */
if (!function_exists('hotelOccupancyForDate')) {
function hotelOccupancyForDate(PDO $pdo, int $tenantId, int $hotelId, string $date, ?int $roomTypeId = null): array
{
    $data = hotelCalendarData($pdo, $tenantId, $hotelId, $date, date('Y-m-d', strtotime($date . ' +1 day')), $roomTypeId);
    $daily = $data['daily'][$date] ?? [];
    return $roomTypeId ? ($daily[$roomTypeId] ?? []) : $daily;
}
}
