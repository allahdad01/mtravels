<?php
/**
 * Shared fulfillment core — Phase 19-21 + skeleton-booking write-back.
 *
 * fulfillment_save() applies one fulfillment to one sold service:
 * validation, fulfillment upsert, typed details (hotel/flight), status
 * mirror + history, audit, AND the cost write-back added for packages sold
 * at a grand price with unpriced skeleton lines:
 *   - umrah_booking_services.base_price  <- actual cost (in sale currency)
 *   - umrah_bookings.price/profit        <- recomputed (profit = sold - discount - costs)
 *   - supplier_transactions              <- created once per supplier+booking+type
 *
 * Endpoints (save_fulfillment.php, save_multi_fulfillment.php) parse their
 * own input and hand it in as $in. All functions are function_exists-guarded
 * so this file can be require()-d repeatedly in CLI probes.
 */

if (!function_exists('resolveFulfillmentType')) {
function resolveFulfillmentType(PDO $pdo, int $tenantId, string $serviceType, ?int $serviceId, ?string $categoryName): string
{
    $cat = strtolower(trim((string)$categoryName));
    if ($cat === 'hotel') return 'hotel';
    if ($cat === 'flight') return 'flight';
    if ($cat === 'visa') return 'visa';
    if ($cat === 'transport') return 'transport';
    if ($cat === 'meal') return 'meal';
    if ($cat === 'ziyarat') return 'ziyarat';

    $t = strtolower(trim($serviceType));
    if ($t === 'ticket') return 'flight';
    if ($t === 'hotel') return 'hotel';
    if ($t === 'visa') return 'visa';
    if ($t === 'transport') return 'transport';
    if ($t === 'meal') return 'meal';
    return 'ziyarat';
}
}

if (!function_exists('statusGroupFor')) {
function statusGroupFor(string $fulfillmentType): string
{
    return $fulfillmentType; // groups are named after the type
}
}

/**
 * Bulk-apply variant guard. Returns null when the target line is a valid
 * recipient for a fulfillment driven by the source context, otherwise a
 * short reason label ('already <status>', 'different hotel',
 * 'different room type', 'different airline').
 *
 *   $src:  ['cat' => type, 'open' => array of open statuses,
 *           'hotel_id' => ?int, 'room_type_id' => ?int,
 *           'airline' => string, 'flight_number' => string]
 *   $line: ['status' => ?string, 'hotel_id' => ?, 'room_type_id' => ?,
 *           'airline' => ?string, 'flight_number' => ?string]
 */
if (!function_exists('fulfillment_variant_ok')) {
function fulfillment_variant_ok(array $src, array $line): ?string
{
    $status = isset($line['status']) && $line['status'] !== '' ? (string)$line['status'] : null;
    if ($status !== null && !in_array($status, $src['open'], true)) {
        return 'already ' . $status;
    }

    if (($src['cat'] ?? '') === 'hotel') {
        $tHotel = !empty($line['hotel_id']) ? (int)$line['hotel_id'] : null;
        if (!empty($src['hotel_id']) && $tHotel !== null && $tHotel !== (int)$src['hotel_id']) {
            return 'different hotel';
        }
        $tRoom = !empty($line['room_type_id']) ? (int)$line['room_type_id'] : null;
        if (!empty($src['room_type_id']) && $tRoom !== null && $tRoom !== (int)$src['room_type_id']) {
            return 'different room type';
        }
    } elseif (($src['cat'] ?? '') === 'flight') {
        $srcAir = (string)($src['airline'] ?? '');
        if ($srcAir !== '' && !empty($line['airline']) && (string)$line['airline'] !== $srcAir) {
            return 'different airline';
        }
        $srcFl = (string)($src['flight_number'] ?? '');
        if ($srcFl !== '' && !empty($line['flight_number']) && (string)$line['flight_number'] !== $srcFl) {
            return 'different flight';
        }
    }

    return null;
}
}

/**
 * Apply one fulfillment + write-back. Returns:
 *   ['success' => true, 'message' => ..., 'fulfillment_id' => int,
 *    'fulfillment_ids' => int[], 'status' => string,
 *    'cost_amount' => ?float, 'booking_price' => float,
 *    'booking_profit' => ?float]
 * or ['success' => false, 'code' => int, 'message' => string].
 *
 * Multi-hotel stays: for hotel services the caller may pass 'hotel_stays'
 * (array of stays, each with its own hotel_id / room_type_id / room_id /
 * check_in / check_out / nights / nightly_rate / contract_id / fulfillment_id).
 * Every stay is persisted as its own umrah_fulfillments + hotel details row,
 * so one hotel service can cover several cities (e.g. Makkah + Madinah).
 * The first stay carries the supplier cost; extra stays keep status/details
 * but no cost. Stays whose fulfillment_id is omitted become new rows, and
 * existing rows not referenced by the payload are removed (stay deletions).
 * When 'hotel_stays' is absent the flat hotel fields behave exactly like
 * before (single fulfillment per sold service).
 */
