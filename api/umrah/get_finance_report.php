<?php
/**
 * Get Finance Report API (Phases 26-28)
 * Read-only financial reporting derived from transactional data:
 *   report=members      -> member profitability (selling, cost, profit, margin, paid, due)
 *   report=services     -> service profitability per service_type
 *   report=suppliers    -> supplier payables (from fulfilled services, not manual totals)
 *   report=hotels       -> hotel report (rooms, reservations, occupancy, contract utilization)
 *   report=outstanding  -> outstanding payments (total, paid, due)
 *
 * Cost amounts (umrah_fulfillments.cost_amount) are USD-normalized at save time;
 * selling prices are converted to USD using the booking exchange rate so
 * profit/margin are comparable.
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.finance_view');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';
require_once __DIR__ . '/hotels/occupancy_helper.php';

$report = isset($_GET['report']) ? DbSecurity::validateInput($_GET['report'], 'string') : 'members';

// USD conversion factor for a booking's native currency (1 USD = X native)
// Mirrors add_umrah_transaction.php: converting TO USD always divides.
$sellFactor = "CASE WHEN b.currency = 'USD' OR b.exchange_rate IS NULL OR b.exchange_rate <= 0 THEN 1 ELSE 1 / b.exchange_rate END";

// ---- Group profitability ---------------------------------------------------
if ($report === 'members') {
    // Step 1: Get per-client total USD fund
    $clientFundStmt = $pdo->prepare("
        SELECT client_id, SUM(amount) AS total_fund
        FROM client_transactions
        WHERE type = 'credit' AND transaction_of = 'fund' AND currency = 'USD' AND tenant_id = ?
        GROUP BY client_id
    ");
    $clientFundStmt->execute([$tenant_id]);
    $clientFunds = [];
    while ($cfRow = $clientFundStmt->fetch(PDO::FETCH_ASSOC)) {
        $clientFunds[(int)$cfRow['client_id']] = floatval($cfRow['total_fund']);
    }

    // Step 2: Get per-client-per-group totals (oldest group first per client)
    $clientGroupStmt = $pdo->prepare("
        SELECT ub.sold_to AS client_id, f.group_id, g.created_at,
               SUM(COALESCE(ub.sold_price, 0)) AS client_booking_total
        FROM umrah_bookings ub
        JOIN families f ON ub.family_id = f.family_id AND f.tenant_id = ub.tenant_id
        JOIN umrah_groups g ON f.group_id = g.group_id AND f.tenant_id = g.tenant_id
        WHERE ub.sold_to IS NOT NULL AND f.tenant_id = ?
          AND ub.status NOT IN ('refunded', 'cancelled')
        GROUP BY ub.sold_to, f.group_id, g.created_at
        ORDER BY ub.sold_to, g.created_at ASC, g.group_id ASC
    ");
    $clientGroupStmt->execute([$tenant_id]);
    $clientGroups = [];
    while ($cgRow = $clientGroupStmt->fetch(PDO::FETCH_ASSOC)) {
        $clientId = (int)$cgRow['client_id'];
        $clientGroups[$clientId][] = [
            'group_id' => (int)$cgRow['group_id'],
            'created_at' => $cgRow['created_at'],
            'booking_total' => floatval($cgRow['client_booking_total']),
        ];
    }

    // Step 3: Waterfall — allocate each client's fund to their groups in creation order
    $groupFundAllocations = [];
    foreach ($clientFunds as $clientId => $totalFund) {
        if (!isset($clientGroups[$clientId])) continue;
        $remaining = $totalFund;
        foreach ($clientGroups[$clientId] as $cg) {
            if ($remaining <= 0) break;
            $alloc = min($remaining, $cg['booking_total']);
            $gid = $cg['group_id'];
            $groupFundAllocations[$gid] = ($groupFundAllocations[$gid] ?? 0) + $alloc;
            $remaining -= $alloc;
        }
    }

    // Step 4: Group-level selling, cost, profit
    $stmt = $pdo->prepare("
        SELECT g.group_id, g.group_number, g.group_name,
               COALESCE(fam.total_price, 0) AS total_price,
               COUNT(DISTINCT b.booking_id) AS member_count,
               SUM(" . $sellFactor . " * b.sold_price) AS selling_usd,
               SUM(" . $sellFactor . " * b.paid) AS booking_paid_usd,
               SUM(
                   COALESCE((
                       SELECT SUM(COALESCE(ff.cost_amount, 0))
                       FROM umrah_fulfillments ff
                       JOIN umrah_booking_services bs2 ON ff.booking_service_id = bs2.id
                       WHERE bs2.booking_id = b.booking_id AND ff.status <> 'cancelled'
                   ), 0)
               ) AS cost_usd
        FROM umrah_bookings b
        JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
        JOIN umrah_groups g ON g.group_id = f.group_id AND g.tenant_id = f.tenant_id
        LEFT JOIN (
            SELECT group_id, tenant_id, SUM(total_price) AS total_price
            FROM families GROUP BY group_id, tenant_id
        ) fam ON fam.group_id = g.group_id AND fam.tenant_id = g.tenant_id
        WHERE b.tenant_id = ? AND b.branch_id = ?
          AND b.status NOT IN ('refunded', 'cancelled')
        GROUP BY g.group_id, g.group_number, g.group_name, fam.total_price
        ORDER BY CAST(g.group_number AS UNSIGNED) ASC, g.group_id ASC");
    $stmt->execute([$tenant_id, $branch_id]);

    $rows = [];
    $totals = ['selling_usd' => 0, 'cost_usd' => 0, 'profit_usd' => 0, 'paid_usd' => 0, 'due_usd' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $selling_usd = round((float)$r['selling_usd'], 2);
        $cost_usd = round((float)$r['cost_usd'], 2);
        $profit_usd = round($selling_usd - $cost_usd, 2);
        $groupId = (int)$r['group_id'];
        $totalPrice = round((float)$r['total_price'], 2);
        // Combined paid: booking-level payments + fund waterfall, capped at group total
        $bookingPaid = round((float)$r['booking_paid_usd'], 2);
        $fundAlloc = round(min($groupFundAllocations[$groupId] ?? 0, $totalPrice), 2);
        $paid_usd = round(min($bookingPaid + $fundAlloc, $totalPrice), 2);
        $due_usd = round(max($selling_usd - $paid_usd, 0), 2);
        $rows[] = [
            'group_id'     => $groupId,
            'group_number' => $r['group_number'],
            'group_name'   => $r['group_name'],
            'member_count' => (int)$r['member_count'],
            'selling_usd'  => $selling_usd,
            'cost_usd'     => $cost_usd,
            'profit_usd'   => $profit_usd,
            'margin'       => $selling_usd > 0 ? round($profit_usd / $selling_usd * 100, 1) : 0,
            'paid_usd'     => $paid_usd,
            'due_usd'      => $due_usd,
        ];
        $totals['selling_usd'] += $selling_usd;
        $totals['cost_usd'] += $cost_usd;
        $totals['profit_usd'] += $profit_usd;
        $totals['paid_usd'] += $paid_usd;
        $totals['due_usd'] += $due_usd;
    }
    $totals['margin'] = $totals['selling_usd'] > 0
        ? round($totals['profit_usd'] / $totals['selling_usd'] * 100, 1) : 0;
    echo json_encode(['success' => true, 'report' => 'groups', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Service cost (services carry cost only — no per-service selling price) ----
if ($report === 'services') {
    $stmt = $pdo->prepare("
        SELECT bs.service_type,
               COUNT(*) AS cnt,
               SUM(COALESCE(bs.base_price, 0)) AS cost_usd
        FROM umrah_booking_services bs
        JOIN umrah_bookings b ON bs.booking_id = b.booking_id AND b.tenant_id = ? AND b.branch_id = ?
        WHERE bs.tenant_id = ?
        GROUP BY bs.service_type
        ORDER BY cost_usd DESC");
    $stmt->execute([$tenant_id, $branch_id, $tenant_id]);
    $rows = [];
    $totals = ['cost_usd' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cost_usd = round((float)$r['cost_usd'], 2);
        $rows[] = [
            'service_type' => $r['service_type'],
            'count' => (int)$r['cnt'],
            'cost_usd' => $cost_usd,
        ];
        $totals['cost_usd'] += $cost_usd;
    }
    echo json_encode(['success' => true, 'report' => 'services', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Supplier payables (waterfall allocation from fulfilled services) -----------
if ($report === 'suppliers') {
    // Step 1: Get all active fulfillments per supplier, ordered by creation date
    $fulStmt = $pdo->prepare("
        SELECT f.id, f.supplier_id, f.fulfillment_type, f.cost_amount,
               COALESCE(f.supplier_cost, f.cost_amount) AS payable_ccy,
               f.created_at,
               bs.booking_id,
               b.name AS member_name
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON f.booking_service_id = bs.id AND bs.tenant_id = f.tenant_id
        JOIN umrah_bookings b ON bs.booking_id = b.booking_id AND b.tenant_id = f.tenant_id
        JOIN suppliers sup ON f.supplier_id = sup.id AND sup.tenant_id = ?
        WHERE f.tenant_id = ? AND f.status <> 'cancelled' AND f.cost_amount IS NOT NULL
        ORDER BY f.supplier_id, f.created_at ASC, f.id ASC
    ");
    $fulStmt->execute([$tenant_id, $tenant_id]);
    $allFulfills = [];
    while ($fr = $fulStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$fr['supplier_id'];
        $allFulfills[$sid][] = $fr;
    }

    // Step 2: Get all credit (fund) payments per supplier, ordered by date
    $payStmt = $pdo->prepare("
        SELECT st.supplier_id, st.amount, st.transaction_date, st.transaction_of
        FROM supplier_transactions st
        JOIN suppliers sup ON st.supplier_id = sup.id AND sup.tenant_id = ?
        WHERE st.transaction_type = 'credit'
          AND st.tenant_id = ?
        ORDER BY st.supplier_id, st.transaction_date ASC, st.id ASC
    ");
    $payStmt->execute([$tenant_id, $tenant_id]);
    $allPayments = [];
    while ($pr = $payStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$pr['supplier_id'];
        $allPayments[$sid][] = floatval($pr['amount']);
    }

    // Step 3: Waterfall allocation per supplier
    $rows = [];
    $totals = ['total_payable' => 0, 'services_count' => 0];

    foreach ($allFulfills as $sid => $fulfills) {
        // Get supplier info from first fulfillment's supplier join
        $supInfo = $pdo->prepare("SELECT name, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $supInfo->execute([$sid, $tenant_id]);
        $sup = $supInfo->fetch(PDO::FETCH_ASSOC);
        if (!$sup) continue;

        $payments = $allPayments[$sid] ?? [];
        $remainingPayments = $payments;

        // Per-type cost aggregation
        $typeCosts = ['flight' => 0, 'hotel' => 0, 'visa' => 0, 'transport' => 0, 'meal' => 0, 'ziyarat' => 0];
        $totalPayable = 0;
        $totalPaid = 0;
        $servicesCount = 0;
        $fulfillmentDetails = [];

        foreach ($fulfills as $f) {
            $cost = round((float)$f['payable_ccy'], 2);
            $totalPayable += $cost;
            $servicesCount++;
            $ft = $f['fulfillment_type'];
            if (isset($typeCosts[$ft])) {
                $typeCosts[$ft] += $cost;
            }

            // Waterfall: allocate oldest remaining payment to this fulfillment
            $allocated = 0;
            while ($cost > $allocated && !empty($remainingPayments)) {
                $avail = $remainingPayments[0];
                $alloc = min($avail, $cost - $allocated);
                $allocated += $alloc;
                $remainingPayments[0] -= $alloc;
                if ($remainingPayments[0] <= 0.001) {
                    array_shift($remainingPayments);
                }
            }
            $allocated = round($allocated, 2);
            $totalPaid += $allocated;

            $fulfillmentDetails[] = [
                'fulfillment_id' => (int)$f['id'],
                'type'           => $ft,
                'member_name'    => $f['member_name'] ?? '',
                'payable'        => $cost,
                'paid'           => $allocated,
                'balance'        => round($cost - $allocated, 2),
                'date'           => $f['created_at'],
            ];
        }

        $balance = round($totalPayable - $totalPaid, 2);
        $rows[] = [
            'supplier_id'         => $sid,
            'supplier_name'       => $sup['name'],
            'currency'            => $sup['currency'],
            'services_count'      => $servicesCount,
            'flight_cost'         => $typeCosts['flight'],
            'hotel_cost'          => $typeCosts['hotel'],
            'visa_cost'           => $typeCosts['visa'],
            'transport_cost'      => $typeCosts['transport'],
            'meal_cost'           => $typeCosts['meal'],
            'ziyarat_cost'        => $typeCosts['ziyarat'],
            'total_payable'       => $totalPayable,
            'paid_ccy'            => $totalPaid,
            'balance_ccy'         => $balance,
            'fulfillments'        => $fulfillmentDetails,
        ];
        $totals['total_payable'] += $totalPayable;
        $totals['services_count'] += $servicesCount;
    }

    // Sort by balance descending (largest debt first)
    usort($rows, function ($a, $b) {
        return $b['balance_ccy'] <=> $a['balance_ccy'];
    });

    echo json_encode(['success' => true, 'report' => 'suppliers', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Hotel report ---------------------------------------------------------------
if ($report === 'hotels') {
    $today = date('Y-m-d');
    $hStmt = $pdo->prepare("
        SELECT h.id, h.name, h.city, h.status,
               (SELECT COUNT(*) FROM umrah_hotel_rooms r
                 WHERE r.hotel_id = h.id AND r.tenant_id = ? AND r.status = 'active') AS rooms,
               (SELECT COUNT(*) FROM umrah_hotel_fulfillments hf
                 JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
                 WHERE hf.hotel_id = h.id AND f.tenant_id = ? AND f.status <> 'cancelled'
                   AND hf.branch_id = ?
                   AND (hf.check_out IS NULL OR hf.check_out > ?)) AS reservations,
               (SELECT COUNT(*) FROM umrah_hotel_fulfillments hf
                 JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
                 WHERE hf.hotel_id = h.id AND f.tenant_id = ? AND f.status <> 'cancelled'
                   AND hf.branch_id = ?
                   AND hf.check_in IS NOT NULL AND hf.check_in <= ? 
                   AND hf.check_out IS NOT NULL AND hf.check_out > ?) AS occupied_today,
               (SELECT COUNT(DISTINCT i.room_id) FROM umrah_hotel_contract_inventory i
                 JOIN umrah_hotel_contracts c ON i.contract_id = c.id
                 WHERE c.hotel_id = h.id AND c.tenant_id = ?
                   AND c.status = 'active' AND i.status = 'active'
                   AND (i.valid_from <= ? AND i.valid_to >= ?
                        OR (i.valid_from IS NULL AND i.valid_to IS NULL))) AS inventory_rooms,
               (SELECT COUNT(*) FROM umrah_contract_hotels ch
                 JOIN umrah_hotel_contracts c ON c.id = ch.contract_id AND c.status = 'active'
                 WHERE ch.hotel_id = h.id AND ch.tenant_id = ?) AS contracts
        FROM umrah_hotels h
        WHERE h.tenant_id = ?
        ORDER BY h.name");
    $hStmt->execute([$tenant_id, $tenant_id, $branch_id, $today,
                     $tenant_id, $branch_id, $today, $today,
                     $tenant_id, $today, $today,
                     $tenant_id, $tenant_id]);
    $rows = [];
    foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $rooms = (int)$h['rooms'];
        $occupied = (int)$h['occupied_today'];
        $rows[] = [
            'hotel_id' => (int)$h['id'],
            'hotel_name' => $h['name'],
            'city' => $h['city'],
            'status' => $h['status'],
            'rooms' => $rooms,
            'reservations' => (int)$h['reservations'],
            'occupied_today' => $occupied,
            'occupancy_pct' => $rooms > 0 ? min(100, round($occupied / $rooms * 100, 1)) : 0,
            'inventory_rooms' => (int)$h['inventory_rooms'],
            'utilization_pct' => $rooms > 0 ? round((int)$h['inventory_rooms'] / $rooms * 100, 1) : 0,
            'contracts' => (int)$h['contracts'],
        ];
    }
    echo json_encode(['success' => true, 'report' => 'hotels', 'rows' => $rows]);
    exit;
}

// ---- Outstanding payments ----------------------------------------------------------
if ($report === 'outstanding') {
    $stmt = $pdo->prepare("
        SELECT booking_id, name, fname, gfname, passport_number, flight_date, status,
               currency, exchange_rate, sold_price, paid, due
        FROM umrah_bookings
        WHERE tenant_id = ? AND status IN ('active', 'pending') AND due > 0
        ORDER BY due DESC, booking_id DESC");
    $stmt->execute([$tenant_id]);
    $rows = [];
    $totals = ['total_usd' => 0, 'paid_usd' => 0, 'due_usd' => 0, 'members' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $factor = (strtoupper((string)$r['currency']) === 'USD' || !$r['exchange_rate'] || (float)$r['exchange_rate'] <= 0)
            ? 1 : 1 / (float)$r['exchange_rate'];
        $rows[] = [
            'booking_id' => (int)$r['booking_id'],
            'name' => $r['name'],
            'fname' => $r['fname'],
            'gfname' => $r['gfname'],
            'passport_number' => $r['passport_number'],
            'flight_date' => $r['flight_date'],
            'status' => $r['status'],
            'currency' => $r['currency'],
            'total' => (float)$r['sold_price'],
            'total_usd' => round((float)$r['sold_price'] * $factor, 2),
            'paid' => (float)$r['paid'],
            'paid_usd' => round((float)$r['paid'] * $factor, 2),
            'due' => (float)$r['due'],
            'due_usd' => round((float)$r['due'] * $factor, 2),
        ];
        $totals['total_usd'] += (float)$r['sold_price'] * $factor;
        $totals['paid_usd'] += (float)$r['paid'] * $factor;
        $totals['due_usd'] += (float)$r['due'] * $factor;
        $totals['members']++;
    }
    echo json_encode(['success' => true, 'report' => 'outstanding', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Service-wise detail report (filtered by fulfillment date) ----------------
if ($report === 'service_detail') {
    require_once __DIR__ . '/service_report_data.php';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $groupBy = isset($_GET['group_by']) && in_array($_GET['group_by'], ['service', 'group', 'family'], true) ? $_GET['group_by'] : 'service';
    $data = service_report_load($pdo, $tenant_id, $branch_id, $dateFrom, $dateTo, $groupBy);
    echo json_encode(['success' => true, 'report' => 'service_detail', 'data' => $data]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown report.']);
