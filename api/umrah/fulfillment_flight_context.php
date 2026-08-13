<?php
/**
 * Resolve a flight fulfillment into a group-ticket-shaped context so the
 * manifest / group-ticket / rooming / client-report document endpoints can
 * serve fulfillment-saved flights with the same buttons as group tickets.
 *
 * Returns ['ticket' => [group_tickets-shaped row], 'member_ids' => [booking ids],
 *          'flight' => [umrah_flight_fulfillments row]] or null when the
 * booking has no flight fulfillment.
 */
function fulfillment_flight_context(PDO $pdo, int $tenant_id, int $branch_id, int $booking_id): ?array
{
    $q = $pdo->prepare("
        SELECT ff.*, f.status AS fulfillment_status
        FROM umrah_flight_fulfillments ff
        JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
        JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
        WHERE ub.booking_id = ? AND f.tenant_id = ? AND ub.branch_id = ?
        ORDER BY ff.id DESC LIMIT 1");
    $q->execute([$booking_id, $tenant_id, $branch_id]);
    $ff = $q->fetch(PDO::FETCH_ASSOC);
    if (!$ff) {
        return null;
    }

    // Every member on the same flight (same outbound + return ticket identity) is part of this ticket.
    $identity = "ff.airline = ? AND ff.flight_number = ? AND COALESCE(ff.pnr,'') = ? AND COALESCE(ff.ticket_number,'') = ?
                 AND COALESCE(ff.departure_city,'') = ? AND COALESCE(ff.arrival_city,'') = ?
                 AND COALESCE(ff.return_flight_number,'') = ? AND COALESCE(ff.return_departure_time,'') = ?";
    $m = $pdo->prepare("
        SELECT ub.booking_id
        FROM umrah_flight_fulfillments ff
        JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
        JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
        WHERE f.tenant_id = ? AND ub.branch_id = ? AND ub.status NOT IN ('refunded', 'cancelled')
          AND " . $identity . "
        ORDER BY ub.booking_id");
    $m->execute([$tenant_id, $branch_id,
        (string)$ff['airline'], (string)$ff['flight_number'], (string)$ff['pnr'], (string)$ff['ticket_number'],
        (string)$ff['departure_city'], (string)$ff['arrival_city'],
        (string)$ff['return_flight_number'], (string)$ff['return_departure_time']]);
    $memberIds = array_map('intval', $m->fetchAll(PDO::FETCH_COLUMN));

    // Trip duration is not stored on fulfillments — derive it from the
    // outbound -> return departure gap (e.g. "14 Days").
    $duration = '';
    if (!empty($ff['departure_time']) && !empty($ff['return_departure_time'])) {
        $depTs = strtotime((string)$ff['departure_time']);
        $retTs = strtotime((string)$ff['return_departure_time']);
        if ($depTs !== false && $retTs !== false && $retTs >= $depTs) {
            $duration = (int)round(($retTs - $depTs) / 86400) . ' Days';
        }
    }

    $ticket = [
        'airline_name' => (string)($ff['airline'] ?? ''),
        'pnr' => (string)($ff['pnr'] ?? ''),
        'ticket_number' => (string)($ff['ticket_number'] ?? ''),
        'flight_type' => ($ff['flight_type'] ?? 'direct') === 'indirect' ? 'indirect' : 'direct',
        'flight_date' => !empty($ff['departure_time']) ? date('Y-m-d', strtotime($ff['departure_time'])) : '',
        'departure_time' => !empty($ff['departure_time']) ? date('H:i', strtotime($ff['departure_time'])) : '',
        'arrival_time' => !empty($ff['arrival_time']) ? date('H:i', strtotime($ff['arrival_time'])) : '',
        'return_date' => !empty($ff['return_departure_time']) ? date('Y-m-d', strtotime($ff['return_departure_time'])) : '',
        'return_time' => !empty($ff['return_departure_time']) ? date('H:i', strtotime($ff['return_departure_time'])) : '',
        'return_arrival_time' => !empty($ff['return_arrival_time']) ? date('H:i', strtotime($ff['return_arrival_time'])) : '',
        'departure_city' => (string)($ff['departure_city'] ?? ''),
        'arrival_city' => (string)($ff['arrival_city'] ?? ''),
        'flight_number_1' => (string)($ff['flight_number'] ?? ''),
        'flight_number_2' => (string)($ff['return_flight_number'] ?? ''),
        'duration' => $duration,
        'remarks' => '',
        'flight_legs' => (string)($ff['flight_legs'] ?? ''),
        'class' => (string)($ff['class'] ?? ''),
    ];

    return ['ticket' => $ticket, 'member_ids' => $memberIds, 'flight' => $ff];
}

/**
 * Build outbound/return flight arrays from a flight-fulfillment row (direct
 * or indirect via flight_legs JSON) — same shape ticket_pdf_render.php and
 * generate_fulfillment_ticket.php consume.
 */
function fulfillment_flight_legs(array $ff): array
{
    $outbound = [];
    $return = [];

    if (($ff['flight_type'] ?? 'direct') === 'direct') {
        if (!empty($ff['flight_number'])) {
            $outbound[] = [
                'flight_number' => $ff['flight_number'],
                'departure_city' => $ff['departure_city'] ?? '',
                'arrival_city' => $ff['arrival_city'] ?? '',
                'departure_datetime' => $ff['departure_time'] ?? '',
                'arrival_datetime' => $ff['arrival_time'] ?? '',
            ];
        }
        if (!empty($ff['return_flight_number'])) {
            $return[] = [
                'flight_number' => $ff['return_flight_number'],
                'departure_city' => $ff['arrival_city'] ?? '',
                'arrival_city' => $ff['departure_city'] ?? '',
                'departure_datetime' => $ff['return_departure_time'] ?? '',
                'arrival_datetime' => $ff['return_arrival_time'] ?? '',
            ];
        }
        return ['outbound' => $outbound, 'return' => $return];
    }

    $legs = [];
    if (!empty($ff['flight_legs'])) {
        $decoded = json_decode($ff['flight_legs'], true);
        if (is_array($decoded)) {
            $legs = $decoded;
        }
    }
    foreach ($legs as $leg) {
        if (!is_array($leg) || empty($leg['flight_no'])) {
            continue;
        }
        $flight = [
            'flight_number' => $leg['flight_no'],
            'departure_city' => $leg['dep_city'] ?? '',
            'arrival_city' => $leg['arr_city'] ?? '',
            'departure_datetime' => trim(($leg['dep_date'] ?? '') . ' ' . ($leg['dep_time'] ?? '')),
            'arrival_datetime' => trim(($leg['arr_date'] ?? '') . ' ' . ($leg['arr_time'] ?? '')),
        ];
        if (strpos((string)($leg['label'] ?? ''), 'return') === 0) {
            $return[] = $flight;
        } else {
            $outbound[] = $flight;
        }
    }
    return ['outbound' => $outbound, 'return' => $return];
}