if (!function_exists('fulfillment_save')) {
function fulfillment_save(PDO $pdo, array $in): array
{
    $tenant_id = (int)($in['tenant_id'] ?? 0);
    $branch_id = (int)($in['branch_id'] ?? 0);
    $user_id   = (int)($in['user_id'] ?? 0);

    $booking_service_id = (int)($in['booking_service_id'] ?? 0);
    $supplier_id = isset($in['supplier_id']) && $in['supplier_id'] !== '' ? (int)$in['supplier_id'] : null;
    $status = (string)($in['status'] ?? 'pending');
    $supplier_currency = isset($in['supplier_currency']) ? trim((string)$in['supplier_currency']) : '';
    $supplier_cost = (isset($in['supplier_cost']) && $in['supplier_cost'] !== '') ? (float)$in['supplier_cost'] : null;
    $exchange_rate = (isset($in['exchange_rate']) && $in['exchange_rate'] !== '') ? (float)$in['exchange_rate'] : null;
    $requested_date = !empty($in['requested_date']) ? (string)$in['requested_date'] : null;
    $planned_date = !empty($in['planned_date']) ? (string)$in['planned_date'] : null;
    $completed_date = !empty($in['completed_date']) ? (string)$in['completed_date'] : null;
    $notes = isset($in['notes']) ? (string)$in['notes'] : null;

    // Hotel-specific
    $hotel_id = (int)($in['hotel_id'] ?? 0) ?: null;
    $room_id = (int)($in['room_id'] ?? 0) ?: null;
    $room_type_id = (int)($in['room_type_id'] ?? 0) ?: null;
    $extra_bed = !empty($in['extra_bed']) ? 1 : 0;
    $check_in = !empty($in['check_in']) ? (string)$in['check_in'] : null;
    $check_out = !empty($in['check_out']) ? (string)$in['check_out'] : null;
    $nights = (isset($in['nights']) && $in['nights'] !== '') ? (int)$in['nights'] : null;
    $nightly_rate = (isset($in['nightly_rate']) && $in['nightly_rate'] !== '') ? (float)$in['nightly_rate'] : null;
    $contract_id = (int)($in['contract_id'] ?? 0);

    // Multi-stay hotel input: one entry per hotel stay (each stay = its own
    // fulfillment row). Takes precedence over the flat hotel fields above.
    $hotel_stays_in = null;
    if (isset($in['hotel_stays']) && is_array($in['hotel_stays'])) {
        $hotel_stays_in = [];
        foreach ($in['hotel_stays'] as $st) {
            if (!is_array($st)) continue;
            $hotel_stays_in[] = [
                'fulfillment_id' => !empty($st['fulfillment_id']) ? (int)$st['fulfillment_id'] : null,
                'hotel_id'       => (int)($st['hotel_id'] ?? 0) ?: null,
                'room_id'        => (int)($st['room_id'] ?? 0) ?: null,
                'room_type_id'   => (int)($st['room_type_id'] ?? 0) ?: null,
                'extra_bed'      => !empty($st['extra_bed']) ? 1 : 0,
                'check_in'       => !empty($st['check_in']) ? (string)$st['check_in'] : null,
                'check_out'      => !empty($st['check_out']) ? (string)$st['check_out'] : null,
                'nights'         => (isset($st['nights']) && $st['nights'] !== '') ? (int)$st['nights'] : null,
                'nightly_rate'   => (isset($st['nightly_rate']) && $st['nightly_rate'] !== '') ? (float)$st['nightly_rate'] : null,
                'contract_id'    => !empty($st['contract_id']) ? (int)$st['contract_id'] : null,
            ];
        }
    }

    // Flight-specific
    $ticket_number = isset($in['ticket_number']) ? trim((string)$in['ticket_number']) : '';
    $pnr = isset($in['pnr']) ? trim((string)$in['pnr']) : '';
    $airline = isset($in['airline']) ? trim((string)$in['airline']) : '';
    $flight_number = isset($in['flight_number']) ? trim((string)$in['flight_number']) : '';
    $departure_city = isset($in['departure_city']) ? trim((string)$in['departure_city']) : '';
    $arrival_city = isset($in['arrival_city']) ? trim((string)$in['arrival_city']) : '';
    $departure_time = !empty($in['departure_time']) ? (string)$in['departure_time'] : null;
    $arrival_time = !empty($in['arrival_time']) ? (string)$in['arrival_time'] : null;
    $return_flight_number = isset($in['return_flight_number']) ? trim((string)$in['return_flight_number']) : '';
    $return_departure_time = !empty($in['return_departure_time']) ? (string)$in['return_departure_time'] : null;
    $return_arrival_time = !empty($in['return_arrival_time']) ? (string)$in['return_arrival_time'] : null;
    $flight_type = isset($in['flight_type']) ? strtolower(trim((string)$in['flight_type'])) : 'direct';

    // Multi-leg flight input (connecting flights): JSON string or array of
    // leg objects. Each leg: label (outbound_1|outbound_2|return_1|return_2),
    // dep_city, arr_city, flight_no, dep_date, dep_time, arr_date, arr_time.
    // Stored as one JSON blob on umrah_flight_fulfillments.flight_legs.
    $flight_legs = null;
    if (isset($in['flight_legs']) && is_string($in['flight_legs']) && $in['flight_legs'] !== '') {
        $decoded = json_decode($in['flight_legs'], true);
        if (is_array($decoded)) { $flight_legs = $decoded; }
    } elseif (isset($in['flight_legs']) && is_array($in['flight_legs'])) {
        $flight_legs = $in['flight_legs'];
    }
    if (!in_array($flight_type, ['direct', 'indirect'], true)) {
        return ['success' => false, 'code' => 400, 'message' => 'Invalid flight type. Allowed: direct, indirect.'];
    }

    // Transport-specific (free-text vehicle + trip date, stored as
    // umrah_fulfillment_details key/value rows).
    $transport_vehicle = isset($in['transport_vehicle']) ? trim((string)$in['transport_vehicle']) : '';
    $transport_trip_date = !empty($in['transport_trip_date']) ? (string)$in['transport_trip_date'] : null;

    if (!$booking_service_id) {
        return ['success' => false, 'code' => 400, 'message' => 'Booking service is required.'];
    }

        $pdo->beginTransaction();
        $savedFulfillments = [];
        $fulfillment_id = 0;
        try {
        // ---- Load the sold service -------------------------------------------
        $svcStmt = $pdo->prepare("
            SELECT bs.*, s.name AS service_name, c.name AS category_name
            FROM umrah_booking_services bs
            LEFT JOIN umrah_services s ON bs.service_id = s.id
            LEFT JOIN umrah_service_categories c ON s.category_id = c.id
            WHERE bs.id = ? AND bs.tenant_id = ?");
        $svcStmt->execute([$booking_service_id, $tenant_id]);
        $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);

        if (!$svc) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Sold service not found.'];
        }

        $booking_id = (int)$svc['booking_id'];

        $fulfillment_type = resolveFulfillmentType($pdo, $tenant_id, (string)$svc['service_type'], $svc['service_id'], $svc['category_name']);
        $status_group = statusGroupFor($fulfillment_type);

        // ---- Validate status against the catalog -------------------------------
        $statusOk = $pdo->prepare("SELECT 1 FROM umrah_service_statuses WHERE status_group = ? AND code = ? AND is_active = 1");
        $statusOk->execute([$status_group, $status]);
        if (!$statusOk->fetchColumn()) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Invalid status "' . $status . '" for ' . $fulfillment_type . '.'];
        }

        // ---- Validate supplier --------------------------------------------------
        if ($supplier_id) {
            $supOk = $pdo->prepare("SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? AND status = 'active'");
            $supOk->execute([$supplier_id, $tenant_id]);
            if (!$supOk->fetchColumn()) {
                $pdo->rollBack();
                return ['success' => false, 'code' => 400, 'message' => 'Supplier is inactive or does not belong to this tenant.'];
            }
        }

        // ---- Existing fulfillments (hotel: one row per stay) --------------------
        $fStmt = $pdo->prepare("SELECT * FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ? ORDER BY id ASC");
        $fStmt->execute([$booking_service_id, $tenant_id]);
        $existingRows = $fStmt->fetchAll(PDO::FETCH_ASSOC);
        $existingById = [];
        foreach ($existingRows as $er) { $existingById[(int)$er['id']] = $er; }
        $existing = $existingRows ? $existingRows[count($existingRows) - 1] : null;

        // Sum of the service's previous costs (one row per hotel stay) — used
        // to compute the delta for supplier-balance correction transactions.
        $oldSum = 0.0;
        foreach ($existingRows as $er) { $oldSum += (float)$er['cost_amount']; }

        // ---- Build the stay list (hotel only) -----------------------------------
        $stays = [];
        $multiStay = false;
        if ($fulfillment_type === 'hotel') {
            if ($hotel_stays_in !== null) {
                $multiStay = true;
                $stays = $hotel_stays_in;
            } else {
                $stays[] = [
                    'fulfillment_id' => $existing ? (int)$existing['id'] : null,
                    'hotel_id'       => $hotel_id,
                    'room_id'        => $room_id,
                    'room_type_id'   => $room_type_id,
                    'extra_bed'      => $extra_bed,
                    'check_in'       => $check_in,
                    'check_out'      => $check_out,
                    'nights'         => $nights,
                    'nightly_rate'   => $nightly_rate,
                    'contract_id'    => $contract_id ?: null,
                ];
            }
        }

        // ---- Phase 31: currency + price sanity ---------------------------------
        $supplier_currency = strtoupper(trim((string)$supplier_currency));
        if ($supplier_currency !== '' && !in_array($supplier_currency, ['USD', 'AFS', 'SAR'])) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Invalid supplier currency. Allowed: USD, AFS, SAR.'];
        }
        if ($supplier_currency === '') { $supplier_currency = null; }
        if ($supplier_cost !== null && $supplier_cost < 0) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Supplier cost cannot be negative.'];
        }
        if ($exchange_rate !== null && $exchange_rate <= 0) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Exchange rate must be greater than zero.'];
        }

        // ---- Phase 31: hotel assignment integrity (checked per stay) -------------
        if ($fulfillment_type === 'hotel') {
            foreach ($stays as $i => &$stay) {
                $stayId = $stay['fulfillment_id'];
                $tgt = $stayId ? ($existingById[$stayId] ?? null) : null;
                if ($stayId !== null && !$tgt) {
                    $pdo->rollBack();
                    return ['success' => false, 'code' => 400, 'message' => 'One of the hotel stays no longer exists for this service.'];
                }
                $stay['existing'] = $tgt;

                if ($stay['hotel_id'] !== null) {
                    $hOk = $pdo->prepare("SELECT 1 FROM umrah_hotels WHERE id = ? AND tenant_id = ? AND status = 'active'");
                    $hOk->execute([$stay['hotel_id'], $tenant_id]);
                    if (!$hOk->fetchColumn()) {
                        $pdo->rollBack();
                        return ['success' => false, 'code' => 400, 'message' => 'Hotel is inactive or does not belong to this tenant.'];
                    }
                    if ($stay['room_type_id'] !== null) {
                        $rtOk = $pdo->prepare("SELECT 1 FROM umrah_hotel_room_types WHERE id = ? AND tenant_id = ? AND status = 'active'");
                        $rtOk->execute([$stay['room_type_id'], $tenant_id]);
                        if (!$rtOk->fetchColumn()) {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Room type is inactive or does not belong to this tenant.'];
                        }
                    }
                    if ($stay['room_id'] !== null) {
                        $rOk = $pdo->prepare("SELECT room_type_id FROM umrah_hotel_rooms WHERE id = ? AND hotel_id = ? AND status = 'active'");
                        $rOk->execute([$stay['room_id'], $stay['hotel_id']]);
                        $rRow = $rOk->fetch(PDO::FETCH_ASSOC);
                        if (!$rRow) {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Room does not belong to this hotel or is unavailable (maintenance/inactive).'];
                        }
                        if ($stay['room_type_id'] !== null && (int)$rRow['room_type_id'] !== (int)$stay['room_type_id']) {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Room does not match the selected room type.'];
                        }
                    }
                    if (empty($stay['check_in']) || empty($stay['check_out'])) {
                        $pdo->rollBack();
                        return ['success' => false, 'code' => 400, 'message' => 'Hotel assignment requires check-in and check-out dates.'];
                    }
                    $inTs = strtotime($stay['check_in']); $outTs = strtotime($stay['check_out']);
                    if (!$inTs || !$outTs || $outTs <= $inTs) {
                        $pdo->rollBack();
                        return ['success' => false, 'code' => 400, 'message' => 'Check-out must be after check-in.'];
                    }
                    $stay['nights'] = (int)ceil(($outTs - $inTs) / 86400);
                    if ($stay['nightly_rate'] !== null && $stay['nightly_rate'] < 0) {
                        $pdo->rollBack();
                        return ['success' => false, 'code' => 400, 'message' => 'Nightly rate cannot be negative.'];
                    }
                    if (empty($supplier_currency)) {
                        $pdo->rollBack();
                        return ['success' => false, 'code' => 400, 'message' => 'Supplier currency is required for hotel assignments.'];
                    }

                    // Contract / inventory coverage (cannot use expired contract inventory)
                    if ($stay['contract_id'] !== null && $stay['contract_id'] > 0) {
                        $cOk = $pdo->prepare("SELECT c.valid_from, c.valid_to, c.status
                                              FROM umrah_hotel_contracts c
                                              JOIN umrah_contract_hotels ch ON ch.contract_id = c.id AND ch.hotel_id = ?
                                              WHERE c.id = ? AND c.tenant_id = ? AND ch.tenant_id = ?");
                        $cOk->execute([$stay['hotel_id'], $stay['contract_id'], $tenant_id, $tenant_id]);
                        $cRow = $cOk->fetch(PDO::FETCH_ASSOC);
                        if (!$cRow || $cRow['status'] !== 'active') {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Contract is inactive, expired, or does not belong to this hotel.'];
                        }
                        if (($cRow['valid_from'] && $stay['check_in'] < $cRow['valid_from']) || ($cRow['valid_to'] && $stay['check_out'] > $cRow['valid_to'])) {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Stay is outside the contract validity period (contract ' . $stay['contract_id'] . ').'];
                        }
                        if ($stay['room_id'] !== null) {
                            $invOk = $pdo->prepare("SELECT 1 FROM umrah_hotel_contract_inventory WHERE contract_id = ? AND room_id = ? AND status = 'active' AND (valid_from IS NULL OR valid_from <= ?) AND (valid_to IS NULL OR valid_to >= ?)");
                            $invOk->execute([$stay['contract_id'], $stay['room_id'], $stay['check_in'], $stay['check_out']]);
                            if (!$invOk->fetchColumn()) {
                                $pdo->rollBack();
                                return ['success' => false, 'code' => 400, 'message' => 'Room is not covered by this contract inventory for the stay period.'];
                            }
                        }
                    } elseif ($stay['room_id'] !== null) {
                        $bound = $pdo->prepare("
                            SELECT COUNT(*) FROM umrah_hotel_contract_inventory ci
                            JOIN umrah_hotel_contracts c ON c.id = ci.contract_id
                            WHERE ci.room_id = ? AND ci.tenant_id = ? AND ci.status = 'active' AND c.status = 'active'");
                        $bound->execute([$stay['room_id'], $tenant_id]);
                        if ((int)$bound->fetchColumn() > 0) {
                            $cover = $pdo->prepare("
                                SELECT 1 FROM umrah_hotel_contract_inventory ci
                                JOIN umrah_hotel_contracts c ON c.id = ci.contract_id
                                WHERE ci.room_id = ? AND ci.tenant_id = ? AND c.status = 'active' AND ci.status = 'active'
                                  AND (c.valid_from IS NULL OR c.valid_from <= ?) AND (c.valid_to IS NULL OR c.valid_to >= ?)
                                  AND (ci.valid_from IS NULL OR ci.valid_from <= ?) AND (ci.valid_to IS NULL OR ci.valid_to >= ?)");
                            $cover->execute([$stay['room_id'], $tenant_id, $stay['check_in'], $stay['check_out'], $stay['check_in'], $stay['check_out']]);
                            if (!$cover->fetchColumn()) {
                                $pdo->rollBack();
                                return ['success' => false, 'code' => 400, 'message' => 'No active contract inventory covers this stay (contract expired or inventory window closed).'];
                            }
                        }
                    }

                    // Occupancy check: count how many members already hold
                    // this room for the stay dates, then compare against the
                    // room type's capacity. Rooms without a max_occupancy keep
                    // the strict one-stay rule (matched by the old check).
                    // Extra beds (rollaway cots) raise the effective capacity
                    // by 1 per overlapping extra-bedded stay — so a family of
                    // 5 can share a 4-person room with one extra bed (4+1).
                    if ($stay['room_id'] !== null) {
                        $selfId = $tgt ? (int)$tgt['id'] : 0;
                        $occ = $pdo->prepare("
                            SELECT COUNT(DISTINCT bs.booking_id) AS members,
                                   COALESCE(SUM(CASE WHEN hf.extra_bed = 1 THEN 1 ELSE 0 END), 0) AS extra_beds
                            FROM umrah_hotel_fulfillments hf
                            JOIN umrah_fulfillments f ON f.id = hf.fulfillment_id
                            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                            WHERE hf.tenant_id = ? AND hf.hotel_id = ? AND hf.room_id = ? AND hf.fulfillment_id <> ?
                              AND hf.check_in < ? AND hf.check_out > ?
                              AND f.status NOT IN ('cancelled', 'checked_out')");
                        $occ->execute([$tenant_id, $stay['hotel_id'], $stay['room_id'], $selfId, $stay['check_out'], $stay['check_in']]);
                        $occRow = $occ->fetch(PDO::FETCH_ASSOC);
                        $membersOnRoom = (int)($occRow['members'] ?? 0);
                        $extraBeds = (int)($occRow['extra_beds'] ?? 0) + (($stay['extra_bed'] ?? 0) ? 1 : 0);

                        $capStmt = $pdo->prepare("
                            SELECT r.room_number, rt.max_occupancy
                            FROM umrah_hotel_rooms r
                            LEFT JOIN umrah_hotel_room_types rt ON rt.id = r.room_type_id
                            WHERE r.id = ? AND r.tenant_id = ?");
                        $capStmt->execute([$stay['room_id'], $tenant_id]);
                        $capRow = $capStmt->fetch(PDO::FETCH_ASSOC);
                        $roomNumber = $capRow ? (string)($capRow['room_number'] ?? '') : '';
                        $maxOcc = ($capRow !== false && $capRow['max_occupancy'] !== null && (int)$capRow['max_occupancy'] > 0)
                            ? (int)$capRow['max_occupancy'] : 1;

                        if ($membersOnRoom + 1 > $maxOcc + $extraBeds) {
                            $pdo->rollBack();
                            return ['success' => false, 'code' => 400, 'message' => 'Room ' . $roomNumber . ' is full — ' . $membersOnRoom . ' member(s) already assigned for these dates (capacity ' . $maxOcc . ($extraBeds ? ' + ' . $extraBeds . ' extra bed' . ($extraBeds === 1 ? '' : 's') : '') . ').'];
                        }
                    }
                }
            }
            unset($stay);
        }

        // ---- Phase 21: freeze cost once the fulfillment leaves the setup phase ----
        $openStatuses = ['pending', 'requested', 'assigned', 'not_assigned', 'reserved', 'not_applied', 'applied', 'processing', 'confirmed', 'ticketed', 'issued', 'not_ticketed'];
        $costTarget = ($fulfillment_type === 'hotel' && $stays) ? ($stays[0]['existing'] ?? null) : $existing;
        $locked = $costTarget && !in_array($costTarget['status'], $openStatuses);

        // ---- Hotel: derive the supplier cost from the stay rates when the cost
        // was left blank (open fulfillments only — a frozen snapshot is never
        // rewritten). nightly_rate × nights per stay, matching the UI's own
        // contract auto-pricing, so a nightly rate alone can't silently zero
        // the cost and inflate the booking profit.
        if ($fulfillment_type === 'hotel' && $supplier_cost === null && !$locked && $stays) {
            $staySum = 0.0;
            $anyRate = false;
            foreach ($stays as $st) {
                if (!empty($st['nights']) && $st['nightly_rate'] !== null) {
                    $staySum += (float)$st['nights'] * (float)$st['nightly_rate'];
                    $anyRate = true;
                }
            }
            if ($anyRate) { $supplier_cost = round($staySum, 3); }
        }

        // Cost amount in booking (sale) currency (frozen snapshot)
        $cost_amount = null;
        $booking_currency = strtoupper(trim((string)$svc['currency'])) ?: 'USD';
        if ($supplier_cost !== null && $supplier_currency) {
            $cost_amount = ($supplier_currency === $booking_currency)
                ? $supplier_cost
                : ($exchange_rate && $exchange_rate > 0 ? $supplier_cost / $exchange_rate : null);
        }
        if ($supplier_cost !== null && $supplier_currency && $supplier_currency !== $booking_currency
            && !($exchange_rate && $exchange_rate > 0)) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 400, 'message' => 'Exchange rate is required when the supplier currency differs from the booking currency (' . $booking_currency . ').'];
        }

        if ($locked) {
            $costChanged =
                (float)$costTarget['supplier_cost'] !== (float)$supplier_cost ||
                (string)$costTarget['supplier_currency'] !== (string)$supplier_currency ||
                (float)$costTarget['exchange_rate'] !== (float)$exchange_rate;
            if ($costChanged) {
                $pdo->rollBack();
                return ['success' => false, 'code' => 400, 'message' => 'Cost is frozen for this fulfillment (already ' . $costTarget['status'] . '). Historical costs cannot be rewritten.'];
            }
        }

        if ($fulfillment_type === 'hotel') {
            // ---- One fulfillment row per stay; the first stay carries the cost ----
            foreach ($stays as $i => $stay) {
                $tgt = $stay['existing'];
                $bearCost = ($i === 0);

                if ($tgt) {
                    // ---- Update existing fulfillment ------------------------------
                    $upd = $pdo->prepare("
                        UPDATE umrah_fulfillments SET
                            supplier_id = ?, status = ?, requested_date = ?, planned_date = ?, completed_date = ?,
                            supplier_currency = ?, supplier_cost = ?, exchange_rate = ?, cost_amount = ?, notes = ?,
                            updated_at = NOW()
                        WHERE id = ?");
                    $upd->execute([$supplier_id, $status, $requested_date, $planned_date, $completed_date,
                                   $bearCost ? $supplier_currency : $tgt['supplier_currency'],
                                   $bearCost ? $supplier_cost : $tgt['supplier_cost'],
                                   $bearCost ? $exchange_rate : $tgt['exchange_rate'],
                                   $bearCost ? $cost_amount : $tgt['cost_amount'],
                                   $notes, (int)$tgt['id']]);
                    $fulfillment_id = (int)$tgt['id'];
                } else {
                    // ---- Create fulfillment ----------------------------------------
                    $ins = $pdo->prepare("
                        INSERT INTO umrah_fulfillments (
                            tenant_id, branch_id, booking_service_id, fulfillment_type,
                            supplier_id, status, requested_date, planned_date, completed_date,
                            supplier_currency, supplier_cost, exchange_rate, cost_amount, notes, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$tenant_id, $branch_id, $booking_service_id, $fulfillment_type,
                                   $supplier_id, $status, $requested_date, $planned_date, $completed_date,
                                   $bearCost ? $supplier_currency : null,
                                   $bearCost ? $supplier_cost : null,
                                   $bearCost ? $exchange_rate : null,
                                   $bearCost ? $cost_amount : null,
                                   $notes, $user_id]);
                    $fulfillment_id = (int)$pdo->lastInsertId();
                }

                // ---- Typed details: one hotel fulfillment row per stay -------------
                $hStmt = $pdo->prepare("SELECT id FROM umrah_hotel_fulfillments WHERE fulfillment_id = ?");
                $hStmt->execute([$fulfillment_id]);
                $hfId = $hStmt->fetchColumn();

                $hotel_cost = null;
                if ($stay['nights'] && $stay['nightly_rate'] !== null) { $hotel_cost = $stay['nights'] * $stay['nightly_rate']; }

                if ($hfId) {
                    $pdo->prepare("
                        UPDATE umrah_hotel_fulfillments SET
                            hotel_id = ?, room_id = ?, room_type_id = ?, extra_bed = ?, contract_id = ?,
                            check_in = ?, check_out = ?, nights = ?, nightly_rate = ?, currency = ?, cost_amount = ?
                        WHERE id = ?")
                        ->execute([$stay['hotel_id'], $stay['room_id'], $stay['room_type_id'], !empty($stay['extra_bed']) ? 1 : 0, $stay['contract_id'], $stay['check_in'], $stay['check_out'], $stay['nights'], $stay['nightly_rate'], $supplier_currency, $hotel_cost, $hfId]);
                } else {
                    $pdo->prepare("
                        INSERT INTO umrah_hotel_fulfillments (
                            tenant_id, branch_id, fulfillment_id, hotel_id, room_id, room_type_id, extra_bed, contract_id,
                            check_in, check_out, nights, nightly_rate, currency, cost_amount
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$tenant_id, $branch_id, $fulfillment_id, $stay['hotel_id'], $stay['room_id'], $stay['room_type_id'], !empty($stay['extra_bed']) ? 1 : 0, $stay['contract_id'],
                                   $stay['check_in'], $stay['check_out'], $stay['nights'], $stay['nightly_rate'], $supplier_currency, $hotel_cost]);
                }

                $savedFulfillments[] = ['id' => $fulfillment_id, 'old' => $tgt];
            }

            // ---- Reconcile: drop stays that were removed in the UI ----------------
            if ($multiStay) {
                $keepIds = array_column($savedFulfillments, 'id');
                // Capture suppliers of the fulfillments about to be deleted so
                // their exposure can be netted back to the live cost below.
                $delSupStmt = $pdo->prepare("SELECT DISTINCT supplier_id FROM umrah_fulfillments
                                             WHERE booking_service_id = ? AND tenant_id = ? AND supplier_id IS NOT NULL");
                $delSupStmt->execute([$booking_service_id, $tenant_id]);
                $reconcileSuppliers = array_map('intval', $delSupStmt->fetchAll(PDO::FETCH_COLUMN));
                if ($keepIds) {
                    $ph = implode(',', array_fill(0, count($keepIds), '?'));
                    $pdo->prepare("DELETE hf FROM umrah_hotel_fulfillments hf JOIN umrah_fulfillments f ON f.id = hf.fulfillment_id WHERE f.booking_service_id = ? AND f.tenant_id = ? AND f.id NOT IN ($ph)")
                        ->execute(array_merge([$booking_service_id, $tenant_id], $keepIds));
                    $pdo->prepare("DELETE FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ? AND id NOT IN ($ph)")
                        ->execute(array_merge([$booking_service_id, $tenant_id], $keepIds));
                } else {
                    $pdo->prepare("DELETE hf FROM umrah_hotel_fulfillments hf JOIN umrah_fulfillments f ON f.id = hf.fulfillment_id WHERE f.booking_service_id = ? AND f.tenant_id = ?")
                        ->execute([$booking_service_id, $tenant_id]);
                    $pdo->prepare("DELETE FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ?")
                        ->execute([$booking_service_id, $tenant_id]);
                }
                // Removed stays must never leave phantom exposure behind:
                // net each affected supplier back to the live fulfillments.
                if ($reconcileSuppliers) {
                    $mStmt = $pdo->prepare("SELECT name FROM umrah_bookings WHERE booking_id = ?");
                    $mStmt->execute([$booking_id]);
                    $rcMember = (string)($mStmt->fetchColumn() ?: 'Member');
                    $rcType = (string)($svc['service_type'] ?? $fulfillment_type);
                    foreach ($reconcileSuppliers as $rcSupplier) {
                        umrahReconcileSupplierExposure($pdo, $tenant_id, $branch_id, $booking_id, $rcSupplier, $rcMember, $rcType);
                    }
                }
            }
        } else {
            if ($existing) {
                // ---- Update existing fulfillment --------------------------------------
                $upd = $pdo->prepare("
                    UPDATE umrah_fulfillments SET
                        supplier_id = ?, status = ?, requested_date = ?, planned_date = ?, completed_date = ?,
                        supplier_currency = ?, supplier_cost = ?, exchange_rate = ?, cost_amount = ?, notes = ?,
                        updated_at = NOW()
                    WHERE id = ?");
                $upd->execute([$supplier_id, $status, $requested_date, $planned_date, $completed_date,
                               $supplier_currency, $supplier_cost, $exchange_rate, $cost_amount, $notes,
                               $existing['id']]);
                $fulfillment_id = (int)$existing['id'];
            } else {
                // ---- Create fulfillment ------------------------------------------------
                $ins = $pdo->prepare("
                    INSERT INTO umrah_fulfillments (
                        tenant_id, branch_id, booking_service_id, fulfillment_type,
                        supplier_id, status, requested_date, planned_date, completed_date,
                        supplier_currency, supplier_cost, exchange_rate, cost_amount, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$tenant_id, $branch_id, $booking_service_id, $fulfillment_type,
                               $supplier_id, $status, $requested_date, $planned_date, $completed_date,
                               $supplier_currency, $supplier_cost, $exchange_rate, $cost_amount, $notes, $user_id]);
                $fulfillment_id = (int)$pdo->lastInsertId();
            }
            $savedFulfillments[] = ['id' => $fulfillment_id, 'old' => $existing];

            // ---- Typed details ---------------------------------------------------------
            if ($fulfillment_type === 'flight') {
                $flStmt = $pdo->prepare("SELECT id FROM umrah_flight_fulfillments WHERE fulfillment_id = ?");
                $flStmt->execute([$fulfillment_id]);
                $ffId = $flStmt->fetchColumn();

                $flightLegsJson = $flight_legs !== null ? json_encode(array_values($flight_legs), JSON_UNESCAPED_UNICODE) : null;

                if ($ffId) {
                    $pdo->prepare("
                        UPDATE umrah_flight_fulfillments SET
                            ticket_number = ?, pnr = ?, airline = ?, flight_type = ?, flight_legs = ?,
                            flight_number = ?,
                            departure_city = ?, arrival_city = ?, departure_time = ?, arrival_time = ?,
                            return_flight_number = ?, return_departure_time = ?, return_arrival_time = ?, class = ?
                        WHERE id = ?")
                        ->execute([$ticket_number, $pnr, $airline, $flight_type, $flightLegsJson,
                                   $flight_number,
                                   $departure_city, $arrival_city, $departure_time, $arrival_time,
                                   $return_flight_number, $return_departure_time, $return_arrival_time, null, $ffId]);
                } else {
                    $pdo->prepare("
                        INSERT INTO umrah_flight_fulfillments (
                            tenant_id, branch_id, fulfillment_id, ticket_number, pnr, airline, flight_type, flight_legs, flight_number,
                            departure_city, arrival_city, departure_time, arrival_time, return_flight_number,
                            return_departure_time, return_arrival_time
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$tenant_id, $branch_id, $fulfillment_id, $ticket_number, $pnr, $airline, $flight_type, $flightLegsJson, $flight_number,
                                   $departure_city, $arrival_city, $departure_time, $arrival_time, $return_flight_number,
                                   $return_departure_time, $return_arrival_time]);
                }
            } elseif ($fulfillment_type === 'transport') {
                // Free-text vehicle + trip date on the generic detail store.
                $pdo->prepare("DELETE FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key IN ('vehicle', 'trip_date')")
                    ->execute([$fulfillment_id]);
                $details = [];
                if ($transport_vehicle !== '') $details['vehicle'] = $transport_vehicle;
                if ($transport_trip_date) $details['trip_date'] = $transport_trip_date;
                $insD = $pdo->prepare("INSERT INTO umrah_fulfillment_details (tenant_id, branch_id, fulfillment_id, detail_key, detail_value)
                                       VALUES (?, ?, ?, ?, ?)");
                foreach ($details as $k => $v) {
                    $insD->execute([$tenant_id, $branch_id, $fulfillment_id, $k, $v]);
                }
            }
        }

        // ---- Update sold service status + history ---------------------------------
        $old_status = (string)($svc['status'] ?: 'pending');
        if ($old_status !== $status) {
            $pdo->prepare("UPDATE umrah_booking_services SET status = ? WHERE id = ?")
                ->execute([$status, $booking_service_id]);

            $pdo->prepare("
                INSERT INTO umrah_booking_service_statuses (tenant_id, branch_id, booking_service_id, old_status, new_status, changed_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$tenant_id, $branch_id, $booking_service_id, $old_status, $status, $user_id, $notes]);
        }

        // ---- Write-back: actual cost onto the sold service + booking totals ------
        // With multiple hotel stays the cost is the sum of every stay's
        // fulfillment (single stay = the old single-row behaviour).
        $bpStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cost_amount, 0)), 0) FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ?");
        $bpStmt->execute([$booking_service_id, $tenant_id]);
        $newBase = round((float)$bpStmt->fetchColumn(), 3);
        $pdo->prepare("UPDATE umrah_booking_services SET base_price = ? WHERE id = ?")
            ->execute([$newBase, $booking_service_id]);

        $bStmt = $pdo->prepare("SELECT sold_price, discount FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $bStmt->execute([$booking_id, $tenant_id]);
        $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        $booking_price = 0.0;
        $booking_profit = null;
        if ($bRow) {
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(base_price, 0)), 0) FROM umrah_booking_services WHERE booking_id = ?");
            $sumStmt->execute([$booking_id]);
            $booking_price = round((float)$sumStmt->fetchColumn(), 3);
            // BRN procurement costs (saved via save_brn.php) count into the
            // booking totals the same way — recalc with the current snapshot
            // so a fulfillment save never drops a recorded BRN from price.
            $brnStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cost_amount, 0)), 0) FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ?");
            $brnStmt->execute([$booking_id, $tenant_id]);
            $booking_price = round($booking_price + (float)$brnStmt->fetchColumn(), 3);
            $booking_profit = round(((float)$bRow['sold_price'] - (float)$bRow['discount']) - $booking_price, 3);
            $pdo->prepare("UPDATE umrah_bookings SET price = ?, profit = ? WHERE booking_id = ?")
                ->execute([$booking_price, $booking_profit, $booking_id]);
        }

        // ---- Supplier transaction (once per supplier + booking + service type) ----
        // Exact-remark matching keeps the dedupe honest (a LIKE on the service
        // type could false-positive on member names). When a transaction already
        // exists and the cost changed during the open phase, a correction
        // transaction (Debit/Credit for the delta) keeps the supplier balance
        // in sync with the fulfillment's actual cost.
        if ($supplier_id && $booking_id) {
            $memberStmt = $pdo->prepare("SELECT name FROM umrah_bookings WHERE booking_id = ?");
            $memberStmt->execute([$booking_id]);
            $memberName = $memberStmt->fetchColumn() ?: 'Member';

            $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
            $typeStmt->execute([$supplier_id, $tenant_id]);
            $supplierType = $typeStmt->fetchColumn();

            // Supplier balance/transactions are kept in the SUPPLIER's own
            // currency (payments are recorded in the paid currency), so the
            // debit must use the supplier-currency cost — not the booking-
            // currency (USD-normalized) cost_amount. Falls back to cost_amount
            // when no supplier currency was captured (same as booking currency).
            $supAmtStmt = $pdo->prepare("
                SELECT COALESCE(SUM(COALESCE(supplier_cost, cost_amount)), 0)
                FROM umrah_fulfillments
                WHERE booking_service_id = ? AND tenant_id = ? AND supplier_id = ?");
            $supAmtStmt->execute([$booking_service_id, $tenant_id, $supplier_id]);
            $supplierBase = round((float)$supAmtStmt->fetchColumn(), 3);

            $remark = "Fulfillment for {$svc['service_type']}: {$memberName}";
            $corrRemark = "Fulfillment cost correction for {$svc['service_type']}: {$memberName}";

            // ---- Supplier change: undo the previous supplier's exposure for
            // this booking + service type (its main transaction AND any
            // cost-correction deltas are removed, balance restored) so a switch
            // mid-open can never leave a phantom payable on the old supplier or
            // a double debit on the new one. The new supplier's transaction is
            // created below.
            $oldSupplierId = $existingRows
                ? ((int)($existingRows[0]['supplier_id'] ?? 0) ?: null)
                : null;
            if ($oldSupplierId !== null && (int)$oldSupplierId !== (int)$supplier_id) {
                $oldIdStmt = $pdo->prepare("
                    SELECT MIN(id) FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND (remarks = ? OR remarks = ?) AND tenant_id = ?");
                $oldIdStmt->execute([$oldSupplierId, $booking_id, $remark, $corrRemark, $tenant_id]);
                $oldDeletedMinId = (int)($oldIdStmt->fetchColumn() ?: 0);

                $oldTxnStmt = $pdo->prepare("
                    SELECT transaction_type, amount FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND (remarks = ? OR remarks = ?) AND tenant_id = ?");
                $oldTxnStmt->execute([$oldSupplierId, $booking_id, $remark, $corrRemark, $tenant_id]);
                $oldTxns = $oldTxnStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($oldTxns) {
                    $net = 0.0;
                    foreach ($oldTxns as $ot) {
                        $net += $ot['transaction_type'] === 'Debit' ? (float)$ot['amount'] : -((float)$ot['amount']);
                    }
                    if ($net != 0.0) {
                        $oldTypeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
                        $oldTypeStmt->execute([$oldSupplierId, $tenant_id]);
                        if ($oldTypeStmt->fetchColumn() === 'External') {
                            $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                                ->execute([$net, $oldSupplierId, $tenant_id]);
                        }
                    }
                    $pdo->prepare("DELETE FROM supplier_transactions
                                   WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                                     AND (remarks = ? OR remarks = ?) AND tenant_id = ?")
                        ->execute([$oldSupplierId, $booking_id, $remark, $corrRemark, $tenant_id]);
                }
                // The deleted exposure rows were part of every subsequent
                // running balance of the old supplier — bring them back in sync.
                if ($oldDeletedMinId > 0) {
                    umrahRebuildRunningBalances($pdo, $tenant_id, $branch_id, $oldSupplierId, $oldDeletedMinId);
                }
            }

            $dupStmt = $pdo->prepare("
                SELECT id FROM supplier_transactions
                WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                  AND remarks = ? AND tenant_id = ? LIMIT 1");
            $dupStmt->execute([$supplier_id, $booking_id, $remark, $tenant_id]);

            if (!$dupStmt->fetchColumn()) {
                // Legacy safety: a booking approved before this fulfillment
                // existed may already have exposed the supplier via an
                // approval debit ("Base amount of ...") — never double-debit.
                $legacyStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND remarks LIKE 'Base amount of %' AND tenant_id = ?");
                $legacyStmt->execute([$supplier_id, $booking_id, $tenant_id]);
                if ((int)$legacyStmt->fetchColumn() === 0
                    && $supplier_cost !== null && (float)$supplier_cost > 0) {
                    if ($supplierType === 'External') {
                        $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                            ->execute([$supplierBase, $supplier_id, $tenant_id]);
                        $balStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                        $balStmt->execute([$supplier_id, $tenant_id]);
                        $newBalance = (float)$balStmt->fetchColumn();
                        $pdo->prepare("
                            INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                            VALUES (?, ?, ?, ?, 'Debit', ?, ?, ?, 'umrah', '')")
                            ->execute([$tenant_id, $branch_id, $supplier_id, $booking_id, $supplierBase, $remark, $newBalance]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                            VALUES (?, ?, ?, ?, 'Debit', ?, ?, 0, 'umrah', '')")
                            ->execute([$tenant_id, $branch_id, $supplier_id, $booking_id, $supplierBase, $remark]);
                    }
                }
            } else {
                // Open-phase cost change — net the correction row to the live
                // cost (rebuild, never skip) so sign flips and deletes can't
                // leave a stale correction (phantom) behind.
                umrahReconcileSupplierExposure($pdo, $tenant_id, $branch_id, $booking_id, (int)$supplier_id, (string)$memberName, (string)($svc['service_type'] ?? $fulfillment_type));
            }
        }

        // ---- Booking activation (replaces the old approval step) --------------
        // The fulfillment flow is now the single point where a booking's money
        // moves. Once EVERY non-optional service has a cost-bearing fulfillment,
        // the client is debited, the booking becomes active, family totals are
        // refreshed and notifications fire — exactly once (guarded by the
        // pending status).
        $bkStmt = $pdo->prepare("
            SELECT booking_id, family_id, sold_to, paid_to, name,
                   (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                       JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                       JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                       WHERE ubs2.booking_id = umrah_bookings.booking_id
                         AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                       ORDER BY ff.id DESC LIMIT 1) AS flight_date,
                   (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                       JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                       JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                       WHERE ubs2.booking_id = umrah_bookings.booking_id
                         AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                       ORDER BY ff.id DESC LIMIT 1) AS return_date,
                   room_type, sold_price, discount, paid, received_bank_payment, due, status, currency
            FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $bkStmt->execute([$booking_id, $tenant_id]);
        $bkRow = $bkStmt->fetch(PDO::FETCH_ASSOC);
        $activated = false;

        if ($bkRow && (string)$bkRow['status'] === 'pending') {
            $cntStmt = $pdo->prepare("
                SELECT COUNT(*) FROM umrah_booking_services
                WHERE booking_id = ? AND tenant_id = ? AND (is_optional = 0 OR is_optional IS NULL)");
            $cntStmt->execute([$booking_id, $tenant_id]);
            $nonOptionalCnt = (int)$cntStmt->fetchColumn();

            $fulStmt = $pdo->prepare("
                SELECT COUNT(*) FROM umrah_booking_services ubs
                WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND (ubs.is_optional = 0 OR ubs.is_optional IS NULL)
                  AND EXISTS (
                      SELECT 1 FROM umrah_fulfillments f
                      WHERE f.booking_service_id = ubs.id AND f.tenant_id = ?
                        AND f.supplier_cost IS NOT NULL AND f.status <> 'cancelled')");
            $fulStmt->execute([$booking_id, $tenant_id, $tenant_id]);
            $fulfilledCnt = (int)$fulStmt->fetchColumn();

            if ($nonOptionalCnt === 0 || $fulfilledCnt >= $nonOptionalCnt) {
                if (empty($bkRow['sold_to'])) {
                    $pdo->rollBack();
                    return ['success' => false, 'code' => 400, 'message' => 'Booking has no client (sold_to) — cannot activate.'];
                }

                $clientStmt = $pdo->prepare("
                    SELECT name, email, usd_balance, afs_balance, client_type
                    FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $clientStmt->execute([$bkRow['sold_to'], $tenant_id, $branch_id]);
                $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                if (!$clientRow) {
                    $pdo->rollBack();
                    return ['success' => false, 'code' => 400, 'message' => 'Client not found for booking activation.'];
                }

                $currencyUpper = strtoupper($bkRow['currency']);
                $balanceField   = $currencyUpper === 'USD' ? 'usd_balance' : 'afs_balance';
                $debitAmount    = round((float)$bkRow['sold_price'], 3);
                $newClientBalance = round((float)$clientRow[$balanceField] - $debitAmount, 3);
                $memberName     = $bkRow['name'] ?: 'Member';

                $pdo->prepare("
                    INSERT INTO client_transactions (client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id)
                    VALUES (?, 'Debit', 'umrah', ?, ?, ?, ?, ?, NOW(), ?, ?)")
                    ->execute([
                        $bkRow['sold_to'], $booking_id, $debitAmount, $newClientBalance, $currencyUpper,
                        "Client was debited $debitAmount $currencyUpper for umrah booking for $memberName",
                        $tenant_id, $branch_id,
                    ]);

                if ($clientRow['client_type'] === 'regular') {
                    $pdo->prepare("UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$debitAmount, $bkRow['sold_to'], $tenant_id, $branch_id]);
                }

                $pdo->prepare("UPDATE umrah_bookings SET status = 'active' WHERE booking_id = ? AND tenant_id = ?")
                    ->execute([$booking_id, $tenant_id]);

                if (!empty($bkRow['family_id'])) {
                    $pdo->prepare("
                        UPDATE families f
                        SET
                            f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                            f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                            f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                            f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                            f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
                        WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?")
                        ->execute([
                            $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id,
                            $tenant_id, $branch_id, $tenant_id, $branch_id,
                            $bkRow['family_id'], $tenant_id, $branch_id,
                        ]);
                }

                umrah_audit($pdo, 'update', 'umrah_bookings', (int)$booking_id,
                    ['status' => 'pending'], ['status' => 'active', 'transactions_created' => true]);

                $activated = true;
                $activationClientEmail = $clientRow['email'] ?: '';
                $activationAmountPaid  = round((float)$bkRow['paid'] + (float)$bkRow['received_bank_payment'], 3);
            }
        }

        // ---- Activity log (Who / When / What / Before / After) ------------------
        $auditKeys = ['supplier_id', 'status', 'supplier_currency', 'supplier_cost', 'exchange_rate', 'cost_amount', 'requested_date', 'planned_date', 'completed_date'];
        foreach ($savedFulfillments as $sf) {
            $oldAudit = $sf['old'] ? array_intersect_key($sf['old'], array_flip($auditKeys)) : [];
            $newAudit = [
                'booking_service_id' => $booking_service_id,
                'status'             => $status,
                'supplier_id'        => $supplier_id,
                'supplier_currency'  => $supplier_currency,
                'supplier_cost'      => $supplier_cost,
                'exchange_rate'      => $exchange_rate,
                'cost_amount'        => $cost_amount,
                'requested_date'     => $requested_date,
                'planned_date'       => $planned_date,
                'completed_date'     => $completed_date,
            ];
            umrah_audit($pdo, $sf['old'] ? 'update' : 'add', 'umrah_fulfillments', (int)$sf['id'], $oldAudit, $newAudit);
        }

        $pdo->commit();

        if ($activated) {
            // Notifications fire only when the tenant enabled the channel:
            // email needs SMTP enabled, WhatsApp needs active auto-notifications.
            try {
                require_once '../../includes/functions.php';
                $smtp = getTenantSMTPSettings($tenant_id);
                if (!empty($activationClientEmail) && !empty($smtp['smtp_enabled']) && function_exists('sendUmrahNotification')) {
                    sendUmrahNotification(
                        $activationClientEmail,
                        ($clientRow['name'] ?? '') ?: 'Client',
                        $booking_id,
                        $memberName,
                        $bkRow['flight_date'],
                        $bkRow['return_date'],
                        $bkRow['room_type'],
                        (float)$bkRow['sold_price'],
                        $activationAmountPaid,
                        (float)$bkRow['due'],
                        $currencyUpper
                    );
                }
            } catch (Exception $e) {
                error_log('Booking activation email failed: ' . $e->getMessage());
            }

            try {
                $whatsappFile = '../../api/whatsapp/WhatsAppManager.php';
                if (file_exists($whatsappFile)) {
                    require_once $whatsappFile;
                    $waStmt = $pdo->prepare("SELECT 1 FROM whatsapp_settings WHERE tenant_id = ? AND status = 'active' AND auto_notifications = 1 LIMIT 1");
                    $waStmt->execute([$tenant_id]);
                    if ($waStmt->fetchColumn()) {
                        (new WhatsAppManager($tenant_id))->sendBookingNotification('umrah', $booking_id);
                    }
                }
            } catch (Exception $e) {
                error_log('Booking activation WhatsApp failed: ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => 'Fulfillment saved (status: ' . $status . ')',
            'fulfillment_id' => $fulfillment_id,
            'fulfillment_ids' => array_column($savedFulfillments, 'id'),
            'status' => $status,
            'cost_amount' => $cost_amount,
            'booking_price' => $booking_price,
            'booking_profit' => $booking_profit,
            'booking_activated' => $activated,
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return ['success' => false, 'code' => 500, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
}

if (!function_exists('umrahReconcileSupplierExposure')) {
/**
 * Net the supplier exposure ledger for one booking+supplier back to the live
 * fulfillment costs. The 'Fulfillment for ...' main rows stay as the baseline;
 * the single 'Fulfillment cost correction for ...' row is updated IN PLACE
 * (same id, same position — the running-balance adjustment pattern of
 * update_ticket_payment.php) so sign flips, deleted fulfillments and
 * multi-stay removals can never leave phantom debits behind (see the
 * sync-delete paths).
 *
 * BRN rows (remarks LIKE 'BRN%') belong to save_brn.php and are excluded here.
 */
function umrahReconcileSupplierExposure(PDO $pdo, int $tenantId, int $branchId, int $bookingId, int $supplierId, string $memberName, string $serviceType): void
{
    $corrRemark = "Fulfillment cost correction for {$serviceType}: {$memberName}";

    // Existing correction rows (one canonical row; legacy duplicates from the
    // old skip-bug are absorbed too).
    $cStmt = $pdo->prepare("
        SELECT id, transaction_type, amount FROM supplier_transactions
        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
          AND remarks = ? AND tenant_id = ? ORDER BY id");
    $cStmt->execute([$supplierId, $bookingId, $corrRemark, $tenantId]);
    $corrRows = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    $tStmt = $pdo->prepare("
        SELECT COALESCE(SUM(COALESCE(f.supplier_cost, f.cost_amount)), 0)
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON f.booking_service_id = bs.id
        WHERE bs.booking_id = ? AND f.supplier_id = ? AND f.tenant_id = ? AND f.status <> 'cancelled'");
    $tStmt->execute([$bookingId, $supplierId, $tenantId]);
    $target = round((float)$tStmt->fetchColumn(), 3);

    // Baseline ledger = the 'Fulfillment for ...' main rows only.
    $lStmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN LOWER(st.transaction_type) = 'credit' THEN -st.amount ELSE st.amount END), 0)
        FROM supplier_transactions st
        WHERE st.supplier_id = ? AND st.reference_id = ? AND st.transaction_of = 'umrah'
          AND st.remarks LIKE 'Fulfillment for %' AND st.tenant_id = ?");
    $lStmt->execute([$supplierId, $bookingId, $tenantId]);
    $mains = round((float)$lStmt->fetchColumn(), 3);

    // The correction row should carry (target - mains); that is its new effect.
    $newEffect = round($target - $mains, 3);

    // Effects in DEBIT-POSITIVE convention (Debit adds, Credit subtracts) so
    // the adjustment arithmetic matches the balance-column update exactly.
    $oldEffect = 0.0;
    $minCorrId = 0;
    foreach ($corrRows as $cr) {
        $oldEffect += strcasecmp((string)$cr['transaction_type'], 'Credit') === 0
            ? -(float)$cr['amount'] : (float)$cr['amount'];
        $minCorrId = $minCorrId === 0 ? (int)$cr['id'] : min($minCorrId, (int)$cr['id']);
    }

    // Running balances (column) and suppliers.balance both move by
    // -(newEffect - oldEffect), applied as `balance = balance - adjustment`
    // (the update_ticket_payment.php adjustment pattern, incl. the row's own
    // balance = the id >= minCorrId bound).
    $adjustment = round($newEffect - $oldEffect, 3);

    if ($corrRows) {
        if (abs($newEffect) < 0.001) {
            // Correction truly obsolete — drop it and restore subsequent rows.
            $pdo->prepare("DELETE FROM supplier_transactions
                           WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                             AND remarks = ? AND tenant_id = ?")
                ->execute([$supplierId, $bookingId, $corrRemark, $tenantId]);
        } else {
            // Update IN PLACE — same id, same position, audit preserved.
            // Type flips (Debit<->Credit) when the sign changes.
            $pdo->prepare("UPDATE supplier_transactions SET transaction_type = ?, amount = ?
                           WHERE id = ? AND tenant_id = ?")
                ->execute([$newEffect > 0 ? 'Debit' : 'Credit', abs($newEffect), (int)$corrRows[0]['id'], $tenantId]);
            if (count($corrRows) > 1) {
                $pdo->prepare("DELETE FROM supplier_transactions
                               WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                                 AND remarks = ? AND tenant_id = ? AND id <> ?")
                    ->execute([$supplierId, $bookingId, $corrRemark, $tenantId, (int)$corrRows[0]['id']]);
            }
        }
        if ($adjustment != 0.0) {
            $pdo->prepare("UPDATE supplier_transactions SET balance = balance - ?
                           WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id >= ?")
                ->execute([$adjustment, $supplierId, $tenantId, $branchId, $minCorrId]);
            $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
            $typeStmt->execute([$supplierId, $tenantId]);
            if ((string)$typeStmt->fetchColumn() === 'External') {
                $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$adjustment, $supplierId, $tenantId]);
            }
        }
        return;
    }

    // No correction row yet — append one at the tail (nothing after it to shift).
    if (abs($newEffect) >= 0.001) {
        $amount = abs($newEffect);
        $txType = $newEffect > 0 ? 'Debit' : 'Credit';
        $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $typeStmt->execute([$supplierId, $tenantId]);
        if ((string)$typeStmt->fetchColumn() === 'External') {
            $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                ->execute([$newEffect, $supplierId, $tenantId]);
            $balStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
            $balStmt->execute([$supplierId, $tenantId]);
            $newBalance = (float)$balStmt->fetchColumn();
            $pdo->prepare("
                INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'umrah', '')")
                ->execute([$tenantId, $branchId, $supplierId, $bookingId, $txType, $amount, $corrRemark, $newBalance]);
        } else {
            $pdo->prepare("
                INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'umrah', '')")
                ->execute([$tenantId, $branchId, $supplierId, $bookingId, $txType, $amount, $corrRemark]);
        }
    }
}
}

if (!function_exists('umrahRebuildRunningBalances')) {
/**
 * Rebuild the running balance column for one supplier from $fromId onward
 * (Credit adds, Debit subtracts) — mirroring the subsequent-balance
 * maintenance of the delete-transaction paths. The row before $fromId is
 * used as the seed.
 */
function umrahRebuildRunningBalances(PDO $pdo, int $tenantId, int $branchId, int $supplierId, int $fromId): void
{
    $seed = $pdo->prepare("SELECT balance FROM supplier_transactions
        WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id < ?
        ORDER BY id DESC LIMIT 1");
    $seed->execute([$supplierId, $tenantId, $branchId, $fromId]);
    $prev = (float)($seed->fetchColumn() ?: 0);

    $rows = $pdo->prepare("SELECT id, transaction_type, amount FROM supplier_transactions
        WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id >= ?
        ORDER BY id");
    $rows->execute([$supplierId, $tenantId, $branchId, $fromId]);
    $upd = $pdo->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ?");
    foreach ($rows->fetchAll() as $r) {
        $prev += strcasecmp((string)$r['transaction_type'], 'Credit') === 0
            ? (float)$r['amount'] : -(float)$r['amount'];
        $upd->execute([round($prev, 2), (int)$r['id']]);
    }
}
}

if (!function_exists('brnReconcileSupplierExposure')) {
/**
 * Net the BRN exposure ledger for one booking+supplier back to the live
 * umrah_brn_costs. The 'BRN for ...' main row stays as the baseline; the
 * single 'BRN cost correction for ...' row is updated IN PLACE (same id,
 * same position — the update_ticket_payment.php adjustment pattern) so sign
 * flips and re-saves can never leave a stale correction behind.
 */
function brnReconcileSupplierExposure(PDO $pdo, int $tenantId, int $branchId, int $bookingId, int $supplierId, string $memberName): void
{
    $corrRemark = "BRN cost correction for {$memberName}";

    $cStmt = $pdo->prepare("
        SELECT id, transaction_type, amount FROM supplier_transactions
        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
          AND remarks = ? AND tenant_id = ? ORDER BY id");
    $cStmt->execute([$supplierId, $bookingId, $corrRemark, $tenantId]);
    $corrRows = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    $tStmt = $pdo->prepare("
        SELECT COALESCE(SUM(COALESCE(supplier_cost, cost_amount)), 0)
        FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ? AND supplier_id = ?");
    $tStmt->execute([$bookingId, $tenantId, $supplierId]);
    $target = round((float)$tStmt->fetchColumn(), 3);

    // Baseline ledger = the 'BRN for ...' main row only.
    $lStmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN LOWER(st.transaction_type) = 'credit' THEN -st.amount ELSE st.amount END), 0)
        FROM supplier_transactions st
        WHERE st.supplier_id = ? AND st.reference_id = ? AND st.transaction_of = 'umrah'
          AND st.remarks LIKE 'BRN for %' AND st.tenant_id = ?");
    $lStmt->execute([$supplierId, $bookingId, $tenantId]);
    $mains = round((float)$lStmt->fetchColumn(), 3);

    $newEffect = round($target - $mains, 3);

    // Effects in DEBIT-POSITIVE convention (Debit adds, Credit subtracts).
    $oldEffect = 0.0;
    $minCorrId = 0;
    foreach ($corrRows as $cr) {
        $oldEffect += strcasecmp((string)$cr['transaction_type'], 'Credit') === 0
            ? -(float)$cr['amount'] : (float)$cr['amount'];
        $minCorrId = $minCorrId === 0 ? (int)$cr['id'] : min($minCorrId, (int)$cr['id']);
    }

    // Running balances (column) and suppliers.balance both move by
    // -(newEffect - oldEffect), applied as `balance = balance - adjustment`.
    $adjustment = round($newEffect - $oldEffect, 3);

    if ($corrRows) {
        if (abs($newEffect) < 0.001) {
            $pdo->prepare("DELETE FROM supplier_transactions
                           WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                             AND remarks = ? AND tenant_id = ?")
                ->execute([$supplierId, $bookingId, $corrRemark, $tenantId]);
        } else {
            $pdo->prepare("UPDATE supplier_transactions SET transaction_type = ?, amount = ?
                           WHERE id = ? AND tenant_id = ?")
                ->execute([$newEffect > 0 ? 'Debit' : 'Credit', abs($newEffect), (int)$corrRows[0]['id'], $tenantId]);
            if (count($corrRows) > 1) {
                $pdo->prepare("DELETE FROM supplier_transactions
                               WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                                 AND remarks = ? AND tenant_id = ? AND id <> ?")
                    ->execute([$supplierId, $bookingId, $corrRemark, $tenantId, (int)$corrRows[0]['id']]);
            }
        }
        if ($adjustment != 0.0) {
            $pdo->prepare("UPDATE supplier_transactions SET balance = balance - ?
                           WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id >= ?")
                ->execute([$adjustment, $supplierId, $tenantId, $branchId, $minCorrId]);
            $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
            $typeStmt->execute([$supplierId, $tenantId]);
            if ((string)$typeStmt->fetchColumn() === 'External') {
                $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$adjustment, $supplierId, $tenantId]);
            }
        }
        return;
    }

    if (abs($newEffect) >= 0.001) {
        $amount = abs($newEffect);
        $txType = $newEffect > 0 ? 'Debit' : 'Credit';
        $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $typeStmt->execute([$supplierId, $tenantId]);
        if ((string)$typeStmt->fetchColumn() === 'External') {
            $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                ->execute([$newEffect, $supplierId, $tenantId]);
            $balStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
            $balStmt->execute([$supplierId, $tenantId]);
            $newBalance = (float)$balStmt->fetchColumn();
            $pdo->prepare("
                INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'umrah', '')")
                ->execute([$tenantId, $branchId, $supplierId, $bookingId, $txType, $amount, $corrRemark, $newBalance]);
        } else {
            $pdo->prepare("
                INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'umrah', '')")
                ->execute([$tenantId, $branchId, $supplierId, $bookingId, $txType, $amount, $corrRemark]);
        }
    }
}
}